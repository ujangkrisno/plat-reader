import io
import os
import re
import cv2
import time
import threading
import mysql.connector
from http.server import HTTPServer, BaseHTTPRequestHandler
from socketserver import ThreadingMixIn

DB_HOST = '127.0.0.1'
DB_USER = 'root'
DB_PASS = ''
DB_NAME = 'plat_reader'
STREAM_PORT = 8093
cameras = {}
lock = threading.Lock()

def get_cameras():
    db = mysql.connector.connect(host=DB_HOST, user=DB_USER, password=DB_PASS, database=DB_NAME)
    cur = db.cursor(dictionary=True)
    cur.execute("SELECT * FROM cameras WHERE aktif=1")
    rows = cur.fetchall()
    cur.close()
    db.close()
    return rows

def reload_cameras():
    with lock:
        for cap in cameras.values():
            cap.release()
        cameras.clear()
        for cam in get_cameras():
            url = cam['url'].strip()
            cap = cv2.VideoCapture(0 if url == '0' else url)
            if cap.isOpened():
                cameras[cam['id']] = cap
                print(f"[STREAM] Camera {cam['id']} ({cam['nama']}) connected")
            else:
                print(f"[STREAM] Camera {cam['id']} ({cam['nama']}) FAILED")

class ThreadingHTTPServer(ThreadingMixIn, HTTPServer):
    daemon_threads = True

class StreamHandler(BaseHTTPRequestHandler):
    def do_GET(self):
        if self.path == '/':
            self.send_response(200)
            self.send_header('Content-Type', 'text/html; charset=utf-8')
            self.end_headers()
            html = '<html><head><meta charset="utf-8"><title>Camera Streams</title><style>body{background:#0f1923;color:#fff;font-family:sans-serif}img{max-width:100%%;border-radius:8px;margin:10px 0}.cam{background:#1a2a3a;padding:15px;border-radius:12px;margin:10px 0}h1{color:#00e5ff}</style></head><body><h1>Live Camera Streams</h1>'
            with lock:
                for cid in sorted(cameras.keys()):
                    html += f'<div class="cam"><h3>Camera {cid}</h3><img src="/stream/{cid}"></div>'
            html += '</body></html>'
            self.wfile.write(html.encode())
        elif self.path.startswith('/stream/'):
            try:
                cam_id = int(self.path.split('/')[-1])
            except ValueError:
                self.send_error(404)
                return
            with lock:
                cap = cameras.get(cam_id)
                if cap is None:
                    self.send_error(404, 'Camera not found')
                    return
            self.send_response(200)
            self.send_header('Content-Type', 'multipart/x-mixed-replace; boundary=frame')
            self.send_header('Cache-Control', 'no-cache')
            self.send_header('Connection', 'keep-alive')
            self.end_headers()
            last_frame = None
            try:
                while True:
                    with lock:
                        ret, frame = cap.read()
                    if not ret:
                        time.sleep(0.5)
                        continue
                    _, buf = cv2.imencode('.jpg', frame, [cv2.IMWRITE_JPEG_QUALITY, 60])
                    if buf is None:
                        time.sleep(0.5)
                        continue
                    self.wfile.write(b'--frame\r\n')
                    self.wfile.write(b'Content-Type: image/jpeg\r\n')
                    self.wfile.write(f'Content-Length: {len(buf)}\r\n\r\n'.encode())
                    self.wfile.write(buf.tobytes())
                    time.sleep(0.05)
            except (BrokenPipeError, ConnectionResetError, ConnectionAbortedError):
                pass
        elif self.path.startswith('/snapshot/'):
            try:
                cam_id = int(self.path.split('/')[-1])
            except ValueError:
                self.send_error(404)
                return
            with lock:
                cap = cameras.get(cam_id)
                if cap is None:
                    self.send_error(404, 'Camera not found')
                    return
                ret, frame = cap.read()
            if not ret:
                self.send_error(500, 'Failed to capture frame')
                return
            _, buf = cv2.imencode('.jpg', frame, [cv2.IMWRITE_JPEG_QUALITY, 70])
            self.send_response(200)
            self.send_header('Content-Type', 'image/jpeg')
            self.send_header('Cache-Control', 'no-cache, no-store, must-revalidate')
            self.send_header('Pragma', 'no-cache')
            self.send_header('Content-Length', str(len(buf)))
            self.end_headers()
            self.wfile.write(buf.tobytes())
        elif self.path == '/reload':
            reload_cameras()
            self.send_response(200)
            self.end_headers()
            self.wfile.write(b'Reloaded')
        else:
            self.send_error(404)

    def log_message(self, format, *args):
        pass

if __name__ == '__main__':
    print(f"[STREAM] Starting camera streamer on port {STREAM_PORT}...")
    print(f"[STREAM] Access: http://127.0.0.1:{STREAM_PORT}/")
    reload_cameras()

    # Thread to periodically reload cameras
    def reload_loop():
        while True:
            time.sleep(60)
            reload_cameras()
    t = threading.Thread(target=reload_loop, daemon=True)
    t.start()

    server = ThreadingHTTPServer(('0.0.0.0', STREAM_PORT), StreamHandler)
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        print("\n[STREAM] Shutting down...")
        with lock:
            for cap in cameras.values():
                cap.release()
