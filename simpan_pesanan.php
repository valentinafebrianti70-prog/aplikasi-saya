<?php
include "koneksi.php";
session_start();
header('Content-Type: application/json');

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

// Ambil data JSON
$data = json_decode(file_get_contents("php://input"), true);

if (!$data || empty($data['items'])) {
    echo json_encode(['status' => 'error', 'message' => 'Data tidak valid']);
    exit;
}

$items_input        = $data['items'];
$metode              = mysqli_real_escape_string($conn, $data['metode'] ?? 'Transfer Bank');
$kode_voucher_input  = trim($data['kode_voucher'] ?? '');
$tanggal             = date('Y-m-d');

// =====================================================
// VALIDASI ULANG SETIAP ITEM + AMBIL HARGA ASLI DARI DB
// (jangan percaya harga/qty yang dikirim dari browser)
// =====================================================
$items_valid = [];
$subtotal    = 0;

foreach ($items_input as $item) {
    $nama_barang = mysqli_real_escape_string($conn, $item['nama'] ?? '');
    $jumlah      = max(1, (int)($item['qty'] ?? 1));

    $barang = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT id_barang, harga, stok FROM barang WHERE nama_barang='$nama_barang' LIMIT 1"
    ));

    if (!$barang) continue; // lewati produk yang tidak ditemukan di database

    $items_valid[] = [
        'id_barang' => $barang['id_barang'],
        'harga'     => (int) $barang['harga'],
        'jumlah'    => $jumlah,
    ];
    $subtotal += $barang['harga'] * $jumlah;
}

if (empty($items_valid) || $subtotal <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Tidak ada produk valid pada pesanan']);
    exit;
}

// =====================================================
// VALIDASI VOUCHER (kalau dipakai) — sumber kebenaran di server, bukan browser
// =====================================================
$diskon_voucher     = 0;
$kode_voucher_valid = null;

if ($kode_voucher_input !== '') {
    $kodeEsc = mysqli_real_escape_string($conn, strtoupper($kode_voucher_input));
    $today   = date('Y-m-d');

    $v = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM voucher WHERE kode_voucher='$kodeEsc' LIMIT 1"));

    if (!$v) {
        echo json_encode(['status' => 'error', 'message' => 'Kode voucher tidak ditemukan']);
        exit;
    }
    if ($v['status'] != 'aktif') {
        echo json_encode(['status' => 'error', 'message' => 'Voucher tidak aktif']);
        exit;
    }
    if ($v['tanggal_mulai'] > $today) {
        echo json_encode(['status' => 'error', 'message' => 'Voucher belum mulai berlaku']);
        exit;
    }
    if ($v['tanggal_berakhir'] < $today) {
        echo json_encode(['status' => 'error', 'message' => 'Voucher sudah kadaluarsa']);
        exit;
    }
    if ($v['jumlah_maksimal'] > 0 && $v['jumlah_terpakai'] >= $v['jumlah_maksimal']) {
        echo json_encode(['status' => 'error', 'message' => 'Kuota voucher sudah habis']);
        exit;
    }

    if ($v['tipe_diskon'] == 'persentase') {
        $diskon_voucher = round($subtotal * ($v['nilai_diskon'] / 100));
    } else {
        $diskon_voucher = (float) $v['nilai_diskon'];
    }
    if ($diskon_voucher > $subtotal) $diskon_voucher = $subtotal;

    $kode_voucher_valid = $v['kode_voucher'];
}

// =====================================================
// HITUNG ONGKIR & TOTAL DI SERVER (jangan percaya total dari browser)
// Ongkir: COD = gratis, metode lain = Rp 5.000 (sesuai logika di dashboard_pelanggan.php)
// =====================================================
$metode_lower = strtolower($metode);
$ongkir = (strpos($metode_lower, 'cod') !== false || strpos($metode_lower, 'tempat') !== false) ? 0 : 5000;

$total = $subtotal - $diskon_voucher + $ongkir;
if ($total < 0) $total = 0;

// ── SIMPAN KE TABEL pesanan ──
$kodeSql = $kode_voucher_valid !== null
    ? "'" . mysqli_real_escape_string($conn, $kode_voucher_valid) . "'"
    : "NULL";

$insert_pesanan = mysqli_query($conn,
    "INSERT INTO pesanan (id_pelanggan, tanggal_pesanan, total_harga, status_pesanan, kode_voucher, diskon_voucher)
     VALUES ('$id_pelanggan', '$tanggal', '$total', 'diproses', $kodeSql, '$diskon_voucher')"
);

if (!$insert_pesanan) {
    echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan pesanan: ' . mysqli_error($conn)]);
    exit;
}

$id_pesanan = mysqli_insert_id($conn);

// ── SIMPAN KE TABEL detail_pesanan (pakai data yang sudah diverifikasi dari DB) ──
foreach ($items_valid as $item) {
    mysqli_query($conn,
        "INSERT INTO detail_pesanan (id_pesanan, id_barang, jumlah, harga)
         VALUES ('$id_pesanan', '{$item['id_barang']}', '{$item['jumlah']}', '{$item['harga']}')"
    );

    // Kurangi stok
    mysqli_query($conn,
        "UPDATE barang SET stok = stok - {$item['jumlah']}
         WHERE id_barang = '{$item['id_barang']}'"
    );
}

// ── SIMPAN KE TABEL pembayaran ──
mysqli_query($conn,
    "INSERT INTO pembayaran (id_pesanan, tanggal_pembayaran, metode_pembayaran, jumlah_bayar, status_pembayaran)
     VALUES ('$id_pesanan', '$tanggal', '$metode', '$total', 'pending')"
);

// ── TAMBAH PEMAKAIAN VOUCHER (kalau dipakai) ──
if ($kode_voucher_valid !== null) {
    mysqli_query($conn,
        "UPDATE voucher SET jumlah_terpakai = jumlah_terpakai + 1
         WHERE kode_voucher='" . mysqli_real_escape_string($conn, $kode_voucher_valid) . "'");
}

echo json_encode([
    'status'         => 'success',
    'message'        => 'Pesanan berhasil dibuat!',
    'id_pesanan'     => $id_pesanan,
    'subtotal'       => $subtotal,
    'diskon_voucher' => $diskon_voucher,
    'ongkir'         => $ongkir,
    'total'          => $total,
]);
?>