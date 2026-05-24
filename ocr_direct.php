<?php
// Direct OCR: call Python script directly on an image file
header('Content-Type: application/json');

$cam_id = (int)($_GET['cam'] ?? 1);
$captures = __DIR__ . '/captures';

// Find latest captured image for this camera
$files = glob("$captures/captured_{$cam_id}_*.jpg");
if (!$files) {
    echo json_encode(['error' => 'No captured image. Click TANGKAP GAMBAR first.']);
    exit;
}
usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
$fpath = $files[0];

// Call Python OCR directly (redirect stderr to avoid buffer deadlock on Windows)
$python = 'C:\Users\aero\AppData\Local\Programs\Python\Python310\python.exe';
$script = __DIR__ . '/python/ocr_file.py';
$cmd = sprintf('"%s" "%s" "%s" 2>NUL', $python, $script, $fpath);

$stdout = shell_exec($cmd);
$ret = $stdout !== null ? 0 : 1;

if ($ret !== 0) {
    echo json_encode(['error' => 'Gagal menjalankan Python. Cek PATH atau instalasi Python.']);
    exit;
}

$data = json_decode($stdout, true);
if (!$data) {
    echo json_encode(['error' => 'Output tidak valid: ' . substr($stdout, 0, 200)]);
    exit;
}

echo $stdout;
