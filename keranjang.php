<?php
session_start();
include "koneksi.php";

header('Content-Type: application/json');

if (!isset($_SESSION['login']) || $_SESSION['role'] != 'pelanggan') {
    echo json_encode(['status' => 'error', 'pesan' => 'Unauthorized']);
    exit;
}

// AMBIL ID PELANGGAN
$data_pelanggan = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT id_pelanggan FROM pelanggan WHERE username='" .
    mysqli_real_escape_string($conn, $_SESSION['user']) . "'"));
$id_pelanggan = $data_pelanggan['id_pelanggan'] ?? 0;

if (!$id_pelanggan) {
    echo json_encode(['status' => 'error', 'pesan' => 'Pelanggan tidak ditemukan']);
    exit;
}

$aksi      = $_POST['aksi'] ?? '';
$id_barang = (int)($_POST['id_barang'] ?? 0);

// =====================================================
// FUNGSI VALIDASI VOUCHER (dipakai oleh cek_voucher & checkout)
// =====================================================
function validasiVoucher($conn, $kode, $subtotal) {
    $kodeEsc = mysqli_real_escape_string($conn, strtoupper(trim($kode)));
    $today   = date('Y-m-d');

    $v = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM voucher WHERE kode_voucher='$kodeEsc' LIMIT 1"));

    if (!$v) {
        return ['valid' => false, 'pesan' => 'Kode voucher tidak ditemukan'];
    }
    if ($v['status'] != 'aktif') {
        return ['valid' => false, 'pesan' => 'Voucher tidak aktif'];
    }
    if ($v['tanggal_mulai'] > $today) {
        return ['valid' => false, 'pesan' => 'Voucher belum mulai berlaku'];
    }
    if ($v['tanggal_berakhir'] < $today) {
        return ['valid' => false, 'pesan' => 'Voucher sudah kadaluarsa'];
    }
    if ($v['jumlah_maksimal'] > 0 && $v['jumlah_terpakai'] >= $v['jumlah_maksimal']) {
        return ['valid' => false, 'pesan' => 'Kuota voucher sudah habis'];
    }

    if ($v['tipe_diskon'] == 'persentase') {
        $diskon = round($subtotal * ($v['nilai_diskon'] / 100));
    } else {
        $diskon = (float) $v['nilai_diskon'];
    }
    if ($diskon > $subtotal) $diskon = $subtotal; // diskon tidak boleh melebihi subtotal

    return [
        'valid'  => true,
        'pesan'  => 'Voucher berhasil dipakai!',
        'diskon' => $diskon,
        'tipe'   => $v['tipe_diskon'],
        'nilai'  => $v['nilai_diskon'],
        'kode'   => $v['kode_voucher'],
    ];
}

// ── TAMBAH ──
if ($aksi === 'tambah') {
    $cek = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM keranjang
         WHERE id_pelanggan='$id_pelanggan' AND id_barang='$id_barang'"));
    if ($cek) {
        mysqli_query($conn,
            "UPDATE keranjang SET jumlah=jumlah+1
             WHERE id_pelanggan='$id_pelanggan' AND id_barang='$id_barang'");
    } else {
        mysqli_query($conn,
            "INSERT INTO keranjang (id_pelanggan, id_barang, jumlah)
             VALUES ('$id_pelanggan','$id_barang',1)");
    }
    echo json_encode(['status' => 'ok']);
    exit;
}

// ── HAPUS ──
if ($aksi === 'hapus') {
    mysqli_query($conn,
        "DELETE FROM keranjang
         WHERE id_pelanggan='$id_pelanggan' AND id_barang='$id_barang'");
    echo json_encode(['status' => 'ok']);
    exit;
}

// ── UPDATE QTY ──
if ($aksi === 'update') {
    $qty = (int)($_POST['qty'] ?? 1);
    if ($qty <= 0) {
        mysqli_query($conn,
            "DELETE FROM keranjang
             WHERE id_pelanggan='$id_pelanggan' AND id_barang='$id_barang'");
    } else {
        mysqli_query($conn,
            "UPDATE keranjang SET jumlah='$qty'
             WHERE id_pelanggan='$id_pelanggan' AND id_barang='$id_barang'");
    }
    echo json_encode(['status' => 'ok']);
    exit;
}

// ── CEK VOUCHER (preview, belum checkout) ──
if ($aksi === 'cek_voucher') {
    $kode = $_POST['kode_voucher'] ?? '';
    if (trim($kode) == '') {
        echo json_encode(['status' => 'error', 'pesan' => 'Masukkan kode voucher']);
        exit;
    }

    // Subtotal bisa dikirim langsung dari client (keranjang berbasis array JS
    // di dashboard_pelanggan.php). Kalau tidak dikirim, hitung dari tabel keranjang.
    $subtotal = isset($_POST['subtotal']) ? (float) $_POST['subtotal'] : null;

    if ($subtotal === null) {
        $keranjang = mysqli_query($conn,
            "SELECT k.jumlah, b.harga
             FROM keranjang k
             LEFT JOIN barang b ON k.id_barang = b.id_barang
             WHERE k.id_pelanggan='$id_pelanggan'");
        $subtotal = 0;
        while ($item = mysqli_fetch_assoc($keranjang)) {
            $subtotal += $item['harga'] * $item['jumlah'];
        }
    }

    if ($subtotal <= 0) {
        echo json_encode(['status' => 'error', 'pesan' => 'Keranjang kosong']);
        exit;
    }

    $hasil = validasiVoucher($conn, $kode, $subtotal);
    if (!$hasil['valid']) {
        echo json_encode(['status' => 'error', 'pesan' => $hasil['pesan']]);
        exit;
    }

    echo json_encode([
        'status'   => 'ok',
        'pesan'    => $hasil['pesan'],
        'kode'     => $hasil['kode'],
        'diskon'   => $hasil['diskon'],
        'subtotal' => $subtotal,
    ]);
    exit;
}

// ── CHECKOUT ──
if ($aksi === 'checkout') {
    $keranjang = mysqli_query($conn,
        "SELECT k.*, b.harga, b.stok
         FROM keranjang k
         LEFT JOIN barang b ON k.id_barang = b.id_barang
         WHERE k.id_pelanggan='$id_pelanggan'");

    if (mysqli_num_rows($keranjang) == 0) {
        echo json_encode(['status' => 'error', 'pesan' => 'Keranjang kosong']);
        exit;
    }

    // Hitung subtotal item
    $subtotal = 0;
    $items = [];
    while ($item = mysqli_fetch_assoc($keranjang)) {
        $subtotal += $item['harga'] * $item['jumlah'];
        $items[] = $item;
    }

    // ── VALIDASI VOUCHER (kalau diisi saat checkout) ──
    $kode_voucher_input = trim($_POST['kode_voucher'] ?? '');
    $diskon_voucher      = 0;
    $kode_voucher_valid  = null;

    if ($kode_voucher_input != '') {
        $hasil = validasiVoucher($conn, $kode_voucher_input, $subtotal);
        if (!$hasil['valid']) {
            echo json_encode(['status' => 'error', 'pesan' => $hasil['pesan']]);
            exit;
        }
        $diskon_voucher     = $hasil['diskon'];
        $kode_voucher_valid = $hasil['kode'];
    }

    $ongkir = 15000;
    $total  = $subtotal - $diskon_voucher + $ongkir;
    if ($total < 0) $total = 0;

    // Ambil metode dari POST
    $metode = mysqli_real_escape_string($conn, $_POST['metode'] ?? 'transfer');

    // Buat pesanan
    $tanggal = date('Y-m-d');
    $kodeSql = $kode_voucher_valid !== null
        ? "'" . mysqli_real_escape_string($conn, $kode_voucher_valid) . "'"
        : "NULL";

    mysqli_query($conn,
        "INSERT INTO pesanan (id_pelanggan, tanggal_pesanan, total_harga, status_pesanan, kode_voucher, diskon_voucher)
         VALUES ('$id_pelanggan','$tanggal','$total','diproses',$kodeSql,'$diskon_voucher')");

    $id_pesanan = mysqli_insert_id($conn);

    if (!$id_pesanan) {
        echo json_encode(['status' => 'error', 'pesan' => 'Gagal membuat pesanan']);
        exit;
    }

    // Simpan detail pesanan
    foreach ($items as $item) {
        mysqli_query($conn,
            "INSERT INTO detail_pesanan (id_pesanan, id_barang, jumlah, harga)
             VALUES ('$id_pesanan','{$item['id_barang']}','{$item['jumlah']}','{$item['harga']}')");

        // Kurangi stok
        mysqli_query($conn,
            "UPDATE barang SET stok=stok-{$item['jumlah']}
             WHERE id_barang='{$item['id_barang']}'");
    }

    // Simpan pembayaran
    mysqli_query($conn,
        "INSERT INTO pembayaran (id_pesanan, tanggal_pembayaran, metode_pembayaran, jumlah_bayar, status_pembayaran)
         VALUES ('$id_pesanan','$tanggal','$metode','$total','pending')");

    // Tambah jumlah_terpakai voucher (kalau dipakai)
    if ($kode_voucher_valid !== null) {
        mysqli_query($conn,
            "UPDATE voucher SET jumlah_terpakai = jumlah_terpakai + 1
             WHERE kode_voucher='" . mysqli_real_escape_string($conn, $kode_voucher_valid) . "'");
    }

    // Kosongkan keranjang
    mysqli_query($conn,
        "DELETE FROM keranjang WHERE id_pelanggan='$id_pelanggan'");

    echo json_encode([
        'status'         => 'ok',
        'id_pesanan'     => $id_pesanan,
        'subtotal'       => $subtotal,
        'diskon_voucher' => $diskon_voucher,
        'ongkir'         => $ongkir,
        'total'          => $total,
    ]);
    exit;
}

echo json_encode(['status' => 'error', 'pesan' => 'Aksi tidak dikenali']);
?>