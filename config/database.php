<?php
$host = '127.0.0.1';
$user = 'root';
$pass = '';
$db = 'plat_reader';
mysqli_report(MYSQLI_REPORT_OFF);
$con = @mysqli_connect($host, $user, $pass, $db);
if (!$con) {
    $con = mysqli_connect($host, $user, $pass);
    if ($con) {
        mysqli_query($con, "CREATE DATABASE IF NOT EXISTS $db");
        mysqli_select_db($con, $db);
    }
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
