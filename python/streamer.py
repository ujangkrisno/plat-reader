import io
import os
import re
import cv2
import time
import threading
import datetime
import mysql.connector
import easyocr
from http.server import HTTPServer, BaseHTTPRequestHandler
from socketserver import ThreadingMixIn
from urllib.parse import urlparse

DB_HOST = '127.0.0.1'
DB_USER = 'root'
DB_PASS = ''
DB_NAME = 'plat_reader'
STREAM_PORT = 8093
DETECT_INTERVAL = 5
CAPTURE_DIR = os.path.join(os.path.dirname(__file__), '..', 'captures')
MIN_CONFIDENCE = 0.4
PLATE_PATTERN = re.compile(r'[A-Z]{1,2}\s?\d{1,4}\s?[A-Z]{1,3}')
detected_cache = {}
CACHE_TIMEOUT = 300
cameras = {}
lock = threading.Lock()

os.makedirs(CAPTURE_DIR, exist_ok=True)

# EasyOCR (lazy init)
ocr_reader = None
def get_ocr():
    global ocr_reader
    if ocr_reader is None:
        ocr_reader = easyocr.Reader(['en'], gpu=False)
    return ocr_reader

def get_db():
    return mysql.connector.connect(host=DB_HOST, user=DB_USER, password=DB_PASS, database=DB_NAME)

def get_cameras():
    db = get_db()
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
        self.path = urlparse(self.path).path
        if self.path == '/':
            self.send_response(200)
            self.send_header('Content-Type', 'text/html; charset=utf-8')
            self.end_headers()
            html = '''<html><head><meta charset="utf-8"><title>Camera Streams</title>
<style>
body{background:#0f1923;color:#fff;font-family:sans-serif;margin:20px}
.cam{background:#1a2a3a;border:1px solid #2a3a4a;border-radius:12px;padding:15px;margin:10px 0}
.cam img{width:100%;border-radius:8px;background:#000;min-height:200px}
.cam h3{color:#00e5ff}
.status{float:right;font-size:0.8rem;padding:2px 10px;border-radius:10px}
.on{background:#00c853;color:#fff}
.off{background:#ff5252;color:#fff}
h1{color:#00e5ff}
</style></head><body><h1>Live Camera Streams</h1>'''
            with lock:
                for cid in sorted(cameras.keys()):
                    html += f'<div class="cam"><h3>Camera {cid} <span class="status on" id="s{cid}">Live</span></h3><img id="i{cid}" src=""></div>'
            html += '''<script>
var cids=''' + str(sorted(cameras.keys())).replace(' ','') + ''';
function load(cid){document.getElementById('i'+cid).src='/snapshot/'+cid+'?_='+Date.now();}
function ok(cid){var s=document.getElementById('s'+cid);if(s){s.className='status on';s.textContent='Live';}}
function fail(cid){var s=document.getElementById('s'+cid);if(s){s.className='status off';s.textContent='Off';}}
cids.forEach(function(cid){
var i=document.getElementById('i'+cid);
if(i){i.onload=function(){ok(cid);};i.onerror=function(){fail(cid);};load(cid);}
});
setInterval(function(){cids.forEach(function(cid){load(cid);});},2000);
</script></body></html>'''
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

# ─── Plate Detection ──────────────────────────────────────────

def clean_plate_text(text):
    text = text.upper().strip()
    text = re.sub(r'[^A-Z0-9]', '', text)
    return text

def extract_plate(text):
    matches = PLATE_PATTERN.findall(text.upper())
    if matches:
        return matches[0].replace(' ', '')
    return None

def save_plate(camera_id, plat, confidence, filename):
    db = None
    try:
        now = time.time()
        if plat in detected_cache and now - detected_cache[plat] < CACHE_TIMEOUT:
            return False
        detected_cache[plat] = now
        db = get_db()
        cur = db.cursor()
        gambar = 'captures/' + filename if filename else ''
        cur.execute(
            "INSERT INTO plat_nomor (camera_id, plat, gambar, confidence) VALUES (%s, %s, %s, %s)",
            (camera_id, plat, gambar, confidence)
        )
        db.commit()
        cur.close()
        return True
    except Exception as e:
        print(f"[PLATE] DB error: {e}")
        return False
    finally:
        if db:
            try: db.close()
            except: pass

def process_frame(frame, camera_id, timestamp):
    h, w = frame.shape[:2]
    if w > 1280:
        scale = 1280 / w
        frame = cv2.resize(frame, (int(w*scale), int(h*scale)))
    results = get_ocr().readtext(frame)
    best_plate = None
    best_conf = 0
    for bbox, text, conf in results:
        if conf < MIN_CONFIDENCE:
            continue
        cleaned = clean_plate_text(text)
        plate = extract_plate(cleaned)
        if plate and conf > best_conf:
            best_plate = plate
            best_conf = conf
    if best_plate:
        ts = timestamp.strftime('%Y%m%d_%H%M%S')
        fname = f"plat_{ts}_{camera_id}_{best_plate}.jpg"
        fpath = os.path.join(CAPTURE_DIR, fname)
        cv2.imwrite(fpath, frame)
        saved = save_plate(camera_id, best_plate, best_conf * 100, fname)
        if saved:
            print(f"[DETECT] CAM {camera_id} \u2192 {best_plate} ({best_conf*100:.1f}%)")
        return best_plate, best_conf
    return None, 0

def detection_worker(cam):
    cam_id = cam['id']
    name = cam['nama']
    print(f"[DETECT] Starting detection for {name} (cam {cam_id})")
    while True:
        try:
            with lock:
                cap = cameras.get(cam_id)
                if cap is None:
                    time.sleep(5)
                    continue
                ret, frame = cap.read()
            if not ret:
                time.sleep(1)
                continue
            timestamp = datetime.datetime.now()
            process_frame(frame, cam_id, timestamp)
            time.sleep(DETECT_INTERVAL)
        except KeyboardInterrupt:
            break
        except Exception as e:
            print(f"[DETECT] CAM {cam_id} error: {e}")
            time.sleep(5)

# ─── Main ──────────────────────────────────────────────────────

if __name__ == '__main__':
    print(f"[STREAM] Starting plat-reader on port {STREAM_PORT}...")
    print(f"[STREAM] Live view: http://127.0.0.1:{STREAM_PORT}/")
    print(f"[STREAM] Detection interval: {DETECT_INTERVAL}s")
    print("=" * 50)
    reload_cameras()

    # Reload cameras every 60s
    def reload_loop():
        while True:
            time.sleep(60)
            reload_cameras()
    threading.Thread(target=reload_loop, daemon=True).start()

    # Start detection threads for each camera
    for cam in get_cameras():
        t = threading.Thread(target=detection_worker, args=(cam,), daemon=True)
        t.start()

    # Start HTTP server
    server = ThreadingHTTPServer(('0.0.0.0', STREAM_PORT), StreamHandler)
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        print("\nShutting down...")
        with lock:
            for cap in cameras.values():
                cap.release()
