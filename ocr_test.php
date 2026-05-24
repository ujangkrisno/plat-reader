<?php
$title = 'OCR Test';
include 'config/database.php';
$msg = '';
$ocr_result = '';

if (isset($_GET['test'])) {
    $cam_id = (int)($_GET['cam'] ?? 1);
    $snap = @file_get_contents("http://127.0.0.1:8093/snapshot/$cam_id");
    if ($snap) {
        $fname = "test_manual_" . date('Ymd_His') . "_cam$cam_id.jpg";
        file_put_contents(__DIR__ . '/captures/' . $fname, $snap);
        $ocr_result = "Frame saved: <a href='captures/$fname' target='_blank'>$fname</a> (" . round(strlen($snap)/1024) . "KB)";
    } else {
        $msg = 'Gagal ambil snapshot dari streamer';
    }
}

$cams = mysqli_query($con, "SELECT * FROM cameras WHERE aktif=1 ORDER BY id");
$latest_cam1 = glob(__DIR__ . "/captures/debug_*cam1*");
$latest_cam1 = $latest_cam1 ? basename(end($latest_cam1)) : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OCR Test - Plat Reader</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { background:#0f1923; color:#fff; font-family:'Segoe UI',sans-serif; }
        .navbar { background:linear-gradient(90deg,#0d2137,#1a3a5c); }
        .card-ocr { background:#1a2a3a; border:1px solid #2a3a4a; border-radius:12px; padding:20px; }
        .pre-box { background:#0a121c; color:#00e5ff; padding:15px; border-radius:8px; font-family:monospace; max-height:400px; overflow:auto; }
    </style>
</head>
<body>
<nav class="navbar navbar-dark px-3 mb-4">
    <span class="navbar-brand mb-0"><i class="fas fa-microscope me-2"></i>OCR Test Manual</span>
    <a href="index.php" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left"></i> Dashboard</a>
</nav>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-8">
            <div class="card-ocr mb-3">
                <h6 class="fw-bold mb-3"><i class="fas fa-camera me-2"></i>Live View + Test OCR</h6>
                <div class="row">
                    <?php while ($cam = mysqli_fetch_assoc($cams)): ?>
                    <div class="col-md-6 mb-3">
                        <div class="card-ocr text-center p-2" style="background:#0f1923;">
                            <strong><?= $cam['nama'] ?></strong>
                            <img src="http://127.0.0.1:8093/snapshot/<?= $cam['id'] ?>?_=<?= time() ?>" style="width:100%;border-radius:8px;margin:10px 0;background:#000;min-height:150px;" onerror="this.style.display='none'">
                            <a href="?test=1&cam=<?= $cam['id'] ?>" class="btn btn-sm btn-info"><i class="fas fa-search"></i> OCR Test Frame Ini</a>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <?php if ($ocr_result): ?>
            <div class="card-ocr mb-3">
                <h6 class="fw-bold mb-2 text-success"><?= $ocr_result ?></h6>
                <p class="text-muted small">Proses OCR via Python butuh ~5-10 detik. Hasil akan muncul di log:</p>
                <code class="text-info">captures/ocr_1.log</code>
            </div>
            <?php endif; ?>

            <div class="card-ocr">
                <h6 class="fw-bold mb-3"><i class="fas fa-list me-2"></i>Log OCR Terakhir (cam1)</h6>
                <div class="pre-box">
                    <?php
                    $log = @file(__DIR__ . '/captures/ocr_1.log');
                    if ($log) {
                        $lines = array_slice($log, -15);
                        echo htmlspecialchars(implode('', $lines));
                    } else {
                        echo 'Belum ada log OCR';
                    }
                    ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card-ocr mb-3">
                <h6 class="fw-bold mb-2">Debug Frame Terbaru</h6>
                <?php if ($latest_cam1): ?>
                <a href="captures/<?= $latest_cam1 ?>" target="_blank">
                    <img src="captures/<?= $latest_cam1 ?>" style="width:100%;border-radius:8px;">
                </a>
                <small class="text-muted d-block mt-1">Klik untuk perbesar</small>
                <?php else: ?>
                <p class="text-muted">Belum ada debug frame</p>
                <?php endif; ?>
            </div>

            <div class="card-ocr">
                <h6 class="fw-bold mb-2">Cara Test</h6>
                <ol class="text-muted small" style="padding-left:18px;">
                    <li>Arahkan plat nomor ke kamera (jarak 20-30cm)</li>
                    <li>Klik <strong>"OCR Test Frame Ini"</strong></li>
                    <li>Tunggu 5-10 detik, refresh halaman</li>
                    <li>Cek log OCR untuk hasil deteksi teks</li>
                </ol>
            </div>
        </div>
    </div>
</div>
</body>
</html>
