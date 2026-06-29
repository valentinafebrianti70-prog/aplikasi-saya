<?php
session_start();
include "koneksi.php";
header('Content-Type: application/json');

if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'pelanggan') {
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak.']);
    exit;
}

$user = $_SESSION['user'];
$data = json_decode(file_get_contents('php://input'), true);

$passwordBaru = $data['password']      ?? '';
$passwordLama = $data['password_lama'] ?? '';

if (strlen($passwordBaru) < 6) {
    echo json_encode(['status' => 'error', 'message' => 'Password minimal 6 karakter.']);
    exit;
}

$userEsc = mysqli_real_escape_string($conn, $user);
$result  = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT password FROM pelanggan WHERE username='$userEsc' LIMIT 1"
));

if (!$result) {
    echo json_encode(['status' => 'error', 'message' => 'Akun tidak ditemukan.']);
    exit;
}

// Verifikasi password lama (md5)
if (md5($passwordLama) !== $result['password']) {
    echo json_encode(['status' => 'error', 'message' => 'Password lama tidak sesuai.']);
    exit;
}

$hashBaru = md5($passwordBaru);
$hashEsc  = mysqli_real_escape_string($conn, $hashBaru);

if (mysqli_query($conn, "UPDATE pelanggan SET password='$hashEsc' WHERE username='$userEsc'")) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Gagal mengubah password: ' . mysqli_error($conn)]);
}
?>