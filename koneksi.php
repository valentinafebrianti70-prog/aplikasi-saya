<?php
$host = "sql202.infinityfree.com";
$user = "if0_42300102";
$pass = "5LN4vMcivz";
$db   = "if0_42300102_db_perlengkapan_sekolah";
$port = 3306;

// Tampilkan error PHP untuk debugging sementara
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$conn = mysqli_connect($host, $user, $pass, $db, $port);

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8");

date_default_timezone_set("Asia/Jakarta");

?>
