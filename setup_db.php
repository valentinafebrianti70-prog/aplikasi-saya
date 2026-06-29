<?php
include "koneksi.php";

// Cek apakah tabel pesanan_detail ada, jika tidak buat
$result = mysqli_query($conn, "SHOW TABLES LIKE 'pesanan_detail'");
if (mysqli_num_rows($result) == 0) {
    $create_table = "CREATE TABLE pesanan_detail (
        id_detail INT AUTO_INCREMENT PRIMARY KEY,
        id_pesanan INT NOT NULL,
        nama_barang VARCHAR(255) NOT NULL,
        harga INT NOT NULL,
        jumlah INT NOT NULL,
        subtotal INT NOT NULL,
        FOREIGN KEY (id_pesanan) REFERENCES pesanan(id_pesanan) ON DELETE CASCADE
    )";
    
    if (mysqli_query($conn, $create_table)) {
        echo "Tabel pesanan_detail berhasil dibuat.";
    } else {
        echo "Error membuat tabel: " . mysqli_error($conn);
    }
} else {
    echo "Tabel pesanan_detail sudah ada.";
}

// Cek kolom pada tabel pesanan
$result = mysqli_query($conn, "DESCRIBE pesanan");
$kolom = [];
while ($row = mysqli_fetch_assoc($result)) {
    $kolom[] = $row['Field'];
}

// Tambah kolom jika belum ada
$kolom_baru = [
    'metode_pembayaran' => "ALTER TABLE pesanan ADD COLUMN metode_pembayaran VARCHAR(50) AFTER status_pesanan",
    'nama_penerima' => "ALTER TABLE pesanan ADD COLUMN nama_penerima VARCHAR(255) AFTER metode_pembayaran",
    'nomor_telpon' => "ALTER TABLE pesanan ADD COLUMN nomor_telpon VARCHAR(20) AFTER nama_penerima",
    'alamat_lengkap' => "ALTER TABLE pesanan ADD COLUMN alamat_lengkap TEXT AFTER nomor_telpon",
    'kota' => "ALTER TABLE pesanan ADD COLUMN kota VARCHAR(100) AFTER alamat_lengkap",
    'kode_pos' => "ALTER TABLE pesanan ADD COLUMN kode_pos VARCHAR(10) AFTER kota",
];

foreach ($kolom_baru as $nama => $query) {
    if (!in_array($nama, $kolom)) {
        mysqli_query($conn, $query);
        echo "Kolom $nama ditambahkan. ";
    }
}

// Tambah kolom untuk tabel admin jika belum ada
$adminColumns = [
    'nama_lengkap' => "ALTER TABLE admin ADD COLUMN nama_lengkap VARCHAR(255) NULL AFTER username",
    'email' => "ALTER TABLE admin ADD COLUMN email VARCHAR(255) NULL AFTER nama_lengkap",
];

foreach ($adminColumns as $column => $query) {
    $cekKolom = mysqli_query($conn, "SHOW COLUMNS FROM admin LIKE '$column'");
    if (!$cekKolom || mysqli_num_rows($cekKolom) === 0) {
        mysqli_query($conn, $query);
        echo "Kolom $column ditambahkan ke tabel admin. ";
    }
}

echo "Setup database selesai!";
?>
