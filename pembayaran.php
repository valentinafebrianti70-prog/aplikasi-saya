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
// UPDATE STATUS PEMBAYARAN & OTOMATISASI STATUS PESANAN
// =====================
if (isset($_POST['update_status'])) {
    $id_pembayaran     = mysqli_real_escape_string($conn, $_POST['id_pembayaran']);
    $status_pembayaran = mysqli_real_escape_string($conn, $_POST['status_pembayaran']);
    
    // 1. Update status pembayaran terlebih dahulu
    mysqli_query($conn, "UPDATE pembayaran SET status_pembayaran='$status_pembayaran' WHERE id_pembayaran='$id_pembayaran'");
    
    // 2. Jika status diubah menjadi 'lunas', otomatis update status pesanan terkait menjadi 'diproses'
    if ($status_pembayaran == 'lunas') {
        $get_pembayaran = mysqli_query($conn, "SELECT id_pesanan FROM pembayaran WHERE id_pembayaran='$id_pembayaran'");
        if ($data_pb = mysqli_fetch_assoc($get_pembayaran)) {
            $id_pesanan = $data_pb['id_pesanan'];
            mysqli_query($conn, "UPDATE pesanan SET status_pesanan='diproses' WHERE id_pesanan='$id_pesanan'");
        }
    }
    
    header("Location: pembayaran.php");
    exit;
}

// =====================
// FILTER & SEARCH (Sanitasi Keamanan SQL Injection)
// =====================
$filter = isset($_GET['filter']) ? mysqli_real_escape_string($conn, $_GET['filter']) : '';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

$where = [];
if ($filter != '') $where[] = "pb.status_pembayaran='$filter'";
if ($search != '') $where[] = "pl.username LIKE '%$search%'";
$where_sql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

// =====================
// QUERY PEMBAYARAN (Konsisten mengambil data finansial pb.jumlah_bayar)
// =====================
$query = mysqli_query($conn, "
    SELECT pb.*, p.tanggal_pesanan, p.total_harga, pl.username 
    FROM pembayaran pb
    LEFT JOIN pesanan p ON pb.id_pesanan = p.id_pesanan
    LEFT JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan
    $where_sql
    ORDER BY pb.id_pembayaran DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Manajemen Pembayaran - Panel Admin</title>
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
body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; }

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
.topbar-right   { display: flex; align-items: center; gap: 14px; }
.avatar { width: 38px; height: 38px; border-radius: 10px; background: linear-gradient(135deg, var(--primary), var(--secondary)); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 14px; }

/* ── CONTENT ── */
.content { padding: 28px 32px; flex: 1; }

/* ── FILTER & SEARCH CARD ── */
.filter-card { background: white; border-radius: 16px; padding: 20px 24px; border: 1.5px solid var(--border); margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
.filter-card form { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; width: 100%; justify-content: space-between; }
.form-group { display: flex; align-items: center; gap: 8px; }
.form-group label { font-size: 13px; color: var(--muted); font-weight: 500; }
.filter-card select, .filter-card input[type="text"] { padding: 10px 14px; border: 1.5px solid var(--border); border-radius: 10px; font-family: inherit; font-size: 13.5px; outline: none; color: var(--text); background: white; transition: all 0.2s; }
.filter-card select:focus, .filter-card input[type="text"]:focus { border-color: var(--primary); }
.filter-card input[type="text"] { width: 220px; }

.btn-filter { padding: 10px 20px; background: linear-gradient(135deg, var(--primary), var(--primary-2)); color: white; border: none; border-radius: 10px; font-family: inherit; font-size: 13.5px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
.btn-filter:hover { opacity: 0.85; transform: translateY(-1px); }
.btn-reset { padding: 10px 16px; background: #FFF0F3; color: var(--secondary); text-decoration: none; border-radius: 10px; font-size: 13.5px; font-weight: 600; transition: all 0.2s; }
.btn-reset:hover { background: var(--secondary); color: white; }

/* ── TABLE ── */
.table-card { background: white; border-radius: 16px; border: 1.5px solid var(--border); overflow: hidden; }
.table-header { padding: 20px 24px; border-bottom: 1px solid var(--border); }
.table-title { font-size: 15px; font-weight: 700; }
table { width: 100%; border-collapse: collapse; }
thead th { background: var(--bg); padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }
tbody tr { border-bottom: 1px solid var(--border); transition: all 0.2s; }
tbody tr:last-child { border-bottom: none; }
tbody tr:hover { background: #F8F9FF; }
tbody td { padding: 14px 20px; font-size: 13.5px; vertical-align: middle; }

/* ── BADGE ── */
.badge { padding: 5px 12px; border-radius: 20px; font-size: 11.5px; font-weight: 700; display: inline-block; }
.badge-pending { background: #FFF5EC; color: #FA8231; }
.badge-lunas   { background: #EDFFF5; color: #22c55e; }
.badge-gagal   { background: #FFF0F3; color: #FF6584; }

/* ── STATUS FORM FIXED (Mencegah elemen bergeser atau patah) ── */
.status-form { 
    display: flex; 
    align-items: center; 
    gap: 6px; 
    white-space: nowrap; 
}
.status-form select { 
    padding: 6px 10px; 
    border: 1.5px solid var(--border); 
    border-radius: 8px; 
    font-family: inherit; 
    font-size: 12px; 
    outline: none; 
    color: var(--text); 
    background: white; 
    cursor: pointer; 
    transition: all 0.2s; 
}
.status-form select:focus { border-color: var(--primary); }

.btn-update { 
    padding: 6px 12px; 
    background: linear-gradient(135deg, var(--primary), var(--primary-2)); 
    color: white; 
    border: none; 
    border-radius: 8px; 
    font-family: inherit; 
    font-size: 12px; 
    font-weight: 600; 
    cursor: pointer; 
    transition: all 0.2s; 
    display: inline-flex; 
    align-items: center; 
    gap: 4px; 
}
.btn-update:hover { 
    opacity: 0.9; 
    transform: translateY(-0.5px); 
    box-shadow: 0 2px 6px rgba(108, 99, 255, 0.3); 
}

.empty-state { text-align: center; padding: 40px; color: var(--muted); }
.empty-state .empty-icon { font-size: 40px; margin-bottom: 10px; }
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
            <h1>Kelola Pembayaran</h1>
            <p>Validasi konfirmasi pembayaran dari pelanggan</p>
        </div>
        <div class="topbar-right">
            <div class="avatar">
                <?php if (!empty($data_admin['foto'])): ?>
                    <img src="uploads/<?php echo $foto_admin; ?>" alt="Foto" style="width:100%; height:100%; object-fit:cover; border-radius:8px;">
                <?php else: ?>
                    <?php echo strtoupper(substr($nama_display, 0, 1)); ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="filter-card">
            <form method="GET">
                <div style="display:flex; gap:16px; flex-wrap:wrap;">
                    <div class="form-group">
                        <label>Status:</label>
                        <select name="filter">
                            <option value="">Semua Status</option>
                            <option value="pending" <?php echo $filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="lunas"   <?php echo $filter == 'lunas'   ? 'selected' : ''; ?>>Lunas</option>
                            <option value="gagal"   <?php echo $filter == 'gagal'   ? 'selected' : ''; ?>>Gagal</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Cari Pelanggan:</label>
                        <input type="text" name="search" placeholder="Ketik nama pelanggan..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div style="display:flex; gap:8px;">
                    <button type="submit" class="btn-filter">🔍 Filter</button>
                    <?php if ($filter != '' || $search != '') { ?>
                        <a href="pembayaran.php" class="btn-reset">🔄 Reset</a>
                    <?php } ?>
                </div>
            </form>
        </div>

        <div class="table-card">
            <div class="table-header">
                <div class="table-title">💳 Daftar Pembayaran Masuk</div>
            </div>
            <?php if (mysqli_num_rows($query) > 0) { ?>
            <table>
                <thead>
                    <tr>
                        <th>ID Pembayaran</th>
                        <th>ID Pesanan</th>
                        <th>Pelanggan</th>
                        <th>Metode Bayar</th>
                        <th>Jumlah Bayar</th>
                        <th>Status</th>
                        <th>Update Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($query)) { ?>
                    <tr>
                        <td><strong>#<?php echo $row['id_pembayaran']; ?></strong></td>
                        <td><a href="detail_pesanan.php?id=<?php echo $row['id_pesanan']; ?>" style="color:var(--primary);font-weight:700;text-decoration:none;">#<?php echo $row['id_pesanan']; ?></a></td>
                        <td><strong><?php echo htmlspecialchars($row['username']); ?></strong></td>
                        <td><?php echo ucfirst($row['metode_pembayaran']); ?></td>
                        <td><strong style="color:var(--primary);">Rp <?php echo number_format($row['jumlah_bayar'], 0, ',', '.'); ?></strong></td>
                        <td>
                            <span class="badge badge-<?php echo $row['status_pembayaran']; ?>">
                                <?php echo ucfirst($row['status_pembayaran']); ?>
                            </span>
                        </td>
                        <td>
                            <form method="POST" class="status-form">
                                <input type="hidden" name="id_pembayaran" value="<?php echo $row['id_pembayaran']; ?>">
                                <select name="status_pembayaran">
                                    <option value="pending" <?php echo $row['status_pembayaran'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="lunas"   <?php echo $row['status_pembayaran'] == 'lunas'   ? 'selected' : ''; ?>>Lunas</option>
                                    <option value="gagal"   <?php echo $row['status_pembayaran'] == 'gagal'   ? 'selected' : ''; ?>>Gagal</option>
                                </select>
                                <button type="submit" name="update_status" class="btn-update">&#10004; Simpan</button>
                            </form>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
            <?php } else { ?>
            <div class="empty-state">
                <div class="empty-icon">💳</div>
                <p>Belum ada data pembayaran</p>
            </div>
            <?php } ?>
        </div>

    </div>
</div>

</body>
</html>