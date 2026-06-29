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

// Tentukan nama yang akan ditampilkan (prioritaskan nama_lengkap, jika kosong pakai username)
$nama_display = !empty($data_admin['nama_lengkap']) ? $data_admin['nama_lengkap'] : $user;
$foto_admin   = !empty($data_admin['foto']) ? $data_admin['foto'] : '';


// =====================
// HAPUS PELANGGAN
// =====================
if (isset($_GET['hapus'])) {
    $id = mysqli_real_escape_string($conn, $_GET['hapus']);
    mysqli_query($conn, "DELETE FROM keranjang WHERE id_pelanggan='$id'");
    mysqli_query($conn, "DELETE FROM pelanggan WHERE id_pelanggan='$id'");
    header("Location: pelanggan.php");
    exit;
}

// =====================
// FILTER & SEARCH
// =====================
$filter = isset($_GET['filter']) ? mysqli_real_escape_string($conn, $_GET['filter']) : '';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

$where = [];
if ($filter == 'terverifikasi')   $where[] = "status_verifikasi = 1";
if ($filter == 'belum_verifikasi') $where[] = "status_verifikasi = 0";
if ($search != '') $where[] = "(username LIKE '%$search%' OR email LIKE '%$search%')";
$where_sql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

// =====================
// QUERY PELANGGAN
// =====================
$query = mysqli_query($conn, "
    SELECT * FROM pelanggan
    $where_sql
    ORDER BY id_pelanggan DESC
");

// =====================
// STATS (Optimasi COUNT agar performa cepat)
// =====================
$total_pelanggan   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM pelanggan"))['total'] ?? 0;
$total_verified    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM pelanggan WHERE status_verifikasi=1"))['total'] ?? 0;
$total_unverified  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM pelanggan WHERE status_verifikasi=0"))['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Pelanggan Admin - Perlengkapan Sekolah</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
:root {
    --primary:   #6C63FF;
    --primary-2: #8B85FF;
    --secondary: #FF6584;
    --bg:        #F0F2FF;
    --sidebar-bg:#1E1B4B;
    --text:      #1E1B4B;
    --muted:     #8B8FAD;
    --border:    #E8EAFF;
}
body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; -webkit-font-smoothing: antialiased; }

/* ── SIDEBAR STYLE (KONSISTEN & SERAGAM 100%) ── */
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

/* Dikunci gap: 4px agar pas di layar dan tidak terpotong */
.sidebar-menu { 
    padding: 12px 12px; 
    display: flex;
    flex-direction: column;
    gap: 4px;
    overflow: hidden; 
}

.sidebar-menu a { 
    display: flex; 
    align-items: center; 
    gap: 12px; 
    padding: 16px 14px; 
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

/* ── MAIN ── */
.main { margin-left: 220px; flex: 1; display: flex; flex-direction: column; min-height: 100vh; }

/* ── TOPBAR ── */
.topbar { background: white; padding: 0 28px; height: 68px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 50; }
.topbar-left h1 { font-size: 18px; font-weight: 700; }
.topbar-left p  { font-size: 12px; color: var(--muted); margin-top: 1px; }
.topbar-right { display: flex; align-items: center; gap: 14px; }
.avatar { width: 38px; height: 38px; border-radius: 10px; background: linear-gradient(135deg, var(--primary), var(--secondary)); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 14px; }

/* ── CONTENT ── */
.content { padding: 28px 32px; flex: 1; }

/* ── STATS CARD (Ramping) ── */
.stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 20px; }
.stat-card { background: white; border-radius: 12px; padding: 14px 16px; border: 1.5px solid var(--border); position: relative; overflow: hidden; transition: all 0.2s; }
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(108,99,255,0.08); }
.stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; border-radius: 12px 12px 0 0; }
.stat-card.blue::before   { background: linear-gradient(90deg, #6C63FF, #8B85FF); }
.stat-card.green::before  { background: linear-gradient(90deg, #43E97B, #38F9D7); }
.stat-card.orange::before { background: linear-gradient(90deg, #FA8231, #FFC048); }
.stat-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px; margin-bottom: 10px; }
.stat-card.blue   .stat-icon { background: #EEF0FF; }
.stat-card.green  .stat-icon { background: #EDFFF5; }
.stat-card.orange .stat-icon { background: #FFF5EC; }
.stat-number { font-size: 22px; font-weight: 800; margin-bottom: 2px; color: var(--text); }
.stat-label { font-size: 11.5px; color: var(--muted); font-weight: 500; }

/* ── FILTER & SEARCH ── */
.filter-section { background: white; border-radius: 12px; padding: 14px 16px; border: 1.5px solid var(--border); margin-bottom: 16px; }
.filter-section form { display: flex; align-items: center; gap: 10px; width: 100%; }
.filter-section input { padding: 8px 12px; border: 1.5px solid var(--border); border-radius: 8px; font-family: inherit; font-size: 12.5px; outline: none; color: var(--text); flex: 1; transition: all 0.2s; }
.filter-section input:focus { border-color: var(--primary); }
.filter-section select { padding: 8px 12px; border: 1.5px solid var(--border); border-radius: 8px; font-family: inherit; font-size: 12.5px; outline: none; color: var(--text); cursor: pointer; background: white; }
.btn-filter { padding: 8px 16px; background: linear-gradient(135deg, var(--primary), var(--primary-2)); color: white; border: none; border-radius: 8px; font-family: inherit; font-size: 12.5px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
.btn-filter:hover { opacity: 0.9; }
.btn-reset { padding: 8px 14px; background: var(--bg); color: var(--muted); border: 1.5px solid var(--border); border-radius: 8px; font-family: inherit; font-size: 12.5px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; transition: all 0.2s; }

/* ── TABLE CARD ── */
.table-card { background: white; border-radius: 12px; border: 1.5px solid var(--border); overflow: hidden; }
.table-header { padding: 14px 16px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
.table-title { font-size: 13.5px; font-weight: 700; }
table { width: 100%; border-collapse: collapse; }
thead th { background: var(--bg); padding: 10px 16px; text-align: left; font-size: 11px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }
tbody tr { border-bottom: 1px solid var(--border); transition: all 0.2s; }
tbody tr:last-child { border-bottom: none; }
tbody tr:hover { background: #F8F9FF; }
tbody td { padding: 10px 16px; font-size: 12.5px; vertical-align: middle; }

/* ── USER PROFILE IN TABLE ── */
.user-info { display: flex; align-items: center; gap: 10px; }
.user-avatar { width: 32px; height: 32px; border-radius: 8px; background: linear-gradient(135deg, var(--primary), var(--primary-2)); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 12px; flex-shrink: 0; }
.user-detail { display: flex; flex-direction: column; }
.user-name { font-weight: 600; font-size: 12.5px; color: var(--text); }
.user-email { font-size: 11px; color: var(--muted); }

/* ── BADGE VERIFIKASI ── */
.badge { padding: 4px 10px; border-radius: 12px; font-size: 10.5px; font-weight: 700; display: inline-block; }
.badge-verified   { background: #EDFFF5; color: #22c55e; }
.badge-unverified { background: #FFF5EC; color: #FA8231; }

/* ── ACTION BUTTONS ── */
.action-group { display: flex; gap: 4px; align-items: center; }
.btn-hapus { padding: 5px 10px; background: #FFF0F3; color: #FF6584; border: none; border-radius: 6px; font-family: inherit; font-size: 11px; font-weight: 600; cursor: pointer; text-decoration: none; transition: all 0.2s; }
.btn-hapus:hover { background: #ffdce3; }

/* ── EMPTY STATE ── */
.empty-state { text-align: center; padding: 40px 20px; color: var(--muted); }
.empty-state .empty-icon { font-size: 36px; margin-bottom: 10px; }
.empty-state p { font-size: 13px; font-weight: 500; }
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
            <h1>Manajemen Pelanggan</h1>
            <p style="margin-top: 2px;"><?php echo date('l, d M Y'); ?></p>
        </div>
        <div class="topbar-right">
            <div class="avatar">
                <?php if (!empty($foto_admin)): ?>
                    <img src="uploads/<?php echo $foto_admin; ?>" alt="Foto" style="width:100%; height:100%; object-fit:cover; border-radius:8px;">
                <?php else: ?>
                    <?php echo strtoupper(substr($nama_display, 0, 1)); ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="content">

        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="stat-icon">👥</div>
                <div class="stat-number"><?php echo $total_pelanggan; ?></div>
                <div class="stat-label">Total Pelanggan</div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon">✅</div>
                <div class="stat-number"><?php echo $total_verified; ?></div>
                <div class="stat-label">Sudah Verifikasi</div>
            </div>
            <div class="stat-card orange">
                <div class="stat-icon">⏳</div>
                <div class="stat-number"><?php echo $total_unverified; ?></div>
                <div class="stat-label">Belum Verifikasi</div>
            </div>
        </div>

        <div class="filter-section">
            <form method="GET">
                <input type="text" name="search" placeholder="Cari username atau email..." value="<?php echo htmlspecialchars($search); ?>">
                <select name="filter">
                    <option value="">Semua Pelanggan</option>
                    <option value="terverifikasi"    <?php echo $filter == 'terverifikasi'    ? 'selected' : ''; ?>>Sudah Verifikasi</option>
                    <option value="belum_verifikasi" <?php echo $filter == 'belum_verifikasi' ? 'selected' : ''; ?>>Belum Verifikasi</option>
                </select>
                <button type="submit" class="btn-filter">🔍 Cari</button>
                <?php if ($filter != '' || $search != '') { ?>
                    <a href="pelanggan.php" class="btn-reset">🔄 Reset</a>
                <?php } ?>
            </form>
        </div>

        <div class="table-card">
            <div class="table-header">
                <div class="table-title">📋 Daftar Pelanggan</div>
                <div style="font-size:11.5px; color:var(--muted); font-weight: 500;">
                    Total Terfilter: <strong><?php echo mysqli_num_rows($query); ?></strong> pelanggan
                </div>
            </div>

            <?php if (mysqli_num_rows($query) > 0) { ?>
            <table>
                <thead>
                    <tr>
                        <th style="width: 80px;">ID</th>
                        <th>Pelanggan</th>
                        <th>No. HP</th>
                        <th>Alamat</th>
                        <th>Status Verifikasi</th>
                        <th>Terdaftar</th>
                        <th style="text-align: center; width: 100px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($query)) { ?>
                    <tr>
                        <td><strong>#<?php echo $row['id_pelanggan']; ?></strong></td>
                        <td>
                            <div class="user-info">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($row['username'], 0, 1)); ?>
                                </div>
                                <div class="user-detail">
                                    <div class="user-name"><?php echo htmlspecialchars($row['username']); ?></div>
                                    <div class="user-email"><?php echo htmlspecialchars($row['email']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($row['no_hp'] ?? '-'); ?></td>
                        <td style="max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                            <?php echo htmlspecialchars($row['alamat'] ?? '-'); ?>
                        </td>
                        <td>
                            <?php if ($row['status_verifikasi'] == 1) { ?>
                                <span class="badge badge-verified">✅ Terverifikasi</span>
                            <?php } else { ?>
                                <span class="badge badge-unverified">⏳ Belum Verifikasi</span>
                            <?php } ?>
                        </td>
                        <td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                        <td>
                            <div class="action-group" style="justify-content: center;">
                                <a href="?hapus=<?php echo $row['id_pelanggan']; ?>" class="btn-hapus" onclick="return confirm('Yakin ingin menghapus pelanggan ini beserta data keranjangnya?')">
                                    Hapus
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
            <?php } else { ?>
            <div class="empty-state">
                <div class="empty-icon">👥</div>
                <p>Belum ada data pelanggan</p>
            </div>
            <?php } ?>
        </div>

    </div>
</div>

</body>
</html>