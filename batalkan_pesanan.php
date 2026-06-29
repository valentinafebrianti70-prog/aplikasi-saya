<?php
include "koneksi.php";
session_start();

if (!isset($_SESSION['login']) || $_SESSION['role'] != "pelanggan") {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user = $_SESSION['user'];
$data_pelanggan = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT * FROM pelanggan WHERE username='" . mysqli_real_escape_string($conn, $user) . "'"
));
$id_pelanggan = $data_pelanggan['id_pelanggan'] ?? 0;

if (!$id_pelanggan) {
    echo json_encode(['status' => 'error', 'message' => 'Data pelanggan tidak ditemukan']);
    exit;
}

$id_pesanan = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if (!$id_pesanan) {
    echo json_encode(['status' => 'error', 'message' => 'ID pesanan tidak valid']);
    exit;
}

$cek = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT status_pesanan FROM pesanan WHERE id_pesanan='$id_pesanan' AND id_pelanggan='$id_pelanggan'"
));

if (!$cek) {
    echo json_encode(['status' => 'error', 'message' => 'Pesanan tidak ditemukan']);
    exit;
}

if (in_array($cek['status_pesanan'], ['selesai', 'dibatalkan'])) {
    echo json_encode(['status' => 'error', 'message' => 'Pesanan tidak dapat dibatalkan']);
    exit;
}

$update = mysqli_query($conn,
    "UPDATE pesanan SET status_pesanan='dibatalkan' WHERE id_pesanan='$id_pesanan' AND id_pelanggan='$id_pelanggan'"
);

if ($update) {
    echo json_encode(['status' => 'success', 'message' => 'Pesanan berhasil dibatalkan']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Gagal membatalkan pesanan']);
}
?>