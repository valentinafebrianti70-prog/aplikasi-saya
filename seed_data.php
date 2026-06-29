<?php
// =====================================================================
// seed_data.php — Generator data contoh (pelanggan + pesanan + voucher)
// JALANKAN SEKALI lewat browser: http://localhost/Perlengkapan_Sekolah/seed_data.php
// Setelah selesai dipakai, HAPUS file ini dari server.
// =====================================================================

include 'koneksi.php';

$password_test = 'pelanggan123';
$password_hash = md5($password_test);

// [nama_lengkap, username, email, no_hp, alamat]
$nama_list = [
    ['Putri Ayu Lestari',   'putri.ayu',       'putri.ayu@gmail.com',       '081234560001', 'Jl. Melati No. 12, Makassar'],
    ['Bagas Pratama',       'bagas.pratama',   'bagas.pratama@gmail.com',   '081234560002', 'Jl. Sudirman No. 45, Makassar'],
    ['Siti Nurhaliza',      'siti.nur',        'siti.nur@gmail.com',        '081234560003', 'Jl. Pendidikan No. 8, Makassar'],
    ['Rizky Ramadhan',      'rizky.ramadhan',  'rizky.ramadhan@gmail.com',  '081234560004', 'Jl. Veteran No. 21, Makassar'],
    ['Dewi Anggraini',      'dewi.anggraini',  'dewi.anggraini@gmail.com',  '081234560005', 'Jl. Cendrawasih No. 33, Makassar'],
    ['Fajar Nugroho',       'fajar.nugroho',   'fajar.nugroho@gmail.com',   '081234560006', 'Jl. Antang Raya No. 7, Makassar'],
    ['Indah Permatasari',   'indah.permata',   'indah.permata@gmail.com',   '081234560007', 'Jl. Perintis No. 19, Makassar'],
    ['Yusuf Maulana',       'yusuf.maulana',   'yusuf.maulana@gmail.com',   '081234560008', 'Jl. Toddopuli No. 56, Makassar'],
    ['Citra Wulandari',     'citra.wulandari', 'citra.wulandari@gmail.com', '081234560009', 'Jl. Hertasning No. 14, Makassar'],
    ['Andika Saputra',      'andika.saputra',  'andika.saputra@gmail.com',  '081234560010', 'Jl. Adyaksa No. 3, Makassar'],
    ['Nabila Az-Zahra',     'nabila.zahra',    'nabila.zahra@gmail.com',    '081234560011', 'Jl. Boulevard No. 27, Makassar'],
    ['Galih Setiawan',      'galih.setiawan',  'galih.setiawan@gmail.com',  '081234560012', 'Jl. Urip Sumoharjo No. 41, Makassar'],
];

$hasil_log = [];

// =====================================================
// 1. INSERT PELANGGAN (lewati kalau username sudah ada)
// =====================================================
$id_pelanggan_list = [];

foreach ($nama_list as $p) {
    [$nama_lengkap, $username, $email, $no_hp, $alamat] = $p;

    $cek = mysqli_query($conn, "SELECT id_pelanggan FROM pelanggan WHERE username='" . mysqli_real_escape_string($conn, $username) . "'");
    if (mysqli_num_rows($cek) > 0) {
        $row = mysqli_fetch_assoc($cek);
        $id_pelanggan_list[] = $row['id_pelanggan'];
        $hasil_log[] = "⏭️ $username sudah ada, dilewati.";
        continue;
    }

    mysqli_query($conn, "INSERT INTO pelanggan 
        (nama_pelanggan, email, username, nama_lengkap, password, alamat, no_hp, status_verifikasi)
        VALUES (
            '" . mysqli_real_escape_string($conn, $nama_lengkap) . "',
            '" . mysqli_real_escape_string($conn, $email) . "',
            '" . mysqli_real_escape_string($conn, $username) . "',
            '" . mysqli_real_escape_string($conn, $nama_lengkap) . "',
            '$password_hash',
            '" . mysqli_real_escape_string($conn, $alamat) . "',
            '" . mysqli_real_escape_string($conn, $no_hp) . "',
            1
        )");

    $id_pelanggan_list[] = mysqli_insert_id($conn);
    $hasil_log[] = "✅ Pelanggan dibuat: $username ($nama_lengkap)";
}

// =====================================================
// 2. AMBIL PRODUK YANG SUDAH ADA DI TABEL barang
// =====================================================
$produk = [];
$q_barang = mysqli_query($conn, "SELECT id_barang, nama_barang, harga, stok FROM barang WHERE stok > 0");
while ($row = mysqli_fetch_assoc($q_barang)) {
    $produk[] = $row;
}

if (empty($produk)) {
    array_map(fn($l) => print("$l<br>"), $hasil_log);
    die("<br><strong style='color:red;'>❌ Tidak ada produk dengan stok di tabel 'barang'. Tambahkan produk dulu sebelum menjalankan skrip ini.</strong>");
}

// =====================================================
// 3. AMBIL VOUCHER AKTIF YANG SUDAH ADA
// =====================================================
$voucher_aktif = [];
$q_voucher = mysqli_query($conn, "SELECT * FROM voucher WHERE status='aktif' AND tanggal_mulai <= CURDATE() AND tanggal_berakhir >= CURDATE()");
while ($row = mysqli_fetch_assoc($q_voucher)) {
    $voucher_aktif[] = $row;
}

// =====================================================
// 4. BUAT PESANAN ACAK UNTUK SETIAP PELANGGAN
// =====================================================
$status_list = ['diproses', 'dikirim', 'selesai'];
$metode_list = ['Transfer Bank', 'QRIS', 'E-Wallet', 'Bayar di Tempat (COD)'];

$jumlah_pesanan_dibuat = 0;

foreach ($id_pelanggan_list as $id_pelanggan) {

    $jumlah_pesanan = rand(1, 3); // tiap pelanggan dapat 1-3 pesanan

    for ($p = 0; $p < $jumlah_pesanan; $p++) {

        // Pilih beberapa produk acak untuk pesanan ini
        $jumlah_item = rand(1, min(4, count($produk)));
        $produk_terpilih = array_rand($produk, $jumlah_item);
        if (!is_array($produk_terpilih)) $produk_terpilih = [$produk_terpilih];

        $subtotal = 0;
        $items = [];
        foreach ($produk_terpilih as $i) {
            $qty = rand(1, 3);
            $harga = $produk[$i]['harga'];
            $subtotal += $harga * $qty;
            $items[] = ['id_barang' => $produk[$i]['id_barang'], 'harga' => $harga, 'jumlah' => $qty];
        }

        // Kira-kira 1 dari 3 pesanan dipakaikan voucher (kalau ada voucher aktif & kuota masih ada)
        $kode_voucher = null;
        $diskon = 0;

        if (!empty($voucher_aktif) && rand(1, 3) == 1) {
            $v = $voucher_aktif[array_rand($voucher_aktif)];
            if ($v['jumlah_maksimal'] == 0 || $v['jumlah_terpakai'] < $v['jumlah_maksimal']) {
                $kode_voucher = $v['kode_voucher'];
                $diskon = $v['tipe_diskon'] == 'persentase'
                    ? round($subtotal * ($v['nilai_diskon'] / 100))
                    : (float) $v['nilai_diskon'];
                if ($diskon > $subtotal) $diskon = $subtotal;

                mysqli_query($conn, "UPDATE voucher SET jumlah_terpakai = jumlah_terpakai + 1 WHERE id_voucher = {$v['id_voucher']}");

                foreach ($voucher_aktif as &$va) {
                    if ($va['id_voucher'] == $v['id_voucher']) $va['jumlah_terpakai']++;
                }
                unset($va);
            }
        }

        $ongkir = 15000;
        $total  = $subtotal - $diskon + $ongkir;
        if ($total < 0) $total = 0;

        $status = $status_list[array_rand($status_list)];
        $metode = $metode_list[array_rand($metode_list)];

        // Tanggal acak dalam 30 hari terakhir, biar grafik laporan terlihat hidup
        $hari_lalu = rand(0, 30);
        $tanggal = date('Y-m-d', strtotime("-$hari_lalu days"));

        $kodeSql = $kode_voucher !== null ? "'" . mysqli_real_escape_string($conn, $kode_voucher) . "'" : "NULL";

        mysqli_query($conn, "INSERT INTO pesanan 
            (id_pelanggan, tanggal_pesanan, total_harga, status_pesanan, kode_voucher, diskon_voucher)
            VALUES ('$id_pelanggan', '$tanggal', '$total', '$status', $kodeSql, '$diskon')");

        $id_pesanan = mysqli_insert_id($conn);

        foreach ($items as $it) {
            mysqli_query($conn, "INSERT INTO detail_pesanan (id_pesanan, id_barang, jumlah, harga)
                VALUES ('$id_pesanan', '{$it['id_barang']}', '{$it['jumlah']}', '{$it['harga']}')");
        }

        $status_bayar = $status === 'diproses' ? 'pending' : 'lunas';

        mysqli_query($conn, "INSERT INTO pembayaran 
            (id_pesanan, tanggal_pembayaran, metode_pembayaran, jumlah_bayar, status_pembayaran)
            VALUES ('$id_pesanan', '$tanggal', '" . mysqli_real_escape_string($conn, $metode) . "', '$total', '$status_bayar')");

        $jumlah_pesanan_dibuat++;
    }
}

$hasil_log[] = "🎉 Total <strong>$jumlah_pesanan_dibuat pesanan</strong> berhasil dibuat untuk <strong>" . count($id_pelanggan_list) . " pelanggan</strong>.";
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Seed Data - Perlengkapan Sekolah</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
body { font-family: 'Plus Jakarta Sans', sans-serif; background:#F0F2FF; padding:40px; margin:0; }
.box { max-width:680px; margin:0 auto; background:white; border-radius:16px; padding:28px 32px; border:1.5px solid #E8EAFF; }
h2 { color:#1E1B4B; margin-bottom:16px; }
p.log { font-size:13.5px; margin:5px 0; color:#374151; }
.info { background:#EEF0FF; color:#1E1B4B; padding:16px 18px; border-radius:10px; font-size:13.5px; font-weight:500; margin-top:20px; line-height:1.7; }
.info strong { color:#6C63FF; }
.warn { background:#FFF0F3; color:#C2466A; padding:14px 18px; border-radius:10px; font-size:13px; font-weight:700; margin-top:14px; }
</style>
</head>
<body>
<div class="box">
    <h2>🌱 Hasil Generate Data Contoh</h2>
    <?php foreach ($hasil_log as $log) echo "<p class='log'>$log</p>"; ?>
    <div class="info">
        Password untuk <strong>semua</strong> akun pelanggan contoh: <strong><?php echo $password_test; ?></strong><br>
        Contoh username untuk login: <strong>putri.ayu</strong>, <strong>bagas.pratama</strong>, <strong>siti.nur</strong>, dst (lihat daftar lengkap di tabel <code>pelanggan</code>).
    </div>
    <div class="warn">
        ⚠️ Hapus file <strong>seed_data.php</strong> ini dari server setelah selesai dipakai — supaya tidak bisa diakses orang lain dan tidak sengaja membuat data dobel kalau dibuka ulang.
    </div>
</div>
</body>
</html>