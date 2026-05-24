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

// Call Python OCR directly
$python = 'python';
$script = __DIR__ . '/python/ocr_file.py';
$cmd = sprintf('"%s" "%s" "%s"', $python, $script, $fpath);

$descriptorspec = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$proc = proc_open($cmd, $descriptorspec, $pipes);
if (!is_resource($proc)) {
    echo json_encode(['error' => 'Failed to start Python process']);
    exit;
}

fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$ret = proc_close($proc);

if ($ret !== 0) {
    echo json_encode(['error' => 'Python error: ' . trim($stderr)]);
    exit;
}

$data = json_decode($stdout, true);
if (!$data) {
    echo json_encode(['error' => 'Invalid output: ' . substr($stdout, 0, 200)]);
    exit;
}

echo $stdout;
