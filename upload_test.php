<?php include 'config/database.php'; ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>OCR Test - Plat Reader</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#0f1923;color:#fff;font-family:'Segoe UI',sans-serif}
.navbar{background:linear-gradient(90deg,#0d2137,#1a3a5c)}
.card{background:#1a2a3a;border:1px solid #2a3a4a;border-radius:12px;padding:20px;margin:10px 0}
.pre-box{background:#0a121c;color:#00e5ff;padding:15px;border-radius:8px;font-family:monospace;max-height:300px;overflow:auto;font-size:13px}
.plate-result{font-size:1.6rem;font-weight:bold;text-align:center;padding:15px;border-radius:12px;margin:10px 0}
.plate-found{background:#004d26;border:2px solid #00e676;color:#00e676}
.plate-none{background:#2a1520;border:2px solid #ff5252;color:#ff5252}
.upload-btn{background:#ffab00;color:#000;border:none;padding:16px 20px;border-radius:12px;font-weight:bold;font-size:1.1rem;width:100%;cursor:pointer;transition:0.3s}
.upload-btn:hover{background:#ffc107;transform:scale(1.02)}
.upload-btn:disabled{background:#555;cursor:wait;transform:none}
.ocr-btn{background:#00bcd4;color:#000;border:none;padding:14px 20px;border-radius:12px;font-weight:bold;font-size:1.1rem;width:100%;cursor:pointer;transition:0.3s;margin-top:8px}
.ocr-btn:hover{background:#26c6da;transform:scale(1.02)}
.ocr-btn:disabled{background:#555;cursor:wait;transform:none}
#preview{max-width:100%;max-height:350px;border-radius:8px;margin:10px 0;display:none}
#loading{display:none;text-align:center;padding:30px}
#loading .spinner{width:50px;height:50px;border:4px solid #2a3a4a;border-top:4px solid #ffab00;border-radius:50%;animation:spin 1s linear infinite;margin:10px auto}
@keyframes spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}
.progress-track{height:4px;background:#2a3a4a;border-radius:2px;margin:10px 0;overflow:hidden}
.progress-bar{height:100%;background:linear-gradient(90deg,#ffab00,#00e676);width:0%;transition:width 0.5s}
</style>
</head>
<body>
<nav class="navbar navbar-dark px-3 mb-3">
<span class="navbar-brand mb-0"><i class="fas fa-camera me-2"></i>OCR Test - Foto HP</span>
<a href="index.php" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left"></i> Dashboard</a>
</nav>

<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-8">
      <div class="card text-center">
        <p class="text-muted small mb-3">
          Buka dari HP: <code style="color:#ffab00">http://192.168.1.12:8092/upload_test.php</code>

        <form method="post" enctype="multipart/form-data" id="uploadForm">
          <button type="button" class="upload-btn" id="pickBtn" onclick="document.getElementById('fileInput').click()">
            <i class="fas fa-camera me-2"></i> AMBIL FOTO
          </button>
          <input type="file" name="foto" accept="image/*" id="fileInput" style="display:none">

          <img id="preview">

          <div class="progress-track"><div class="progress-bar" id="progBar"></div></div>

          <div id="loading">
            <div class="spinner"></div>
            <strong class="text-warning">Memproses OCR...</strong>
            <p class="text-muted small mb-0">~10-20 detik (EasyOCR CPU)</p>
          </div>

          <button type="submit" class="ocr-btn" id="ocrBtn" style="display:none">
            <i class="fas fa-microchip me-2"></i> PROSES OCR
          </button>
        </form>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK):
          $fname = 'captured_1_upload_' . date('Ymd_His') . '.jpg';
          move_uploaded_file($_FILES['foto']['tmp_name'], __DIR__ . '/captures/' . $fname);

          $ocr_url = "http://127.0.0.1:8093/ocr_path/1";
          $ctx = stream_context_create(['http' => ['timeout' => 60]]);
          $json = @file_get_contents($ocr_url, false, $ctx);
          $data = $json ? json_decode($json, true) : null;
        ?>
        <div class="text-start mt-3">
          <?php if ($data && empty($data['error'])): ?>
            <?php if ($data['plate']): ?>
              <div class="plate-result plate-found">
                <i class="fas fa-check-circle me-2"></i> PLAT TERDETEKSI!<br>
                <span style="font-size:2.8rem;letter-spacing:3px"><?= $data['plate'] ?></span><br>
                <small>Confidence: <?= $data['confidence'] ?>%</small>
              </div>
            <?php else: ?>
              <div class="plate-result plate-none">
                <i class="fas fa-times-circle me-2"></i> TIDAK ADA PLAT<br>
                <small>Coba foto lebih dekat / jelas / lurus</small>
              </div>
            <?php endif; ?>
            <div class="text-center mt-2">
              <img src="captures/<?= $fname ?>" style="max-width:100%;max-height:300px;border-radius:8px;">
            </div>
            <?php if (!empty($data['ocr_log'])): ?>
              <div class="pre-box mt-2">
                <strong class="text-info">Teks terdeteksi:</strong><br>
                <?php foreach ($data['ocr_log'] as $l): ?>
                  <span class="<?= strpos($l, 'plate=') !== false && strpos($l, 'plate=None') === false ? 'text-success' : 'text-muted' ?>"><?= htmlspecialchars(is_string($l)?$l:json_encode($l)) ?><br>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          <?php else: ?>
            <div class="alert alert-danger"><?= ($data['error'] ?? 'Gagal proses OCR') ?></div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>

      <div class="card">
        <h6 class="fw-bold mb-2">Cara Pakai</h6>
        <ol class="text-muted small mb-0">
          <li>Klik <strong>AMBIL FOTO</strong> → kamera HP terbuka</li>
          <li>Foto plat nomor, tap <strong>✓ centang</strong></li>
          <li>Klik <strong>PROSES OCR</strong> → tunggu hasil</li>
        </ol>
      </div>
    </div>
  </div>
</div>

<script>
var fi = document.getElementById('fileInput');
var prv = document.getElementById('preview');
var ocrBtn = document.getElementById('ocrBtn');
var loading = document.getElementById('loading');
var progBar = document.getElementById('progBar');

fi.onchange = function(e) {
  var f = e.target.files[0];
  if (!f) return;
  var r = new FileReader();
  r.onload = function(ev) {
    prv.src = ev.target.result;
    prv.style.display = 'block';
    ocrBtn.style.display = 'block';
    ocrBtn.innerHTML = '<i class="fas fa-microchip me-2"></i> PROSES OCR (' + Math.round(f.size/1024) + 'KB)';
  };
  r.readAsDataURL(f);
};

document.getElementById('uploadForm').onsubmit = function() {
  ocrBtn.style.display = 'none';
  loading.style.display = 'block';
  progBar.style.width = '30%';
  // Animate progress
  var w = 30;
  var iv = setInterval(function() { w += 2; if (w < 90) progBar.style.width = w + '%'; }, 1000);
  setTimeout(function() { clearInterval(iv); progBar.style.width = '95%'; }, 15000);
  return true;
};
</script>
</body>
</html>
