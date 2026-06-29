<?php
session_start();
include "koneksi.php";

if (!isset($_SESSION['login']) || $_SESSION['role'] != "pelanggan") {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$id_pesanan = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$id_pelanggan = $_SESSION['id_pelanggan'];

if (!$id_pesanan) {
    echo json_encode(['status' => 'error', 'message' => 'ID Pesanan tidak valid']);
    exit;
}

$stmt = mysqli_prepare($conn, 
    "SELECT * FROM pesanan WHERE id_pesanan = ? AND id_pelanggan = ?"
);
mysqli_stmt_bind_param($stmt, "ii", $id_pesanan, $id_pelanggan);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Pesanan tidak ditemukan']);
    exit;
}

$pesanan = mysqli_fetch_assoc($result);

$stmt2 = mysqli_prepare($conn, 
    "SELECT * FROM pesanan_detail WHERE id_pesanan = ?"
);
mysqli_stmt_bind_param($stmt2, "i", $id_pesanan);
mysqli_stmt_execute($stmt2);
$result2 = mysqli_stmt_get_result($stmt2);

$details = [];
while ($row = mysqli_fetch_assoc($result2)) {
    $details[] = $row;
}

echo json_encode([
    'status' => 'success',
    'pesanan' => $pesanan,
    'details' => $details
]);
?>