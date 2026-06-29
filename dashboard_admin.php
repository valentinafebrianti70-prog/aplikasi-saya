<?php
session_start();
include "koneksi.php";

if (!isset($_SESSION['login']) || $_SESSION['role'] != "admin") {
    header("Location: login.php");
    exit;
}

$user = $_SESSION['user'];

// Mengambil Informasi Profil Admin Terbaru Langsung dari Database untuk Sinkronisasi Global
$query_admin = mysqli_query($conn, "SELECT * FROM admin WHERE username = '" . mysqli_real_escape_string($conn, $user) . "'");
$data_admin  = mysqli_fetch_assoc($query_admin);

// Ambil nama: coba nama_lengkap dulu, fallback ke nama, lalu username
$nama_lengkap_admin = !empty($data_admin['nama_lengkap']) ? $data_admin['nama_lengkap'] : 
                      (!empty($data_admin['nama']) ? $data_admin['nama'] : $user);
$email_admin        = !empty($data_admin['email']) ? $data_admin['email'] : 'admin@perlengkapansekolah.com';

// Foto disimpan di folder 'uploads/' (pakai S) oleh update_admin_profile.php
$foto_admin         = !empty($data_admin['foto']) ? $data_admin['foto'] : '';

// Menetapkan nama display admin untuk mengatasi error di sidebar
$nama_display_admin = $nama_lengkap_admin;

// Mengambil Statistik Ringkasan Dashboard
$total_barang     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM barang"))['total'] ?? 0;
$total_pelanggan  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM pelanggan"))['total'] ?? 0;
$total_pesanan    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM pesanan"))['total'] ?? 0;
$total_pendapatan = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(jumlah_bayar) as total FROM pembayaran WHERE status_pembayaran='lunas'"))['total'] ?? 0;

$pesanan_terbaru = mysqli_query($conn, "
    SELECT p.*, pl.username
    FROM pesanan p
    LEFT JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan
    ORDER BY p.id_pesanan DESC LIMIT 4
");

$notif_pesanan = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM pesanan WHERE status_pesanan='diproses'"))['total'] ?? 0;
$notif_bayar    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM pembayaran WHERE status_pembayaran='pending'"))['total'] ?? 0;
$total_notif    = $notif_pesanan + $notif_bayar;

// Menyiapkan Data Grafik Batang Bulanan
$chart_labels = [];
$chart_data   = [];
for ($i = 5; $i >= 0; $i--) {
    $bulan = date('Y-m', strtotime("-$i months"));
    $label = date('M', strtotime("-$i months"));
    $chart_labels[] = $label;
    
    $bulan_safe = mysqli_real_escape_string($conn, $bulan);
    $res = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT COALESCE(SUM(jumlah_bayar),0) as total
        FROM pembayaran
        WHERE status_pembayaran='lunas'
        AND DATE_FORMAT(tanggal_pembayaran,'%Y-%m') = '$bulan_safe'
    "));
    $chart_data[] = (int)($res['total'] ?? 0);
}

// Menyiapkan Data Donat Distribusi Status Pesanan
$status_labels = ['Diproses','Selesai','Dibatalkan','Baru'];
$status_colors = ['#FA8231','#43E97B','#FF6584','#6C63FF'];
$status_data   = [];
foreach (['diproses','selesai','dibatalkan','baru'] as $s) {
    $s_safe = mysqli_real_escape_string($conn, $s);
    $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM pesanan WHERE status_pesanan='$s_safe'"));
    $status_data[] = (int)($r['total'] ?? 0);
}

// Menyiapkan Data Top 5 Produk Terlaris
$top_barang = mysqli_query($conn, "
    SELECT b.nama_barang, SUM(dp.jumlah) as terjual
    FROM detail_pesanan dp
    JOIN barang b ON dp.id_barang = b.id_barang
    GROUP BY dp.id_barang
    ORDER BY terjual DESC
    LIMIT 5
");
$top_names = [];
$top_vals  = [];
if ($top_barang) {
    while ($row = mysqli_fetch_assoc($top_barang)) {
        $top_names[] = $row['nama_barang'];
        $top_vals[]  = (int)$row['terjual'];
    }
}
if (empty($top_names)) {
    $top_names = ['Buku Tulis','Pensil','Penggaris','Tas Sekolah','Pulpen'];
    $top_vals  = [0,0,0,0,0];
}

// Deteksi halaman aktif via parameter URL ?page=...
$page_aktif = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title><?php echo $page_aktif == 'pengaturan' ? 'Pengaturan Profil' : 'Dashboard Admin'; ?> - Perlengkapan Sekolah</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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

        /* ── SIDEBAR STYLE ── */
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
            padding: 24px 20px; 
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

        .sidebar-menu { 
            padding: 16px 12px; 
            flex: 1; 
            display: flex;
            flex-direction: column;
            gap: 4px;
            overflow-y: auto;
        }

        .sidebar-menu::-webkit-scrollbar {
            width: 5px;
        }

        .sidebar-menu::-webkit-scrollbar-track {
            background: transparent;
        }

        .sidebar-menu::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }

        .sidebar-menu:hover::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.25);
        }

        .sidebar-menu a { 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            padding: 9px 14px; 
            color: rgba(255,255,255,0.7); 
            text-decoration: none; 
            border-radius: 12px; 
            font-size: 13px; 
            font-weight: 600; 
            transition: all 0.2s; 
        }

        .sidebar-menu a .icon { 
            width: 28px; 
            height: 28px; 
            border-radius: 8px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 14px; 
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

        .sidebar-footer { 
            padding: 16px 12px; 
            border-top: 1px solid rgba(255,255,255,0.08); 
            background: var(--sidebar-bg);
        }

        .sidebar-footer a { 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            padding: 10px 14px; 
            color: #FF6584; 
            text-decoration: none; 
            border-radius: 12px; 
            font-size: 13px; 
            font-weight: 600; 
            transition: all 0.2s; 
        }

        .sidebar-footer a .icon { 
            width: 28px; 
            height: 28px; 
            border-radius: 8px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 14px; 
            background: rgba(255, 101, 132, 0.1); 
            flex-shrink: 0; 
        }

        .sidebar-footer a:hover { 
            color: white; 
            background: var(--danger); 
        }

        .sidebar-footer a:hover .icon {
            background: rgba(255, 255, 255, 0.2);
        }

        /* ── MAIN AREA ── */
        .main { margin-left: 220px; flex: 1; display: flex; flex-direction: column; min-height: 100vh; }

        /* ── TOPBAR ── */
        .topbar { background: white; padding: 0 28px; height: 68px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 50; }
        .topbar-left h1 { font-size: 18px; font-weight: 700; }
        .topbar-left p  { font-size: 12px; color: var(--muted); margin-top: 1px; }
        .topbar-right    { display: flex; align-items: center; gap: 10px; position: relative; }
        .topbar-btn { width: 38px; height: 38px; border-radius: 10px; border: 1.5px solid var(--border); background: white; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 16px; transition: all 0.2s; position: relative; }
        .topbar-btn:hover { background: var(--bg); }
        .notif-badge { position: absolute; top: -5px; right: -5px; width: 18px; height: 18px; background: var(--secondary); color: white; border-radius: 50%; font-size: 10px; font-weight: 700; display: flex; align-items: center; justify-content: center; border: 2px solid white; }
        
        .dropdown { position: absolute; top: 50px; background: white; border-radius: 16px; border: 1.5px solid var(--border); box-shadow: 0 20px 60px rgba(0,0,0,0.15); z-index: 200; display: none; }
        .dropdown.show { display: block; }
        #notif-dropdown { right: 55px; width: 300px; }
        .notif-header { padding: 16px 20px; border-bottom: 1px solid var(--border); font-size: 14px; font-weight: 700; }
        .notif-item { display: flex; align-items: center; gap: 12px; padding: 14px 20px; border-bottom: 1px solid var(--border); text-decoration: none; color: var(--text); transition: all 0.2s; }
        .notif-item:last-child { border-bottom: none; }
        .notif-item:hover { background: var(--bg); }
        .notif-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
        .notif-text { flex: 1; }
        .notif-title { font-size: 13px; font-weight: 600; margin-bottom: 2px; }
        .notif-sub    { font-size: 11.5px; color: var(--muted); margin-top: 2px; }
        .notif-count { font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 20px; }
        .avatar { width: 38px; height: 38px; border-radius: 10px; background: linear-gradient(135deg, var(--primary), var(--secondary)); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 14px; cursor: pointer; }

        /* ── CONTENT ── */
        .content { padding: 28px 32px; flex: 1; }
        .page-section { display: none; animation: fadeUp 0.3s ease both; }
        .page-section.active { display: block; }
        @keyframes fadeUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }

        /* ── GRID LAYOUTS ── */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: white; border-radius: 16px; padding: 20px; border: 1.5px solid var(--border); text-decoration: none; display: block; transition: all 0.3s; position: relative; overflow: hidden; }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; border-radius: 16px 16px 0 0; }
        .stat-card.blue::before   { background: linear-gradient(90deg,#6C63FF,#8B85FF); }
        .stat-card.pink::before   { background: linear-gradient(90deg,#FF6584,#FF8FA3); }
        .stat-card.green::before  { background: linear-gradient(90deg,#43E97B,#38F9D7); }
        .stat-card.orange::before { background: linear-gradient(90deg,#FA8231,#FFC048); }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 12px 32px rgba(108,99,255,0.12); }
        
        .stat-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
        .stat-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .stat-card.blue   .stat-icon { background: #EEF0FF; }
        .stat-card.pink   .stat-icon { background: #FFF0F3; }
        .stat-card.green  .stat-icon { background: #EDFFF5; }
        .stat-card.orange .stat-icon { background: #FFF5EC; }
        .stat-trend { font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 20px; }
        .stat-trend.up    { background: #EDFFF5; color: #22c55e; }
        .stat-number { font-size: 24px; font-weight: 800; color: var(--text); margin-bottom: 3px; }
        .stat-label  { font-size: 12px; color: var(--muted); font-weight: 500; }

        .chart-grid   { display: grid; grid-template-columns: 1fr 340px; gap: 18px; margin-bottom: 20px; }
        .chart-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-bottom: 20px; }

        .section-card { background: white; border-radius: 16px; padding: 22px; border: 1.5px solid var(--border); }
        .section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; }
        .section-title  { font-size: 15px; font-weight: 700; }
        .section-sub    { font-size: 11.5px; color: var(--muted); margin-top: 2px; }
        .section-link   { font-size: 12px; color: var(--primary); text-decoration: none; font-weight: 600; padding: 6px 14px; border-radius: 8px; background: #EEF0FF; transition: all 0.2s; }
        .section-link:hover { background: var(--primary); color: white; }

        .chart-wrap    { position: relative; height: 220px; }
        .chart-wrap-sm { position: relative; height: 180px; }

        .donut-legend { display: flex; flex-direction: column; gap: 8px; margin-top: 16px; }
        .legend-item  { display: flex; align-items: center; gap: 8px; font-size: 12px; }
        .legend-dot   { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
        .legend-label { flex: 1; color: var(--muted); font-weight: 500; }
        .legend-val   { font-weight: 700; font-size: 12px; }

        /* LIST AKTIVITAS & TOP ITEMS */
        .activity-list { display: flex; flex-direction: column; gap: 10px; }
        .activity-item { display: flex; align-items: center; gap: 12px; padding: 11px 14px; border-radius: 12px; background: var(--bg); text-decoration: none; color: var(--text); transition: all 0.2s; }
        .activity-item:hover { background: #EEEEFF; }
        .act-icon   { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
        .act-info   { flex: 1; }
        .act-title  { font-size: 13px; font-weight: 600; margin-bottom: 2px; }
        .act-time   { font-size: 11px; color: var(--muted); }
        .act-status { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; white-space: nowrap; }
        
        .status-proses  { background: #FFF5EC; color: #FA8231; }
        .status-selesai { background: #EDFFF5; color: #22c55e; }
        .status-batal   { background: #FFF0F3; color: #FF6584; }
        .status-baru    { background: #EEF0FF; color: #6C63FF; }

        .top-item { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
        .top-rank { width: 24px; height: 24px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 800; flex-shrink: 0; }
        .top-info { flex: 1; }
        .top-name { font-size: 12.5px; font-weight: 600; margin-bottom: 4px; }
        .top-bar-wrap { height: 5px; background: var(--bg); border-radius: 10px; overflow: hidden; }
        .top-bar { height: 100%; border-radius: 10px; transition: width 1s ease; }
        .top-val { font-size: 12px; font-weight: 700; color: var(--muted); white-space: nowrap; }

        /* ── SETTINGS PAGE LAYOUT ── */
        .settings-layout { display: flex; gap: 24px; align-items: flex-start; }
        .settings-nav { width: 220px; flex-shrink: 0; background: white; border: 1.5px solid var(--border); border-radius: 16px; padding: 16px; position: sticky; top: 88px; }
        .settings-nav .nav-header { font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: 1px; padding: 0 8px; margin-bottom: 12px; }
        .settings-nav .nav-item { display: flex; align-items: center; gap: 10px; padding: 10px 12px; color: var(--text); text-decoration: none; border-radius: 10px; font-size: 13px; font-weight: 600; transition: all 0.2s; margin-bottom: 4px; }
        .settings-nav .nav-item:hover { background: var(--bg); color: var(--primary); }
        .settings-nav .nav-item.active { background: linear-gradient(135deg, var(--primary), var(--primary-2)); color: white; }
        
        .settings-content { flex: 1; }
        .tab { display: none; }
        .tab.active { display: block; animation: fadeUp 0.3s ease both; }
        
        .tab-header { background: white; border-radius: 16px; border: 1.5px solid var(--border); padding: 20px 24px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; }
        .tab-title { font-size: 18px; font-weight: 800; }
        .tab-desc  { font-size: 13px; color: var(--muted); margin-top: 2px; }
        
        .btn-save { padding: 10px 22px; background: linear-gradient(135deg, var(--primary), var(--primary-2)); color: white; border: none; border-radius: 10px; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; }
        .btn-save:hover { opacity: 0.88; transform: translateY(-1px); }
        .btn-cancel { padding: 10px 22px; background: white; color: var(--muted); border: 1.5px solid var(--border); border-radius: 10px; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s; margin-right: 8px; }
        .btn-cancel:hover { background: var(--bg); }
        
        .settings-card { background: white; border-radius: 16px; border: 1.5px solid var(--border); padding: 24px; margin-bottom: 18px; }
        .settings-card-title { font-size: 15px; font-weight: 700; margin-bottom: 20px; }
        
        .profile-upload-wrap { display: flex; align-items: center; gap: 20px; margin-bottom: 24px; padding: 20px; background: var(--bg); border-radius: 14px; }
        .profile-avatar-big { width: 72px; height: 72px; border-radius: 18px; background: linear-gradient(135deg, var(--primary), var(--secondary)); display: flex; align-items: center; justify-content: center; color: white; font-weight: 800; font-size: 24px; flex-shrink: 0; }
        .profile-upload-info strong { font-size: 15px; font-weight: 700; display: block; margin-bottom: 2px; }
        .profile-upload-info p { font-size: 12px; color: var(--muted); margin-bottom: 12px; }
        
        .btn-upload { padding: 8px 16px; border-radius: 8px; background: white; border: 1.5px solid var(--border); font-size: 12px; font-weight: 700; cursor: pointer; font-family: 'Plus Jakarta Sans', sans-serif; transition: all 0.2s; margin-right: 8px; }
        .btn-upload:hover { border-color: var(--primary); color: var(--primary); }
        
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 12px; font-weight: 700; color: var(--muted); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px 14px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 13px; font-family: 'Plus Jakarta Sans', sans-serif; outline: none; transition: border 0.2s; background: white; color: var(--text); }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: var(--primary); }
        .form-group input[readonly] { background: #F8F9FF; color: var(--muted); cursor: not-allowed; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-logo">
            <h2>🎒 Perlengkapan Sekolah</h2>
            <span class="admin-meta">Admin: <span style="color:#8B85FF; font-weight:600;"><?php echo htmlspecialchars($nama_display_admin); ?></span></span> 
        </div>
        
        <div class="sidebar-menu">
            <a href="dashboard_admin.php" class="<?php echo $page_aktif == 'dashboard' ? 'active' : ''; ?>">
                <div class="icon">🏠</div> Dashboard
            </a>
            <a href="data_barang.php">
                <div class="icon">📦</div> Data Barang
            </a>
            <a href="pesanan.php">
                <div class="icon">🛒</div> Pesanan
            </a>
            <a href="pembayaran.php">
                <div class="icon">💳</div> Pembayaran
            </a>
            <a href="pelanggan.php">
                <div class="icon">👥</div> Pelanggan
            </a>
            <a href="laporan.php">
                <div class="icon">📊</div> Laporan
            </a>
            <a href="voucher.php" class="<?php echo $page_aktif == 'voucher' ? 'active' : ''; ?>">
                <div class="icon">🎟️</div> Voucher
            </a>
            <a href="pengaturan.php">
                 <div class="icon">⚙️</div> Pengaturan
            </a>
        </div>
        
        <div class="sidebar-footer">
            <a href="logout.php">
                <div class="icon">🚪</div> Keluar
            </a>
        </div>
    </div>

    <div class="main">
        
        <div class="topbar">
            <div class="topbar-left">
                <h1 id="page-title">
                    <?php echo $page_aktif == 'pengaturan' ? 'Pengaturan Profil' : 'Dashboard'; ?>
                </h1>
                <p><?php echo date('l, d F Y'); ?></p>
            </div>
            <div class="topbar-right">
                <div class="topbar-btn" onclick="toggleNotif()" id="notif-btn">
                    🔔
                    <?php if ($total_notif > 0) { ?>
                    <span class="notif-badge"><?php echo $total_notif; ?></span>
                    <?php } ?>
                </div>
                
                <div class="dropdown" id="notif-dropdown">
                    <div class="notif-header">🔔 Notifikasi (<?php echo $total_notif; ?>)</div>
                    <a href="pesanan.php" class="notif-item">
                        <div class="notif-icon" style="background:#FFF5EC;">🛒</div>
                        <div class="notif-text">
                            <div class="notif-title">Pesanan Diproses</div>
                            <div class="notif-sub">Menunggu konfirmasi admin</div>
                        </div>
                        <span class="notif-count" style="background:#FFF5EC;color:#FA8231;"><?php echo $notif_pesanan; ?></span>
                    </a>
                    <a href="pembayaran.php" class="notif-item">
                        <div class="notif-icon" style="background:#EEF0FF;">💳</div>
                        <div class="notif-text">
                            <div class="notif-title">Pembayaran Pending</div>
                            <div class="notif-sub">Menunggu konfirmasi</div>
                        </div>
                        <span class="notif-count" style="background:#EEF0FF;color:#6C63FF;"><?php echo $notif_bayar; ?></span>
                    </a>
                </div>
                <a href="pengaturan.php" class="avatar" style="overflow:hidden; text-decoration:none;"><?php if (!empty($foto_admin)): ?><img src="uploads/<?php echo htmlspecialchars($foto_admin); ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:10px; display:block;"><?php else: echo strtoupper(substr($nama_lengkap_admin, 0, 1)); endif; ?></a>
            </div>
        </div>

        <div class="content">
            
            <div class="page-section <?php echo $page_aktif == 'dashboard' ? 'active' : ''; ?>" id="page-dashboard">
                
                <div class="stats-grid">
                    <a href="data_barang.php" class="stat-card blue">
                        <div class="stat-top"><div class="stat-icon">📦</div><span class="stat-trend up">Barang</span></div>
                        <div class="stat-number"><?php echo $total_barang; ?></div>
                        <div class="stat-label">Total Barang</div>
                    </a>
                    <a href="pelanggan.php" class="stat-card pink">
                        <div class="stat-top"><div class="stat-icon">👥</div><span class="stat-trend up">Pelanggan</span></div>
                        <div class="stat-number"><?php echo $total_pelanggan; ?></div>
                        <div class="stat-label">Total Pelanggan</div>
                    </a>
                    <a href="pesanan.php" class="stat-card green">
                        <div class="stat-top"><div class="stat-icon">🛒</div><span class="stat-trend up">Pesanan</span></div>
                        <div class="stat-number"><?php echo $total_pesanan; ?></div>
                        <div class="stat-label">Total Pesanan</div>
                    </a>
                    <a href="laporan.php" class="stat-card orange">
                        <div class="stat-top"><div class="stat-icon">💰</div><span class="stat-trend up">Pendapatan</span></div>
                        <div class="stat-number" style="font-size:16px;line-height:1.3;">Rp <?php echo number_format($total_pendapatan,0,',','.'); ?></div>
                        <div class="stat-label">Total Pendapatan</div>
                    </a>
                </div>

                <div class="chart-grid">
                    <div class="section-card">
                        <div class="section-header">
                            <div>
                                <div class="section-title">📈 Pendapatan 6 Bulan Terakhir</div>
                                <div class="section-sub">Berdasarkan pembayaran lunas</div>
                            </div>
                            <a href="laporan.php" class="section-link">Lihat Laporan →</a>
                        </div>
                        <div class="chart-wrap">
                            <canvas id="chartPendapatan"></canvas>
                        </div>
                    </div>

                    <div class="section-card">
                        <div class="section-header">
                            <div>
                                <div class="section-title">🍩 Status Pesanan</div>
                                <div class="section-sub">Distribusi semua pesanan</div>
                            </div>
                        </div>
                        <div class="chart-wrap-sm">
                            <canvas id="chartStatus"></canvas>
                        </div>
                        <div class="donut-legend">
                            <?php foreach ($status_labels as $i => $label) { ?>
                            <div class="legend-item">
                                <div class="legend-dot" style="background:<?php echo $status_colors[$i]; ?>;"></div>
                                <span class="legend-label"><?php echo $label; ?></span>
                                <span class="legend-val"><?php echo $status_data[$i]; ?></span>
                            </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>

                <div class="chart-grid-2">
                    <div class="section-card">
                        <div class="section-header">
                            <div>
                                <div class="section-title">🏆 Top 5 Barang Terlaris</div>
                                <div class="section-sub">Berdasarkan jumlah terjual</div>
                            </div>
                            <a href="data_barang.php" class="section-link">Semua →</a>
                        </div>
                        <?php
                        $rank_colors = ['#6C63FF','#FF6584','#FA8231','#43E97B','#8B85FF'];
                        $max_val = max(array_merge($top_vals, [1]));
                        foreach ($top_names as $i => $name) {
                            $pct = $max_val > 0 ? round(($top_vals[$i] / $max_val) * 100) : 0;
                        ?>
                        <div class="top-item">
                            <div class="top-rank" style="background:<?php echo $rank_colors[$i]; ?>22; color:<?php echo $rank_colors[$i]; ?>;">
                                <?php echo $i+1; ?>
                            </div>
                            <div class="top-info">
                                <div class="top-name"><?php echo htmlspecialchars($name); ?></div>
                                <div class="top-bar-wrap">
                                    <div class="top-bar" style="width:<?php echo $pct; ?>%; background:<?php echo $rank_colors[$i]; ?>;"></div>
                                </div>
                            </div>
                            <div class="top-val"><?php echo $top_vals[$i]; ?> pcs</div>
                        </div>
                        <?php } ?>
                    </div>

                    <div class="section-card">
                        <div class="section-header">
                            <div>
                                <div class="section-title">🕐 Aktivitas Terbaru</div>
                                <div class="section-sub">4 pesanan terakhir masuk</div>
                            </div>
                            <a href="pesanan.php" class="section-link">Lihat Semua →</a>
                        </div>
                        <div class="activity-list">
                        <?php
                        if ($pesanan_terbaru && mysqli_num_rows($pesanan_terbaru) > 0) {
                            while ($row_pesanan = mysqli_fetch_assoc($pesanan_terbaru)) {
                                $status = $row_pesanan['status_pesanan'];
                                $status_class = 'status-baru';
                                if ($status == 'diproses') $status_class = 'status-proses';
                                if ($status == 'selesai')  $status_class = 'status-selesai';
                                if ($status == 'dibatalkan') $status_class = 'status-batal';
                        ?>
                            <a href="pesanan.php" class="activity-item">
                                <div class="act-icon" style="background:#EEF0FF;">🛒</div>
                                <div class="act-info">
                                    <div class="act-title">Pesanan #<?php echo $row_pesanan['id_pesanan']; ?> - <?php echo htmlspecialchars($row_pesanan['username']); ?></div>
                                    <div class="act-time">Rp <?php echo number_format($row_pesanan['total_harga'], 0, ',', '.'); ?></div>
                                </div>
                                <span class="act-status <?php echo $status_class; ?>"><?php echo ucfirst($status); ?></span>
                            </a>
                        <?php 
                            }
                        } else {
                        ?>
                            <div style="text-align:center; padding:20px; color:var(--muted); font-size:13px;">Tidak ada aktivitas pesanan terbaru.</div>
                        <?php } ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="page-section <?php echo $page_aktif == 'pengaturan' ? 'active' : ''; ?>" id="page-pengaturan">
                <div class="settings-layout">
                    
                    <div class="settings-nav">
                        <div class="nav-header">Pengaturan</div>
                        <a href="#" class="nav-item active">👤 Profil Saya</a>
                        <a href="dashboard_admin.php" class="nav-item">🏠 Kembali Utama</a>
                    </div>
                    
                    <div class="settings-content">
                        <form action="update_profil.php" method="POST" enctype="multipart/form-data">
                            <div class="tab-header">
                                <div>
                                    <div class="tab-title">Profil Admin</div>
                                    <div class="tab-desc">Kelola identitas akun dan sinkronisasi data global.</div>
                                </div>
                                <div style="display: flex; gap: 8px;">
                                    <a href="dashboard_admin.php" class="btn-cancel" style="text-decoration:none; display:inline-block; line-height:1.4;">Batal</a>
                                    <button type="submit" class="btn-save">Simpan Perubahan</button>
                                </div>
                            </div>
                            
                            <div class="settings-card">
                                <div class="settings-card-title">Informasi Pribadi</div>
                                
                                <div class="profile-upload-wrap">
                                    <div class="profile-avatar-big">
                                        <?php if (!empty($foto_admin)): ?>
                                            <img src="uploads/<?php echo htmlspecialchars($foto_admin); ?>" alt="Foto Admin" style="width:100%;height:100%;object-fit:cover;border-radius:18px;">
                                        <?php else: ?>
                                            <?php echo strtoupper(substr($nama_lengkap_admin, 0, 1)); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="profile-upload-info">
                                        <strong>Foto Profil</strong>
                                        <p>Upload foto profil Anda. Format JPG, PNG, maks 2MB.</p>
                                        <input type="file" name="foto" id="input-foto" accept="image/*" style="display:none;" onchange="previewFoto(this)">
                                        <button type="button" class="btn-upload" onclick="document.getElementById('input-foto').click()">📷 Pilih Foto</button>
                                        <?php if (!empty($foto_admin)): ?>
                                        <button type="button" class="btn-upload" style="color:#FF6584;border-color:#FF6584;" onclick="if(confirm('Hapus foto profil?')) window.location='hapus_foto_admin.php'">🗑 Hapus Foto</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Username Admin (Sesi Akun)</label>
                                    <input type="text" value="<?php echo htmlspecialchars($user); ?>" readonly>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Nama Lengkap Baru</label>
                                        <input type="text" name="nama" value="<?php echo htmlspecialchars($nama_lengkap_admin); ?>" placeholder="Masukkan nama lengkap..." required>
                                    </div>
                                    <div class="form-group">
                                        <label>Email Korespondensi</label>
                                        <input type="email" name="email" value="<?php echo htmlspecialchars($email_admin); ?>" placeholder="admin@example.com" required>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                </div>
            </div>

        </div> </div> <script>
        // Preview foto sebelum disimpan
        function previewFoto(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const avatarBig = document.querySelector('.profile-avatar-big');
                    avatarBig.innerHTML = '<img src="' + e.target.result + '" alt="Preview" style="width:100%;height:100%;object-fit:cover;border-radius:18px;">';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Toggle Dropdown Notifikasi
        function toggleNotif() {
            document.getElementById('notif-dropdown').classList.toggle('show');
        }

        // Close dropdown dengan proteksi deteksi target container yang solid
        window.onclick = function(event) {
            if (!document.getElementById('notif-btn').contains(event.target)) {
                var dropdowns = document.getElementsByClassName("dropdown");
                for (var i = 0; i < dropdowns.length; i++) {
                    var openDropdown = dropdowns[i];
                    if (openDropdown.classList.contains('show')) {
                        openDropdown.classList.remove('show');
                    }
                }
            }
        }

        // Inisialisasi ChartJS hanya jika halaman dashboard yang aktif diakses
        <?php if ($page_aktif == 'dashboard') { ?>
        // 1. Grafik Batang Pendapatan (6 Bulan Terakhir)
        const ctxBar = document.getElementById('chartPendapatan').getContext('2d');
        new Chart(ctxBar, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'Pendapatan (Rp)',
                    data: <?php echo json_encode($chart_data); ?>,
                    backgroundColor: '#6C63FF',
                    borderRadius: 6,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: { 
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                if (value >= 1000000) return 'Rp ' + (value / 1000000) + 'jt';
                                if (value >= 1000) return 'Rp ' + (value / 1000) + 'rb';
                                return 'Rp ' + value;
                            }
                        }
                    }
                }
            }
        });

        // 2. Grafik Donut Status Pesanan
        const ctxDonut = document.getElementById('chartStatus').getContext('2d');
        new Chart(ctxDonut, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($status_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($status_data); ?>,
                    backgroundColor: <?php echo json_encode($status_colors); ?>,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                cutout: '75%'
            }
        });
        <?php } ?>
    </script>
</body>
</html>