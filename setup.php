<?php
include 'config/database.php';
// Drop existing tables
foreach (['plat_nomor','cameras'] as $t) @mysqli_query($con, "DROP TABLE IF EXISTS $t");
// Create from SQL
$sql = file_get_contents(__DIR__ . '/database.sql');
foreach (explode(';', $sql) as $q) {
    $q = trim($q);
    if ($q) mysqli_query($con, $q);
}
echo "Setup selesai!<br><a href='index.php'>Buka Dashboard</a>";
