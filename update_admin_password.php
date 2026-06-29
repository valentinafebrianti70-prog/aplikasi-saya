<?php
session_start();
include "koneksi.php";
header('Content-Type: application/json');

if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak.']);
    exit;
}

$user = $_SESSION['user'];
$data = json_decode(file_get_contents('php://input'), true);

$passwordBaru  = $data['password']      ?? '';
$passwordLama  = $data['password_lama'] ?? '';

if (strlen($passwordBaru) < 6) {
    echo json_encode(['status' => 'error', 'message' => 'Password minimal 6 karakter.']);
    exit;
}

$userEsc = mysqli_real_escape_string($conn, $user);

// Ambil password lama dari DB untuk verifikasi
$result = mysqli_query($conn, "SELECT password FROM admin WHERE username = '$userEsc' LIMIT 1");
if ($result && $row = mysqli_fetch_assoc($result)) {
    $dbPass = $row['password'];
    // Deteksi apakah password di DB pakai hash (md5/bcrypt) atau plain
    // Coba cocokkan: md5, password_verify (bcrypt), lalu plain
    $valid = false;
    if ($passwordLama !== '') {
        if (strlen($dbPass) === 32 && $dbPass === md5($passwordLama)) {
            $valid = true; // md5
        } elseif (password_verify($passwordLama, $dbPass)) {
            $valid = true; // bcrypt
        } elseif ($dbPass === $passwordLama) {
            $valid = true; // plain text
        }
        if (!$valid) {
            echo json_encode(['status' => 'error', 'message' => 'Password lama tidak sesuai.']);
            exit;
        }
    }
}

// Simpan password baru — gunakan md5 agar konsisten dengan sistem lama
// Ganti password_hash() jika sistem Anda sudah pakai bcrypt
$hashedPassword = md5($passwordBaru);
$hashEsc        = mysqli_real_escape_string($conn, $hashedPassword);

$sql = "UPDATE admin SET password = '$hashEsc' WHERE username = '$userEsc'";

if (mysqli_query($conn, $sql)) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Gagal mengubah password: ' . mysqli_error($conn)]);
}
?>