<?php
session_start();
include "koneksi.php";
header('Content-Type: application/json');

if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'pelanggan') {
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak.']);
    exit;
}

$user = mysqli_real_escape_string($conn, $_SESSION['user']);
$row  = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT nama_lengkap, email, no_hp, alamat, foto_profil FROM pelanggan WHERE username='$user' LIMIT 1"
));

if (!$row) {
    echo json_encode(['status' => 'error', 'message' => 'Data tidak ditemukan.']);
    exit;
}

echo json_encode([
    'status' => 'success',
    'data'   => [
        'nama'    => $row['nama_lengkap'] ?? '',
        'email'   => $row['email'] ?? '',
        'telepon' => $row['no_hp'] ?? '',
        'alamat'  => $row['alamat'] ?? '',
        'foto'    => $row['foto_profil'] ?? '',
    ]
]);
?>