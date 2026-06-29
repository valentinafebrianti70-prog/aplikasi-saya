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

// Mekanisme Keamanan: Auto-Migrasi Kolom Database (Termasuk kolom 'foto')
$requiredColumns = [
    'nama_lengkap' => "ALTER TABLE admin ADD COLUMN nama_lengkap VARCHAR(255) NULL AFTER username",
    'email'        => "ALTER TABLE admin ADD COLUMN email VARCHAR(255) NULL AFTER nama_lengkap",
    'phone'        => "ALTER TABLE admin ADD COLUMN phone VARCHAR(20) NULL AFTER email",
    'address'      => "ALTER TABLE admin ADD COLUMN address TEXT NULL AFTER phone",
    'foto'         => "ALTER TABLE admin ADD COLUMN foto VARCHAR(255) NULL AFTER address"
];

foreach ($requiredColumns as $column => $query) {
    $cekKolom = mysqli_query($conn, "SHOW COLUMNS FROM admin LIKE '$column'");
    if (!$cekKolom || mysqli_num_rows($cekKolom) === 0) {
        mysqli_query($conn, $query);
    }
}


// ========================================================
// 1. HANDLER UNTUK UPLOAD FOTO PROFIL (Multipart FormData)
// ========================================================
if (isset($_FILES['foto_profil'])) {
    $file = $_FILES['foto_profil'];
    $namaFile = $file['name'];
    $tmpName = $file['tmp_name'];
    $error = $file['error'];
    
    if ($error === 0) {
        $ekstensiValid = ['jpg', 'jpeg', 'png', 'webp'];
        $ekstensiFile = strtolower(pathinfo($namaFile, PATHINFO_EXTENSION));
        
        if (!in_array($ekstensiFile, $ekstensiValid)) {
            echo json_encode(["status" => "error", "message" => "Format gambar harus JPG, JPEG, PNG, atau WEBP."]);
            exit;
        }
        
        // Membuat nama unik baru agar berkas lama tidak bentrok
        $namaFileBaru = "admin_" . uniqid() . "." . $ekstensiFile;
        
        // Membuat folder 'uploads' otomatis di server jika belum ada
        if (!is_dir('uploads')) {
            mkdir('uploads', 0775, true);
        }
        
        // Ambil data foto lama dari database untuk dihapus dari penyimpanan fisik server
        $cek = mysqli_fetch_assoc(mysqli_query($conn, "SELECT foto FROM admin WHERE username='$userEsc'"));
        if (!empty($cek['foto']) && file_exists("uploads/" . $cek['foto'])) {
            unlink("uploads/" . $cek['foto']);
        }
        
        // Pindahkan file baru ke folder tujuan
        if (move_uploaded_file($tmpName, 'uploads/' . $namaFileBaru)) {
            $update = mysqli_query($conn, "UPDATE admin SET foto='$namaFileBaru' WHERE username='$userEsc'");
            if ($update) {
                echo json_encode(["status" => "success", "message" => "Foto profil berhasil diperbarui!"]);
            } else {
                echo json_encode(["status" => "error", "message" => "Gagal memperbarui data foto di database."]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Gagal mengunggah berkas ke server."]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Terjadi kesalahan pada file foto."]);
    }
    exit;
}


// ========================================================
// 2. HANDLER UNTUK REQUEST JSON (Simpan Profil Teks & Hapus Foto)
// ========================================================
$data = json_decode(file_get_contents('php://input'), true);

if ($data) {
    
    // Sub-Fitur: Hapus Foto Profil
    if (isset($data['action']) && $data['action'] === 'delete_photo') {
        $cek = mysqli_fetch_assoc(mysqli_query($conn, "SELECT foto FROM admin WHERE username='$userEsc'"));
        if (!empty($cek['foto']) && file_exists("uploads/" . $cek['foto'])) {
            unlink("uploads/" . $cek['foto']);
        }
        mysqli_query($conn, "UPDATE admin SET foto='' WHERE username='$userEsc'");
        echo json_encode(["status" => "success", "message" => "Foto profil berhasil dihapus."]);
        exit;
    }
    
    // Sub-Fitur: Update Informasi Teks Akun
    // Menyesuaikan kunci data JSON dari JavaScript ('nama_lengkap')
    $nama    = trim($data['nama_lengkap'] ?? ''); 
    $email   = trim($data['email']        ?? '');
    $phone   = trim($data['phone']        ?? '');
    $address = trim($data['address']      ?? '');

    if ($nama === '') {
        echo json_encode(['status' => 'error', 'message' => 'Nama tidak boleh kosong.']);
        exit;
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Email tidak valid.']);
        exit;
    }

    // Cek apakah email sudah digunakan admin lain
    $emailEsc = mysqli_real_escape_string($conn, $email);
    $cekEmail = mysqli_query($conn, "SELECT username FROM admin WHERE email='$emailEsc' AND username <> '$userEsc'");
    if ($cekEmail && mysqli_num_rows($cekEmail) > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Email sudah digunakan oleh admin lain.']);
        exit;
    }

    $namaEsc    = mysqli_real_escape_string($conn, $nama);
    $phoneEsc   = mysqli_real_escape_string($conn, $phone);
    $addressEsc = mysqli_real_escape_string($conn, $address);

    $sql = "UPDATE admin SET
                nama_lengkap = '$namaEsc',
                email        = '$emailEsc',
                phone        = '$phoneEsc',
                address      = '$addressEsc'
            WHERE username = '$userEsc'";

    if (mysqli_query($conn, $sql)) {
        echo json_encode([
            'status'   => 'success',
            'nama_lengkap' => $nama,
            'email'    => $email,
            'phone'    => $phone,
            'address'  => $address,
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan: ' . mysqli_error($conn)]);
    }
    exit;
}
?>