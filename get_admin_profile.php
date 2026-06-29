<?php
session_start();
include "koneksi.php";
header('Content-Type: application/json');

if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak.']);
    exit;
}

$user    = $_SESSION['user'];
$userEsc = mysqli_real_escape_string($conn, $user);

// Mekanisme Auto-Migrasi Kolom Database
$requiredColumns = [
    'nama_lengkap' => "ALTER TABLE admin ADD COLUMN nama_lengkap VARCHAR(255) NULL AFTER username",
    'email'        => "ALTER TABLE admin ADD COLUMN email VARCHAR(255) NULL AFTER nama_lengkap",
    'phone'        => "ALTER TABLE admin ADD COLUMN phone VARCHAR(20) NULL AFTER email",
    'address'      => "ALTER TABLE admin ADD COLUMN address TEXT NULL AFTER phone",
    'foto'         => "ALTER TABLE admin ADD COLUMN foto VARCHAR(255) NULL AFTER address"
];

foreach ($requiredColumns as $column => $query) {
    $cek = mysqli_query($conn, "SHOW COLUMNS FROM admin LIKE '$column'");
    if (!$cek || mysqli_num_rows($cek) === 0) {
        mysqli_query($conn, $query);
    }
}

$result = mysqli_query($conn, "SELECT username, nama_lengkap, email, phone, address, foto FROM admin WHERE username = '$userEsc' LIMIT 1");

if ($result && $row = mysqli_fetch_assoc($result)) {
    // Dibungkus ke dalam indeks 'data' agar sesuai dengan JavaScript (data.data.nama)
    echo json_encode([
        'status'   => 'success',
        'data'     => [
            'username'     => $row['username'],
            'nama'         => $row['nama_lengkap'] ?? '', // Di-mapping ke 'nama' agar dibaca oleh js
            'email'        => $row['email'] ?? '',
            'telepon'      => $row['phone'] ?? '',        // Di-mapping ke 'telepon' agar dibaca oleh js
            'alamat'       => $row['address'] ?? '',      // Di-mapping ke 'alamat' agar dibaca oleh js
            'foto'         => $row['foto'] ?? ''
        ]
    ]);
} else {
    // Fallback jika user belum terdaftar secara detail di tabel
    echo json_encode([
        'status'   => 'success',
        'data'     => [
            'username'     => $user,
            'nama'         => $user,
            'email'        => '',
            'telepon'      => '',
            'alamat'       => '',
            'foto'         => ''
        ]
    ]);
}
?>