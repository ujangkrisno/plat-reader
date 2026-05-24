<?php
$host = '127.0.0.1';
$user = 'root';
$pass = '';
$db = 'plat_reader';
$con = mysqli_connect($host, $user, $pass, $db);
if (!$con) {
    // Try creating database
    $con = mysqli_connect($host, $user, $pass);
    mysqli_query($con, "CREATE DATABASE IF NOT EXISTS $db");
    mysqli_select_db($con, $db);
}
