"""OCR on a single image file. Usage: python ocr_file.py <image_path>"""
import sys, json, os, cv2, easyocr, re

PLATE_FULL = re.compile(r'[A-Z]{1,2}\s?\d{1,4}\s?[A-Z]{1,3}')
reader = easyocr.Reader(['en'], gpu=False)

def ensure_3channel(img):
    if img is None: return None
    if len(img.shape) == 2: return cv2.cvtColor(img, cv2.COLOR_GRAY2BGR)
    if img.shape[2] == 4: return cv2.cvtColor(img, cv2.COLOR_BGRA2BGR)
    return img

def extract_plate(text):
    m = PLATE_FULL.search(text.upper())
    return m.group().replace(' ', '') if m else None

fpath = sys.argv[1]
img = cv2.imread(fpath)
if img is None:
    print(json.dumps({'error': f'Cannot read image: {fpath}'}))
    sys.exit(1)

img = ensure_3channel(img)
h, w = img.shape[:2]
if w > 1280:
    scale = 1280 / w
    img = cv2.resize(img, (int(w*scale), int(h*scale)))

results = reader.readtext(img)
results.sort(key=lambda r: sum(p[0] for p in r[0]) / 4)

best_plate = None
best_conf = 0
best_bbox = None
ocr_log = []

for bbox, text, conf in results:
    if conf >= 0.3:
        p = extract_plate(text)
        ocr_log.append({'text': text, 'conf': round(conf, 3), 'plate': p})
        if p and conf > best_conf:
            best_plate = p
            best_conf = conf
            best_bbox = bbox

for start in range(len(results)):
    for end in range(start + 1, min(start + 4, len(results))):
        group = results[start:end + 1]
        y_positions = [b[0][1] for b, _, _ in group]
        if max(y_positions) - min(y_positions) > 30: continue
        texts = [t for _, t, _ in group]
        combined = ' '.join(texts)
        plate = extract_plate(combined)
        if plate:
            avg_c = sum(c * len(t) for _, t, c in group) / max(sum(len(t) for _, t, _ in group), 1)
            if avg_c > best_conf:
                best_plate = plate
                best_conf = avg_c
                all_pts = [p for b, _, _ in group for p in b]
                xs = [p[0] for p in all_pts]
                ys = [p[1] for p in all_pts]
                best_bbox = [[xs[0], ys[0]], [xs[0], ys[-1]], [xs[-1], ys[-1]], [xs[-1], ys[0]]]
            ocr_log.append({'text': combined, 'conf': round(avg_c, 3), 'plate': plate, 'merged': True})

out = {
    'plate': best_plate,
    'confidence': round(best_conf * 100, 1) if best_conf else 0,
    'ocr_log': ocr_log[-15:]
}
if best_bbox:
    out['bbox'] = [[int(p[0]), int(p[1])] for p in best_bbox]

print(json.dumps(out))
