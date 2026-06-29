<?php
session_start();
include "koneksi.php";
header('Content-Type: application/json');

if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'pelanggan') {
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak.']);
    exit;
}

$user = $_SESSION['user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
    $data = $_POST;
} else {
    $data = json_decode(file_get_contents('php://input'), true);
}

$nama   = trim($data['nama'] ?? '');
$email  = trim($data['email'] ?? '');
$telp   = trim($data['telp'] ?? '');
$alamat = trim($data['alamat'] ?? '');

if ($nama === '') {
    echo json_encode(['status' => 'error', 'message' => 'Nama tidak boleh kosong.']);
    exit;
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Email tidak valid.']);
    exit;
}

$namaEsc   = mysqli_real_escape_string($conn, $nama);
$emailEsc  = mysqli_real_escape_string($conn, $email);
$telpEsc   = mysqli_real_escape_string($conn, $telp);
$alamatEsc = mysqli_real_escape_string($conn, $alamat);
$userEsc   = mysqli_real_escape_string($conn, $user);

// Pastikan email tidak dipakai pengguna lain.
$cekEmail = mysqli_query($conn, "SELECT id_pelanggan FROM pelanggan WHERE email='$emailEsc' AND username <> '$userEsc'");
if ($cekEmail && mysqli_num_rows($cekEmail) > 0) {
    echo json_encode(['status' => 'error', 'message' => 'Email sudah digunakan oleh akun lain.']);
    exit;
}

// Tambahkan kolom nama_lengkap, no_hp, alamat, dan foto_profil jika belum ada.
$requiredColumns = [
    'nama_lengkap' => "ALTER TABLE pelanggan ADD COLUMN nama_lengkap VARCHAR(255) NULL AFTER username",
    'no_hp'       => "ALTER TABLE pelanggan ADD COLUMN no_hp VARCHAR(20) NULL AFTER email",
    'alamat'      => "ALTER TABLE pelanggan ADD COLUMN alamat TEXT NULL AFTER no_hp",
    'foto_profil' => "ALTER TABLE pelanggan ADD COLUMN foto_profil VARCHAR(255) NULL AFTER alamat",
];
foreach ($requiredColumns as $column => $query) {
    $cekKolom = mysqli_query($conn, "SHOW COLUMNS FROM pelanggan LIKE '$column'");
    if (!$cekKolom || mysqli_num_rows($cekKolom) === 0) {
        mysqli_query($conn, $query);
    }
}

$profileQuery = mysqli_query($conn, "SELECT id_pelanggan, foto_profil FROM pelanggan WHERE username='$userEsc' LIMIT 1");
if (!$profileQuery || mysqli_num_rows($profileQuery) === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Akun pelanggan tidak ditemukan.']);
    exit;
}
$userData = mysqli_fetch_assoc($profileQuery);
$id_pelanggan = $userData['id_pelanggan'];
$currentFoto = $userData['foto_profil'];

$fotoFilename = '';
if (isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
    $foto = $_FILES['foto'];
    if ($foto['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan saat mengunggah foto.']);
        exit;
    }

    $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
    $ext = strtolower(pathinfo($foto['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        echo json_encode(['status' => 'error', 'message' => 'Format foto tidak didukung. Gunakan JPG, PNG, atau WEBP.']);
        exit;
    }

    if ($foto['size'] > 2 * 1024 * 1024) {
        echo json_encode(['status' => 'error', 'message' => 'Ukuran foto maksimal 2MB.']);
        exit;
    }

    if (!is_dir('upload')) {
        mkdir('upload', 0755, true);
    }

    $fotoFilename = 'profile_' . $id_pelanggan . '.' . $ext;
    $targetPath = 'upload/' . $fotoFilename;

    if (move_uploaded_file($foto['tmp_name'], $targetPath)) {
        if (!empty($currentFoto) && $currentFoto !== $fotoFilename && file_exists('upload/' . $currentFoto)) {
            @unlink('upload/' . $currentFoto);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan file foto.']);
        exit;
    }
}

$updateSql = "UPDATE pelanggan SET ";
$updateSql .= "nama_lengkap='$namaEsc', ";
$updateSql .= "email='$emailEsc', ";
$updateSql .= "no_hp='$telpEsc', ";
$updateSql .= "alamat='$alamatEsc'";
if ($fotoFilename !== '') {
    $fotoEsc = mysqli_real_escape_string($conn, $fotoFilename);
    $updateSql .= ", foto_profil='$fotoEsc'";
}
$updateSql .= " WHERE username='$userEsc'";

if (mysqli_query($conn, $updateSql)) {
    echo json_encode([
        'status' => 'success',
        'nama'   => $nama,
        'email'  => $email,
        'telp'   => $telp,
        'alamat' => $alamat,
        'foto'   => $fotoFilename,
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan profil: ' . mysqli_error($conn)]);
}
