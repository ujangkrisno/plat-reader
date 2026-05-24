"""
Plat Reader - License Plate Recognition
Reads from IP cameras / phone cameras, detects Indonesian license plates.
"""
import os
import re
import time
import threading
import argparse
import datetime
import mysql.connector
import cv2
import easyocr

# ─── Konfigurasi ───────────────────────────────────────────────
DB_HOST = '127.0.0.1'
DB_USER = 'root'
DB_PASS = ''
DB_NAME = 'plat_reader'

CAPTURE_DIR = os.path.join(os.path.dirname(__file__), '..', 'captures')
os.makedirs(CAPTURE_DIR, exist_ok=True)

# Interval capture (detik)
INTERVAL = 5

# Minimal confidence untuk OCR
MIN_CONFIDENCE = 0.4

# Regex pola plat Indonesia: 1-2 huruf, 1-4 angka, 1-3 huruf
PLATE_PATTERN = re.compile(r'[A-Z]{1,2}\s?\d{1,4}\s?[A-Z]{1,3}')

# Simpan plat yang sudah terdeteksi (untuk avoid duplikat)
# Key=plat, Value=timestamp
detected_cache = {}
CACHE_TIMEOUT = 300  # 5 menit sebelum bisa deteksi ulang plat yang sama

# ─── Database ──────────────────────────────────────────────────
def get_db():
    return mysql.connector.connect(
        host=DB_HOST, user=DB_USER, password=DB_PASS, database=DB_NAME
    )

def get_cameras():
    db = get_db()
    cur = db.cursor(dictionary=True)
    cur.execute("SELECT * FROM cameras WHERE aktif=1")
    rows = cur.fetchall()
    cur.close()
    db.close()
    return rows

def save_plate(camera_id, plat, confidence, filename):
    db = get_db()
    cur = db.cursor()
    # Cek apakah plat sudah pernah terdeteksi dalam CACHE_TIMEOUT detik
    now = time.time()
    if plat in detected_cache:
        if now - detected_cache[plat] < CACHE_TIMEOUT:
            cur.close()
            db.close()
            return False
    detected_cache[plat] = now

    gambar = 'captures/' + filename if filename else ''
    cur.execute(
        "INSERT INTO plat_nomor (camera_id, plat, gambar, confidence) VALUES (%s, %s, %s, %s)",
        (camera_id, plat, gambar, confidence)
    )
    db.commit()
    cur.close()
    db.close()
    return True

# ─── OCR ───────────────────────────────────────────────────────
reader = easyocr.Reader(['en'], gpu=False)

def clean_plate_text(text):
    """Bersihkan hasil OCR jadi format plat standar: AB 1234 CD"""
    text = text.upper().strip()
    text = re.sub(r'[^A-Z0-9]', '', text)  # hapus non alfanumerik
    return text

def extract_plate(text):
    """Coba ekstrak pola plat dari text"""
    matches = PLATE_PATTERN.findall(text.upper())
    if matches:
        return matches[0].replace(' ', '')
    return None

def process_frame(frame, camera_id, timestamp):
    """Proses satu frame untuk deteksi plat"""
    h, w = frame.shape[:2]

    # Resize untuk performa
    if w > 1280:
        scale = 1280 / w
        new_w = int(w * scale)
        new_h = int(h * scale)
        frame = cv2.resize(frame, (new_w, new_h))

    # OCR
    results = reader.readtext(frame)

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
        # Simpan gambar
        ts = timestamp.strftime('%Y%m%d_%H%M%S')
        fname = f"plat_{ts}_{camera_id}_{best_plate}.jpg"
        fpath = os.path.join(CAPTURE_DIR, fname)
        cv2.imwrite(fpath, frame)

        # Simpan ke DB
        saved = save_plate(camera_id, best_plate, best_conf * 100, fname)
        if saved:
            print(f"[{ts}] CAM {camera_id} → {best_plate} ({best_conf*100:.1f}%)")
        return best_plate, best_conf
    return None, 0

# ─── Camera Loop ──────────────────────────────────────────────
def camera_worker(cam):
    """Loop untuk satu kamera"""
    cam_id = cam['id']
    url = cam['url']
    nama = cam['nama']
    print(f"[CAM {cam_id}] Starting: {nama} - {url}")

    cap = None
    retry_count = 0
    max_retries = 10

    # Laptop webcam jika URL = "0"
    is_webcam = (url.strip() == '0')
    if is_webcam:
        url = 0

    while True:
        try:
            if cap is None or not cap.isOpened():
                cap = cv2.VideoCapture(url)
                if not cap.isOpened():
                    retry_count += 1
                    print(f"[CAM {cam_id}] Failed to open stream, retry {retry_count}/{max_retries}")
                    if retry_count > max_retries:
                        print(f"[CAM {cam_id}] Giving up after {max_retries} retries")
                        break
                    time.sleep(10)
                    continue
                retry_count = 0
                print(f"[CAM {cam_id}] Stream opened")

            ret, frame = cap.read()
            if not ret:
                print(f"[CAM {cam_id}] Frame read failed, reconnecting...")
                cap.release()
                cap = None
                time.sleep(3)
                continue

            timestamp = datetime.datetime.now()
            process_frame(frame, cam_id, timestamp)

            time.sleep(INTERVAL)

        except KeyboardInterrupt:
            break
        except Exception as e:
            print(f"[CAM {cam_id}] Error: {e}")
            time.sleep(5)

    if cap:
        cap.release()
    print(f"[CAM {cam_id}] Stopped")

# ─── Main ──────────────────────────────────────────────────────
def main():
    parser = argparse.ArgumentParser(description='Plat Reader - License Plate Recognition')
    parser.add_argument('--interval', type=int, default=INTERVAL, help='Capture interval in seconds')
    parser.add_argument('--camera', type=int, default=None, help='Specific camera ID to run')
    args = parser.parse_args()

    global INTERVAL
    INTERVAL = args.interval

    print("=" * 50)
    print("Plat Reader - License Plate Recognition")
    print(f"Interval: {INTERVAL}s")
    print("=" * 50)

    cameras = get_cameras()
    if not cameras:
        print("No active cameras found. Add cameras via setup.php or directly in DB.")
        return

    if args.camera:
        cameras = [c for c in cameras if c['id'] == args.camera]
        if not cameras:
            print(f"Camera ID {args.camera} not found or not active.")
            return

    threads = []
    for cam in cameras:
        t = threading.Thread(target=camera_worker, args=(cam,), daemon=True)
        t.start()
        threads.append(t)

    try:
        for t in threads:
            t.join()
    except KeyboardInterrupt:
        print("\nShutting down...")

if __name__ == '__main__':
    main()
