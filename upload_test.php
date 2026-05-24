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
.card{background:#1a2a3a;border:1px solid #2a3a4a;border-radius:12px;padding:20px;margin-bottom:15px}
.pre-box{background:#0a121c;color:#00e5ff;padding:15px;border-radius:8px;font-family:monospace;max-height:300px;overflow:auto;font-size:13px}
.pre-box .plate{color:#00e676}
.pre-box .no-plate{color:#667788}
.plate-result{font-size:1.6rem;font-weight:bold;text-align:center;padding:15px;border-radius:12px;margin:10px 0}
.plate-found{background:#004d26;border:2px solid #00e676;color:#00e676}
.plate-none{background:#2a1520;border:2px solid #ff5252;color:#ff5252}
.upload-area{border:2px dashed #2a3a4a;border-radius:12px;padding:40px;text-align:center;cursor:pointer;transition:0.3s}
.upload-area:hover{border-color:#ffab00;background:#1a2a3a}
.upload-area.processing{pointer-events:none;opacity:0.5}
#resultArea{display:none}
#statusBar{height:4px;background:#ffab00;border-radius:2px;transition:width 0.3s;width:0%;margin:8px 0}
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
        <h5 class="fw-bold mb-3">Foto Plat Nomor Pakai HP</h5>
        <p class="text-muted small">
          Buka dari HP: <code>http://192.168.1.12:8092/upload_test.php</code><br>
          Foto plat → langsung diproses → hasil muncul.

        <label class="upload-area d-block" id="dropzone">
          <i class="fas fa-camera" style="font-size:3rem;color:#ffab00"></i>
          <p class="mt-2 mb-0"><strong>Tap untuk foto / pilih gambar</strong></p>
          <small class="text-muted">Kamera belakang otomatis</small>
          <input type="file" accept="image/*" capture="environment" style="display:none" id="fileInput">
        </label>

        <div id="statusBar"></div>
        <div id="statusText" class="text-info small mt-2" style="display:none">
          <i class="fas fa-spinner fa-spin"></i> Memproses OCR...
        </div>
      </div>

      <div id="resultArea">
        <div class="card">
          <h6 class="fw-bold mb-3">Hasil Deteksi</h6>
          <div id="plateResult"></div>
          <div id="imageResult" class="text-center mt-2"></div>
          <div id="ocrLog" class="pre-box mt-2" style="display:none"></div>
        </div>
      </div>

      <div class="card">
        <h6 class="fw-bold mb-2">Cara</h6>
        <ol class="text-muted small mb-0">
          <li>Tap area upload → kamera HP terbuka</li>
          <li>Foto plat nomor (dekat, jelas, lurus)</li>
          <li>Tap <strong>centang ✓</strong> → <strong>LANGSUNG DIPROSES</strong></li>
          <li>Hasil muncul otomatis</li>
        </ol>
      </div>

    </div>
  </div>
</div>

<script>
var dz = document.getElementById('dropzone');
var fi = document.getElementById('fileInput');
var sb = document.getElementById('statusBar');
var st = document.getElementById('statusText');
var ra = document.getElementById('resultArea');
var pr = document.getElementById('plateResult');
var ir = document.getElementById('imageResult');
var ol = document.getElementById('ocrLog');

dz.onclick = function(){ fi.click(); };

fi.onchange = function(e) {
  var f = e.target.files[0];
  if (!f) return;

  // Show processing UI
  dz.classList.add('processing');
  dz.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size:3rem;color:#ffab00"></i><p class="mt-2"><strong>Memproses...</strong></p>';
  st.style.display = 'block';
  ra.style.display = 'none';
  sb.style.width = '20%';

  // Upload via AJAX
  var fd = new FormData();
  fd.append('foto', f);

  var xhr = new XMLHttpRequest();
  xhr.open('POST', 'upload_ajax.php', true);

  xhr.upload.onprogress = function(e) {
    if (e.lengthComputable) sb.style.width = (10 + e.loaded / e.total * 30) + '%';
  };

  xhr.onload = function() {
    sb.style.width = '100%';
    st.style.display = 'none';
    dz.classList.remove('processing');

    try {
      var d = JSON.parse(xhr.responseText);
    } catch(e) {
      dz.innerHTML = '<i class="fas fa-exclamation-triangle" style="font-size:3rem;color:#ff5252"></i><p class="mt-2 text-danger">Gagal baca respons server</p>';
      return;
    }

    if (d.error) {
      dz.innerHTML = '<i class="fas fa-exclamation-triangle" style="font-size:3rem;color:#ff5252"></i><p class="mt-2 text-danger">' + esc(d.error) + '</p>';
      return;
    }

    // Show result
    if (d.plate) {
      pr.innerHTML = '<div class="plate-result plate-found">'
        + '<i class="fas fa-check-circle me-2"></i> PLAT TERDETEKSI!<br>'
        + '<span style="font-size:2.8rem;letter-spacing:3px">' + d.plate + '</span><br>'
        + '<small>Confidence: ' + d.confidence + '%</small></div>';
    } else {
      pr.innerHTML = '<div class="plate-result plate-none">'
        + '<i class="fas fa-times-circle me-2"></i> TIDAK ADA PLAT<br>'
        + '<small>Coba foto lebih dekat / jelas / lurus</small></div>';
    }

    if (d.image) {
      ir.innerHTML = '<img src="captures/' + d.image + '" style="max-width:100%;max-height:300px;border-radius:8px;">';
    }

    if (d.ocr_log && d.ocr_log.length) {
      ol.style.display = 'block';
      ol.innerHTML = '<strong class="text-info">Teks terdeteksi:</strong><br>';
      d.ocr_log.forEach(function(l) {
        var isPlate = l.indexOf('plate=') > -1 && !l.includes('plate=None');
        ol.innerHTML += '<span class="' + (isPlate ? 'plate' : 'no-plate') + '">'
          + esc(typeof l === 'string' ? l : JSON.stringify(l)) + '</span><br>';
      });
    }

    ra.style.display = 'block';
    dz.innerHTML = '<i class="fas fa-camera" style="font-size:3rem;color:#ffab00"></i>'
      + '<p class="mt-2 mb-0"><strong>Tap untuk foto lagi</strong></p>';
    sb.style.width = '0%';
  };

  xhr.onerror = function() {
    st.style.display = 'none';
    dz.classList.remove('processing');
    dz.innerHTML = '<i class="fas fa-exclamation-triangle" style="font-size:3rem;color:#ff5252"></i><p class="mt-2 text-danger">Koneksi gagal</p>';
  };

  xhr.send();
};

function esc(s) { var d=document.createElement('div'); d.appendChild(document.createTextNode(s)); return d.innerHTML; }
</script>
</body>
</html>
