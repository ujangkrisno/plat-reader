<?php
// Save snapshot from streamer as captured_{cam_id}.jpg
$cam_id = (int)($_GET['cam'] ?? 0);
if (!$cam_id) { http_response_code(400); die('Invalid cam'); }

$snap = @file_get_contents("http://127.0.0.1:8093/snapshot/$cam_id?_=" . time());
if (!$snap) { http_response_code(500); die('Snapshot failed'); }

$fname = "captured_{$cam_id}_" . date('Ymd_His') . ".jpg";
$path = __DIR__ . '/captures/' . $fname;
file_put_contents($path, $snap);

// Remove old captured images for this cam (keep max 2)
$old = glob(__DIR__ . "/captures/captured_{$cam_id}_*.jpg");
usort($old, function($a, $b) { return filemtime($a) - filemtime($b); });
while (count($old) > 2) {
    unlink(array_shift($old));
}

header('Content-Type: application/json');
echo json_encode(['status' => 'ok', 'file' => $fname, 'size' => strlen($snap)]);
