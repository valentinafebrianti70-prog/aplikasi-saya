<?php
include "koneksi.php";
session_start();

// Simulasi login jika belum ada session (untuk testing)
if (!isset($_SESSION['login']) || $_SESSION['role'] != "pelanggan") {
    // Untuk testing langsung, aktifkan baris di bawah ini:
    // $_SESSION['login'] = true; $_SESSION['role'] = 'pelanggan'; $_SESSION['user'] = 'Demo User';
    header("Location: login.php");
    exit;
}

$user = isset($_SESSION['user']) ? $_SESSION['user'] : "Pelanggan";
$avatar = strtoupper(substr($user, 0, 2));

// ── AMBIL ID PELANGGAN ──
$data_pelanggan = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT * FROM pelanggan WHERE username='" . mysqli_real_escape_string($conn, $user) . "'"
));
$id_pelanggan = $data_pelanggan['id_pelanggan'] ?? 0;
$display_name = !empty($data_pelanggan['nama_lengkap']) ? $data_pelanggan['nama_lengkap'] : $user;
$display_email = $data_pelanggan['email'] ?? 'Belum diisi';
$display_telp  = $data_pelanggan['no_hp'] ?? 'Belum diisi';
$display_alamat= $data_pelanggan['alamat'] ?? 'Belum diisi';
$profile_image = '';
if (!empty($data_pelanggan['foto_profil']) && file_exists('upload/' . $data_pelanggan['foto_profil'])) {
    $profile_image = $data_pelanggan['foto_profil'];
}

// ── STATS DARI DATABASE ──
$pesanan_aktif = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as total FROM pesanan
     WHERE id_pelanggan='$id_pelanggan'
     AND status_pesanan NOT IN ('selesai','dibatalkan')"))['total'] ?? 0;

$item_keranjang = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT SUM(jumlah) as total FROM keranjang
     WHERE id_pelanggan='$id_pelanggan'"))['total'] ?? 0;

$pesanan_selesai = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as total FROM pesanan
     WHERE id_pelanggan='$id_pelanggan'
     AND status_pesanan='selesai'"))['total'] ?? 0;

// ── PRODUK TERBARU DARI DATABASE ──
$produk_terbaru = [];
$q_produk = mysqli_query($conn, "SELECT * FROM barang ORDER BY id_barang DESC LIMIT 4");
while ($row = mysqli_fetch_assoc($q_produk)) {
    $produk_terbaru[] = [
        'id'    => $row['id_barang'],
        'nama'  => $row['nama_barang'],
        'harga' => $row['harga'],
        'stok'  => $row['stok'] > 0 ? 'Tersedia' : 'Habis',
        'gambar'=> $row['gambar'],
    ];
}

// ── SEMUA PRODUK UNTUK HALAMAN PRODUK ──
$semua_produk = [];
$q_semua = mysqli_query($conn, "SELECT * FROM barang ORDER BY id_barang DESC");
while ($row = mysqli_fetch_assoc($q_semua)) {
    $semua_produk[] = [
        'id'       => $row['id_barang'],
        'nama'     => $row['nama_barang'],
        'harga'    => $row['harga'],
        'kategori' => $row['kategori'] ?? 'Umum',
        'stok'     => $row['stok'] > 0,
        'gambar'   => $row['gambar'],
        'deskripsi'=> $row['deskripsi'] ?? '',
    ];
}

// ── RIWAYAT PESANAN DARI DATABASE ──
$riwayat_pesanan = [];
$q_pesanan = mysqli_query($conn,
    "SELECT * FROM pesanan WHERE id_pelanggan='$id_pelanggan' ORDER BY id_pesanan DESC");
while ($row = mysqli_fetch_assoc($q_pesanan)) {
    $status = $row['status_pesanan'];
    $cls    = $status == 'diproses'   ? 'status-proses'  :
             ($status == 'dikirim'    ? 'status-kirim'   :
             ($status == 'selesai'    ? 'status-selesai' : 'status-batal'));
    $riwayat_pesanan[] = [
        'id'      => $row['id_pesanan'],
        'tanggal' => date('d M Y', strtotime($row['tanggal_pesanan'])),
        'status'  => ucfirst($status),
        'total'   => $row['total_harga'],
        'cls'     => $cls,
    ];
}

// Tentukan halaman aktif dari parameter URL
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Pelanggan - Perlengkapan Sekolah</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* ── RESET & ROOT ── */
* { margin: 0; padding: 0; box-sizing: border-box; }
:root {
    --primary:    #6C63FF;
    --primary-2:  #8B85FF;
    --secondary:  #FF6584;
    --accent:     #43E97B;
    --bg:         #F0F2FF;
    --card:       #FFFFFF;
    --sidebar-bg: #1E1B4B;
    --text:       #1E1B4B;
    --muted:      #8B8FAD;
    --border:     #E8EAFF;
    --danger:     #FF6584;
    --success:    #43E97B;
    --warning:    #FFB347;
}
body {
    font-family: 'Plus Jakarta Sans', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    display: flex;
}

/* ── SIDEBAR ── */
.sidebar {
    width: 220px;
    min-height: 100vh;
    background: var(--sidebar-bg);
    display: flex;
    flex-direction: column;
    position: fixed;
    top: 0; left: 0;
    z-index: 100;
    padding-bottom: 12px;
    overflow-y: auto;
    transition: transform 0.3s ease;
}
.sidebar-logo {
    padding: 24px 18px 16px;
    border-bottom: 1px solid rgba(255,255,255,0.08);
}
.sidebar-logo .logo-icon {
    width: 40px; height: 40px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px;
    margin-bottom: 10px;
    box-shadow: 0 4px 15px rgba(108,99,255,0.4);
}
.sidebar-logo h2 { color: white; font-size: 13px; font-weight: 700; line-height: 1.3; }
.sidebar-logo span { color: var(--primary-2); font-size: 10px; font-weight: 500; }
.sidebar-menu { padding: 14px 10px; flex: 1; }
.menu-label {
    color: rgba(255,255,255,0.35);
    font-size: 9px; font-weight: 700;
    letter-spacing: 1.5px; text-transform: uppercase;
    padding: 0 10px; margin: 14px 0 6px;
}
.sidebar-menu a {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 12px;
    color: rgba(255,255,255,0.6);
    text-decoration: none;
    border-radius: 10px; font-size: 12.5px; font-weight: 500;
    transition: all 0.2s; margin-bottom: 2px;
}
.sidebar-menu a .icon {
    width: 30px; height: 30px;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px;
    background: rgba(255,255,255,0.05);
    transition: all 0.2s; flex-shrink: 0;
}
.sidebar-menu a:hover, .sidebar-menu a.active {
    color: white;
    background: rgba(108,99,255,0.25);
}
.sidebar-menu a.active .icon {
    background: linear-gradient(135deg, var(--primary), var(--primary-2));
    box-shadow: 0 4px 12px rgba(108,99,255,0.4);
}
.sidebar-menu a:hover .icon { background: rgba(108,99,255,0.3); }
.badge-count {
    margin-left: auto;
    background: var(--secondary);
    color: white;
    font-size: 10px; font-weight: 700;
    padding: 2px 7px; border-radius: 20px; min-width: 20px;
    text-align: center;
}
.sidebar-footer {
    padding: 10px;
    border-top: 1px solid rgba(255,255,255,0.08);
    margin-top: auto;
}
.sidebar-footer a {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 12px;
    color: rgba(255,255,255,0.5);
    text-decoration: none; border-radius: 10px;
    font-size: 12px; font-weight: 500; transition: all 0.2s;
}
.sidebar-footer a:hover { color: var(--danger); background: rgba(255,101,132,0.1); }

/* ── MAIN ── */
.main { margin-left: 240px; flex: 1; display: flex; flex-direction: column; min-height: 100vh; }

/* ── TOPBAR ── */
.topbar {
    background: white;
    padding: 0 32px; height: 68px;
    display: flex; align-items: center; justify-content: space-between;
    border-bottom: 1px solid var(--border);
    position: sticky; top: 0; z-index: 50;
}
.topbar-left h1 { font-size: 18px; font-weight: 700; }
.topbar-left p { font-size: 12px; color: var(--muted); margin-top: 1px; }
.topbar-right { display: flex; align-items: center; gap: 12px; }
.topbar-btn {
    width: 38px; height: 38px; border-radius: 10px;
    border: 1.5px solid var(--border); background: white;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: 16px; transition: all 0.2s;
    text-decoration: none; position: relative;
}
.topbar-btn:hover { background: var(--bg); }
.topbar-btn .notif-dot {
    position: absolute; top: 4px; right: 4px;
    width: 8px; height: 8px; border-radius: 50%;
    background: var(--danger); border: 2px solid white;
}
.avatar {
    width: 38px; height: 38px; border-radius: 10px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    display: flex; align-items: center; justify-content: center;
    color: white; font-weight: 700; font-size: 13px;
    overflow: hidden;
}
.avatar img {
    width: 100%; height: 100%; object-fit: cover;
}
.profile-avatar-big {
    width: 68px; height: 68px; border-radius: 16px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    display: flex; align-items: center; justify-content: center;
    color: white; font-weight: 800; font-size: 22px; overflow: hidden;
}
.profile-avatar-big img {
    width: 100%; height: 100%; object-fit: cover;
}
.btn-logout {
    display: flex; align-items: center; gap: 6px;
    padding: 8px 16px;
    background: linear-gradient(135deg, #FF6584, #FF8FA3);
    color: white; border-radius: 10px;
    text-decoration: none; font-size: 13px; font-weight: 600;
    transition: all 0.2s; border: none; cursor: pointer;
}
.btn-logout:hover { opacity: 0.85; transform: translateY(-1px); }

/* ── CONTENT ── */
.content { padding: 28px 32px; flex: 1; }

/* ── PAGE SECTION ── */
.page-section { display: none; animation: fadeUp 0.35s ease both; }
.page-section.active { display: block; }

/* ── WELCOME BANNER ── */
.welcome-banner {
    background: linear-gradient(135deg, #FF6584 0%, #FF8FA3 50%, #FFB3C1 100%);
    border-radius: 20px; padding: 28px 32px;
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 28px; position: relative; overflow: hidden;
}
.welcome-banner::before {
    content: ''; position: absolute;
    top: -40px; right: -40px;
    width: 200px; height: 200px;
    border-radius: 50%; background: rgba(255,255,255,0.08);
}
.welcome-banner::after {
    content: ''; position: absolute;
    bottom: -60px; right: 100px;
    width: 150px; height: 150px;
    border-radius: 50%; background: rgba(255,255,255,0.05);
}
.welcome-text h2 { color: white; font-size: 22px; font-weight: 800; margin-bottom: 6px; }
.welcome-text p { color: rgba(255,255,255,0.85); font-size: 13.5px; line-height: 1.6; max-width: 380px; }
.welcome-emoji {
    font-size: 60px; position: relative; z-index: 1;
    animation: float 3s ease-in-out infinite;
}
@keyframes float {
    0%, 100% { transform: translateY(0); }
    50%       { transform: translateY(-8px); }
}

/* ── STATS GRID ── */
.stats-grid {
    display: grid; grid-template-columns: repeat(3, 1fr);
    gap: 18px; margin-bottom: 28px;
}
.stat-card {
    background: white; border-radius: 16px; padding: 20px;
    border: 1.5px solid var(--border);
    transition: all 0.3s; cursor: pointer;
    position: relative; overflow: hidden;
}
.stat-card::before {
    content: ''; position: absolute;
    top: 0; left: 0; right: 0; height: 3px; border-radius: 16px 16px 0 0;
}
.stat-card.blue::before   { background: linear-gradient(90deg, #6C63FF, #8B85FF); }
.stat-card.pink::before   { background: linear-gradient(90deg, #FF6584, #FF8FA3); }
.stat-card.green::before  { background: linear-gradient(90deg, #43E97B, #38F9D7); }
.stat-card:hover { transform: translateY(-4px); box-shadow: 0 12px 32px rgba(108,99,255,0.12); }
.stat-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
.stat-icon {
    width: 44px; height: 44px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center; font-size: 20px;
}
.stat-card.blue  .stat-icon { background: #EEF0FF; }
.stat-card.pink  .stat-icon { background: #FFF0F3; }
.stat-card.green .stat-icon { background: #EDFFF5; }
.stat-number { font-size: 28px; font-weight: 800; color: var(--text); margin-bottom: 3px; }
.stat-label  { font-size: 12.5px; color: var(--muted); font-weight: 500; }

/* ── BOTTOM GRID ── */
.bottom-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
.section-card {
    background: white; border-radius: 16px; padding: 22px;
    border: 1.5px solid var(--border);
}
.section-header {
    display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px;
}
.section-title { font-size: 15px; font-weight: 700; color: var(--text); }
.section-link { font-size: 12px; color: var(--primary); text-decoration: none; font-weight: 600; }
.section-link:hover { text-decoration: underline; }

/* ── MENU CEPAT ── */
.menu-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.menu-item {
    display: flex; flex-direction: column; align-items: center;
    justify-content: center; padding: 20px 16px;
    border-radius: 14px; text-decoration: none;
    border: 1.5px solid var(--border); transition: all 0.25s; gap: 10px;
    cursor: pointer; background: white;
}
.menu-item:hover {
    border-color: var(--primary); background: #F5F4FF;
    transform: translateY(-2px); box-shadow: 0 8px 20px rgba(108,99,255,0.1);
}
.menu-item .m-icon {
    width: 48px; height: 48px; border-radius: 14px;
    display: flex; align-items: center; justify-content: center; font-size: 22px;
}
.menu-item span { font-size: 12.5px; font-weight: 600; color: var(--text); text-align: center; }

/* ── PRODUK ── */
.produk-list { display: flex; flex-direction: column; gap: 12px; }
.produk-item {
    display: flex; align-items: center; gap: 12px;
    padding: 12px; border-radius: 12px; background: var(--bg); transition: all 0.2s;
}
.produk-item:hover { background: #EEEEFF; }
.produk-icon {
    width: 42px; height: 42px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0;
}
.produk-info { flex: 1; }
.produk-nama  { font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 2px; }
.produk-harga { font-size: 12px; color: var(--primary); font-weight: 700; }
.produk-stok-habis { font-size: 11px; color: var(--danger); font-weight: 600; }
.btn-beli {
    font-size: 11px; font-weight: 600; padding: 6px 14px;
    border-radius: 20px;
    background: linear-gradient(135deg, var(--primary), var(--primary-2));
    color: white; text-decoration: none; border: none; cursor: pointer;
    transition: all 0.2s; white-space: nowrap;
}
.btn-beli:hover { opacity: 0.85; transform: scale(1.05); }
.btn-beli:disabled, .btn-beli.disabled {
    background: #ccc; cursor: not-allowed; transform: none; opacity: 1;
}

/* ── HALAMAN: PRODUK ── */
.produk-header {
    display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;
}
.search-bar {
    display: flex; align-items: center; gap: 10px;
    background: white; border: 1.5px solid var(--border);
    border-radius: 12px; padding: 10px 16px; flex: 1; max-width: 360px;
}
.search-bar input {
    border: none; outline: none; font-size: 13px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    width: 100%; background: transparent;
}
.filter-select {
    padding: 10px 14px; border: 1.5px solid var(--border);
    border-radius: 12px; font-size: 13px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    outline: none; background: white; cursor: pointer;
}
.produk-grid {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px;
}
.produk-card {
    background: white; border-radius: 16px;
    border: 1.5px solid var(--border); overflow: hidden;
    transition: all 0.3s;
}
.produk-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 30px rgba(108,99,255,0.12);
}
.produk-card-img {
    width: 110px; height: 110px;
    display: flex; align-items: center;
    justify-content: center; font-size: 50px;
    margin: 0 auto; /* center horizontally */
}
.produk-card-body { padding: 14px 16px 16px; }
.produk-card-nama { font-size: 13px; font-weight: 700; color: var(--text); margin-bottom: 4px; }
.produk-card-kategori { font-size: 11px; color: var(--muted); margin-bottom: 8px; }
.produk-card-footer {
    display: flex; align-items: center; justify-content: space-between;
}
.produk-card-harga { font-size: 14px; font-weight: 800; color: var(--primary); }
.badge-stok {
    font-size: 10px; padding: 3px 8px; border-radius: 20px; font-weight: 600;
}
.badge-tersedia { background: #EDFFF5; color: #0a7a3e; }
.badge-habis    { background: #FFF0F3; color: var(--danger); }

/* ── HALAMAN: KERANJANG ── */
.keranjang-wrap { display: grid; grid-template-columns: 1fr 340px; gap: 20px; }
.keranjang-list { display: flex; flex-direction: column; gap: 14px; }
.keranjang-item {
    background: white; border-radius: 16px; border: 1.5px solid var(--border);
    padding: 18px; display: flex; align-items: center; gap: 16px;
}
.keranjang-ikon {
    width: 56px; height: 56px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 28px; flex-shrink: 0;
}
.keranjang-info { flex: 1; }
.keranjang-nama  { font-size: 14px; font-weight: 700; margin-bottom: 4px; }
.keranjang-harga { font-size: 13px; color: var(--primary); font-weight: 700; margin-bottom: 10px; }
.qty-wrap { display: flex; align-items: center; gap: 10px; }
.qty-btn {
    width: 30px; height: 30px; border-radius: 8px;
    border: 1.5px solid var(--border); background: white;
    font-size: 16px; cursor: pointer; display: flex;
    align-items: center; justify-content: center;
    transition: all 0.2s; font-weight: 700;
}
.qty-btn:hover { background: var(--bg); border-color: var(--primary); }
.qty-num { font-size: 15px; font-weight: 700; min-width: 24px; text-align: center; }
.btn-hapus {
    background: none; border: 1.5px solid #FFD0DA; color: var(--danger);
    padding: 7px 14px; border-radius: 10px; font-size: 12px; font-weight: 600;
    cursor: pointer; transition: all 0.2s; font-family: 'Plus Jakarta Sans', sans-serif;
}
.btn-hapus:hover { background: #FFF0F3; }
.ringkasan-card {
    background: white; border-radius: 16px; border: 1.5px solid var(--border);
    padding: 22px; position: sticky; top: 90px; height: fit-content;
}
.ringkasan-title { font-size: 15px; font-weight: 700; margin-bottom: 18px; }
.ringkasan-row {
    display: flex; justify-content: space-between;
    font-size: 13px; margin-bottom: 12px; color: var(--muted);
}
.ringkasan-row.total {
    font-size: 15px; font-weight: 800;
    color: var(--text); border-top: 1.5px solid var(--border);
    padding-top: 14px; margin-top: 6px;
}
.btn-checkout {
    width: 100%; padding: 14px; margin-top: 18px;
    background: linear-gradient(135deg, var(--primary), var(--primary-2));
    color: white; border: none; border-radius: 14px;
    font-size: 14px; font-weight: 700; cursor: pointer;
    font-family: 'Plus Jakarta Sans', sans-serif;
    transition: all 0.2s;
}
.btn-checkout:hover { opacity: 0.88; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(108,99,255,0.3); }

/* ── HALAMAN: PESANAN ── */
.pesanan-table-wrap {
    background: white; border-radius: 16px;
    border: 1.5px solid var(--border); overflow: hidden;
}
.pesanan-table { width: 100%; border-collapse: collapse; }
.pesanan-table th {
    background: var(--bg); padding: 14px 18px;
    font-size: 12px; font-weight: 700;
    color: var(--muted); text-align: left;
    text-transform: uppercase; letter-spacing: 0.5px;
    border-bottom: 1.5px solid var(--border);
}
.pesanan-table td {
    padding: 16px 18px; font-size: 13px; font-weight: 500;
    border-bottom: 1px solid var(--border); color: var(--text);
    vertical-align: middle;
}
.pesanan-table tr:last-child td { border-bottom: none; }
.pesanan-table tr:hover td { background: #FAFAFF; }
.status-badge {
    display: inline-block; padding: 4px 12px;
    border-radius: 20px; font-size: 11px; font-weight: 700;
}
.status-proses  { background: #FFF5EC; color: #e06c00; }
.status-kirim   { background: #EEF0FF; color: var(--primary); }
.status-selesai { background: #EDFFF5; color: #0a7a3e; }
.status-batal   { background: #FFF0F3; color: var(--danger); }
.btn-detail {
    padding: 6px 14px; border-radius: 8px;
    border: 1.5px solid var(--border); background: white;
    font-size: 11px; font-weight: 600; color: var(--primary);
    cursor: pointer; transition: all 0.2s;
    font-family: 'Plus Jakarta Sans', sans-serif;
}
.btn-detail:hover { background: #EEF0FF; border-color: var(--primary); }

/* ── HALAMAN: PEMBAYARAN ── */
.pembayaran-wrap { display: grid; grid-template-columns: 1fr 340px; gap: 20px; }
.metode-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px; }
.metode-item {
    border: 2px solid var(--border); border-radius: 14px;
    padding: 16px; cursor: pointer; transition: all 0.2s;
    display: flex; align-items: center; gap: 12px;
}
.metode-item.selected { border-color: var(--primary); background: #F5F4FF; }
.metode-item:hover { border-color: var(--primary-2); }
.metode-ikon { font-size: 24px; }
.metode-nama { font-size: 13px; font-weight: 700; }
.metode-desc { font-size: 11px; color: var(--muted); }
.form-group { margin-bottom: 16px; }
.form-group label {
    display: block; font-size: 12px; font-weight: 700;
    color: var(--muted); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;
}
.form-group input, .form-group select {
    width: 100%; padding: 12px 14px;
    border: 1.5px solid var(--border); border-radius: 12px;
    font-size: 13px; font-family: 'Plus Jakarta Sans', sans-serif;
    outline: none; transition: border 0.2s; background: white;
}
.form-group input:focus, .form-group select:focus { border-color: var(--primary); }
.btn-bayar {
    width: 100%; padding: 14px; margin-top: 8px;
    background: linear-gradient(135deg, var(--accent), #38F9D7);
    color: #1a4a2e; border: none; border-radius: 14px;
    font-size: 14px; font-weight: 800; cursor: pointer;
    font-family: 'Plus Jakarta Sans', sans-serif; transition: all 0.2s;
}
.btn-bayar:hover { opacity: 0.88; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(67,233,123,0.3); }

/* ── HALAMAN: PENGATURAN ── */
.pengaturan-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.pengaturan-card {
    background: white; border-radius: 16px;
    border: 1.5px solid var(--border); padding: 24px;
}
.pengaturan-card h3 {
    font-size: 15px; font-weight: 700; margin-bottom: 18px;
    display: flex; align-items: center; gap: 10px;
}
.profile-avatar-wrap { display: flex; align-items: center; gap: 16px; margin-bottom: 20px; }
.profile-avatar-big {
    width: 68px; height: 68px; border-radius: 16px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    display: flex; align-items: center; justify-content: center;
    color: white; font-weight: 800; font-size: 22px;
}
.profile-avatar-info p { font-size: 13px; color: var(--muted); margin-top: 2px; }
.btn-ubah-foto {
    padding: 8px 16px; border-radius: 10px;
    border: 1.5px solid var(--border); background: white;
    font-size: 12px; font-weight: 600; cursor: pointer;
    font-family: 'Plus Jakarta Sans', sans-serif;
    transition: all 0.2s; margin-top: 8px;
}
.btn-ubah-foto:hover { background: var(--bg); border-color: var(--primary); color: var(--primary); }
.file-label { font-size: 12px; color: var(--muted); margin-top: 8px; }
.btn-simpan {
    padding: 11px 24px; border-radius: 12px;
    background: linear-gradient(135deg, var(--primary), var(--primary-2));
    color: white; border: none; font-size: 13px; font-weight: 700;
    cursor: pointer; font-family: 'Plus Jakarta Sans', sans-serif;
    transition: all 0.2s; margin-top: 4px;
}
.btn-simpan:hover { opacity: 0.88; }
.toggle-wrap { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.toggle-label { font-size: 13px; font-weight: 600; }
.toggle-desc   { font-size: 11px; color: var(--muted); margin-top: 2px; }
.toggle {
    position: relative; width: 44px; height: 24px;
}
.toggle input { opacity: 0; width: 0; height: 0; }
.toggle-slider {
    position: absolute; inset: 0;
    background: #ddd; border-radius: 24px; cursor: pointer;
    transition: 0.3s;
}
.toggle-slider::before {
    content: ''; position: absolute;
    left: 3px; top: 3px;
    width: 18px; height: 18px;
    background: white; border-radius: 50%;
    transition: 0.3s;
}
.toggle input:checked + .toggle-slider { background: var(--primary); }
.toggle input:checked + .toggle-slider::before { transform: translateX(20px); }

/* ── MODAL ── */
.modal-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.4);
    z-index: 200; display: none;
    align-items: center; justify-content: center;
}
.modal-overlay.active { display: flex; }
.modal-box {
    background: white; border-radius: 20px;
    padding: 28px; max-width: 440px; width: 90%;
    animation: fadeUp 0.3s ease both;
}
.modal-title { font-size: 17px; font-weight: 700; margin-bottom: 6px; }
.modal-desc  { font-size: 13px; color: var(--muted); margin-bottom: 20px; }
.modal-footer { display: flex; gap: 10px; justify-content: flex-end; }
.btn-cancel {
    padding: 10px 20px; border-radius: 10px;
    border: 1.5px solid var(--border); background: white;
    font-size: 13px; font-weight: 600; cursor: pointer;
    font-family: 'Plus Jakarta Sans', sans-serif;
}
.btn-confirm {
    padding: 10px 20px; border-radius: 10px;
    background: linear-gradient(135deg, var(--primary), var(--primary-2));
    color: white; border: none; font-size: 13px; font-weight: 600;
    cursor: pointer; font-family: 'Plus Jakarta Sans', sans-serif;
}

/* ── TOAST NOTIFIKASI ── */
.toast {
    position: fixed; bottom: 28px; right: 28px;
    background: #1E1B4B; color: white;
    padding: 13px 20px; border-radius: 14px;
    font-size: 13px; font-weight: 600; z-index: 300;
    transform: translateY(20px); opacity: 0;
    transition: all 0.3s; pointer-events: none;
    display: flex; align-items: center; gap: 10px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
}
.toast.show { transform: translateY(0); opacity: 1; }
.toast.success { background: #0a7a3e; }
.toast.error   { background: #c0392b; }

/* ── ANIMASI ── */
@keyframes fadeUp {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
}
.stat-card:nth-child(1) { animation: fadeUp 0.4s 0.1s both; }
.stat-card:nth-child(2) { animation: fadeUp 0.4s 0.15s both; }
.stat-card:nth-child(3) { animation: fadeUp 0.4s 0.2s both; }

/* ── RESPONSIVE ── */
@media (max-width: 900px) {
    .sidebar { transform: translateX(-100%); }
    .sidebar.open { transform: translateX(0); }
    .main { margin-left: 0; }
    .stats-grid { grid-template-columns: 1fr 1fr; }
    .bottom-grid, .keranjang-wrap, .pembayaran-wrap, .pengaturan-grid { grid-template-columns: 1fr; }
    .produk-grid { grid-template-columns: 1fr 1fr; }
    .topbar { padding: 0 18px; }
    .content { padding: 20px 18px; }
}
@media (max-width: 560px) {
    .stats-grid, .produk-grid { grid-template-columns: 1fr; }
    .welcome-emoji { display: none; }
}
</style>
</head>
<body>

<!-- ── SIDEBAR ── -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="logo-icon">🎒</div>
        <h2>Perlengkapan Sekolah</h2>
        <span>Dashboard Pelanggan</span>
    </div>
    <div class="sidebar-menu">
        <a href="#" class="nav-link active" data-page="dashboard">
            <div class="icon">🏠</div> Dashboard
        </a>
        <a href="#" class="nav-link" data-page="produk">
            <div class="icon">🛍️</div> Produk
        </a>
        <a href="#" class="nav-link" data-page="keranjang">
            <div class="icon">🛒</div> Keranjang
            <span class="badge-count" id="badge-keranjang"><?php echo $item_keranjang; ?></span>
        </a>
        <a href="#" class="nav-link" data-page="pesanan">
            <div class="icon">🧾</div> Pesanan Saya
            <span class="badge-count" id="badge-pesanan"><?php echo $pesanan_aktif; ?></span>
        </a>
        <a href="#" class="nav-link" data-page="pembayaran">
            <div class="icon">💳</div> Pembayaran
        </a>
        <a href="#" class="nav-link" data-page="profil">
            <div class="icon">👤</div> Profil Saya
        </a>
        <a href="#" class="nav-link" data-page="pengaturan">
            <div class="icon">⚙️</div> Pengaturan
        </a>
    </div>
    <div class="sidebar-footer">
        <a href="#" id="sidebar-logout">
            <span>🚪</span> Logout
        </a>
    </div>
</div>

<!-- ── MAIN ── -->
<div class="main">

    <!-- TOPBAR -->
    <div class="topbar">
        <div class="topbar-left">
            <h1 id="page-title">Dashboard</h1>
            <p><?php echo date('l, d F Y'); ?></p>
        </div>
        <div class="topbar-right">
            <button class="topbar-btn" id="notif-btn" title="Notifikasi">
                🔔 <span class="notif-dot"></span>
            </button>
            <a href="#" class="topbar-btn nav-link" data-page="keranjang" title="Keranjang">🛒</a>
            <div class="avatar" id="topbar-avatar">
                <?php if ($profile_image): ?>
                    <img src="upload/<?php echo htmlspecialchars($profile_image); ?>?t=<?php echo time(); ?>" alt="Avatar">
                <?php else: ?>
                    <?php echo $avatar; ?>
                <?php endif; ?>
            </div>
            <button class="btn-logout" id="topbar-logout">🚪 Logout</button>
        </div>
    </div>

    <!-- ── CONTENT ── -->
    <div class="content">

        <!-- ═══════════════ HALAMAN: DASHBOARD ═══════════════ -->
        <div class="page-section active" id="page-dashboard">

            <div class="stats-grid">
                <div class="stat-card blue" onclick="navigateTo('pesanan')" title="Lihat pesanan aktif">
                    <div class="stat-top"><div class="stat-icon">🧾</div></div>
                    <div class="stat-number" id="stat-aktif"><?php echo $pesanan_aktif; ?></div>
                    <div class="stat-label">Pesanan Aktif</div>
                </div>
                <div class="stat-card pink" onclick="navigateTo('keranjang')" title="Lihat keranjang">
                    <div class="stat-top"><div class="stat-icon">🛒</div></div>
                    <div class="stat-number" id="stat-keranjang"><?php echo $item_keranjang; ?></div>
                    <div class="stat-label">Item di Keranjang</div>
                </div>
                <div class="stat-card green" onclick="navigateTo('pesanan')" title="Lihat riwayat pesanan">
                    <div class="stat-top"><div class="stat-icon">✅</div></div>
                    <div class="stat-number"><?php echo $pesanan_selesai; ?></div>
                    <div class="stat-label">Pesanan Selesai</div>
                </div>
            </div>

            <div class="bottom-grid">
                <div class="section-card">
                    <div class="section-header">
                        <div class="section-title">Menu Cepat</div>
                    </div>
                    <div class="menu-grid">
                        <a class="menu-item nav-link" data-page="produk" href="#">
                            <div class="m-icon" style="background:#EEF0FF;">🛍️</div>
                            <span>Lihat Produk</span>
                        </a>
                        <a class="menu-item nav-link" data-page="keranjang" href="#">
                            <div class="m-icon" style="background:#FFF0F3;">🛒</div>
                            <span>Keranjang</span>
                        </a>
                        <a class="menu-item nav-link" data-page="pesanan" href="#">
                            <div class="m-icon" style="background:#EDFFF5;">🧾</div>
                            <span>Pesanan Saya</span>
                        </a>
                        <a class="menu-item nav-link" data-page="pembayaran" href="#">
                            <div class="m-icon" style="background:#FFF5EC;">💳</div>
                            <span>Pembayaran</span>
                        </a>
                    </div>
                </div>

                <div class="section-card">
                    <div class="section-header">
                        <div class="section-title">Produk Terbaru</div>
                        <a href="#" class="section-link nav-link" data-page="produk">Lihat Semua →</a>
                    </div>
                    <div class="produk-list">
                        <?php foreach ($produk_terbaru as $p): ?>
                        <div class="produk-item">
                            <div class="produk-icon">
                                <?php if ($p['gambar']): ?>
                                    <img src="upload/<?php echo $p['gambar']; ?>" alt="<?php echo $p['nama']; ?>" style="width:100%;height:100%;object-fit:contain;border-radius:10px;">
                                <?php else: ?>
                                    📦
                                <?php endif; ?>
                            </div>
                            <div class="produk-info">
                                <div class="produk-nama"><?php echo $p['nama']; ?></div>
                                <div class="produk-harga">Rp <?php echo number_format($p['harga'],0,',','.'); ?></div>
                            </div>
                            <?php if ($p['stok'] === 'Tersedia'): ?>
                                <button class="btn-beli"
                                    onclick="tambahKeranjang('<?php echo addslashes($p['nama']); ?>', <?php echo $p['harga']; ?>, '<?php echo $p['gambar'] ?? ''; ?>')">
                                    Beli
                                </button>
                            <?php else: ?>
                                <span class="produk-stok-habis">Habis</span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════════ HALAMAN: PROFIL ═══════════════ -->
        <div class="page-section" id="page-profil">
            <div class="section-card" style="max-width:920px;">
                <div class="section-header">
                    <div class="section-title">Profil Saya</div>
                    <a href="#" class="section-link nav-link" data-page="pengaturan">Edit Profil →</a>
                </div>
                <div class="profile-avatar-wrap">
                    <div class="profile-avatar-big" id="profile-page-avatar">
                        <?php if ($profile_image): ?>
                            <img src="upload/<?php echo htmlspecialchars($profile_image); ?>?t=<?php echo time(); ?>" alt="Avatar">
                        <?php else: ?>
                            <?php echo $avatar; ?>
                        <?php endif; ?>
                    </div>
                    <div class="profile-avatar-info">
                        <strong id="profile-page-name" style="font-size:18px;"><?php echo htmlspecialchars($user); ?></strong>
                        <p id="profile-page-email"><?php echo htmlspecialchars($data_pelanggan['email'] ?? 'Belum diisi'); ?></p>
                        <p style="color:var(--muted);margin-top:6px;">Pelanggan</p>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:14px;">
                    <div class="section-card" style="background:#F9FAFF;border:none;padding:20px;">
                        <div style="font-size:12px;color:var(--muted);margin-bottom:10px;">Username</div>
                        <div style="font-weight:700;"><?php echo htmlspecialchars($data_pelanggan['username'] ?? '-'); ?></div>
                    </div>
                    <div class="section-card" style="background:#F9FAFF;border:none;padding:20px;">
                        <div style="font-size:12px;color:var(--muted);margin-bottom:10px;">Telepon</div>
                        <div style="font-weight:700;"><?php echo htmlspecialchars($data_pelanggan['no_hp'] ?? 'Belum diisi'); ?></div>
                    </div>
                    <div class="section-card" style="background:#F9FAFF;border:none;padding:20px;grid-column:span 2;">
                        <div style="font-size:12px;color:var(--muted);margin-bottom:10px;">Alamat</div>
                        <div style="font-weight:700;"><?php echo htmlspecialchars($data_pelanggan['alamat'] ?? 'Belum diisi'); ?></div>
                    </div>
                </div>
                <div style="margin-top:20px;color:var(--muted);font-size:13px;">
                    Ini adalah halaman profil Anda. Klik tombol "Edit Profil" untuk mengubah data akun dan informasi kontak.
                </div>
            </div>
        </div>

        <!-- ═══════════════ HALAMAN: PRODUK ═══════════════ -->
        <div class="page-section" id="page-produk">
            <div class="produk-header">
                <div class="search-bar">
                    <span>🔍</span>
                    <input type="text" placeholder="Cari produk..." id="search-produk" oninput="filterProduk()">
                </div>
                <select class="filter-select" id="filter-kategori" onchange="filterProduk()">
                    <option value="">Semua Kategori</option>
                    <option value="Alat Tulis">Alat Tulis</option>
                    <option value="Buku">Buku</option>
                    <option value="Tas">Tas</option>
                    <option value="Peralatan">Peralatan</option>
                </select>
            </div>
            <div class="produk-grid" id="produk-grid">
                <!-- Diisi via JavaScript -->
            </div>
        </div>

        <!-- ═══════════════ HALAMAN: KERANJANG ═══════════════ -->
        <div class="page-section" id="page-keranjang">
            <div class="keranjang-wrap">
                <div>
                    <div class="keranjang-list" id="keranjang-list">
                        <!-- Diisi via JavaScript -->
                    </div>
                </div>
                <div>
                    <div class="ringkasan-card">
                        <div class="ringkasan-title">Ringkasan Belanja</div>
                        <div class="ringkasan-row">
                            <span>Subtotal</span>
                            <span id="ring-subtotal">Rp 0</span>
                        </div>
                        <div class="ringkasan-row">
                            <span>Ongkir</span>
                            <span id="ring-ongkir">Rp 0</span>
                        </div>
                        <div class="ringkasan-row">
                            <span>Diskon</span>
                            <span id="ring-diskon" style="color:var(--success);">- Rp 0</span>
                        </div>
                        <div class="ringkasan-row total">
                            <span>Total</span>
                            <span id="ring-total">Rp 0</span>
                        </div>
                        <button class="btn-checkout" onclick="navigateTo('pembayaran')">
                            Lanjut ke Pembayaran →
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════════ HALAMAN: PESANAN ═══════════════ -->
        <div class="page-section" id="page-pesanan">
            <div class="pesanan-table-wrap">
                <table class="pesanan-table">
                    <thead>
                        <tr>
                            <th>No. Pesanan</th>
                            <th>Tanggal</th>
                            <th>Status</th>
                            <th>Total</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($riwayat_pesanan as $p): ?>
                        <tr>
                            <td><strong>#<?php echo $p['id']; ?></strong></td>
                            <td><?php echo $p['tanggal']; ?></td>
                            <td>
                                <span class="status-badge <?php echo $p['cls']; ?>">
                                    <?php echo $p['status']; ?>
                                </span>
                            </td>
                            <td><strong>Rp <?php echo number_format($p['total'],0,',','.'); ?></strong></td>
                            <td style="display:flex;gap:8px;flex-wrap:wrap;">
                                <button class="btn-detail"
                                    onclick="showModalDetail('<?php echo $p['id']; ?>', '<?php echo $p['status']; ?>', <?php echo $p['total']; ?>)">
                                    Detail
                                </button>
                                <?php if (!in_array(strtolower($p['status']), ['selesai', 'dibatalkan'])): ?>
                                <button class="btn-hapus" onclick="batalkanPesanan(<?php echo $p['id']; ?>)">
                                    Batalkan
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ═══════════════ HALAMAN: PEMBAYARAN ═══════════════ -->
<div class="page-section" id="page-pembayaran">
    <div class="pembayaran-wrap">
        <div>

            <!-- METODE PEMBAYARAN -->
            <div class="section-card" style="margin-bottom:18px;">
                <div class="section-title" style="margin-bottom:16px;">Pilih Metode Pembayaran</div>
                <div class="metode-grid">
                    <div class="metode-item selected" onclick="pilihMetode(this, 'transfer')">
                        <div class="metode-ikon">🏦</div>
                        <div>
                            <div class="metode-nama">Transfer Bank</div>
                            <div class="metode-desc">BCA, BNI, Mandiri</div>
                        </div>
                    </div>
                    <div class="metode-item" onclick="pilihMetode(this, 'qris')">
                        <div class="metode-ikon">💰</div>
                        <div>
                            <div class="metode-nama">QRIS</div>
                            <div class="metode-desc">Scan & bayar</div>
                        </div>
                    </div>
                    <div class="metode-item" onclick="pilihMetode(this, 'ewallet')">
                        <div class="metode-ikon">📱</div>
                        <div>
                            <div class="metode-nama">E-Wallet</div>
                            <div class="metode-desc">OVO, GoPay, Dana</div>
                        </div>
                    </div>
                    <div class="metode-item" onclick="pilihMetode(this, 'cod')">
                        <div class="metode-ikon">💵</div>
                        <div>
                            <div class="metode-nama">COD</div>
                            <div class="metode-desc">Bayar di tempat</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- DETAIL TRANSFER BANK -->
            <div class="section-card detail-metode" id="detail-transfer" style="margin-bottom:18px;">
                <div class="section-title" style="margin-bottom:16px;">🏦 Transfer Bank</div>
                <div class="form-group">
                    <label>Pilih Bank</label>
                    <select id="pilih-bank" onchange="tampilRekening()">
                        <option value="">-- Pilih Bank --</option>
                        <option value="bri">BRI</option>
                    </select>
                </div>
                <div id="info-rekening" style="display:none;">
                    <div style="background:#F0F2FF;border-radius:14px;padding:18px;margin-bottom:16px;">
                        <div style="font-size:12px;color:var(--muted);margin-bottom:4px;">Transfer ke Rekening</div>
                        <div style="font-size:20px;font-weight:800;color:var(--primary);letter-spacing:2px;" id="no-rekening">-</div>
                        <div style="font-size:13px;font-weight:600;margin-top:4px;" id="nama-rekening">-</div>
                        <div style="font-size:12px;color:var(--muted);margin-top:2px;" id="nama-bank">-</div>
                    </div>
                    <div style="background:#EDFFF5;border-radius:12px;padding:14px;margin-bottom:16px;border-left:4px solid #43E97B;">
                        <div style="font-size:12px;color:var(--muted);">Jumlah Transfer</div>
                        <div style="font-size:18px;font-weight:800;color:#0a7a3e;" id="jumlah-transfer">Rp 0</div>
                        <div style="font-size:11px;color:var(--muted);margin-top:4px;">⚠️ Transfer sesuai nominal, termasuk 3 digit unik terakhir</div>
                    </div>
                    <div style="font-size:12px;color:var(--muted);line-height:1.7;">
                        📋 <strong>Langkah Pembayaran:</strong><br>
                        1. Buka aplikasi mobile banking atau ATM<br>
                        2. Pilih Transfer → masukkan nomor rekening<br>
                        3. Masukkan nominal yang tertera<br>
                        4. Konfirmasi dan simpan bukti transfer<br>
                        5. Upload bukti transfer di bawah
                    </div>
                    <div class="form-group" style="margin-top:16px;">
                        <label>Upload Bukti Transfer</label>
                        <input type="file" accept="image/*" id="bukti-transfer"
                               style="padding:10px;border:2px dashed var(--border);border-radius:12px;cursor:pointer;">
                    </div>
                </div>
            </div>

            <!-- DETAIL QRIS -->
            <div class="section-card detail-metode" id="detail-qris" style="margin-bottom:18px;display:none;">
                <div class="section-title" style="margin-bottom:16px;">💰 Pembayaran QRIS</div>
                <div style="text-align:center;padding:20px 0;">
                    <div style="background:#F0F2FF;border-radius:16px;padding:24px;display:inline-block;margin-bottom:16px;">
                        <!-- Simulasi QR Code -->
                        <div style="width:180px;height:180px;background:white;border-radius:12px;overflow:hidden;border:2px solid var(--border);">
    <img src="images/qris.png" 
         style="width:100%;height:100%;object-fit:contain;"
         onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
    <div style="display:none;width:100%;height:100%;align-items:center;justify-content:center;font-size:80px;">📱</div>
</div>
                    </div>
                    <div style="font-size:14px;font-weight:700;margin-bottom:4px;">Scan QR Code</div>
                    <div style="font-size:12px;color:var(--muted);margin-bottom:16px;">Gunakan aplikasi apapun yang mendukung QRIS</div>
                    <div style="background:#EDFFF5;border-radius:12px;padding:14px;margin-bottom:16px;border-left:4px solid #43E97B;">
                        <div style="font-size:12px;color:var(--muted);">Total Pembayaran</div>
                        <div style="font-size:20px;font-weight:800;color:#0a7a3e;" id="total-qris">Rp 0</div>
                    </div>
                    <div style="font-size:12px;color:var(--muted);text-align:left;line-height:1.7;background:#F8F9FF;padding:14px;border-radius:12px;">
                        📋 <strong>Langkah Pembayaran:</strong><br>
                        1. Buka aplikasi GoPay, OVO, Dana, ShopeePay, atau m-Banking<br>
                        2. Pilih menu Scan / QRIS<br>
                        3. Scan QR Code di atas<br>
                        4. Pastikan nominal sesuai lalu konfirmasi<br>
                        5. Pembayaran otomatis terkonfirmasi
                    </div>
                    <div style="margin-top:14px;font-size:12px;color:var(--muted);">
                        ⏱️ QR Code berlaku selama <strong style="color:var(--primary);" id="countdown-qris">05:00</strong>
                    </div>
                </div>
            </div>

            <!-- DETAIL E-WALLET -->
            <div class="section-card detail-metode" id="detail-ewallet" style="margin-bottom:18px;display:none;">
                <div class="section-title" style="margin-bottom:16px;">📱 Pembayaran E-Wallet</div>
                <div class="form-group">
                    <label>Pilih E-Wallet</label>
                    <select id="pilih-ewallet" onchange="tampilEwallet()">
                        <option value="">-- Pilih E-Wallet --</option>
                        <option value="gopay">GoPay</option>
                        <option value="ovo">OVO</option>
                        <option value="dana">Dana</option>
                        <option value="shopeepay">ShopeePay</option>
                    </select>
                </div>
                <div id="info-ewallet" style="display:none;">
                    <div style="background:#F0F2FF;border-radius:14px;padding:18px;margin-bottom:16px;">
                        <div style="display:flex;align-items:center;gap:14px;">
                            <div style="font-size:40px;" id="ikon-ewallet">💳</div>
                            <div>
                                <div style="font-size:12px;color:var(--muted);">Kirim ke Nomor</div>
                                <div style="font-size:18px;font-weight:800;color:var(--primary);" id="no-ewallet">-</div>
                                <div style="font-size:12px;color:var(--muted);" id="nama-ewallet">-</div>
                            </div>
                        </div>
                    </div>
                    <div style="background:#EDFFF5;border-radius:12px;padding:14px;margin-bottom:16px;border-left:4px solid #43E97B;">
                        <div style="font-size:12px;color:var(--muted);">Jumlah Pembayaran</div>
                        <div style="font-size:18px;font-weight:800;color:#0a7a3e;" id="jumlah-ewallet">Rp 0</div>
                    </div>
                    <div style="font-size:12px;color:var(--muted);line-height:1.7;background:#F8F9FF;padding:14px;border-radius:12px;">
                        📋 <strong>Langkah Pembayaran:</strong><br>
                        1. Buka aplikasi <span id="nama-app-ewallet">E-Wallet</span> Anda<br>
                        2. Pilih menu Transfer / Kirim<br>
                        3. Masukkan nomor tujuan di atas<br>
                        4. Masukkan nominal yang tertera<br>
                        5. Konfirmasi pembayaran
                    </div>
                </div>
            </div>

            <!-- DETAIL COD -->
            <div class="section-card detail-metode" id="detail-cod" style="margin-bottom:18px;display:none;">
                <div class="section-title" style="margin-bottom:16px;">💵 Bayar di Tempat (COD)</div>
                <div style="background:#FFF5EC;border-radius:14px;padding:18px;margin-bottom:16px;border-left:4px solid #FA8231;">
                    <div style="font-size:13px;font-weight:700;color:#FA8231;margin-bottom:6px;">⚠️ Perhatian COD</div>
                    <div style="font-size:12px;color:#8B6914;line-height:1.7;">
                        • Siapkan uang tunai <strong>pas</strong> sesuai tagihan<br>
                        • Kurir tidak menyediakan kembalian<br>
                        • Pastikan ada orang di alamat pengiriman<br>
                        • Periksa barang sebelum membayar
                    </div>
                </div>
                <div style="background:#EDFFF5;border-radius:12px;padding:14px;margin-bottom:16px;border-left:4px solid #43E97B;">
                    <div style="font-size:12px;color:var(--muted);">Siapkan Uang Tunai</div>
                    <div style="font-size:20px;font-weight:800;color:#0a7a3e;" id="jumlah-cod">Rp 0</div>
                </div>
                <div style="font-size:12px;color:var(--muted);line-height:1.7;background:#F8F9FF;padding:14px;border-radius:12px;">
                    📋 <strong>Alur COD:</strong><br>
                    1. Buat pesanan dan pilih COD<br>
                    2. Kurir akan mengantar ke alamat Anda<br>
                    3. Periksa kondisi barang<br>
                    4. Bayar tunai kepada kurir<br>
                    5. Kurir memberikan bukti pembayaran
                </div>
                <div class="form-group" style="margin-top:16px;">
                    <label>Catatan untuk Kurir (Opsional)</label>
                    <input type="text" placeholder="Contoh: Hubungi dulu sebelum datang">
                </div>
            </div>

            <!-- ALAMAT PENGIRIMAN -->
            <div class="section-card">
                <div class="section-title" style="margin-bottom:16px;">📍 Alamat Pengiriman</div>
                <div class="form-group">
                    <label>Nama Penerima</label>
                    <input type="text" id="form-nama" placeholder="Nama lengkap" value="<?php echo htmlspecialchars($user); ?>">
                </div>
                <div class="form-group">
                    <label>Nomor Telepon</label>
                    <input type="text" id="form-telpon" placeholder="08xx-xxxx-xxxx">
                </div>
                <div class="form-group">
                    <label>Alamat Lengkap</label>
                    <input type="text" id="form-alamat" placeholder="Jl. Contoh No. 1, Kelurahan, Kecamatan">
                </div>
                <div class="form-group">
                    <label>Kota</label>
                    <input type="text" id="form-kota" placeholder="Makassar">
                </div>
                <div class="form-group">
                    <label>Kode Pos</label>
                    <input type="text" id="form-kodepos" placeholder="90111">
                </div>
            </div>

        </div>

        <!-- RINGKASAN -->
        <div>
            <div class="ringkasan-card">
                <div class="ringkasan-title">Ringkasan Pembayaran</div>
                <div class="ringkasan-row">
                    <span>Subtotal</span>
                    <span id="pay-subtotal">Rp 0</span>
                </div>
                <div class="ringkasan-row">
                    <span>Ongkir</span>
                    <span id="pay-ongkir">Rp 15.000</span>
                </div>
                <div class="ringkasan-row">
                    <span>Diskon Voucher</span>
                    <span id="pay-diskon" style="color:var(--success);">- Rp 0</span>
                </div>
                <div class="ringkasan-row total">
                    <span>Total Bayar</span>
                    <span id="pay-total">Rp 0</span>
                </div>

                <!-- METODE TERPILIH -->
                <div style="background:#F0F2FF;border-radius:12px;padding:12px;margin:14px 0;display:flex;align-items:center;gap:10px;">
                    <span id="ikon-metode-terpilih" style="font-size:20px;">🏦</span>
                    <div>
                        <div style="font-size:11px;color:var(--muted);">Metode Pembayaran</div>
                        <div style="font-size:13px;font-weight:700;" id="nama-metode-terpilih">Transfer Bank</div>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:0;">
                    <label>Kode Voucher</label>
                    <div style="display:flex;gap:8px;">
                        <input type="text" placeholder="Contoh: SEKOLAH10" id="voucher-input" style="flex:1;">
                        <button onclick="pakaiVoucher()" id="btn-pakai-voucher" style="
                            padding:10px 14px;border-radius:10px;
                            border:1.5px solid var(--primary);
                            background:white;color:var(--primary);
                            font-size:12px;font-weight:700;cursor:pointer;
                            font-family:'Plus Jakarta Sans',sans-serif;
                            white-space:nowrap;">
                            Pakai
                        </button>
                    </div>
                    <div id="voucher-status" style="font-size:11.5px;margin-top:6px;font-weight:600;"></div>
                </div>
                <button class="btn-bayar" onclick="prosesCheckout()" style="margin-top:16px;">
                    ✅ Konfirmasi Pembayaran
                </button>
            </div>
        </div>
    </div>
</div>

        <!-- ═══════════════ HALAMAN: PENGATURAN ═══════════════ -->
        <div class="page-section" id="page-pengaturan">
            <div class="pengaturan-grid">
                <div class="pengaturan-card">
                    <h3>👤 Profil Saya</h3>
                    <div class="profile-avatar-wrap">
                        <div class="profile-avatar-big" id="profile-avatar-big">
                            <?php if ($profile_image): ?>
                                <img src="upload/<?php echo htmlspecialchars($profile_image); ?>?t=<?php echo time(); ?>" alt="Avatar">
                            <?php else: ?>
                                <?php echo $avatar; ?>
                            <?php endif; ?>
                        </div>
                        <div class="profile-avatar-info">
                            <strong id="profile-display-name"><?php echo htmlspecialchars($display_name); ?></strong>
                            <p id="profile-display-email"><?php echo htmlspecialchars($display_email); ?></p>
                            <p style="color:var(--muted);margin-top:6px;">Pelanggan</p>
                            <button class="btn-ubah-foto" type="button" id="btn-ubah-foto">📷 Ubah Foto</button>
                            <input type="file" accept="image/*" id="profile-file" style="display:none;" />
                            <div class="file-label" id="file-label"></div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Nama Lengkap</label>
                        <input type="text" value="<?php echo htmlspecialchars($display_name); ?>" id="set-nama">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" value="<?php echo htmlspecialchars($display_email); ?>" id="set-email">
                    </div>
                    <div class="form-group">
                        <label>Nomor Telepon</label>
                        <input type="text" value="<?php echo htmlspecialchars($data_pelanggan['no_hp'] ?? ''); ?>" id="set-telp">
                    </div>
                    <div class="form-group">
                        <label>Alamat</label>
                        <input type="text" value="<?php echo htmlspecialchars($data_pelanggan['alamat'] ?? ''); ?>" id="set-alamat">
                    </div>
                    <button class="btn-simpan" onclick="simpanProfil()">💾 Simpan Profil</button>
                </div>

                <div>
                    <div class="pengaturan-card" style="margin-bottom:18px;">
                        <h3>🔒 Keamanan</h3>
                        <div class="form-group">
                            <label>Password Lama</label>
                            <input type="password" placeholder="••••••••">
                        </div>
                        <div class="form-group">
                            <label>Password Baru</label>
                            <input type="password" placeholder="••••••••" id="pass-baru">
                        </div>
                        <div class="form-group">
                            <label>Konfirmasi Password</label>
                            <input type="password" placeholder="••••••••" id="pass-konfirm">
                        </div>
                        <button class="btn-simpan" onclick="ubahPassword()">🔑 Ubah Password</button>
                    </div>

                    <div class="pengaturan-card">
                        <h3>🔔 Notifikasi</h3>
                        <div class="toggle-wrap">
                            <div>
                                <div class="toggle-label">Pesanan Baru</div>
                                <div class="toggle-desc">Notif saat pesanan masuk</div>
                            </div>
                            <label class="toggle">
                                <input type="checkbox" checked>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="toggle-wrap">
                            <div>
                                <div class="toggle-label">Promo & Diskon</div>
                                <div class="toggle-desc">Info penawaran terbaru</div>
                            </div>
                            <label class="toggle">
                                <input type="checkbox" checked>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="toggle-wrap">
                            <div>
                                <div class="toggle-label">Status Pengiriman</div>
                                <div class="toggle-desc">Update posisi paket</div>
                            </div>
                            <label class="toggle">
                                <input type="checkbox">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /content -->
</div><!-- /main -->

<!-- ── MODAL DETAIL PESANAN ── -->
<div class="modal-overlay" id="modal-detail">
    <div class="modal-box">
        <div class="modal-title" id="modal-title">Detail Pesanan</div>
        <div class="modal-desc" id="modal-desc">Informasi pesanan Anda.</div>
        <div id="modal-body" style="font-size:13px;margin-bottom:20px;"></div>
        <div class="modal-footer">
            <button class="btn-cancel" onclick="tutupModal('modal-detail')">Tutup</button>
        </div>
    </div>
</div>

<!-- ── MODAL LOGOUT ── -->
<div class="modal-overlay" id="modal-logout">
    <div class="modal-box">
        <div class="modal-title">Keluar dari Akun?</div>
        <div class="modal-desc">Anda akan keluar dari dashboard. Lanjutkan?</div>
        <div class="modal-footer">
            <button class="btn-cancel" onclick="tutupModal('modal-logout')">Batal</button>
            <a class="btn-confirm" href="logout.php" style="text-decoration:none;display:inline-block;padding:10px 20px;">Ya, Logout</a>
        </div>
    </div>
</div>

<!-- ── TOAST ── -->
<div class="toast" id="toast"></div>

<script>
// ── DATA PRODUK ───────────────────────────────────────────────────────────────
const semuaProduk = <?php echo json_encode(array_map(function($p) {
    return [
        'id'       => $p['id'],
        'nama'     => $p['nama'],
        'harga'    => (int)$p['harga'],
        'kategori' => $p['kategori'],
        'stok'     => $p['stok'],
        'gambar'   => $p['gambar'],
        'deskripsi'=> $p['deskripsi'],
    ];
}, $semua_produk)); ?>;

// ── STATE KERANJANG ───────────────────────────────────────────────────────────
let keranjang = [];

// ── NAVIGASI ─────────────────────────────────────────────────────────────────
const pageTitle = {
    dashboard:  'Dashboard',
    profil:     'Profil Saya',
    produk:     'Produk',
    keranjang:  'Keranjang Belanja',
    pesanan:    'Pesanan Saya',
    pembayaran: 'Pembayaran',
    pengaturan: 'Pengaturan',
};

function navigateTo(page) {
    document.querySelectorAll('.page-section').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.nav-link').forEach(a => a.classList.remove('active'));
    document.querySelectorAll(`.nav-link[data-page="${page}"]`).forEach(a => a.classList.add('active'));
    const sec = document.getElementById('page-' + page);
    if (sec) { sec.classList.add('active'); sec.style.animation = 'none'; sec.offsetHeight; sec.style.animation = ''; }
    document.getElementById('page-title').textContent = pageTitle[page] || page;
    window.scrollTo(0, 0);
    if (page === 'produk')     renderProduk(semuaProduk);
    if (page === 'keranjang')  renderKeranjang();
    if (page === 'pembayaran') updateRingkasanPembayaran();
}

document.querySelectorAll('.nav-link').forEach(a => {
    a.addEventListener('click', function(e) {
        e.preventDefault();
        navigateTo(this.dataset.page);
    });
});

// ── RENDER PRODUK ─────────────────────────────────────────────────────────────
function renderProduk(list) {
    const grid = document.getElementById('produk-grid');
    if (!list.length) {
        grid.innerHTML = '<p style="color:var(--muted);font-size:13px;grid-column:1/-1;text-align:center;padding:40px 0;">Produk tidak ditemukan.</p>';
        return;
    }
    grid.innerHTML = list.map(p => `
        <div class="produk-card">
            <div class="produk-card-img">
                ${p.gambar ? `<img src="upload/${p.gambar}" alt="${p.nama}" style="width:100%;height:100%;object-fit:contain;border-radius:16px;">` : '📦'}
            </div>
            <div class="produk-card-body">
                <div class="produk-card-nama">${p.nama}</div>
                <div class="produk-card-kategori">${p.kategori}</div>
                <div class="produk-card-footer">
                    <div class="produk-card-harga">Rp ${p.harga.toLocaleString('id-ID')}</div>
                    ${p.stok
                        ? `<button class="btn-beli" onclick="tambahKeranjang('${p.nama.replace(/'/g,"\\'")}', ${p.harga}, '${p.gambar || ''}')">+ Keranjang</button>`
                        : `<span class="badge-stok badge-habis">Habis</span>`
                    }
                </div>
            </div>
        </div>
    `).join('');
}

function filterProduk() {
    const q   = document.getElementById('search-produk').value.toLowerCase();
    const kat = document.getElementById('filter-kategori').value;
    const hasil = semuaProduk.filter(p =>
        (!q   || p.nama.toLowerCase().includes(q)) &&
        (!kat || p.kategori === kat)
    );
    renderProduk(hasil);
}

// ── KERANJANG ─────────────────────────────────────────────────────────────────
function tambahKeranjang(nama, harga, gambar) {
    const idx = keranjang.findIndex(k => k.nama === nama);
    if (idx >= 0) keranjang[idx].qty++;
    else keranjang.push({ nama, harga, gambar, qty: 1 });
    updateBadgeKeranjang();
    showToast('✅ ' + nama + ' ditambahkan ke keranjang', 'success');
}

function updateBadgeKeranjang() {
    const total = keranjang.reduce((s, k) => s + k.qty, 0);
    document.getElementById('badge-keranjang').textContent = total;
    document.getElementById('stat-keranjang').textContent  = total;
}

function renderKeranjang() {
    const list = document.getElementById('keranjang-list');
    if (!keranjang.length) {
        list.innerHTML = `<div class="section-card" style="text-align:center;padding:48px;">
            <div style="font-size:48px;margin-bottom:12px;">🛒</div>
            <div style="font-weight:700;font-size:15px;margin-bottom:6px;">Keranjang Kosong</div>
            <div style="color:var(--muted);font-size:13px;margin-bottom:20px;">Belum ada produk di keranjang Anda.</div>
            <button class="btn-beli" onclick="navigateTo('produk')">🛍️ Mulai Belanja</button>
        </div>`;
        updateRingkasan(0);
        return;
    }
    list.innerHTML = keranjang.map((k, i) => `
        <div class="keranjang-item">
            <div class="keranjang-ikon">
                ${k.gambar ? `<img src="upload/${k.gambar}" alt="${k.nama}" style="width:100%;height:100%;object-fit:contain;border-radius:12px;">` : '📦'}
            </div>
            <div class="keranjang-info">
                <div class="keranjang-nama">${k.nama}</div>
                <div class="keranjang-harga">Rp ${k.harga.toLocaleString('id-ID')}</div>
                <div class="qty-wrap">
                    <button class="qty-btn" onclick="ubahQty(${i}, -1)">−</button>
                    <span class="qty-num">${k.qty}</span>
                    <button class="qty-btn" onclick="ubahQty(${i}, 1)">+</button>
                </div>
            </div>
            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;">
                <strong style="font-size:14px;color:var(--primary);">
                    Rp ${(k.harga * k.qty).toLocaleString('id-ID')}
                </strong>
                <button class="btn-hapus" onclick="hapusKeranjang(${i})">🗑️ Hapus</button>
            </div>
        </div>
    `).join('');
    const subtotal = keranjang.reduce((s, k) => s + k.harga * k.qty, 0);
    updateRingkasan(subtotal);
}

function ubahQty(i, delta) {
    keranjang[i].qty = Math.max(1, keranjang[i].qty + delta);
    updateBadgeKeranjang();
    renderKeranjang();
}

function hapusKeranjang(i) {
    const nama = keranjang[i].nama;
    keranjang.splice(i, 1);
    updateBadgeKeranjang();
    renderKeranjang();
    showToast('🗑️ ' + nama + ' dihapus dari keranjang', 'error');
}

function getOngkir(metode = metodeTerpilih) {
    if (!keranjang.length) return 0;
    switch (metode) {
        case 'cod': return 0;
        case 'qris': return 5000;
        case 'ewallet': return 5000;
        case 'transfer': return 5000;
        default: return 5000;
    }
}

function getTotalCheckout(subtotal, metode) {
    const ongkir = getOngkir(metode);
    const diskon = Math.min(voucherDiskon, subtotal); // jaga-jaga diskon tidak melebihi subtotal
    return { ongkir, diskon, total: subtotal + ongkir - diskon };
}

function updateRingkasan(subtotal) {
    const { ongkir, diskon, total } = getTotalCheckout(subtotal, metodeTerpilih);
    document.getElementById('ring-subtotal').textContent = 'Rp ' + subtotal.toLocaleString('id-ID');
    document.getElementById('ring-ongkir').textContent   = 'Rp ' + ongkir.toLocaleString('id-ID');
    document.getElementById('ring-diskon').textContent   = '- Rp ' + diskon.toLocaleString('id-ID');
    document.getElementById('ring-total').textContent    = 'Rp ' + total.toLocaleString('id-ID');
}

function updateRingkasanPembayaran() {
    const subtotal = keranjang.reduce((s, k) => s + k.harga * k.qty, 0);
    const { ongkir, diskon, total } = getTotalCheckout(subtotal, metodeTerpilih);
    document.getElementById('pay-subtotal').textContent = 'Rp ' + subtotal.toLocaleString('id-ID');
    document.getElementById('pay-ongkir').textContent   = 'Rp ' + ongkir.toLocaleString('id-ID');
    document.getElementById('pay-diskon').textContent   = '- Rp ' + diskon.toLocaleString('id-ID');
    document.getElementById('pay-total').textContent    = 'Rp ' + total.toLocaleString('id-ID');
}

// ── PEMBAYARAN ────────────────────────────────────────────────────────────────
const infoBank = {
    bri:     { no: '0232 0109 1979  504', nama: 'Toko Perlengkapan Sekolah', bank: 'Bank BRI' },
};

const infoEwallet = {
    gopay:     { no: '0823-4861-0695', nama: 'Toko Sekolah', ikon: '💚', app: 'GoPay' },
    ovo:       { no: '0823-4861-0695', nama: 'Toko Sekolah', ikon: '💜', app: 'OVO' },
    shopeepay: { no: '0823-4861-0695', nama: 'Toko Sekolah', ikon: '🧡', app: 'ShopeePay' },
};

let metodeTerpilih = 'transfer';

function pilihMetode(el, metode) {
    document.querySelectorAll('.metode-item').forEach(m => m.classList.remove('selected'));
    el.classList.add('selected');
    metodeTerpilih = metode;

    document.querySelectorAll('.detail-metode').forEach(d => d.style.display = 'none');
    document.getElementById('detail-' + metode).style.display = 'block';

    const labels = {
        transfer: { ikon: '🏦', nama: 'Transfer Bank' },
        qris:     { ikon: '💰', nama: 'QRIS' },
        ewallet:  { ikon: '📱', nama: 'E-Wallet' },
        cod:      { ikon: '💵', nama: 'Bayar di Tempat (COD)' },
    };
    document.getElementById('ikon-metode-terpilih').textContent = labels[metode].ikon;
    document.getElementById('nama-metode-terpilih').textContent = labels[metode].nama;

    const subtotal = keranjang.reduce((s,k) => s + k.harga * k.qty, 0);
    const { ongkir, total } = getTotalCheckout(subtotal, metode);
    const unik              = Math.floor(Math.random() * 900) + 100;

    if (metode === 'qris') {
        document.getElementById('total-qris').textContent = 'Rp ' + total.toLocaleString('id-ID');
        mulaiCountdown();
    }
    if (metode === 'cod') {
        document.getElementById('jumlah-cod').textContent = 'Rp ' + total.toLocaleString('id-ID');
    }
    updateRingkasanPembayaran();
}


function tampilRekening() {
    const bank = document.getElementById('pilih-bank').value;
    const info = document.getElementById('info-rekening');
    if (!bank) { info.style.display = 'none'; return; }
    const subtotal = keranjang.reduce((s,k) => s + k.harga * k.qty, 0);
    const { ongkir, diskon, total } = getTotalCheckout(subtotal, metodeTerpilih);
    const unik     = Math.floor(Math.random() * 900) + 100;
    document.getElementById('no-rekening').textContent    = infoBank[bank].no;
    document.getElementById('nama-rekening').textContent  = infoBank[bank].nama;
    document.getElementById('nama-bank').textContent      = infoBank[bank].bank;
    document.getElementById('jumlah-transfer').textContent = 'Rp ' + (total + unik).toLocaleString('id-ID') + ' (kode unik: ' + unik + ')';
    info.style.display = 'block';
}

function tampilEwallet() {
    const ew   = document.getElementById('pilih-ewallet').value;
    const info = document.getElementById('info-ewallet');
    if (!ew) { info.style.display = 'none'; return; }
    const subtotal = keranjang.reduce((s,k) => s + k.harga * k.qty, 0);
    const { ongkir, diskon, total } = getTotalCheckout(subtotal, metodeTerpilih);
    document.getElementById('ikon-ewallet').textContent     = infoEwallet[ew].ikon;
    document.getElementById('no-ewallet').textContent       = infoEwallet[ew].no;
    document.getElementById('nama-ewallet').textContent     = infoEwallet[ew].nama;
    document.getElementById('jumlah-ewallet').textContent   = 'Rp ' + total.toLocaleString('id-ID');
    document.getElementById('nama-app-ewallet').textContent = infoEwallet[ew].app;
    info.style.display = 'block';
}

let countdownTimer;
function mulaiCountdown() {
    clearInterval(countdownTimer);
    let detik = 300;
    const el = document.getElementById('countdown-qris');
    countdownTimer = setInterval(() => {
        detik--;
        const m = String(Math.floor(detik / 60)).padStart(2, '0');
        const s = String(detik % 60).padStart(2, '0');
        if (el) el.textContent = m + ':' + s;
        if (detik <= 0) {
            clearInterval(countdownTimer);
            if (el) el.textContent = 'Kedaluwarsa';
            showToast('⏰ QR Code kedaluwarsa! Silakan refresh.', 'error');
        }
    }, 1000);
}

let voucherKode   = null;
let voucherDiskon = 0;

function pakaiVoucher() {
    const inputEl  = document.getElementById('voucher-input');
    const statusEl = document.getElementById('voucher-status');
    const kode     = inputEl.value.trim().toUpperCase();

    if (!kode) {
        showToast('⚠️ Masukkan kode voucher dulu.', 'error');
        return;
    }
    if (!keranjang.length) {
        showToast('⚠️ Keranjang masih kosong.', 'error');
        return;
    }
    if (voucherKode) {
        showToast('⚠️ Voucher sudah dipakai. Hapus dulu untuk ganti kode.', '');
        return;
    }

    const subtotal = keranjang.reduce((s, k) => s + k.harga * k.qty, 0);

    fetch('keranjang.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'aksi=cek_voucher&kode_voucher=' + encodeURIComponent(kode) + '&subtotal=' + subtotal
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'ok') {
            voucherKode      = data.kode;
            voucherDiskon    = Number(data.diskon);
            inputEl.value    = data.kode;
            inputEl.disabled = true;
            statusEl.style.color = 'var(--success)';
            statusEl.innerHTML   = '✅ Hemat Rp ' + voucherDiskon.toLocaleString('id-ID') +
                ' &nbsp;<a href="#" onclick="batalkanVoucher();return false;" style="color:var(--danger);text-decoration:underline;">Batal</a>';
            updateRingkasanPembayaran();
            showToast('🎉 Voucher ' + voucherKode + ' berhasil dipakai!', 'success');
        } else {
            voucherKode   = null;
            voucherDiskon = 0;
            statusEl.style.color = 'var(--danger)';
            statusEl.textContent = '❌ ' + data.pesan;
            updateRingkasanPembayaran();
            showToast('❌ ' + data.pesan, 'error');
        }
    })
    .catch(() => showToast('❌ Gagal menghubungi server, coba lagi.', 'error'));
}

function batalkanVoucher() {
    voucherKode   = null;
    voucherDiskon = 0;
    const inputEl  = document.getElementById('voucher-input');
    const statusEl = document.getElementById('voucher-status');
    inputEl.value    = '';
    inputEl.disabled = false;
    statusEl.textContent = '';
    updateRingkasanPembayaran();
}

function prosesCheckout() {
    // Validasi keranjang
    if (!keranjang.length) {
        showToast('⚠️ Keranjang kosong! Tambahkan produk terlebih dahulu.', 'error');
        return;
    }
    
    // Ambil data dari form pembayaran
    const nama_penerima = document.getElementById('form-nama').value.trim();
    const nomor_telpon = document.getElementById('form-telpon').value.trim();
    const alamat_lengkap = document.getElementById('form-alamat').value.trim();
    const kota = document.getElementById('form-kota').value.trim();
    const kode_pos = document.getElementById('form-kodepos').value.trim();
    
    // Validasi form
    if (!nama_penerima || !nomor_telpon || !alamat_lengkap || !kota || !kode_pos) {
        showToast('⚠️ Semua data pengiriman harus diisi!', 'error');
        return;
    }
    
    // Hitung total
    const subtotal = keranjang.reduce((s, k) => s + k.harga * k.qty, 0);
    const ongkir = getOngkir(metodeTerpilih);
    const diskon = Math.min(voucherDiskon, subtotal);
    const total = subtotal + ongkir - diskon;
    
    // Ambil metode pembayaran yang dipilih
    const metodeEl = document.querySelector('.metode-item.selected');
    const metode = metodeEl ? metodeEl.querySelector('.metode-nama').textContent : 'Transfer Bank';
    
    // Data yang akan dikirim
    const pesananData = {
        items: keranjang,
        subtotal: subtotal,
        ongkir: ongkir,
        diskon: diskon,
        kode_voucher: voucherKode,
        total: total,
        metode: metode,
        nama_penerima: nama_penerima,
        nomor_telpon: nomor_telpon,
        alamat_lengkap: alamat_lengkap,
        kota: kota,
        kode_pos: kode_pos
    };
    
    // Kirim ke server via AJAX
    fetch('simpan_pesanan.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(pesananData)
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            showToast('✅ Pesanan berhasil dibuat! No. Pesanan: #' + data.id_pesanan, 'success');
            keranjang = [];
            updateBadgeKeranjang();
            voucherKode   = null;
            voucherDiskon = 0;
            // Reset form
            document.getElementById('form-telpon').value = '';
            document.getElementById('form-alamat').value = '';
            document.getElementById('form-kota').value = '';
            document.getElementById('form-kodepos').value = '';
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast('❌ ' + data.message, 'error');
        }
    })
    .catch(err => {
        console.error('Error:', err);
        showToast('❌ Terjadi kesalahan saat menyimpan pesanan', 'error');
    });
}

// ── PESANAN: DETAIL ───────────────────────────────────────────────────────────
function showModalDetail(id, status, total) {
    fetch('get_detail_pesanan.php?id=' + id)
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            const pesanan = data.pesanan;
            const details = data.details;
            
            document.getElementById('modal-title').textContent = 'Detail Pesanan #' + id;
            document.getElementById('modal-desc').textContent = 'Status: ' + status;
            
            let html = `
                <table style="width:100%;border-collapse:collapse;margin-bottom:12px;">
                    <tr><td style="padding:6px 0;color:var(--muted);">No. Pesanan</td><td style="font-weight:700;">#${id}</td></tr>
                    <tr><td style="padding:6px 0;color:var(--muted);">Tanggal</td><td style="font-weight:600;">${new Date(pesanan.tanggal_pesanan).toLocaleDateString('id-ID', { year: 'numeric', month: 'long', day: 'numeric' })}</td></tr>
                    <tr><td style="padding:6px 0;color:var(--muted);">Status</td>
                        <td><span class="status-badge ${status==='Selesai'?'status-selesai':status==='Dikirim'?'status-kirim':'status-proses'}">${status}</span></td></tr>
                    <tr><td style="padding:6px 0;color:var(--muted);">Metode Pembayaran</td><td style="font-weight:600;">${pesanan.metode_pembayaran}</td></tr>
                    <tr><td style="padding:6px 0;color:var(--muted);">Penerima</td><td style="font-weight:600;">${pesanan.nama_penerima}</td></tr>
                    <tr><td style="padding:6px 0;color:var(--muted);">Alamat</td><td style="font-weight:600;">${pesanan.alamat_lengkap}, ${pesanan.kota} ${pesanan.kode_pos}</td></tr>
                </table>
                <div style="border-top:1px solid #E8EAFF;padding-top:12px;margin-bottom:12px;">
                    <div style="font-weight:700;margin-bottom:8px;">📦 Produk yang Dibeli:</div>
            `;
            
            details.forEach((detail, idx) => {
                html += `
                    <div style="background:#F5F4FF;padding:8px 12px;border-radius:8px;margin-bottom:8px;display:flex;justify-content:space-between;">
                        <div>
                            <div style="font-weight:600;font-size:13px;">${detail.nama_barang}</div>
                            <div style="font-size:11px;color:var(--muted);">${detail.jumlah}x @ Rp ${Number(detail.harga).toLocaleString('id-ID')}</div>
                        </div>
                        <div style="font-weight:700;color:var(--primary);">Rp ${Number(detail.subtotal).toLocaleString('id-ID')}</div>
                    </div>
                `;
            });
            
            html += `
                </div>
                <table style="width:100%;border-collapse:collapse;">
                    <tr><td style="padding:6px 0;color:var(--muted);">Total Bayar</td><td style="font-weight:800;color:var(--primary);text-align:right;">Rp ${Number(pesanan.total_harga).toLocaleString('id-ID')}</td></tr>
                </table>
            `;
            
            if (['diproses', 'dikirim'].includes(pesanan.status_pesanan)) {
                html += `<div style="margin-top:14px;text-align:right;">
                    <button class="btn-hapus" onclick="batalkanPesanan(${id})">Batalkan Pesanan</button>
                </div>`;
            }
            
            document.getElementById('modal-body').innerHTML = html;
            document.getElementById('modal-detail').classList.add('active');
        } else {
            showToast('❌ ' + data.message, 'error');
        }
    })
    .catch(err => {
        console.error('Error:', err);
        showToast('❌ Gagal memuat detail pesanan', 'error');
    });
}

function batalkanPesanan(id) {
    if (!confirm('Yakin ingin membatalkan pesanan ini?')) return;
    fetch('batalkan_pesanan.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + encodeURIComponent(id)
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            showToast('✅ ' + data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('❌ ' + data.message, 'error');
        }
    })
    .catch(err => {
        console.error('Error:', err);
        showToast('❌ Gagal membatalkan pesanan', 'error');
    });
}

function tutupModal(id) {
    document.getElementById(id).classList.remove('active');
}

document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', function(e) { if (e.target === this) this.classList.remove('active'); });
});

const btnUbahFoto = document.getElementById('btn-ubah-foto');
const profileFile = document.getElementById('profile-file');
const fileLabel = document.getElementById('file-label');
if (btnUbahFoto && profileFile) {
    btnUbahFoto.addEventListener('click', () => profileFile.click());
}
if (profileFile && fileLabel) {
    profileFile.addEventListener('change', function() {
        if (this.files.length > 0) {
            fileLabel.textContent = 'File siap diunggah: ' + this.files[0].name;
        } else {
            fileLabel.textContent = '';
        }
    });
}

// ── PENGATURAN ────────────────────────────────────────────────────────────────
function simpanProfil() {
    const nama   = document.getElementById('set-nama').value.trim();
    const email  = document.getElementById('set-email').value.trim();
    const telp   = document.getElementById('set-telp').value.trim();
    const alamat = document.getElementById('set-alamat').value.trim();

    if (!nama) { showToast('⚠️ Nama tidak boleh kosong!', 'error'); return; }
    if (!email) { showToast('⚠️ Email tidak boleh kosong!', 'error'); return; }
    if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) {
        showToast('❌ Format email tidak valid!', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('nama', nama);
    formData.append('email', email);
    formData.append('telp', telp);
    formData.append('alamat', alamat);
    const fotoInput = document.getElementById('profile-file');
    if (fotoInput && fotoInput.files.length > 0) {
        formData.append('foto', fotoInput.files[0]);
    }

    fetch('update_profile.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            document.getElementById('profile-display-name').textContent = data.nama;
            document.getElementById('profile-display-email').textContent = data.email;
            document.getElementById('profile-display-telp').textContent = data.telp || 'Belum diisi';
            document.getElementById('profile-display-alamat').textContent = data.alamat || 'Belum diisi';
            if (data.foto) {
                const fotoUrl = 'upload/' + data.foto + '?t=' + Date.now();
                document.getElementById('profile-avatar-big').innerHTML = '<img src="' + fotoUrl + '" alt="Avatar">';
                document.getElementById('topbar-avatar').innerHTML = '<img src="' + fotoUrl + '" alt="Avatar">';
                const profilePageAvatar = document.getElementById('profile-page-avatar');
                if (profilePageAvatar) {
                    profilePageAvatar.innerHTML = '<img src="' + fotoUrl + '" alt="Avatar">';
                }
                const fotoInput = document.getElementById('profile-file');
                if (fotoInput) {
                    fotoInput.value = '';
                }
                const fileLabel = document.getElementById('file-label');
                if (fileLabel) {
                    fileLabel.textContent = '';
                }
            }
            const profilePageName = document.getElementById('profile-page-name');
            if (profilePageName) {
                profilePageName.textContent = data.nama;
            }
            const profilePageEmail = document.getElementById('profile-page-email');
            if (profilePageEmail) {
                profilePageEmail.textContent = data.email;
            }
            document.getElementById('page-title').textContent = pageTitle[document.querySelector('.page-section.active').id.replace('page-', '')] || 'Profil';
            showToast('✅ Profil berhasil disimpan!', 'success');
        } else {
            showToast('❌ ' + (data.message || 'Gagal menyimpan profil'), 'error');
        }
    })
    .catch(err => {
        console.error(err);
        showToast('❌ Terjadi kesalahan saat menyimpan profil', 'error');
    });
}

function ubahPassword() {
    const baru = document.getElementById('pass-baru').value;
    const konfirm = document.getElementById('pass-konfirm').value;
    if (!baru) { showToast('⚠️ Password baru tidak boleh kosong!', 'error'); return; }
    if (baru !== konfirm) { showToast('❌ Password tidak cocok!', 'error'); return; }
    if (baru.length < 6) { showToast('❌ Password minimal 6 karakter!', 'error'); return; }
    showToast('✅ Password berhasil diubah!', 'success');
    document.getElementById('pass-baru').value = '';
    document.getElementById('pass-konfirm').value = '';
}

// ── LOGOUT ────────────────────────────────────────────────────────────────────
document.getElementById('topbar-logout').addEventListener('click', () => {
    document.getElementById('modal-logout').classList.add('active');
});
document.getElementById('sidebar-logout').addEventListener('click', (e) => {
    e.preventDefault();
    document.getElementById('modal-logout').classList.add('active');
});

// ── NOTIFIKASI ────────────────────────────────────────────────────────────────
document.getElementById('notif-btn').addEventListener('click', () => {
    showToast('🔔 Anda memiliki <?php echo $pesanan_aktif; ?> pesanan aktif.', '');
});

// ── TOAST ─────────────────────────────────────────────────────────────────────
let toastTimer;
function showToast(msg, type = '') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className   = 'toast ' + type;
    t.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove('show'), 3000);
}

// ── INISIALISASI ──────────────────────────────────────────────────────────────
updateBadgeKeranjang();
</script>
</body>
</html>