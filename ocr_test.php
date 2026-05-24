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
.card-ocr{background:#1a2a3a;border:1px solid #2a3a4a;border-radius:12px;padding:20px;margin-bottom:15px}
.pre-box{background:#0a121c;color:#00e5ff;padding:15px;border-radius:8px;font-family:monospace;max-height:350px;overflow:auto;font-size:13px}
.plate-result{font-size:1.8rem;font-weight:bold;text-align:center;padding:20px;border-radius:12px;margin:10px 0}
.plate-found{background:#004d26;border:2px solid #00e676;color:#00e676}
.plate-none{background:#2a1520;border:2px solid #ff5252;color:#ff5252}
.plate-loading{background:#1a2a3a;border:2px solid #ffab00;color:#ffab00}
.cam-img{width:100%;border-radius:8px;background:#000;min-height:120px}
.btn-ocr{width:100%;padding:8px}
.spinner{display:none}
.loading .spinner{display:inline-block}
.loading .btn-text{display:none}
</style>
</head>
<body>
<nav class="navbar navbar-dark px-3 mb-3">
<span class="navbar-brand mb-0"><i class="fas fa-microscope me-2"></i>OCR Test</span>
<a href="index.php" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left"></i> Dashboard</a>
</nav>

<div class="container-fluid">
  <div class="row">
    <?php while ($cam = mysqli_fetch_assoc($cams)): $cid=$cam['id']; ?>
    <div class="col-md-6 mb-3">
      <div class="card-ocr">
        <h6 class="fw-bold mb-2"><?= $cam['nama'] ?></h6>
        <img id="snap<?= $cid ?>" class="cam-img mb-2" src="http://127.0.0.1:8093/snapshot/<?= $cid ?>?_=<?= time() ?>" onerror="this.style.display='none'">

        <button class="btn btn-info btn-ocr" id="btn<?= $cid ?>" onclick="runOcr(<?= $cid ?>)">
          <span class="spinner"><span class="spinner-border spinner-border-sm me-1"></span></span>
          <span class="btn-text"><i class="fas fa-search"></i> OCR Test Frame Ini</span>
        </button>

        <div id="result<?= $cid ?>" style="display:none"></div>
        <div id="ocrtxt<?= $cid ?>" class="pre-box mt-2" style="display:none"></div>
      </div>
    </div>
    <?php endwhile; ?>
  </div>

  <div class="row">
    <div class="col-12">
      <div class="card-ocr">
        <h6 class="fw-bold mb-2">Log OCR Terakhir (cam1)</h6>
        <div class="pre-box" id="logbox">
<?php
$log = @file(__DIR__ . '/captures/ocr_1.log');
if ($log) echo htmlspecialchars(implode('', array_slice($log, -15)));
else echo 'Belum ada log OCR';
?>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function runOcr(cid) {
  var btn = document.getElementById('btn'+cid);
  var res = document.getElementById('result'+cid);
  var txt = document.getElementById('ocrtxt'+cid);
  btn.classList.add('loading');
  btn.disabled = true;
  res.style.display = 'none';
  txt.style.display = 'none';

  fetch('http://127.0.0.1:8093/ocr_now/'+cid)
    .then(function(r){ return r.json(); })
    .then(function(d){
      // Refresh snapshot
      document.getElementById('snap'+cid).src = 'http://127.0.0.1:8093/snapshot/'+cid+'?_='+Date.now();

      // Show plate result
      if (d.plate) {
        res.innerHTML = '<div class="plate-result plate-found">'
          + '<i class="fas fa-check-circle me-2"></i> PLAT TERDETEKSI!<br>'
          + '<span style="font-size:2.2rem">' + d.plate + '</span><br>'
          + '<small>Confidence: ' + d.confidence + '%</small></div>';
      } else {
        res.innerHTML = '<div class="plate-result plate-none">'
          + '<i class="fas fa-times-circle me-2"></i> Tidak ada plat terdeteksi<br>'
          + '<small>Confidence: 0%</small></div>';
      }

      // Show all OCR text blocks
      if (d.ocr_log && d.ocr_log.length) {
        txt.style.display = 'block';
        txt.innerHTML = '<strong class="text-info">OCR Detected Text:</strong><br>';
        d.ocr_log.forEach(function(l){
          var cls = l.indexOf('plate=') > -1 && !l.includes('plate=None') ? 'text-success' : 'text-muted';
          txt.innerHTML += '<span class="'+cls+'">' + esc(l) + '</span><br>';
        });
      }

      res.style.display = 'block';
      btn.classList.remove('loading');
      btn.disabled = false;
    })
    .catch(function(e){
      res.style.display = 'block';
      res.innerHTML = '<div class="plate-result plate-none"><i class="fas fa-exclamation-triangle me-2"></i> Error: ' + e.message + '</div>';
      btn.classList.remove('loading');
      btn.disabled = false;
    });
}

function esc(s) { var d=document.createElement('div'); d.appendChild(document.createTextNode(s)); return d.innerHTML; }
</script>
</body>
</html>
