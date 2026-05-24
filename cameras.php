<?php
$title = 'Kelola Kamera';
include 'config/database.php';

if ($_POST && isset($_POST['simpan'])) {
    $id = (int)($_POST['id'] ?? 0);
    $nama = mysqli_real_escape_string($con, $_POST['nama']);
    $url = mysqli_real_escape_string($con, $_POST['url']);
    $lokasi = mysqli_real_escape_string($con, $_POST['lokasi']);
    $aktif = (int)($_POST['aktif'] ?? 1);
    if ($id) {
        mysqli_query($con, "UPDATE cameras SET nama='$nama', url='$url', lokasi='$lokasi', aktif=$aktif WHERE id=$id");
    } else {
        mysqli_query($con, "INSERT INTO cameras (nama, url, lokasi, aktif) VALUES ('$nama', '$url', '$lokasi', $aktif)");
    }
    $msg = '<div class="alert alert-success py-2">Kamera disimpan.</div>';
}

if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    mysqli_query($con, "DELETE FROM cameras WHERE id=$id");
    $msg = '<div class="alert alert-success py-2">Kamera dihapus.</div>';
}

$q = mysqli_query($con, "SELECT * FROM cameras ORDER BY id");
$edit = [];
if (isset($_GET['edit'])) {
    $r = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM cameras WHERE id=" . (int)$_GET['edit']));
    if ($r) $edit = $r;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kamera - Plat Reader</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { background:#0f1923; color:#fff; font-family:'Segoe UI',sans-serif; }
        .navbar { background:linear-gradient(90deg,#0d2137,#1a3a5c); }
        .card-cam { background:#1a2a3a; border:1px solid #2a3a4a; border-radius:12px; }
        .card-cam .form-label { color:#8899aa; font-size:0.8rem; }
        .form-control, .form-select { background:#0f1923; border-color:#2a3a4a; color:#fff; }
        .form-control:focus { background:#0f1923; border-color:#00e5ff; color:#fff; }
        .table { color:#ccc; font-size:0.85rem; }
        .table th { border-color:#2a3a4a; color:#00e5ff; }
        .table td { border-color:#1a2a3a; }
        .badge-stream { max-width:300px; overflow:hidden; text-overflow:ellipsis; display:inline-block; white-space:nowrap; }
    </style>
</head>
<body>
<nav class="navbar navbar-dark px-3 mb-4">
    <span class="navbar-brand mb-0"><i class="fas fa-video me-2"></i>Kelola Kamera</span>
    <a href="index.php" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left"></i> Dashboard</a>
</nav>
<div class="container-fluid">
    <?= $msg ?? '' ?>

    <div class="card card-cam p-3 mb-4">
        <h6 class="fw-bold mb-3"><?= $edit ? 'Edit Kamera' : 'Tambah Kamera' ?></h6>
        <form method="post">
            <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Nama Kamera</label>
                    <input type="text" name="nama" class="form-control" value="<?= $edit['nama'] ?? '' ?>" required>
                </div>
                <div class="col-md-5">
                    <label class="form-label">URL Stream</label>
                    <input type="text" name="url" class="form-control font-monospace" value="<?= $edit['url'] ?? '' ?>" placeholder="rtsp://user:pass@ip:554/stream atau http://ip:8080/video" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Lokasi</label>
                    <input type="text" name="lokasi" class="form-control" value="<?= $edit['lokasi'] ?? '' ?>">
                </div>
                <div class="col-md-1">
                    <label class="form-label">Aktif</label>
                    <select name="aktif" class="form-select">
                        <option value="1" <?= (isset($edit['aktif']) && $edit['aktif'])?'selected':'' ?>>Ya</option>
                        <option value="0" <?= (isset($edit['aktif']) && !$edit['aktif'])?'selected':'' ?>>Tidak</option>
                    </select>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button class="btn btn-sm btn-info w-100" name="simpan"><i class="fas fa-save"></i></button>
                </div>
            </div>
        </form>
    </div>

    <div class="card card-cam p-3">
        <h6 class="fw-bold mb-3">Daftar Kamera</h6>
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>#</th><th>Nama</th><th>URL Stream</th><th>Lokasi</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody><?php while ($r = mysqli_fetch_assoc($q)): ?>
                    <tr>
                        <td><?= $r['id'] ?></td>
                        <td><strong><?= $r['nama'] ?></strong></td>
                        <td><span class="badge-stream text-info" title="<?= $r['url'] ?>"><?= $r['url'] ?></span></td>
                        <td><?= $r['lokasi'] ?></td>
                        <td><span class="badge bg-<?= $r['aktif']?'success':'secondary' ?>"><?= $r['aktif']?'Aktif':'Nonaktif' ?></span></td>
                        <td>
                            <a href="cameras.php?edit=<?= $r['id'] ?>" class="btn btn-sm btn-outline-info"><i class="fas fa-edit"></i></a>
                            <a href="cameras.php?hapus=<?= $r['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus kamera?')"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                <?php endwhile; ?></tbody>
            </table>
        </div>
    </div>

    <div class="card card-cam p-3 mt-4">
        <h6 class="fw-bold mb-2">Panduan URL Stream</h6>
        <table class="table table-borderless mb-0" style="font-size:0.8rem;">
            <tr><td><strong>IP Camera (CCTV)</strong></td><td class="text-info">rtsp://username:password@192.168.1.100:554/stream1</td></tr>
            <tr><td><strong>HP Android (IP Webcam)</strong></td><td class="text-info">http://192.168.1.20:8080/video</td></tr>
            <tr><td><strong>HP Android (RTSP)</strong></td><td class="text-info">rtsp://192.168.1.20:554/live</td></tr>
        </table>
        <hr class="border-secondary">
        <p class="text-muted mb-0" style="font-size:0.8rem;">
            <i class="fas fa-info-circle"></i>
            Install app <strong>"IP Webcam"</strong> di Play Store, buka, scroll ke bawah pilih <strong>"Start server"</strong>.
            URL akan muncul di layar HP (contoh: <code>http://192.168.1.20:8080/video</code>).
            Pastikan HP dan komputer dalam 1 WiFi yang sama.
        </p>
    </div>
</div>
</body>
</html>
