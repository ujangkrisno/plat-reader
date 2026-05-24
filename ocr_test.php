<?php
include 'config/database.php';
$cams = mysqli_query($con, "SELECT * FROM cameras WHERE aktif=1 ORDER BY id");
?>
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
.step-box{background:#0a121c;border:1px solid #2a3a4a;border-radius:8px;padding:15px;margin:8px 0}
.step-num{display:inline-block;width:28px;height:28px;line-height:28px;text-align:center;border-radius:50%;background:#ffab00;color:#000;font-weight:bold;font-size:14px;margin-right:8px}
.pre-box{background:#0a121c;color:#00e5ff;padding:15px;border-radius:8px;font-family:monospace;max-height:300px;overflow:auto;font-size:13px;margin-top:10px}
.plate-result{font-size:1.6rem;font-weight:bold;text-align:center;padding:15px;border-radius:12px;margin:10px 0}
.plate-found{background:#004d26;border:2px solid #00e676;color:#00e676}
.plate-none{background:#2a1520;border:2px solid #ff5252;color:#ff5252}
.cam-img{width:100%;border-radius:8px;background:#000;min-height:120px;max-height:300px;object-fit:contain}
.btn-step{width:100%;padding:10px;font-weight:bold}
.status-bar{height:4px;border-radius:2px;margin:5px 0;transition:all 0.3s}
</style>
</head>
<body>
<nav class="navbar navbar-dark px-3 mb-3">
<span class="navbar-brand mb-0"><i class="fas fa-microscope me-2"></i>OCR Test - Step by Step</span>
<a href="index.php" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left"></i> Dashboard</a>
</nav>

<div class="container-fluid">
  <div class="row">
    <?php while ($cam = mysqli_fetch_assoc($cams)): $cid=$cam['id']; ?>
    <div class="col-md-6 mb-3">
      <div class="card">
        <h5 class="fw-bold mb-3"><i class="fas fa-video me-2"></i><?= $cam['nama'] ?></h5>

        <!-- Live Preview -->
        <div class="step-box">
          <span class="step-num">1</span><strong>Live View</strong>
          <small class="text-muted float-end">Auto-refresh</small>
          <img id="live<?= $cid ?>" class="cam-img mt-2" src="http://127.0.0.1:8093/snapshot/<?= $cid ?>?_=<?= time() ?>">
        </div>

        <!-- Step 1: Capture -->
        <div class="step-box">
          <span class="step-num">2</span><strong>Tangkap Gambar</strong>
          <small class="text-muted float-end">Freeze frame saat ini</small>
          <button class="btn btn-warning btn-step mt-2" id="btnCap<?= $cid ?>" onclick="capture(<?= $cid ?>)">
            <i class="fas fa-camera"></i> TANGKAP GAMBAR
          </button>
          <div id="capStatus<?= $cid ?>" class="text-info small mt-1" style="display:none"></div>
          <img id="captured<?= $cid ?>" class="cam-img mt-2" style="display:none">
        </div>

        <!-- Step 2: Process -->
        <div class="step-box">
          <span class="step-num">3</span><strong>Proses Deteksi Plat</strong>
          <small class="text-muted float-end">OCR pada gambar yang ditangkap</small>
          <button class="btn btn-info btn-step mt-2" id="btnOcr<?= $cid ?>" onclick="detect(<?= $cid ?>)" disabled>
            <i class="fas fa-microchip"></i> PROSES DETEKSI PLAT
          </button>
          <div class="status-bar" id="bar<?= $cid ?>" style="width:0%"></div>
          <div id="result<?= $cid ?>" style="display:none"></div>
          <div id="ocrtxt<?= $cid ?>" class="pre-box" style="display:none"></div>
        </div>

      </div>
    </div>
    <?php endwhile; ?>
  </div>

  <!-- Log -->
  <div class="row">
    <div class="col-12">
      <div class="card">
        <h6 class="fw-bold mb-2">Log OCR Terakhir (cam1)</h6>
        <div class="pre-box" id="logbox">
<?php
$log = @file(__DIR__ . '/captures/ocr_1.log');
if ($log) echo htmlspecialchars(implode('', array_slice($log, -15)));
else echo 'Belum ada log';
?>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function capture(cid) {
  var btn = document.getElementById('btnCap'+cid);
  var stat = document.getElementById('capStatus'+cid);
  var img = document.getElementById('captured'+cid);
  var ocrBtn = document.getElementById('btnOcr'+cid);
  var res = document.getElementById('result'+cid);
  var txt = document.getElementById('ocrtxt'+cid);

  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengambil gambar...';
  stat.style.display = 'block';
  stat.textContent = 'Mengambil snapshot...';
  res.style.display = 'none';
  txt.style.display = 'none';

  // Save the captured frame via PHP proxy to avoid CORS
  var ts = Date.now();
  var snapUrl = 'http://127.0.0.1:8093/snapshot/' + cid + '?_=' + ts;
  img.src = snapUrl;
  img.onload = function() {
    img.style.display = 'block';
    // Also save to server via PHP
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'capture_ajax.php?cam=' + cid + '&_=' + ts, true);
    xhr.onload = function() {
      if (xhr.status === 200) {
        stat.textContent = 'Gambar tertangkap! Klik "PROSES DETEKSI PLAT" untuk memproses.';
        stat.className = 'text-success small mt-1';
        ocrBtn.disabled = false;
      } else {
        stat.textContent = 'Gagal menyimpan gambar';
        stat.className = 'text-danger small mt-1';
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-camera"></i> TANGKAP GAMBAR';
      }
    };
    xhr.onerror = function() {
      // Still show the image even if save fails
      stat.textContent = 'Gambar tampil (save skipped)';
      stat.className = 'text-warning small mt-1';
      ocrBtn.disabled = false;
    };
    xhr.send();
  };
  img.onerror = function() {
    stat.textContent = 'Gagal mengambil snapshot!';
    stat.className = 'text-danger small mt-1';
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-camera"></i> TANGKAP GAMBAR';
  };
}

function detect(cid) {
  var btn = document.getElementById('btnOcr'+cid);
  var bar = document.getElementById('bar'+cid);
  var res = document.getElementById('result'+cid);
  var txt = document.getElementById('ocrtxt'+cid);

  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses OCR...';
  bar.style.width = '30%';
  bar.style.background = '#ffab00';
  res.style.display = 'none';
  txt.style.display = 'none';

  // Animate progress
  var prog = 30;
  var iv = setInterval(function() {
    prog += 5;
    if (prog > 80) clearInterval(iv);
    bar.style.width = prog + '%';
  }, 500);

  fetch('http://127.0.0.1:8093/ocr_path/' + cid)
    .then(function(r){ return r.json(); })
    .then(function(d){
      clearInterval(iv);
      bar.style.width = '100%';
      bar.style.background = '#00e676';

      if (d.plate) {
        res.innerHTML = '<div class="plate-result plate-found">'
          + '<i class="fas fa-check-circle me-2"></i> PLAT TERDETEKSI!<br>'
          + '<span style="font-size:2.4rem">' + d.plate + '</span><br>'
          + '<small>Confidence: ' + d.confidence + '%</small></div>';
      } else {
        res.innerHTML = '<div class="plate-result plate-none">'
          + '<i class="fas fa-times-circle me-2"></i> TIDAK ADA PLAT<br>'
          + '<small>Coba arahkan plat lebih dekat / cahaya lebih terang</small></div>';
      }

      if (d.ocr_log && d.ocr_log.length) {
        txt.style.display = 'block';
        txt.innerHTML = '<strong class="text-info">Semua teks terdeteksi di gambar ini:</strong><br>';
        d.ocr_log.forEach(function(l){
          var cls = l.indexOf('plate=') > -1 && !l.includes('plate=None') ? 'text-success' : 'text-muted';
          txt.innerHTML += '<span class="'+cls+'">' + esc(l) + '</span><br>';
        });
      }

      res.style.display = 'block';
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-microchip"></i> PROSES DETEKSI PLAT';
    })
    .catch(function(e){
      clearInterval(iv);
      bar.style.width = '100%';
      bar.style.background = '#ff5252';
      res.style.display = 'block';
      res.innerHTML = '<div class="plate-result plate-none"><i class="fas fa-exclamation-triangle me-2"></i> Error: ' + e.message + '</div>';
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-microchip"></i> PROSES DETEKSI PLAT';
    });
}

function esc(s) { var d=document.createElement('div'); d.appendChild(document.createTextNode(s)); return d.innerHTML; }

// Auto-refresh live view
setInterval(function() {
  <?php mysqli_data_seek($cams, 0); while ($cam = mysqli_fetch_assoc($cams)): ?>
  document.getElementById('live<?= $cam['id'] ?>').src = 'http://127.0.0.1:8093/snapshot/<?= $cam['id'] ?>?_=' + Date.now();
  <?php endwhile; ?>
}, 2000);
</script>
</body>
</html>
