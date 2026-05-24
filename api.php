<?php
include 'config/database.php';
$action = $_GET['action'] ?? '';

if ($action === 'recent') {
    $filter_tgl = $_GET['tgl'] ?? date('Y-m-d');
    $filter_plat = $_GET['plat'] ?? '';
    $where = "WHERE DATE(p.created_at)='$filter_tgl'";
    if ($filter_plat) $where .= " AND p.plat LIKE '%" . mysqli_real_escape_string($con, $filter_plat) . "%'";
    $q = mysqli_query($con, "SELECT p.*, c.nama as camera, c.lokasi FROM plat_nomor p LEFT JOIN cameras c ON p.camera_id=c.id $where ORDER BY p.created_at DESC LIMIT 100");
    while ($r = mysqli_fetch_assoc($q)) {
        echo '<tr>';
        echo '<td>' . ($r['gambar'] ? '<a href="'.$r['gambar'].'" target="_blank"><img src="'.$r['gambar'].'" class="img-plat"></a>' : '<span class="text-muted">-</span>') . '</td>';
        echo '<td><strong class="text-info" style="letter-spacing:2px;">'.$r['plat'].'</strong></td>';
        echo '<td><span class="badge badge-cam">'.($r['camera']??'-').'</span></td>';
        echo '<td>'.($r['lokasi']??'-').'</td>';
        echo '<td>'.number_format($r['confidence'],1).'%</td>';
        echo '<td><small>'.date('H:i:s', strtotime($r['created_at'])).'</small></td>';
        echo '</tr>';
    }
}
