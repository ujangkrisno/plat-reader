<?php
// Live detection page for DroidCam - auto OCR every 2 seconds
$cam_id = (int)($_GET['cam'] ?? 2);
include 'config/database.php';
$cam = mysqli_query($con, "SELECT * FROM cameras WHERE id=$cam_id");
$cam = mysqli_fetch_assoc($cam);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Live Detection - <?= $cam['nama'] ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#0f1923;color:#fff;font-family:'Segoe UI',sans-serif;margin:0;padding:10px}
.navbar{background:linear-gradient(90deg,#0d2137,#1a3a5c);border-radius:8px;padding:8px 15px;margin-bottom:10px}
#liveImg{width:100%;border-radius:8px;background:#000;min-height:200px}
#resultBox{display:none}
.plate-text{font-size:2.5rem;font-weight:bold;letter-spacing:4px;text-align:center;padding:20px;border-radius:12px;margin:10px 0;transition:all 0.3s}
.found{background:#004d26;border:2px solid #00e676;color:#00e676}
.none{background:#2a1520;border:2px solid #ff5252;color:#ff5252}
.scanning{background:#1a2a3a;border:2px solid #ffab00;color:#ffab00}
.log-box{background:#0a121c;color:#8899aa;padding:10px;border-radius:8px;font-family:monospace;font-size:12px;max-height:200px;overflow:auto;margin-top:8px}
.log-box .hit{color:#00e676}
#statusDot{display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:5px}
.dot-green{background:#00e676}
.dot-yellow{background:#ffab00}
.dot-red{background:#ff5252}
</style>
</head>
<body>

<nav class="navbar navbar-dark d-flex justify-content-between align-items-center">
  <span class="fw-bold"><span id="statusDot" class="dot-yellow"></span> <?= $cam['nama'] ?></span>
  <span id="fps" class="text-muted small">menunggu...</span>
  <a href="index.php" class="btn btn-outline-light btn-sm">Dashboard</a>
</nav>

<div class="row">
  <div class="col-md-8">
    <img id="liveImg" src="http://127.0.0.1:8093/snapshot/<?= $cam_id ?>?_=<?= time() ?>">
  </div>
  <div class="col-md-4">
    <div id="resultBox">
      <div id="plateDisplay" class="plate-text scanning">
        <i class="fas fa-spinner fa-spin me-2"></i>Memindai...
      </div>
    </div>
    <div id="logBox" class="log-box">
      <small class="text-muted">Menunggu deteksi pertama...</small>
    </div>
  </div>
</div>

<script>
var camId = <?= $cam_id ?>;
var live = document.getElementById('liveImg');
var pd = document.getElementById('plateDisplay');
var lb = document.getElementById('logBox');
var sd = document.getElementById('statusDot');
var fps = document.getElementById('fps');
var lastPlate = '';

function update() {
  var ts = Date.now();
  live.src = 'http://127.0.0.1:8093/snapshot/' + camId + '?_=' + ts;

  fetch('http://127.0.0.1:8093/ocr_now/' + camId, {mode: 'cors'})
    .then(function(r) { return r.json(); })
    .then(function(d) {
      sd.className = 'dot-green';
      fps.textContent = new Date().toLocaleTimeString();

      if (d.plate && d.plate !== lastPlate) {
        lastPlate = d.plate;
        pd.className = 'plate-text found';
        pd.innerHTML = '<i class="fas fa-check-circle me-2"></i>PLAT TERDETEKSI!<br>'
          + '<span style="font-size:3.5rem">' + d.plate + '</span><br>'
          + '<small>' + d.confidence + '%</small>';
      } else if (!d.plate) {
        pd.className = 'plate-text none';
        pd.innerHTML = '<i class="fas fa-times-circle me-2"></i>Tidak ada plat<br>'
          + '<small>Arahkan HP ke plat nomor</small>';
      }

      if (d.ocr_log && d.ocr_log.length) {
        lb.innerHTML = '';
        d.ocr_log.forEach(function(l) {
          var isHit = l.indexOf('plate=') > -1 && !l.includes('plate=None');
          var cls = isHit ? 'hit' : '';
          if (isHit) lb.innerHTML = '<div class="hit"><i class="fas fa-check me-1"></i>' + esc(l) + '</div>' + lb.innerHTML;
          else lb.innerHTML += '<div class="' + cls + '">' + esc(l) + '</div>';
        });
      }
    })
    .catch(function(e) {
      sd.className = 'dot-red';
      fps.textContent = 'error';
    });
}

var esc = function(s) { var d=document.createElement('div'); d.appendChild(document.createTextNode(s)); return d.innerHTML; };

// Update every 2 seconds
update();
setInterval(update, 2000);
</script>
</body>
</html>
