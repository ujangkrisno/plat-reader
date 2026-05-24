<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['foto'])) {
    echo json_encode(['error' => 'No file uploaded']);
    exit;
}

$file = $_FILES['foto'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'Upload error: ' . $file['error']]);
    exit;
}

$fname = 'captured_1_upload_' . date('Ymd_His') . '.jpg';
$fpath = __DIR__ . '/captures/' . $fname;
move_uploaded_file($file['tmp_name'], $fpath);

// Call streamer OCR
$ocr_url = "http://127.0.0.1:8093/ocr_path/1";
$ctx = stream_context_create(['http' => ['timeout' => 60]]);
$json = @file_get_contents($ocr_url, false, $ctx);

if (!$json) {
    echo json_encode(['error' => 'Streamer OCR timeout / down']);
    exit;
}

$data = json_decode($json, true);
if (!$data || !empty($data['error'])) {
    echo json_encode(['error' => ($data['error'] ?? 'Invalid response')]);
    exit;
}

$data['image'] = $fname;
echo json_encode($data);
