<?php
session_start();
include "koneksi.php";

// Izinkan admin DAN pelanggan
if (!isset($_SESSION['login']) || !in_array($_SESSION['role'], ['admin', 'pelanggan'])) {
    header("Location: login.php");
    exit;
}

$user = $_SESSION['user'];
$role = $_SESSION['role'];

// CEK ID
if (!isset($_GET['id'])) {
    $redirect = $role === 'admin' ? 'pembayaran.php' : 'dashboard_pelanggan.php';
    header("Location: $redirect");
    exit;
}

$id_pesanan = (int)$_GET['id'];

// Kalau pelanggan, pastikan pesanan ini memang miliknya (keamanan)
if ($role === 'pelanggan') {
    $data_pel = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT id_pelanggan FROM pelanggan WHERE username='" . mysqli_real_escape_string($conn, $user) . "' LIMIT 1"
    ));
    $id_pelanggan_session = $data_pel['id_pelanggan'] ?? 0;

    $cek_milik = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT id_pesanan FROM pesanan WHERE id_pesanan='$id_pesanan' AND id_pelanggan='$id_pelanggan_session' LIMIT 1"
    ));
    if (!$cek_milik) {
        header("Location: dashboard_pelanggan.php");
        exit;
    }
}

// ===============================
// AMBIL DATA PESANAN
// ===============================
$pesanan = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        p.*, 
        pl.username, 
        pl.email, 
        pl.no_hp, 
        pl.alamat
    FROM pesanan p
    LEFT JOIN pelanggan pl 
        ON p.id_pelanggan = pl.id_pelanggan
    WHERE p.id_pesanan='$id_pesanan'
"));

if (!$pesanan) {
    header("Location: pembayaran.php");
    exit;
}

// ===============================
// AMBIL DETAIL BARANG
// ===============================
$detail = mysqli_query($conn, "
    SELECT 
        dp.*, 
        b.nama_barang,
        b.gambar
    FROM detail_pesanan dp
    LEFT JOIN barang b 
        ON dp.id_barang = b.id_barang
    WHERE dp.id_pesanan='$id_pesanan'
");

// ===============================
// AMBIL DATA PEMBAYARAN
// ===============================
$pembayaran = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT * FROM pembayaran 
    WHERE id_pesanan='$id_pesanan'
"));
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Detail Pesanan</title>

<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    font-family:'Plus Jakarta Sans',sans-serif;
    background:#F3F5FF;
    color:#1E1B4B;
}

.container{
    max-width:1200px;
    margin:auto;
    padding:30px;
}

.topbar{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:30px;
}

.back-btn{
    text-decoration:none;
    background:white;
    padding:12px 18px;
    border-radius:12px;
    color:#1E1B4B;
    font-weight:700;
    border:2px solid #E5E7FF;
}

.page-title{
    font-size:30px;
    font-weight:800;
}

.grid{
    display:grid;
    grid-template-columns:2fr 1fr;
    gap:20px;
}

.card{
    background:white;
    border-radius:24px;
    overflow:hidden;
    margin-bottom:20px;
    border:2px solid #ECEEFF;
}

.card-header{
    padding:20px 24px;
    border-bottom:2px solid #F2F4FF;
    font-size:18px;
    font-weight:800;
}

.card-body{
    padding:24px;
}

.info-row{
    display:flex;
    justify-content:space-between;
    padding:14px 0;
    border-bottom:1px solid #F0F2FF;
}

.info-row:last-child{
    border-bottom:none;
}

.info-label{
    color:#8B8FAD;
    font-weight:600;
}

.info-value{
    font-weight:700;
}

.badge{
    padding:8px 14px;
    border-radius:30px;
    font-size:12px;
    font-weight:700;
}

.pending{
    background:#FFF4E8;
    color:#FA8231;
}

.lunas{
    background:#EAFBF2;
    color:#22C55E;
}

.gagal{
    background:#FFF0F3;
    color:#FF6584;
}

table{
    width:100%;
    border-collapse:collapse;
}

thead{
    background:#F5F7FF;
}

thead th{
    padding:16px;
    text-align:left;
    font-size:13px;
}

tbody td{
    padding:18px 16px;
    border-bottom:1px solid #F0F2FF;
}

.produk{
    display:flex;
    align-items:center;
    gap:14px;
}

.produk img{
    width:65px;
    height:65px;
    object-fit:cover;
    border-radius:12px;
    border:2px solid #E5E7FF;
    background:white;
}

.total{
    display:flex;
    justify-content:space-between;
    padding:24px;
    font-size:22px;
    font-weight:800;
    background:#F8F9FF;
}

.print-btn{
    width:100%;
    border:none;
    padding:14px;
    border-radius:14px;
    background:linear-gradient(135deg,#5B5FFB,#A855F7);
    color:white;
    font-weight:700;
    cursor:pointer;
    margin-top:15px;
}

@media(max-width:900px){

    .grid{
        grid-template-columns:1fr;
    }

}

</style>
</head>
<body>

<div class="container">

    <div class="topbar">

        <div>
            <div class="page-title">
                Detail Pesanan #<?php echo $id_pesanan; ?>
            </div>
        </div>

        <a href="<?php echo $role === 'admin' ? 'pembayaran.php' : 'dashboard_pelanggan.php'; ?>" class="back-btn">
            ← Kembali
        </a>

    </div>

    <div class="grid">

        <!-- KIRI -->
        <div>

            <!-- INFORMASI PESANAN -->
            <div class="card">

                <div class="card-header">
                    🧾 Informasi Pesanan
                </div>

                <div class="card-body">

                    <div class="info-row">
                        <div class="info-label">ID Pesanan</div>
                        <div class="info-value">
                            #<?php echo $pesanan['id_pesanan']; ?>
                        </div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Tanggal</div>
                        <div class="info-value">
                            <?php echo date('d F Y', strtotime($pesanan['tanggal_pesanan'])); ?>
                        </div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Status</div>
                        <div class="info-value">
                            <?php echo ucfirst($pesanan['status_pesanan']); ?>
                        </div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Total</div>
                        <div class="info-value" style="color:#6C63FF;">
                            Rp <?php echo number_format($pesanan['total_harga'],0,',','.'); ?>
                        </div>
                    </div>

                </div>

            </div>

            <!-- DATA PELANGGAN -->
            <div class="card">

                <div class="card-header">
                    👤 Data Pelanggan
                </div>

                <div class="card-body">

                    <div class="info-row">
                        <div class="info-label">Username</div>
                        <div class="info-value">
                            <?php echo $pesanan['username']; ?>
                        </div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Email</div>
                        <div class="info-value">
                            <?php echo $pesanan['email']; ?>
                        </div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">No HP</div>
                        <div class="info-value">
                            <?php echo $pesanan['no_hp']; ?>
                        </div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Alamat</div>
                        <div class="info-value">
                            <?php echo $pesanan['alamat']; ?>
                        </div>
                    </div>

                </div>

            </div>

            <!-- DETAIL BARANG -->
            <div class="card">

                <div class="card-header">
                    📦 Detail Barang
                </div>

                <table>

                    <thead>
                        <tr>
                            <th>Barang</th>
                            <th>Harga</th>
                            <th>Jumlah</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php while($item = mysqli_fetch_assoc($detail)) : ?>

                        <tr>

                            <td>

                                <div class="produk">

                                    <img src="upload/<?php echo $item['gambar']; ?>">

                                    <div>
                                        <?php echo $item['nama_barang']; ?>
                                    </div>

                                </div>

                            </td>

                            <td>
                                Rp <?php echo number_format($item['harga'],0,',','.'); ?>
                            </td>

                            <td>
                                <?php echo $item['jumlah']; ?>
                            </td>

                            <td style="font-weight:800;color:#6C63FF;">
                                Rp <?php echo number_format($item['harga'] * $item['jumlah'],0,',','.'); ?>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                    </tbody>

                </table>

                <div class="total">

                    <div>Total Pembayaran</div>

                    <div>
                        Rp <?php echo number_format($pesanan['total_harga'],0,',','.'); ?>
                    </div>

                </div>

            </div>

        </div>

        <!-- KANAN -->
        <div>

            <!-- PEMBAYARAN -->
            <div class="card">

                <div class="card-header">
                    💳 Pembayaran
                </div>

                <div class="card-body">

                <?php if($pembayaran) : ?>

                    <div class="info-row">
                        <div class="info-label">Metode</div>
                        <div class="info-value">
                            <?php echo ucfirst($pembayaran['metode_pembayaran']); ?>
                        </div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Tanggal Bayar</div>
                        <div class="info-value">
                            <?php echo date('d F Y', strtotime($pembayaran['tanggal_pembayaran'])); ?>
                        </div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Jumlah Bayar</div>
                        <div class="info-value">
                            Rp <?php echo number_format($pembayaran['jumlah_bayar'],0,',','.'); ?>
                        </div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Status</div>

                        <div class="info-value">

                            <span class="badge <?php echo $pembayaran['status_pembayaran']; ?>">

                                <?php echo ucfirst($pembayaran['status_pembayaran']); ?>

                            </span>

                        </div>
                    </div>

                <?php else : ?>

                    <div style="text-align:center;color:#8B8FAD;">
                        Belum ada pembayaran
                    </div>

                <?php endif; ?>

                <button onclick="window.print()" class="print-btn">
                    🖨️ Cetak Detail
                </button>

                </div>

            </div>

        </div>

    </div>

</div>

</body>
</html>