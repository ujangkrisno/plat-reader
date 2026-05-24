<?php
$title = 'Plat Reader - Dashboard';
include 'config/database.php';

// Filter
$filter_plat = $_GET['plat'] ?? '';
$filter_tgl = $_GET['tgl'] ?? date('Y-m-d');
$where = "WHERE DATE(p.created_at)='$filter_tgl'";
if ($filter_plat) $where .= " AND p.plat LIKE '%" . mysqli_real_escape_string($con, $filter_plat) . "%'";

// Stats hari ini
$q = mysqli_query($con, "SELECT COUNT(*) as total, COUNT(DISTINCT plat) as unik FROM plat_nomor WHERE DATE(created_at)='$filter_tgl'");
$stat = mysqli_fetch_assoc($q);

// Data
$q = mysqli_query($con, "SELECT p.*, c.nama as camera, c.lokasi FROM plat_nomor p LEFT JOIN cameras c ON p.camera_id=c.id $where ORDER BY p.created_at DESC LIMIT 100");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plat Reader</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { background:#0f1923; color:#fff; font-family:'Segoe UI',sans-serif; }
        .navbar { background:linear-gradient(90deg,#0d2137,#1a3a5c); }
        .card-plat { background:#1a2a3a; border:1px solid #2a3a4a; border-radius:12px; }
        .card-plat .plat { font-size:1.3rem; font-weight:700; letter-spacing:3px; color:#00e5ff; }
        .card-plat .meta { font-size:0.75rem; color:#8899aa; }
        .card-plat:hover { border-color:#00e5ff; }
        .stat-card { background:linear-gradient(135deg,#0d2137,#1a3a5c); border-radius:12px; padding:20px; }
        .stat-card .angka { font-size:2rem; font-weight:700; }
        .table { color:#ccc; font-size:0.85rem; }
        .table th { border-color:#2a3a4a; color:#00e5ff; }
        .table td { border-color:#1a2a3a; vertical-align:middle; }
        .img-plat { width:120px; height:60px; object-fit:cover; border-radius:6px; }
        .badge-cam { background:#1a3a5c; color:#00e5ff; }
        .img-plat { width:120px; height:60px; object-fit:cover; border-radius:6px; }
        .auto-refresh { position:fixed; bottom:20px; right:20px; z-index:999; background:#1a3a5c; padding:8px 16px; border-radius:8px; font-size:0.75rem; }
    </style>
</head>
<body>
<nav class="navbar navbar-dark px-3 mb-4">
    <span class="navbar-brand mb-0"><i class="fas fa-camera me-2"></i>Plat Reader</span>
    <div>
        <a href="cameras.php" class="btn btn-outline-light btn-sm me-2"><i class="fas fa-video"></i> Kamera</a>
        <a href="live.php" class="btn btn-outline-light btn-sm me-2"><i class="fas fa-eye"></i> Live</a>
        <a href="ocr_test.php" class="btn btn-outline-light btn-sm me-2"><i class="fas fa-microscope"></i> OCR Test</a>
        <a href="laporan.php" class="btn btn-outline-light btn-sm"><i class="fas fa-chart-bar"></i> Laporan</a>
    </div>
</nav>

<div class="container-fluid">
    <!-- Filter -->
    <div class="row mb-3">
        <div class="col">
            <form class="row g-2 align-items-end">
                <div class="col-auto">
                    <input type="date" name="tgl" class="form-control form-control-sm bg-dark text-light border-secondary" value="<?= $filter_tgl ?>">
                </div>
                <div class="col-auto">
                    <input type="text" name="plat" class="form-control form-control-sm bg-dark text-light border-secondary" placeholder="Cari plat..." value="<?= $filter_plat ?>">
                </div>
                <div class="col-auto">
                    <button class="btn btn-sm btn-outline-info"><i class="fas fa-search"></i></button>
                </div>
                <?php if ($filter_plat || $filter_tgl != date('Y-m-d')): ?>
                <div class="col-auto">
                    <a href="?" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="stat-card text-center"><div class="text-info small">Total Tangkapan</div><div class="angka text-info"><?= $stat['total'] ?></div></div></div>
        <div class="col-md-3"><div class="stat-card text-center"><div class="text-success small">Plat Unik</div><div class="angka text-success"><?= $stat['unik'] ?></div></div></div>
        <div class="col-md-3"><div class="stat-card text-center"><div class="text-warning small">Kamera Aktif</div><div class="angka text-warning"><?= mysqli_fetch_assoc(mysqli_query($con,"SELECT COUNT(*) as c FROM cameras WHERE aktif=1"))['c'] ?></div></div></div>
        <div class="col-md-3"><div class="stat-card text-center"><div class="text-primary small">Hari Ini</div><div class="angka text-primary" id="todayCount"><?= $stat['total'] ?></div></div></div>
    </div>

    <!-- Daftar Kamera -->
    <div class="d-flex justify-content-between align-items-center mb-2 mt-4">
        <h6 class="fw-bold"><i class="fas fa-video me-2"></i>Daftar Kamera</h6>
        <a href="cameras.php" class="btn btn-sm btn-outline-info"><i class="fas fa-plus"></i> Kelola</a>
    </div>
    <div class="row g-3 mb-4">
        <?php
        $kc = mysqli_query($con, "SELECT c.*, (SELECT COUNT(*) FROM plat_nomor WHERE camera_id=c.id AND DATE(created_at)='$filter_tgl') as hari_ini FROM cameras c ORDER BY c.aktif DESC, c.nama ASC");
        while ($cam = mysqli_fetch_assoc($kc)):
        ?>
        <div class="col-md-4 col-lg-3">
            <div class="card-plat p-3 h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <strong class="text-light"><?= $cam['nama'] ?></strong>
                        <div class="meta mt-1"><?= $cam['lokasi'] ?: '-' ?></div>
                    </div>
                    <span class="badge bg-<?= $cam['aktif'] ? 'success' : 'secondary' ?>"><?= $cam['aktif'] ? 'Aktif' : 'Nonaktif' ?></span>
                </div>
                <hr class="border-secondary my-2">
                <div class="d-flex justify-content-between">
                    <small class="text-muted">Hari ini</small>
                    <small class="text-info fw-bold"><?= $cam['hari_ini'] ?> plat</small>
                </div>
                <div class="mt-1">
                    <small class="text-muted" style="word-break:break-all;font-size:0.7rem;"><?= $cam['url'] ?></small>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>

    <!-- Recent Plates -->
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="fw-bold">Riwayat Plat</h6>
        <small class="text-muted">Auto-refresh tiap 10 detik</small>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Foto</th><th>Plat Nomor</th><th>Kamera</th><th>Lokasi</th><th>Confidence</th><th>Waktu</th></tr></thead>
            <tbody id="platTable">
                <?php while ($r = mysqli_fetch_assoc($q)): ?>
                <tr>
                    <td><?php if ($r['gambar']): ?><a href="<?= $r['gambar'] ?>" target="_blank"><img src="<?= $r['gambar'] ?>" class="img-plat"></a><?php else: ?><span class="text-muted">-</span><?php endif; ?></td>
                    <td><strong class="text-info" style="letter-spacing:2px;"><?= $r['plat'] ?></strong></td>
                    <td><span class="badge badge-cam"><?= $r['camera'] ?? '-' ?></span></td>
                    <td><?= $r['lokasi'] ?? '-' ?></td>
                    <td><?= number_format($r['confidence'], 1) ?>%</td>
                    <td><small><?= date('H:i:s', strtotime($r['created_at'])) ?></small></td>
                </tr>
                <?php endwhile; ?>
                <?php if (mysqli_num_rows($q) == 0): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">Belum ada data plat terdeteksi</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="auto-refresh">
    <i class="fas fa-sync-alt me-1"></i> Auto-refresh <span id="countdown">10</span>s
</div>

<script>
// Auto refresh table
var countdown = 10;
function refreshData() {
    var url = window.location.href;
    fetch('api.php?action=recent&tgl=<?= $filter_tgl ?>' + (window.location.href.match(/plat=([^&]*)/) ? '&plat=' + window.location.href.match(/plat=([^&]*)/)[1] : ''))
        .then(function(r) { return r.text(); })
        .then(function(html) {
            var table = document.getElementById('platTable');
            if (table) table.innerHTML = html;
        });
}
setInterval(function() {
    countdown--;
    var el = document.getElementById('countdown');
    if (el) el.textContent = countdown;
    if (countdown <= 0) { countdown = 10; refreshData(); }
}, 1000);
</script>
</body>
</html>
