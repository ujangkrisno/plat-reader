<?php
include 'config/database.php';

$result = '';
$plate_found = null;
$conf = 0;
$ocr_texts = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['foto'])) {
    $file = $_FILES['foto'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
            // Konversi ke JPG murni (hapus alpha channel jika PNG)
            $img = @imagecreatefromstring(file_get_contents($file['tmp_name']));
            if ($img) {
                $fname = 'captured_1_upload_' . date('Ymd_His') . '.jpg';
                $fpath = __DIR__ . '/captures/' . $fname;
                imagejpeg($img, $fpath, 90);
                imagedestroy($img);

                // Call streamer OCR on this file
                $ocr_url = "http://127.0.0.1:8093/ocr_path/1";
                $ctx = stream_context_create(['http' => ['timeout' => 60]]);
                $json = @file_get_contents($ocr_url, false, $ctx);

                if ($json) {
                    $data = json_decode($json, true);
                    if ($data && $data['plate']) {
                        $plate_found = $data['plate'];
                        $conf = $data['confidence'];
                    }
                    if ($data && !empty($data['ocr_log'])) {
                        $ocr_texts = $data['ocr_log'];
                    }
                    if ($data && isset($data['error'])) {
                        $result = 'error: ' . $data['error'];
                    } else {
                        $result = $fname;
                    }
                } else {
                    $result = 'Gagal hubungi streamer (port 8093)';
                }
            } else {
                $result = 'Gagal membaca file gambar';
            }
        } else {
            $result = 'Format file tidak didukung (hanya JPG/PNG)';
        }
    }
}

$cams = mysqli_query($con, "SELECT * FROM cameras WHERE aktif=1 ORDER BY id");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Upload Test - Plat Reader</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#0f1923;color:#fff;font-family:'Segoe UI',sans-serif}
.navbar{background:linear-gradient(90deg,#0d2137,#1a3a5c)}
.card{background:#1a2a3a;border:1px solid #2a3a4a;border-radius:12px;padding:20px;margin-bottom:15px}
.pre-box{background:#0a121c;color:#00e5ff;padding:15px;border-radius:8px;font-family:monospace;max-height:300px;overflow:auto}
.plate-result{font-size:1.6rem;font-weight:bold;text-align:center;padding:15px;border-radius:12px;margin:10px 0}
.plate-found{background:#004d26;border:2px solid #00e676;color:#00e676}
.plate-none{background:#2a1520;border:2px solid #ff5252;color:#ff5252}
.upload-area{border:2px dashed #2a3a4a;border-radius:12px;padding:40px;text-align:center;cursor:pointer;transition:0.3s}
.upload-area:hover{border-color:#ffab00;background:#1a2a3a}
</style>
</head>
<body>
<nav class="navbar navbar-dark px-3 mb-3">
<span class="navbar-brand mb-0"><i class="fas fa-upload me-2"></i>Upload Foto Plat</span>
<a href="ocr_test.php" class="btn btn-outline-light btn-sm me-2"><i class="fas fa-microscope"></i> OCR Test</a>
<a href="index.php" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left"></i> Dashboard</a>
</nav>

<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-8">
      <div class="card text-center">
        <h5 class="fw-bold mb-3"><i class="fas fa-camera-retro me-2"></i>Test OCR dengan Foto HP</h5>
        <p class="text-muted">Ambil foto plat nomor pakai HP, upload, langsung deteksi.</p>

        <form method="post" enctype="multipart/form-data">
          <label class="upload-area d-block" id="dropzone">
            <i class="fas fa-cloud-upload-alt" style="font-size:3rem;color:#ffab00"></i>
            <p class="mt-2 mb-0"><strong>Klik untuk pilih foto</strong></p>
            <small class="text-muted">atau drag & drop (JPG/PNG)</small>
            <input type="file" name="foto" accept="image/jpeg,image/png" required style="display:none" id="fileInput">
          </label>
          <div id="preview" class="mt-3" style="display:none">
            <img id="previewImg" style="max-width:100%;max-height:400px;border-radius:8px;">
          </div>
          <button type="submit" class="btn btn-warning btn-lg mt-3 w-100" id="btnUpload">
            <i class="fas fa-microchip"></i> PROSES OCR
          </button>
        </form>
      </div>

      <?php if ($result): ?>
      <div class="card">
        <h6 class="fw-bold mb-3">Hasil Deteksi</h6>

        <?php if ($plate_found): ?>
        <div class="plate-result plate-found">
          <i class="fas fa-check-circle me-2"></i> PLAT TERDETEKSI!<br>
          <span style="font-size:2.8rem"><?= $plate_found ?></span><br>
          <small>Confidence: <?= $conf ?>%</small>
        </div>
        <?php else: ?>
        <div class="plate-result plate-none">
          <i class="fas fa-times-circle me-2"></i> TIDAK ADA PLAT TERDETEKSI<br>
          <small>Coba foto lebih jelas / jarak lebih dekat</small>
        </div>
        <?php endif; ?>

        <?php
        $is_error = $result && (strpos($result, 'error') === 0 || strpos($result, 'Gagal') === 0);
        if ($result && !$is_error):
        ?>
        <div class="text-center mt-2">
          <img src="captures/<?= $result ?>" style="max-width:100%;max-height:300px;border-radius:8px;">
        </div>
        <?php elseif ($is_error): ?>
        <div class="alert alert-danger mt-3"><?= $result ?></div>
        <?php endif; ?>

        <?php if ($ocr_texts): ?>
        <div class="mt-3">
          <strong class="text-info">Semua teks terdeteksi di foto ini:</strong>
          <div class="pre-box mt-2">
            <?php foreach ($ocr_texts as $l): ?>
              <span class="<?= strpos($l, 'plate=') !== false && strpos($l, 'plate=None') === false ? 'text-success' : 'text-muted' ?>"><?= htmlspecialchars($l) ?><br>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <div class="card">
        <h6 class="fw-bold mb-2">Cara Test</h6>
        <ol class="text-muted small mb-0">
          <li>Ambil foto plat nomor pakai HP (usahakan jelas, jarak dekat)</li>
          <li>Upload foto di atas</li>
          <li>Klik <strong>PROSES OCR</strong> — hasil langsung keluar</li>
        </ol>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('dropzone').onclick = function(){ document.getElementById('fileInput').click(); };
document.getElementById('fileInput').onchange = function(e){
  var f = e.target.files[0];
  if (!f) return;
  var r = new FileReader();
  r.onload = function(ev){ document.getElementById('previewImg').src = ev.target.result; };
  r.readAsDataURL(f);
  document.getElementById('preview').style.display = 'block';
};
</script>
</body>
</html>
