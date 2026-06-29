<?php
session_start();
include "koneksi.php";

if (!isset($_SESSION['login']) || $_SESSION['role'] != "admin") {
    header("Location: login.php");
    exit;
}

$user = $_SESSION['user'];

// Ambil data admin terbaru dari database agar Nama (Cata) dan Foto selalu up-to-date
$query_admin = mysqli_query($conn, "SELECT * FROM admin WHERE username = '" . mysqli_real_escape_string($conn, $user) . "'");
$data_admin  = mysqli_fetch_assoc($query_admin);

// Tentukan nama yang akan ditampilkan (prioritaskan nama, jika kosong pakai username)
$nama_display = !empty($data_admin['nama']) ? $data_admin['nama'] : (!empty($data_admin['nama_lengkap']) ? $data_admin['nama_lengkap'] : $user);
$foto_admin   = !empty($data_admin['foto']) ? $data_admin['foto'] : '';


if(isset($_GET['hapus'])){

    $id = $_GET['hapus'];

    $ambil = mysqli_query(
        $conn,
        "SELECT gambar FROM barang WHERE id_barang='$id'"
    );

    $data = mysqli_fetch_assoc($ambil);

    if($data){

        $path = "upload/" . $data['gambar'];

        if(file_exists($path)){
            unlink($path);
        }

    }

    mysqli_query(
        $conn,
        "DELETE FROM barang WHERE id_barang='$id'"
    );

    header("Location: data_barang.php");
    exit;

}

$query = mysqli_query(
        $conn,
        "SELECT * FROM barang ORDER BY id_barang DESC"
);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Data Barang - Admin Perlengkapan Sekolah</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --primary:     #6C63FF;
            --primary-2:  #8B85FF;
            --secondary:  #FF6584;
            --accent:     #43E97B;
            --bg:         #F0F2FF;
            --sidebar-bg: #1E1B4B;
            --text:       #1E1B4B;
            --muted:       #8B8FAD;
            --border:     #E8EAFF;
            --danger:     #FF6584;
            --success:     #43E97B;
            --warning:     #FA8231;
        }

        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; }

        /* ── SIDEBAR FIX PERMANEN (RINGKAS, PADAT, ANTI-SCROLL) ── */
        .sidebar { 
            width: 220px; 
            height: 100vh; 
            background: var(--sidebar-bg); 
            display: flex; 
            flex-direction: column; 
            position: fixed; 
            top: 0; 
            left: 0; 
            z-index: 100; 
        }

        /* Tinggi padding dikurangi agar hemat space vertikal layar */
        .sidebar-logo { 
            padding: 18px 20px; 
            border-bottom: 1px solid rgba(255,255,255,0.08); 
        }

        .sidebar-logo h2 { 
            color: white; 
            font-size: 14px; 
            font-weight: 700; 
        }

        .sidebar-logo .admin-meta { 
            display: block; 
            color: rgba(255,255,255,0.6); 
            font-size: 11px; 
            margin-top: 4px; 
        }

        /* Gap dibuat super rapat (4px) agar menunya naik ke atas sepenuhnya */
        .sidebar-menu { 
            padding: 12px 12px; 
            display: flex;
            flex-direction: column;
            gap: 18px;
            overflow: hidden; /* Mengunci total agar tidak akan pernah muncul scrollbar */
        }

        /* Ukuran padding tombol dibuat 8px agar menu Pengaturan aman terlihat */
        .sidebar-menu a { 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            padding: 8px 14px; 
            color: rgba(255,255,255,0.7); 
            text-decoration: none; 
            border-radius: 12px; 
            font-size: 12.5px; 
            font-weight: 600; 
            transition: all 0.2s; 
        }

        .sidebar-menu a .icon { 
            width: 26px; 
            height: 26px; 
            border-radius: 8px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 13px; 
            background: rgba(255,255,255,0.08); 
            flex-shrink: 0; 
        }

        .sidebar-menu a:hover, .sidebar-menu a.active { 
            color: white; 
            background: rgba(108,99,255,0.2); 
        }

        .sidebar-menu a.active .icon { 
            background: linear-gradient(135deg, var(--primary), var(--primary-2)); 
        }

        /* ── MAIN AREA ── */
        .main { margin-left: 220px; flex: 1; display: flex; flex-direction: column; min-height: 100vh; }

        /* ── TOPBAR ── */
        .topbar { background: white; padding: 0 28px; height: 68px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 50; }
        .topbar-left h1 { font-size: 18px; font-weight: 700; }
        .topbar-left p  { font-size: 12px; color: var(--muted); margin-top: 1px; }
        .topbar-right { display: flex; align-items: center; gap: 14px; }
        
        .btn-tambah { 
            display: flex; 
            align-items: center; 
            gap: 8px; 
            padding: 10px 20px; 
            background: linear-gradient(135deg, var(--primary), var(--primary-2)); 
            color: white; 
            text-decoration: none; 
            border-radius: 12px; 
            font-size: 13px; 
            font-weight: 700; 
            box-shadow: 0 4px 12px rgba(108,99,255,0.2);
            transition: all 0.2s; 
        }
        .btn-tambah:hover { transform: translateY(-1px); opacity: 0.9; }
        .avatar { width: 38px; height: 38px; border-radius: 10px; background: linear-gradient(135deg, var(--primary), var(--secondary)); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 14px; }

        /* ── CONTENT AREA ── */
        .content { padding: 28px 32px; flex: 1; }

        /* Grid layout untuk item barang */
        .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 20px;
        }

        .card {
            background: white;
            border-radius: 16px;
            border: 1.5px solid var(--border);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: all 0.3s;
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(108,99,255,0.08);
        }

        /* Wrapper gambar */
        .card-img-wrap {
            width: 100%;
            height: 180px;
            background: #F7F7FB;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            padding: 10px;
        }

        .card-img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            background: #fdfdfd;
        }

        .card-body {
            padding: 16px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .title {
            font-size: 14px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 6px;
            line-height: 1.4;
        }

        .price {
            font-size: 15px;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 12px;
        }

        .stock {
            font-size: 11px;
            font-weight: 700;
            padding: 6px 12px;
            border-radius: 8px;
            margin-bottom: 16px;
            display: inline-block;
            text-align: center;
        }

        .stock.available { background: #EDFFF5; color: #22c55e; }
        .stock.low       { background: #FFF5EC; color: #FA8231; }
        .stock.empty     { background: #FFF0F3; color: #FF6584; }

        .actions {
            margin-top: auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .btn {
            width: 100%;
            padding: 8px 0;
            border: none;
            border-radius: 10px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn.edit {
            background: #EEF0FF;
            color: var(--primary);
        }
        .btn.edit:hover { background: var(--primary); color: white; }

        .btn.delete {
            background: #FFF0F3;
            color: var(--danger);
        }
        .btn.delete:hover { background: var(--danger); color: white; }

        .empty-data {
            grid-column: 1 / -1;
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 16px;
            border: 1.5px solid var(--border);
            color: var(--muted);
            font-size: 14px;
            font-weight: 500;
        }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-logo">
            <h2>🎒 Perlengkapan Sekolah</h2>
            <span class="admin-meta">Admin: <span style="color:#8B85FF; font-weight:600;"><?php echo htmlspecialchars($nama_display); ?></span></span> 
        </div>
        
        <div class="sidebar-menu">
            <a href="dashboard_admin.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard_admin.php' && !isset($_GET['page']) ? 'active' : ''; ?>">
                <div class="icon">🏠</div> Dashboard
            </a>
            <a href="data_barang.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'data_barang.php' ? 'active' : ''; ?>">
                <div class="icon">📦</div> Data Barang
            </a>
            <a href="pesanan.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'pesanan.php' ? 'active' : ''; ?>">
                <div class="icon">🛒</div> Pesanan
            </a>
            <a href="pembayaran.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'pembayaran.php' ? 'active' : ''; ?>">
                <div class="icon">💳</div> Pembayaran
            </a>
            <a href="pelanggan.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'pelanggan.php' ? 'active' : ''; ?>">
                <div class="icon">👥</div> Pelanggan
            </a>
            <a href="laporan.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'laporan.php' ? 'active' : ''; ?>">
                <div class="icon">📊</div> Laporan
            </a>
            <a href="voucher.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'voucher.php' || basename($_SERVER['PHP_SELF']) == 'tambah_voucher.php' || basename($_SERVER['PHP_SELF']) == 'edit_voucher.php') ? 'active' : ''; ?>">
                <div class="icon">🎟️</div> Voucher
            </a>
            <a href="pengaturan.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'pengaturan.php' || (basename($_SERVER['PHP_SELF']) == 'dashboard_admin.php' && isset($_GET['page']) && $_GET['page'] == 'pengaturan') ? 'active' : ''; ?>">
                <div class="icon">⚙️</div> Pengaturan
            </a>
        </div>
    </div>

    <div class="main">
        <div class="topbar">
            <div class="topbar-left">
                <h1>Data Barang</h1>
                <p>Kelola semua stok perlengkapan sekolah</p>
            </div>
            <div class="topbar-right">
                <a href="tambah_barang.php" class="btn-tambah">
                    <span>➕</span> Tambah Barang
                </a>
                <div class="avatar" style="overflow:hidden;"><?php if (!empty($foto_admin)): ?><img src="uploads/<?php echo htmlspecialchars($foto_admin); ?>" alt="foto" style="width:100%;height:100%;object-fit:cover;border-radius:10px;"><?php else: echo strtoupper(substr($nama_display, 0, 1)); endif; ?></div>
            </div>
        </div>

        <div class="content">
            <div class="grid-container">

                <?php if(mysqli_num_rows($query) > 0) : ?>
                <?php while($row = mysqli_fetch_assoc($query)) : ?>

                <div class="card">
                    <div class="card-img-wrap">
                        <img src="upload/<?php echo $row['gambar']; ?>" class="card-img" alt="<?php echo htmlspecialchars($row['nama_barang']); ?>">
                    </div>
                    
                    <div class="card-body">
                        <div class="title">
                            <?php echo htmlspecialchars($row['nama_barang']); ?>
                        </div>
                        
                        <div class="price">
                            Rp <?php echo number_format($row['harga'], 0, ',', '.'); ?>
                        </div>

                        <?php if($row['stok'] > 5) : ?>
                            <div class="stock available">
                                Stok tersedia : <?php echo $row['stok']; ?>
                            </div>
                        <?php elseif($row['stok'] > 0) : ?>
                            <div class="stock low">
                                Stok sedikit : <?php echo $row['stok']; ?>
                            </div>
                        <?php else : ?>
                            <div class="stock empty">
                                Stok habis
                            </div>
                        <?php endif; ?>

                        <div class="actions">
                            <a href="edit_barang.php?id=<?php echo $row['id_barang']; ?>">
                                <button type="button" class="btn edit">Edit</button>
                            </a>
                            <a href="?hapus=<?php echo $row['id_barang']; ?>" onclick="return confirm('Yakin ingin menghapus barang ini?')">
                                <button type="button" class="btn delete">Hapus</button>
                            </a>
                        </div>
                    </div>
                </div>

                <?php endwhile; ?>
                <?php else : ?>

                <div class="empty-data">
                    Belum ada data barang ditemukan.
                </div>

                <?php endif; ?>

            </div>
        </div>
    </div>

</body>
</html>