<?php
$cam_id = (int)($_GET['cam'] ?? 2);
include 'config/database.php';
$cams = mysqli_query($con, "SELECT * FROM cameras WHERE aktif=1 ORDER BY id");
$cam = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM cameras WHERE id=$cam_id AND aktif=1"));
if (!$cam) $cam = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM cameras WHERE aktif=1 ORDER BY id LIMIT 1"));
if (!$cam) { die('<h2>Tidak ada kamera aktif</h2>'); }
$cam_id = $cam['id'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Live Detection - <?= $cam['nama'] ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
body{background:#0f1923;color:#fff;margin:0;padding:10px;font-family:'Segoe UI',sans-serif}
.navbar{background:linear-gradient(90deg,#0d2137,#1a3a5c);border-radius:8px;padding:8px 15px;margin-bottom:10px}
#liveImg{width:100%;border-radius:8px;background:#000;min-height:200px;object-fit:contain}
#resultBox{display:none}
.plate-text{font-size:2rem;font-weight:bold;letter-spacing:4px;text-align:center;padding:20px;border-radius:12px;margin:10px 0;transition:all 0.3s}
.found{background:#004d26;border:2px solid #00e676;color:#00e676}
.none{background:#2a1520;border:2px solid #ff5252;color:#ff5252}
.scanning{background:#1a2a3a;border:2px solid #ffab00;color:#ffab00}
.offline-box{background:#2a1520;border:2px solid #ff5252;color:#ff5252;padding:30px;border-radius:12px;text-align:center;margin:10px 0}
.offline-box i{font-size:3rem;margin-bottom:10px}
.log-box{background:#0a121c;color:#8899aa;padding:10px;border-radius:8px;font-family:monospace;font-size:12px;max-height:200px;overflow:auto;margin-top:8px}
.log-box .hit{color:#00e676}
#statusDot{display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:5px;flex-shrink:0}
.dot-green{background:#00e676}
.dot-yellow{background:#ffab00}
.dot-red{background:#ff5252}
.cam-select{background:#1a2a3a;color:#fff;border:1px solid #2a3a4a;border-radius:6px;padding:4px 10px;margin-left:8px}
.res-badge{background:#0a121c;color:#8899aa;padding:2px 8px;border-radius:4px;font-size:11px;margin-left:8px}
</style>
</head>
<body>

<nav class="navbar navbar-dark d-flex align-items-center gap-2">
  <span id="statusDot" class="dot-yellow"></span>
  <span class="fw-bold"><?= $cam['nama'] ?></span>
  <span class="res-badge" id="resLabel">...</span>
  <select class="cam-select" id="camSelect" onchange="switchCam(this.value)">
    <?php mysqli_data_seek($cams, 0); while($c = mysqli_fetch_assoc($cams)): ?>
    <option value="<?= $c['id'] ?>" <?= $c['id']==$cam_id?'selected':'' ?>><?= $c['nama'] ?></option>
    <?php endwhile; ?>
  </select>
  <span class="text-muted small ms-auto" id="fps">...</span>
  <a href="index.php" class="btn btn-outline-light btn-sm"><i class="fas fa-table"></i></a>
</nav>

<div class="row g-2">
  <div class="col-md-8">
    <img id="liveImg" src="about:blank">
    <div id="offlineMsg" class="offline-box mt-2" style="display:none">
      <i class="fas fa-video-slash"></i>
      <div class="fw-bold mt-2">Kamera Offline</div>
      <small>Periksa koneksi kamera atau reload streamer</small>
    </div>
  </div>
  <div class="col-md-4">
    <div id="resultBox" style="display:none">
      <div id="plateDisplay" class="plate-text scanning">
        <i class="fas fa-spinner fa-spin me-2"></i>Memindai...
      </div>
    </div>
    <div id="logBox" class="log-box">
      <small class="text-muted">Menunggu deteksi...</small>
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
var resLabel = document.getElementById('resLabel');
var offlineMsg = document.getElementById('offlineMsg');
var lastPlate = '';

function switchCam(id) {
  camId = parseInt(id);
  lastPlate = '';
  pd.className = 'plate-text scanning';
  pd.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Memindai...';
  lb.innerHTML = '<small class="text-muted">Beralih ke kamera...</small>';
  offlineMsg.style.display = 'none';
  sd.className = 'dot-yellow';
  update();
}

function update() {
  var ts = Date.now();
  live.src = 'http://127.0.0.1:8093/snapshot/' + camId + '?_=' + ts;

  fetch('http://127.0.0.1:8093/ocr_now/' + camId, {mode: 'cors'})
    .then(function(r) {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    })
    .then(function(d) {
      sd.className = 'dot-green';
      fps.textContent = new Date().toLocaleTimeString();
      offlineMsg.style.display = 'none';
      document.getElementById('resultBox').style.display = 'block';

      if (d.plate && d.plate !== lastPlate) {
        lastPlate = d.plate;
        pd.className = 'plate-text found';
        pd.innerHTML = '<i class="fas fa-check-circle me-2"></i>PLAT TERDETEKSI!<br>'
          + '<span style="font-size:3rem;letter-spacing:6px">' + d.plate + '</span><br>'
          + '<small><i class="fas fa-star me-1"></i>' + d.confidence + '%</small>';
      } else if (!d.plate) {
        pd.className = 'plate-text none';
        pd.innerHTML = '<i class="fas fa-times-circle me-2"></i>Tidak ada plat<br>'
          + '<small>Arahkan kamera ke plat nomor</small>';
      }

      if (d.ocr_log && d.ocr_log.length) {
        var html = '';
        d.ocr_log.slice(-12).forEach(function(l) {
          var isHit = l.indexOf('plate=') > -1 && !l.includes('plate=None');
          var cls = isHit ? 'hit' : '';
          html = '<div class="' + cls + '">' + esc(l) + '</div>' + html;
        });
        lb.innerHTML = html || '<small class="text-muted">Tidak ada teks terdeteksi</small>';
      }
    })
    .catch(function(e) {
      sd.className = 'dot-red';
      fps.textContent = 'offline';
      offlineMsg.style.display = 'block';
      document.getElementById('resultBox').style.display = 'block';
      pd.className = 'plate-text none';
      pd.innerHTML = '<i class="fas fa-video-slash me-2"></i>Kamera tidak merespon<br>'
        + '<small>cek koneksi atau reload streamer</small>';
      lb.innerHTML = '<small class="text-muted">Error: ' + esc(e.message) + '</small>';
    });
}

live.onerror = function() {
  sd.className = 'dot-red';
  offlineMsg.style.display = 'block';
};

var esc = function(s) {
  var d = document.createElement('div');
  d.appendChild(document.createTextNode(s));
  return d.innerHTML;
};

update();
setInterval(update, 2000);
</script>
</body>
</html>
