<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "koneksi.php";

if (!isset($_SESSION['login']) || $_SESSION['role'] != "admin") {
    header("Location: login.php");
    exit;
}

$user = $_SESSION['user'];

$query_admin = mysqli_query($conn, "SELECT * FROM admin WHERE username = '" . mysqli_real_escape_string($conn, $user) . "'");
$data_admin  = mysqli_fetch_assoc($query_admin);

$nama_display_admin = (!empty($data_admin['nama_lengkap'])) ? $data_admin['nama_lengkap'] : $user;
$foto_admin         = (!empty($data_admin['foto'])) ? $data_admin['foto'] : '';

// =====================
// UPDATE STATUS PESANAN
// =====================
if (isset($_POST['update_status'])) {
    $id_pesanan     = mysqli_real_escape_string($conn, $_POST['id_pesanan']);
    $status_pesanan = mysqli_real_escape_string($conn, $_POST['status_pesanan']);
    mysqli_query($conn, "UPDATE pesanan SET status_pesanan='$status_pesanan' WHERE id_pesanan='$id_pesanan'");
    header("Location: pesanan.php");
    exit;
}

// =====================
// HAPUS PESANAN
// =====================
if (isset($_POST['hapus_pesanan'])) {
    $id = mysqli_real_escape_string($conn, $_POST['id_pesanan']);
    mysqli_query($conn, "DELETE FROM detail_pesanan WHERE id_pesanan='$id'");
    mysqli_query($conn, "DELETE FROM pesanan WHERE id_pesanan='$id'");
    header("Location: pesanan.php");
    exit;
}

// =====================
// FILTER STATUS & SEARCH
// =====================
$filter = isset($_GET['filter']) ? mysqli_real_escape_string($conn, $_GET['filter']) : '';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

$where = [];
if ($filter != '') $where[] = "p.status_pesanan='$filter'";
if ($search != '') $where[] = "pl.username LIKE '%$search%'";
$where_sql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

// =====================
// QUERY PESANAN
// =====================
$query = mysqli_query($conn, "
    SELECT p.*, pl.username, pl.email
    FROM pesanan p
    LEFT JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan
    $where_sql
    ORDER BY p.id_pesanan DESC
");

// =====================
// TOTAL STATS
// =====================
$total_pesanan  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM pesanan"))['total'] ?? 0;
$total_diproses = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM pesanan WHERE status_pesanan='diproses'"))['total'] ?? 0;
$total_selesai  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM pesanan WHERE status_pesanan='selesai'"))['total'] ?? 0;
$total_batal    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM pesanan WHERE status_pesanan='dibatalkan'"))['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Pesanan Admin - Perlengkapan Sekolah</title>
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

/* ── STATS CARD ── */
.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 20px; }
.stat-card { background: white; border-radius: 12px; padding: 14px 16px; border: 1.5px solid var(--border); position: relative; overflow: hidden; transition: all 0.2s; }
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(108,99,255,0.08); }
.stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; border-radius: 12px 12px 0 0; }
.stat-card.blue::before    { background: linear-gradient(90deg, #6C63FF, #8B85FF); }
.stat-card.orange::before { background: linear-gradient(90deg, #FA8231, #FFC048); }
.stat-card.green::before  { background: linear-gradient(90deg, #43E97B, #38F9D7); }
.stat-card.red::before    { background: linear-gradient(90deg, #FF6584, #FF8FA3); }
.stat-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px; margin-bottom: 10px; }
.stat-card.blue   .stat-icon { background: #EEF0FF; }
.stat-card.orange .stat-icon { background: #FFF5EC; }
.stat-card.green  .stat-icon { background: #EDFFF5; }
.stat-card.red    .stat-icon { background: #FFF0F3; }
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
.btn-reset { padding: 8px 14px; background: #FFF0F3; color: var(--secondary); border: none; border-radius: 8px; font-family: inherit; font-size: 12.5px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; transition: all 0.2s; }

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

/* ── STATUS BADGE ── */
.badge { padding: 4px 10px; border-radius: 12px; font-size: 10.5px; font-weight: 700; display: inline-block; }
.badge-diproses   { background: #FFF5EC; color: #FA8231; }
.badge-dikirim    { background: #EEF0FF; color: #6C63FF; }
.badge-selesai    { background: #EDFFF5; color: #22c55e; }
.badge-dibatalkan { background: #FFF0F3; color: #FF6584; }

/* ── ACTION BUTTONS ── */
.action-group { display: flex; gap: 4px; align-items: center; }
.btn-detail { padding: 5px 10px; background: #EEF0FF; color: var(--primary); border: none; border-radius: 6px; font-family: inherit; font-size: 11px; font-weight: 600; cursor: pointer; text-decoration: none; transition: all 0.2s; }
.btn-detail:hover { background: #dfe5ff; }
.btn-hapus { padding: 5px 10px; background: #FFF0F3; color: #FF6584; border: none; border-radius: 6px; font-family: inherit; font-size: 11px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
.btn-hapus:hover { background: #ffdce3; }

/* ── UPDATE STATUS FORM ── */
.status-form { display: flex; align-items: center; gap: 4px; white-space: nowrap; }
.status-form select { padding: 5px 8px; border: 1.5px solid var(--border); border-radius: 6px; font-family: inherit; font-size: 11.5px; outline: none; color: var(--text); background: white; cursor: pointer; }
.btn-update { padding: 5px 10px; background: linear-gradient(135deg, var(--primary), var(--primary-2)); color: white; border: none; border-radius: 6px; font-family: inherit; font-size: 11.5px; font-weight: 600; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; }

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
        <span class="admin-meta">Admin: <span style="color:#8B85FF; font-weight:600;"><?php echo htmlspecialchars($nama_display_admin); ?></span></span> 
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
            <h1>Manajemen Pesanan</h1>
            <p style="margin-top: 2px;"><?php echo date('l, d M Y'); ?></p>
        </div>
        <div class="topbar-right">
            <div class="avatar" style="width: 38px; height: 38px; border-radius: 10px; overflow: hidden; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #6C63FF, #FF6584); color: white; font-weight: 700;">
                <?php if (!empty($foto_admin)): ?>
                    <img src="uploads/<?php echo $foto_admin; ?>" alt="Foto" style="width:100%; height:100%; object-fit:cover;">
                <?php else: ?>
                    <?php echo strtoupper(substr($nama_display_admin, 0, 1)); ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="content">

        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="stat-icon">🛒</div>
                <div class="stat-number"><?php echo $total_pesanan; ?></div>
                <div class="stat-label">Total Pesanan</div>
            </div>
            <div class="stat-card orange">
                <div class="stat-icon">⏳</div>
                <div class="stat-number"><?php echo $total_diproses; ?></div>
                <div class="stat-label">Sedang Diproses</div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon">✅</div>
                <div class="stat-number"><?php echo $total_selesai; ?></div>
                <div class="stat-label">Pesanan Selesai</div>
            </div>
            <div class="stat-card red">
                <div class="stat-icon">❌</div>
                <div class="stat-number"><?php echo $total_batal; ?></div>
                <div class="stat-label">Dibatalkan</div>
            </div>
        </div>

        <div class="filter-section">
            <form method="GET">
                <input type="text" name="search" placeholder="Cari username pelanggan..." value="<?php echo htmlspecialchars($search ?? ''); ?>">
                <select name="filter">
                    <option value="">Semua Status</option>
                    <option value="diproses"   <?php echo ($filter ?? '') == 'diproses'   ? 'selected' : ''; ?>>Diproses</option>
                    <option value="dikirim"    <?php echo ($filter ?? '') == 'dikirim'    ? 'selected' : ''; ?>>Dikirim</option>
                    <option value="selesai"    <?php echo ($filter ?? '') == 'selesai'    ? 'selected' : ''; ?>>Selesai</option>
                    <option value="dibatalkan" <?php echo ($filter ?? '') == 'dibatalkan' ? 'selected' : ''; ?>>Dibatalkan</option>
                </select>
                <button type="submit" class="btn-filter">🔍 Cari</button>
                <?php if (($filter ?? '') != '' || ($search ?? '') != '') { ?>
                    <a href="pesanan.php" class="btn-reset">🔄 Reset</a>
                <?php } ?>
            </form>
        </div>

        <div class="table-card">
            <div class="table-header">
                <div class="table-title">📋 Daftar Pesanan</div>
                <div style="font-size:11.5px; color:var(--muted); font-weight: 500;">
                    Total Terfilter: <strong><?php echo mysqli_num_rows($query); ?></strong> pesanan
                </div>
            </div>

            <?php if (mysqli_num_rows($query) > 0) { ?>
            <table>
                <thead>
                    <tr>
                        <th style="width: 80px;">ID</th>
                        <th>Pelanggan</th>
                        <th>Tanggal</th>
                        <th>Total Harga</th>
                        <th>Status</th>
                        <th>Update Status</th>
                        <th style="text-align: center; width: 150px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($query)) { ?>
                    <tr>
                        <td><strong>#<?php echo $row['id_pesanan']; ?></strong></td>
                        <td>
                            <div style="font-weight:600; color: var(--text);"><?php echo htmlspecialchars($row['username']); ?></div>
                            <div style="font-size:11px; color:var(--muted);"><?php echo htmlspecialchars($row['email']); ?></div>
                        </td>
                        <td><?php echo date('d M Y', strtotime($row['tanggal_pesanan'])); ?></td>
                        <td>
                            <strong style="color:var(--primary);">
                                Rp <?php echo number_format($row['total_harga'], 0, ',', '.'); ?>
                            </strong>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $row['status_pesanan']; ?>">
                                <?php echo ucfirst($row['status_pesanan']); ?>
                            </span>
                        </td>
                        <td>
                            <form method="POST" class="status-form">
                                <input type="hidden" name="id_pesanan" value="<?php echo $row['id_pesanan']; ?>">
                                <select name="status_pesanan">
                                    <option value="diproses"   <?php echo $row['status_pesanan'] == 'diproses'   ? 'selected' : ''; ?>>Diproses</option>
                                    <option value="dikirim"    <?php echo $row['status_pesanan'] == 'dikirim'    ? 'selected' : ''; ?>>Dikirim</option>
                                    <option value="selesai"    <?php echo $row['status_pesanan'] == 'selesai'    ? 'selected' : ''; ?>>Selesai</option>
                                    <option value="dibatalkan" <?php echo $row['status_pesanan'] == 'dibatalkan' ? 'selected' : ''; ?>>Dibatalkan</option>
                                </select>
                                <button type="submit" name="update_status" class="btn-update">&#10004; Simpan</button>
                            </form>
                        </td>
                        <td>
                            <div class="action-group" style="justify-content: center;">
                                <a href="detail_pesanan.php?id=<?php echo $row['id_pesanan']; ?>" class="btn-detail">Detail</a>
                                <form method="POST" onsubmit="return confirm('Yakin ingin menghapus pesanan ini beserta detailnya?')" style="display:inline;">
                                    <input type="hidden" name="id_pesanan" value="<?php echo $row['id_pesanan']; ?>">
                                    <button type="submit" name="hapus_pesanan" class="btn-hapus">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
            <?php } else { ?>
            <div class="empty-state">
                <div class="empty-icon">🛒</div>
                <p>Belum ada pesanan masuk</p>
            </div>
            <?php } ?>
        </div>

    </div>
</div>

</body>
</html>