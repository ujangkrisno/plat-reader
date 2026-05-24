<?php
$title = 'Laporan Plat';
include 'config/database.php';

$periode = $_GET['periode'] ?? 'harian';
$tgl_awal = $tgl_akhir = date('Y-m-d');
$label = date('d M Y');

if ($periode == 'mingguan') {
    $tgl = $_GET['tgl'] ?? date('Y-m-d');
    $day = date('N', strtotime($tgl));
    $tgl_awal = date('Y-m-d', strtotime("-$day day", strtotime($tgl)));
    $tgl_akhir = date('Y-m-d', strtotime('+'.(6-$day).' day', strtotime($tgl)));
    $label = $tgl_awal . ' s/d ' . $tgl_akhir;
} elseif ($periode == 'bulanan') {
    $bln = $_GET['bulan'] ?? date('Y-m');
    $tgl_awal = $bln . '-01';
    $tgl_akhir = date('Y-m-t', strtotime($tgl_awal));
    $label = date('M Y', strtotime($tgl_awal));
} elseif ($periode == 'tahunan') {
    $thn = $_GET['tahun'] ?? date('Y');
    $tgl_awal = "$thn-01-01";
    $tgl_akhir = "$thn-12-31";
    $label = $thn;
}

// Top plat
$q = mysqli_query($con, "SELECT plat, COUNT(*) as total, MAX(confidence) as max_conf FROM plat_nomor WHERE DATE(created_at) BETWEEN '$tgl_awal' AND '$tgl_akhir' GROUP BY plat ORDER BY total DESC LIMIT 20");

// Stat per kamera
$q_cam = mysqli_query($con, "SELECT c.nama, COUNT(p.id) as total FROM cameras c LEFT JOIN plat_nomor p ON c.id=p.camera_id AND DATE(p.created_at) BETWEEN '$tgl_awal' AND '$tgl_akhir' GROUP BY c.id");

// Stat per jam
$q_jam = mysqli_query($con, "SELECT HOUR(created_at) as jam, COUNT(*) as total FROM plat_nomor WHERE DATE(created_at) BETWEEN '$tgl_awal' AND '$tgl_akhir' GROUP BY HOUR(created_at) ORDER BY jam");
$chart_labels = []; $chart_data = [];
for ($i=0; $i<24; $i++) { $chart_labels[] = "$i:00"; $chart_data[$i] = 0; }
while ($r = mysqli_fetch_assoc($q_jam)) { $chart_data[(int)$r['jam']] = (int)$r['total']; }

$total_plat = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as c, COUNT(DISTINCT plat) as u FROM plat_nomor WHERE DATE(created_at) BETWEEN '$tgl_awal' AND '$tgl_akhir'"));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - Plat Reader</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { background:#0f1923; color:#fff; font-family:'Segoe UI',sans-serif; }
        .navbar { background:linear-gradient(90deg,#0d2137,#1a3a5c); }
        .card-lap { background:#1a2a3a; border:1px solid #2a3a4a; border-radius:12px; padding:20px; }
        .stat-box { background:linear-gradient(135deg,#0d2137,#1a3a5c); border-radius:12px; padding:20px; text-align:center; }
        .stat-box .angka { font-size:2rem; font-weight:700; }
        .table { color:#ccc; font-size:0.85rem; }
        .table th { border-color:#2a3a4a; color:#00e5ff; }
        .table td { border-color:#1a2a3a; }
        .form-control, .form-select { background:#0f1923; border-color:#2a3a4a; color:#fff; }
    </style>
</head>
<body>
<nav class="navbar navbar-dark px-3 mb-4">
    <span class="navbar-brand mb-0"><i class="fas fa-chart-bar me-2"></i>Laporan</span>
    <a href="index.php" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left"></i> Dashboard</a>
</nav>
<div class="container-fluid">
    <div class="card-lap mb-4">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-auto">
                <select name="periode" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="harian" <?= $periode=='harian'?'selected':'' ?>>Harian</option>
                    <option value="mingguan" <?= $periode=='mingguan'?'selected':'' ?>>Mingguan</option>
                    <option value="bulanan" <?= $periode=='bulanan'?'selected':'' ?>>Bulanan</option>
                    <option value="tahunan" <?= $periode=='tahunan'?'selected':'' ?>>Tahunan</option>
                </select>
            </div>
            <?php if ($periode=='harian'): ?>
            <div class="col-auto"><input type="date" name="tgl" class="form-control form-control-sm" value="<?= $_GET['tgl']??date('Y-m-d') ?>" onchange="this.form.submit()"></div>
            <?php elseif ($periode=='bulanan'): ?>
            <div class="col-auto"><input type="month" name="bulan" class="form-control form-control-sm" value="<?= $_GET['bulan']??date('Y-m') ?>" onchange="this.form.submit()"></div>
            <?php elseif ($periode=='tahunan'): ?>
            <div class="col-auto"><input type="number" name="tahun" class="form-control form-control-sm" value="<?= $_GET['tahun']??date('Y') ?>" onchange="this.form.submit()"></div>
            <?php endif; ?>
        </form>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="stat-box"><div class="text-info small">Periode</div><div style="font-size:1.1rem;"><?= $label ?></div></div></div>
        <div class="col-md-4"><div class="stat-box"><div class="text-success small">Total Tangkapan</div><div class="angka text-success"><?= $total_plat['c'] ?></div></div></div>
        <div class="col-md-4"><div class="stat-box"><div class="text-warning small">Plat Unik</div><div class="angka text-warning"><?= $total_plat['u'] ?></div></div></div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-8">
            <div class="card-lap">
                <h6 class="fw-bold mb-3">Distribusi per Jam</h6>
                <canvas id="chartJam" height="120"></canvas>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-lap">
                <h6 class="fw-bold mb-3">Per Kamera</h6>
                <table class="table table-borderless mb-0">
                    <?php while ($r = mysqli_fetch_assoc($q_cam)): ?>
                    <tr><td><?= $r['nama'] ?></td><td class="text-end"><span class="badge bg-info"><?= $r['total'] ?></span></td></tr>
                    <?php endwhile; ?>
                </table>
            </div>
        </div>
    </div>

    <div class="card-lap">
        <h6 class="fw-bold mb-3">Plat Paling Sering Terdeteksi</h6>
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>#</th><th>Plat Nomor</th><th>Total</th><th>Confidence Max</th></tr></thead>
                <tbody><?php $no=1; while ($r = mysqli_fetch_assoc($q)): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><strong class="text-info" style="letter-spacing:2px;"><?= $r['plat'] ?></strong></td>
                        <td><span class="badge bg-success"><?= $r['total'] ?>x</span></td>
                        <td><?= number_format($r['max_conf'], 1) ?>%</td>
                    </tr>
                <?php endwhile; ?></tbody>
            </table>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
new Chart(document.getElementById('chartJam'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($chart_labels) ?>,
        datasets: [{ label:'Tangkapan', data:<?= json_encode(array_values($chart_data)) ?>, backgroundColor:'#00e5ff', borderRadius:4 }]
    },
    options: {
        responsive:true, plugins:{legend:{display:false}},
        scales: { y:{ beginAtZero:true, ticks:{color:'#8899aa'}, grid:{color:'#2a3a4a'} }, x:{ ticks:{color:'#8899aa', font:{size:10}} } }
    }
});
</script>
</body>
</html>
