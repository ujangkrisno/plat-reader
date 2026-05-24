<?php
$title = 'Live Camera';
include 'config/database.php';
$rows = mysqli_query($con, "SELECT * FROM cameras WHERE aktif=1 ORDER BY id");
$cameras = [];
while ($r = mysqli_fetch_assoc($rows)) $cameras[] = $r;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Camera - Plat Reader</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { background:#0f1923; color:#fff; font-family:'Segoe UI',sans-serif; }
        .navbar { background:linear-gradient(90deg,#0d2137,#1a3a5c); }
        .cam-card { background:#1a2a3a; border:1px solid #2a3a4a; border-radius:12px; overflow:hidden; }
        .cam-card .cam-header { padding:10px 15px; border-bottom:1px solid #2a3a4a; }
        .cam-card img { width:100%; display:block; background:#000; min-height:200px; object-fit:contain; }
        .offline { opacity:0.4; filter:grayscale(1); }
        .badge-dot { width:8px; height:8px; border-radius:50%; display:inline-block; margin-right:6px; }
        .badge-dot.online { background:#00e676; }
        .badge-dot.offline { background:#ff5252; }
        .cam-error { padding:40px; text-align:center; color:#667; }
    </style>
</head>
<body>
<nav class="navbar navbar-dark px-3 mb-4">
    <span class="navbar-brand mb-0"><i class="fas fa-camera me-2"></i>Live Camera</span>
    <a href="index.php" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left"></i> Dashboard</a>
</nav>

<div class="container-fluid">
    <div class="alert alert-info py-2 d-flex align-items-center">
        <i class="fas fa-info-circle me-2"></i>
        Stream tersedia jika <strong>start_streamer.bat</strong> sedang berjalan di port 8093.
    </div>

    <div class="row g-3" id="camGrid">
        <?php foreach ($cameras as $cam): ?>
        <div class="col-md-6 col-lg-4">
            <div class="cam-card" id="card_<?= $cam['id'] ?>">
                <div class="cam-header d-flex justify-content-between align-items-center">
                    <div>
                        <strong><?= $cam['nama'] ?></strong>
                        <small class="text-muted d-block"><?= $cam['lokasi'] ?: '-' ?></small>
                    </div>
                    <span class="badge bg-secondary" id="status_<?= $cam['id'] ?>"><span class="badge-dot offline"></span>Loading...</span>
                </div>
                <img id="stream_<?= $cam['id'] ?>" src="" data-cam="<?= $cam['id'] ?>" style="cursor:pointer" onclick="this.src=''">
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
var cams = <?= json_encode(array_map(function($c){return $c['id'];}, $cameras)) ?>;

function loadSnapshot(camId) {
    var img = document.getElementById('stream_' + camId);
    if (!img) return;
    var ts = new Date().getTime();
    img.src = 'http://127.0.0.1:8093/snapshot/' + camId + '?_=' + ts;
}

function onImgLoad(camId) {
    var card = document.getElementById('card_' + camId);
    var status = document.getElementById('status_' + camId);
    if (card) card.classList.remove('offline');
    if (status) {
        status.className = 'badge bg-success';
        status.innerHTML = '<span class="badge-dot online"></span>Live';
    }
}

function onImgError(camId) {
    var card = document.getElementById('card_' + camId);
    var status = document.getElementById('status_' + camId);
    if (card) card.classList.add('offline');
    if (status) {
        status.className = 'badge bg-danger';
        status.innerHTML = '<span class="badge-dot offline"></span>Offline';
    }
}

cams.forEach(function(camId) {
    var img = document.getElementById('stream_' + camId);
    if (img) {
        img.onload = function() { onImgLoad(camId); };
        img.onerror = function() { onImgError(camId); };
        loadSnapshot(camId);
    }
});

setInterval(function() {
    cams.forEach(function(camId) {
        var status = document.getElementById('status_' + camId);
        if (status && status.className.indexOf('bg-success') !== -1) {
            loadSnapshot(camId);
        } else {
            var ts = new Date().getTime();
            if (ts % 10000 < 2000) loadSnapshot(camId);
        }
    });
}, 2000);
</script>
</body>
</html>
