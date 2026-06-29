<?php
session_start();
include "koneksi.php";

if (!isset($_SESSION['login']) || $_SESSION['role'] != "admin") {
    header("Location: login.php");
    exit;
}

$user = $_SESSION['user'];

$query_admin        = mysqli_query($conn, "SELECT * FROM admin WHERE username = '" . mysqli_real_escape_string($conn, $user) . "'");
$data_admin         = mysqli_fetch_assoc($query_admin);
$nama_display_admin = !empty($data_admin['nama_lengkap']) ? $data_admin['nama_lengkap']
                    : (!empty($data_admin['nama']) ? $data_admin['nama'] : $user);
$foto_admin         = !empty($data_admin['foto']) ? $data_admin['foto'] : '';
$initial            = strtoupper(substr($nama_display_admin, 0, 1));

/* ── Buat tabel jika belum ada ── */
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `voucher` (
  `id_voucher`       INT(11)  NOT NULL AUTO_INCREMENT,
  `kode_voucher`     VARCHAR(30)  NOT NULL,
  `tipe_diskon`      ENUM('persentase','nominal') NOT NULL DEFAULT 'persentase',
  `nilai_diskon`     DECIMAL(12,2) NOT NULL,
  `tanggal_mulai`    DATE NOT NULL,
  `tanggal_berakhir` DATE NOT NULL,
  `jumlah_maksimal`  INT(11) NOT NULL DEFAULT 0,
  `jumlah_terpakai`  INT(11) NOT NULL DEFAULT 0,
  `status`           ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  `keterangan`       VARCHAR(255) DEFAULT NULL,
  `dibuat_pada`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_voucher`),
  UNIQUE KEY `kode_voucher` (`kode_voucher`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

/* ── Toggle status ── */
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'toggle') {
    $id          = intval($_GET['id']);
    $status_skrg = $_GET['status'] ?? 'aktif';
    $status_baru = ($status_skrg === 'aktif') ? 'nonaktif' : 'aktif';
    mysqli_query($conn, "UPDATE voucher SET status='$status_baru' WHERE id_voucher=$id");
    header("Location: voucher.php");
    exit;
}

/* ── Hapus ── */
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete') {
    $id = intval($_GET['id']);
    mysqli_query($conn, "DELETE FROM voucher WHERE id_voucher=$id");
    header("Location: voucher.php");
    exit;
}

/* ── Tambah via POST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tambah') {
    $kode   = strtoupper(trim(mysqli_real_escape_string($conn, $_POST['kode_voucher']   ?? '')));
    $tipe   = in_array($_POST['tipe_diskon'] ?? '', ['persentase','nominal']) ? $_POST['tipe_diskon'] : 'persentase';
    $nilai  = floatval($_POST['nilai_diskon']  ?? 0);
    $mulai  = mysqli_real_escape_string($conn, $_POST['tanggal_mulai']    ?? '');
    $akhir  = mysqli_real_escape_string($conn, $_POST['tanggal_berakhir'] ?? '');
    $kuota  = intval($_POST['jumlah_maksimal'] ?? 0);
    $ket    = mysqli_real_escape_string($conn, $_POST['keterangan']        ?? '');
    $err    = '';
    if (!$kode || !$nilai || !$mulai || !$akhir) $err = 'Semua kolom wajib diisi!';
    if (!$err) {
        $r = mysqli_query($conn, "INSERT INTO voucher (kode_voucher,tipe_diskon,nilai_diskon,tanggal_mulai,tanggal_berakhir,jumlah_maksimal,keterangan)
             VALUES ('$kode','$tipe',$nilai,'$mulai','$akhir',$kuota,'$ket')");
        if (!$r) $err = 'Kode voucher sudah digunakan!';
    }
    if ($err) {
        $_SESSION['flash_err'] = $err;
    } else {
        $_SESSION['flash_ok']  = 'Voucher berhasil dibuat!';
    }
    header("Location: voucher.php");
    exit;
}

/* ── Statistik ── */
$total_v        = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM voucher"))['c'] ?? 0);
$aktif_v        = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM voucher WHERE status='aktif'"))['c'] ?? 0);
$total_terpakai = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(jumlah_terpakai),0) c FROM voucher"))['c'] ?? 0);

/* ── Data voucher ── */
$query_voucher = mysqli_query($conn, "SELECT * FROM voucher ORDER BY dibuat_pada DESC");

$flash_ok  = $_SESSION['flash_ok']  ?? ''; unset($_SESSION['flash_ok']);
$flash_err = $_SESSION['flash_err'] ?? ''; unset($_SESSION['flash_err']);
$current_file = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manajemen Voucher – Perlengkapan Sekolah</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

:root {
    --purple:      #6C63FF;
    --purple-d:    #5A52E0;
    --purple-bg:   #EEF0FF;
    --sidebar-bg:  #1E1B4B;
    --body-bg:     #F0F2FF;
    --card-bg:     #FFFFFF;
    --text:        #1E1B4B;
    --text-sub:    #6B7280;
    --border:      #E8EAFF;
    --green:       #22C55E;
    --green-bg:    #F0FDF4;
    --green-text:  #15803D;
    --amber:       #F59E0B;
    --amber-bg:    #FFFBEB;
    --amber-text:  #92400E;
    --red:         #EF4444;
    --red-bg:      #FEF2F2;
    --red-text:    --info-bg:     #EFF6FF;
    --info-text:   #1D4ED8;
    --radius-sm:   8px;
    --radius-md:   12px;
    --radius-lg:   16px;
    --shadow:      0 1px 4px rgba(0,0,0,.07);
}

body {
    font-family: 'Plus Jakarta Sans', sans-serif;
    background: var(--body-bg);
    color: var(--text);
    min-height: 100vh;
    display: flex;
}

/* ── SIDEBAR FIX SERAGAM 100% ── */
.sidebar {
    width: 220px; height: 100vh;
    background: var(--sidebar-bg);
    display: flex; flex-direction: column;
    position: fixed; top: 0; left: 0; z-index: 200;
}
.sb-logo { padding: 18px 20px; border-bottom: 1px solid rgba(255,255,255,.08); }
.sb-logo .logo-name { color: #fff; font-size: 14px; font-weight: 700; }
.sb-logo .logo-sub  { color: rgba(255,255,255,.6); font-size: 11px; margin-top: 4px; }
.sb-logo .logo-sub span { color: #8B85FF; font-weight: 600; }

.sb-nav { padding: 12px 12px; display: flex; flex-direction: column; gap: 4px; overflow: hidden; }
.sb-nav a {
    display: flex; align-items: center; gap: 12px;
    padding: 16px 14px; border-radius: var(--radius-md);
    color: rgba(255,255,255,.7); font-size: 12.5px; font-weight: 600;
    text-decoration: none; transition: all .2s;
}
.sb-nav a .ic {
    width: 26px; height: 26px; border-radius: 8px;
    background: rgba(255,255,255,.08);
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; flex-shrink: 0; transition: background .2s;
}
.sb-nav a:hover, .sb-nav a.active { color: #fff; background: rgba(108,99,255,.2); }
.sb-nav a.active .ic { background: linear-gradient(135deg, var(--purple), #8B85FF); }

/* ── MAIN ── */
.main { margin-left: 220px; flex: 1; display: flex; flex-direction: column; min-height: 100vh; }

/* ── TOPBAR ── */
.topbar {
    height: 64px; background: var(--card-bg);
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 28px; position: sticky; top: 0; z-index: 100;
}
.tb-left h1 { font-size: 18px; font-weight: 700; }
.tb-left p  { font-size: 12px; color: var(--text-sub); margin-top: 1px; }
.tb-right { display: flex; align-items: center; gap: 14px; }
.avatar-box {
    width: 38px; height: 38px; border-radius: 10px;
    background: linear-gradient(135deg, var(--purple), #a78bfa);
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-weight: 700; font-size: 14px; overflow: hidden;
}
.avatar-box img { width: 100%; height: 100%; object-fit: cover; }

/* ── CONTENT ── */
.content { padding: 28px 32px; flex: 1; }

/* Flash messages */
.flash {
    padding: 12px 18px; border-radius: var(--radius-sm);
    font-size: 13px; font-weight: 600; margin-bottom: 18px;
    display: flex; align-items: center; gap: 8px;
}
.flash.ok  { background: var(--green-bg); color: var(--green-text); border: 1px solid #BBF7D0; }
.flash.err { background: var(--red-bg);   color: var(--red-text);   border: 1px solid #FECACA; }

/* Header row */
.page-hrow {
    display: flex; justify-content: space-between; align-items: flex-start;
    margin-bottom: 22px;
}
.page-hrow .pt  { font-size: 18px; font-weight: 800; }
.page-hrow .ps  { font-size: 12.5px; color: var(--text-sub); margin-top: 3px; }

/* Primary button */
.btn-primary {
    display: inline-flex; align-items: center; gap: 7px;
    background: var(--purple); color: #fff; border: none;
    padding: 10px 20px; border-radius: var(--radius-sm);
    font-family: inherit; font-size: 13.5px; font-weight: 700;
    cursor: pointer; text-decoration: none; transition: background .18s;
    white-space: nowrap;
}
.btn-primary:hover { background: var(--purple-d); color: #fff; }

/* Stat cards */
.stats-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 14px; margin-bottom: 22px; }
.stat-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-left: 4px solid var(--purple);
    border-radius: var(--radius-md);
    padding: 16px 18px;
    box-shadow: var(--shadow);
}
.stat-card.g { border-left-color: var(--green); }
.stat-card.o { border-left-color: var(--amber); }
.stat-label {
    font-size: 11px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .05em; color: var(--text-sub); margin-bottom: 8px;
    display: flex; align-items: center; gap: 6px;
}
.stat-label svg { width: 14px; height: 14px; }
.stat-val { font-size: 26px; font-weight: 800; color: var(--text); }
.stat-card.g .stat-val { color: var(--green-text); }
.stat-card.o .stat-val { color: var(--amber-text); }

/* Table card */
.table-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    overflow: hidden;
}
.table-toolbar {
    padding: 14px 18px;
    display: flex; align-items: center; justify-content: space-between;
    border-bottom: 1px solid var(--border);
}
.tbl-title { font-size: 14px; font-weight: 700; }
.search-box {
    display: flex; align-items: center; gap: 7px;
    background: var(--body-bg); border: 1px solid var(--border);
    border-radius: var(--radius-sm); padding: 7px 12px;
}
.search-box input {
    border: none; background: transparent;
    font-family: inherit; font-size: 13px; outline: none;
    color: var(--text); width: 200px;
}
.search-box svg { width: 14px; height: 14px; color: var(--text-sub); flex-shrink: 0; }

.tbl-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; min-width: 760px; }
thead tr { background: #F8F9FF; border-bottom: 1px solid var(--border); }
th { padding: 12px 14px; text-align: left; font-size: 11.5px; font-weight: 700; color: var(--text-sub); white-space: nowrap; }
th:last-child { text-align: right; }
td { padding: 13px 14px; font-size: 13px; vertical-align: middle; border-bottom: 1px solid #F3F4F6; }
tr:last-child td { border-bottom: none; }
tr:hover td { background: #FAFAFE; }

/* Code pill */
.code-pill {
    display: inline-flex; align-items: center; gap: 5px;
    background: #F1F0FF; border: 1px solid #D4D2FF;
    border-radius: 7px; padding: 5px 10px;
    font-size: 12.5px; font-weight: 700;
    color: var(--purple); font-family: monospace; letter-spacing: .03em;
}
.td-sub { font-size: 11.5px; color: var(--text-sub); margin-top: 3px; }

/* Badges */
.badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 4px 10px; border-radius: 6px;
    font-size: 11.5px; font-weight: 700;
}
.badge-persen  { background: var(--info-bg); color: var(--info-text); }
.badge-nominal { background: var(--purple-bg); color: var(--purple-d); }
.badge-aktif   { background: var(--green-bg); color: var(--green-text); cursor: pointer; text-decoration: none; }
.badge-nonaktif { background: var(--red-bg); color: var(--red-text); cursor: pointer; text-decoration: none; }

/* Date display */
.date-end { color: var(--red); font-size: 11.5px; }
.date-start { font-size: 11.5px; color: var(--text-sub); }

/* Progress bar kuota */
.kuota-wrap { display: flex; flex-direction: column; gap: 4px; }
.kuota-text { font-size: 12px; font-weight: 600; color: var(--text); }
.kuota-bar { height: 4px; background: #E8EAFF; border-radius: 4px; overflow: hidden; }
.kuota-fill { height: 100%; background: var(--purple); border-radius: 4px; transition: width .3s; }

/* Action buttons */
.act-group { display: flex; gap: 6px; justify-content: flex-end; }
.btn-sm {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 5px 11px; border-radius: 7px;
    font-family: inherit; font-size: 12px; font-weight: 600;
    cursor: pointer; border: 1px solid var(--border);
    background: #fff; color: var(--text-sub); transition: all .15s;
    text-decoration: none;
}
.btn-sm:hover { border-color: var(--purple); color: var(--purple); background: var(--purple-bg); }
.btn-sm.danger { border-color: #FECACA; color: var(--red); }
.btn-sm.danger:hover { background: var(--red-bg); }
.btn-sm svg { width: 12px; height: 12px; }

/* Empty state */
.empty-state { padding: 56px 20px; text-align: center; }
.empty-state svg { width: 52px; height: 52px; color: #C4C7E3; margin-bottom: 14px; }
.empty-state p { color: var(--text-sub); font-size: 14px; }

/* ── MODAL ── */
.modal-bg {
    display: none; position: fixed; inset: 0; z-index: 500;
    background: rgba(30,27,75,.45);
    align-items: center; justify-content: center;
}
.modal-bg.open { display: flex; }
.modal {
    background: var(--card-bg); border-radius: var(--radius-lg);
    width: 480px; max-width: 95vw; max-height: 90vh;
    overflow-y: auto; padding: 26px 28px;
    border: 1px solid var(--border);
}
.modal-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 22px;
}
.modal-header h2 { font-size: 16px; font-weight: 800; }
.modal-close {
    width: 32px; height: 32px; border-radius: 8px;
    border: 1px solid var(--border); background: #fff;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; color: var(--text-sub); font-size: 16px;
    transition: all .15s;
}
.modal-close:hover { background: var(--red-bg); color: var(--red); border-color: #FECACA; }

.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 16px; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-group.full { grid-column: 1/-1; }
.form-group label { font-size: 12px; font-weight: 700; color: var(--text-sub); }
.form-group label .req { color: var(--red); margin-left: 2px; }
.form-group input,
.form-group select,
.form-group textarea {
    width: 100%; border: 1.5px solid var(--border); border-radius: var(--radius-sm);
    padding: 9px 12px; font-family: inherit; font-size: 13.5px;
    color: var(--text); outline: none; background: #fff;
    transition: border-color .18s;
}
.form-group input:focus,
.form-group select:focus { border-color: var(--purple); box-shadow: 0 0 0 3px rgba(108,99,255,.1); }
.form-group input::placeholder { color: #C4C7E3; }
.modal-footer { display: flex; gap: 10px; justify-content: flex-end; margin-top: 22px; }
.btn-cancel {
    padding: 10px 20px; border: 1.5px solid var(--border);
    border-radius: var(--radius-sm); background: #fff;
    font-family: inherit; font-size: 13.5px; font-weight: 700;
    color: var(--text-sub); cursor: pointer; transition: all .15s;
}
.btn-cancel:hover { border-color: var(--text-sub); }

/* ── TOAST ── */
.toast {
    position: fixed; bottom: 24px; right: 24px; z-index: 900;
    background: #1E1B4B; color: #fff; padding: 12px 20px;
    border-radius: 12px; font-size: 13px; font-weight: 700;
    transform: translateY(12px); opacity: 0; transition: all .28s;
    pointer-events: none;
}
.toast.show { transform: translateY(0); opacity: 1; }
.toast.ok  { background: var(--green); }
.toast.err { background: var(--red); }
</style>
</head>
<body>

<aside class="sidebar">
    <div class="sb-logo">
        <div class="logo-name">🎒 Perlengkapan Sekolah</div>
        <div class="logo-sub">Admin: <span><?= htmlspecialchars($nama_display_admin) ?></span></div>
    </div>
    <nav class="sb-nav">
        <a href="dashboard_admin.php" class="<?= $current_file=='dashboard_admin.php'?'active':'' ?>">
            <span class="ic">🏠</span> Dashboard
        </a>
        <a href="data_barang.php" class="<?= $current_file=='data_barang.php'?'active':'' ?>">
            <span class="ic">📦</span> Data Barang
        </a>
        <a href="pesanan.php" class="<?= $current_file=='pesanan.php'?'active':'' ?>">
            <span class="ic">🛒</span> Pesanan
        </a>
        <a href="pembayaran.php" class="<?= $current_file=='pembayaran.php'?'active':'' ?>">
            <span class="ic">💳</span> Pembayaran
        </a>
        <a href="pelanggan.php" class="<?= $current_file=='pelanggan.php'?'active':'' ?>">
            <span class="ic">👥</span> Pelanggan
        </a>
        <a href="laporan.php" class="<?= $current_file=='laporan.php'?'active':'' ?>">
            <span class="ic">📊</span> Laporan
        </a>
        <a href="voucher.php" class="<?= ($current_file=='voucher.php' || $current_file=='tambah_voucher.php' || $current_file=='edit_voucher.php')?'active':'' ?>">
            <span class="ic">🎟️</span> Voucher
        </a>
        <a href="pengaturan.php" class="<?= $current_file=='pengaturan.php'?'active':'' ?>">
            <span class="ic">⚙️</span> Pengaturan
        </a>
    </nav>
</aside>

<div class="main">
    <header class="topbar">
        <div class="tb-left">
            <h1>Manajemen Voucher</h1>
            <p><?= date('l, d F Y') ?></p>
        </div>
        <div class="tb-right">
            <div class="avatar-box">
                <?php if ($foto_admin): ?>
                    <img src="uploads/<?= htmlspecialchars($foto_admin) ?>" alt="avatar">
                <?php else: echo $initial; endif; ?>
            </div>
        </div>
    </header>

    <div class="content">

        <?php if ($flash_ok): ?>
            <div class="flash ok">&#10003; <?= htmlspecialchars($flash_ok) ?></div>
        <?php elseif ($flash_err): ?>
            <div class="flash err">&#9888; <?= htmlspecialchars($flash_err) ?></div>
        <?php endif; ?>

        <div class="page-hrow">
            <div>
                <div class="pt">Daftar Voucher Toko</div>
                <div class="ps">Kelola aturan promo pemotongan belanja secara efisien</div>
            </div>
            <button class="btn-primary" onclick="document.getElementById('modal-tambah').classList.add('open')">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Buat Voucher Baru
            </button>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2z"/></svg>
                    Total Voucher
                </div>
                <div class="stat-val"><?= $total_v ?></div>
            </div>
            <div class="stat-card g">
                <div class="stat-label">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    Voucher Aktif
                </div>
                <div class="stat-val"><?= $aktif_v ?></div>
            </div>
            <div class="stat-card o">
                <div class="stat-label">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                    Total Digunakan
                </div>
                <div class="stat-val"><?= $total_terpakai ?>x</div>
            </div>
        </div>

        <div class="table-card">
            <div class="table-toolbar">
                <span class="tbl-title">Semua Voucher</span>
                <div class="search-box">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" id="search-input" placeholder="Cari kode voucher..." oninput="filterTable(this.value)">
                </div>
            </div>
            <div class="tbl-wrap">
                <table id="voucher-table">
                    <thead>
                        <tr>
                            <th style="padding-left:20px">Kode Voucher</th>
                            <th>Tipe</th>
                            <th>Nilai Potongan</th>
                            <th>Masa Berlaku</th>
                            <th>Kuota (Terpakai)</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (mysqli_num_rows($query_voucher) == 0): ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2z"/><path d="M13 5v14"/></svg>
                                    <p>Belum ada voucher. Klik <strong>Buat Voucher Baru</strong> untuk memulai.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php while ($v = mysqli_fetch_assoc($query_voucher)):
                            $pct = ($v['jumlah_maksimal'] > 0)
                                ? min(100, round($v['jumlah_terpakai'] / $v['jumlah_maksimal'] * 100))
                                : 0;
                        ?>
                        <tr>
                            <td style="padding-left:20px">
                                <div class="code-pill"><?= htmlspecialchars($v['kode_voucher']) ?></div>
                                <?php if ($v['keterangan']): ?>
                                    <div class="td-sub"><?= htmlspecialchars($v['keterangan']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $v['tipe_diskon']=='persentase'?'badge-persen':'badge-nominal' ?>">
                                    <?= $v['tipe_diskon']=='persentase' ? '%  Persentase' : 'Rp Nominal' ?>
                                </span>
                            </td>
                            <td style="font-weight:700">
                                <?= $v['tipe_diskon']=='persentase'
                                    ? number_format($v['nilai_diskon'],0).'%'
                                    : 'Rp '.number_format($v['nilai_diskon'],0,',','.') ?>
                            </td>
                            <td>
                                <div class="date-start"><?= date('d M Y', strtotime($v['tanggal_mulai'])) ?></div>
                                <div class="date-end">&#9719; <?= date('d M Y', strtotime($v['tanggal_berakhir'])) ?></div>
                            </td>
                            <td>
                                <div class="kuota-wrap">
                                    <div class="kuota-text">
                                        <?= $v['jumlah_terpakai'] ?> / <?= $v['jumlah_maksimal']==0 ? '&#8734;' : $v['jumlah_maksimal'] ?>
                                    </div>
                                    <?php if ($v['jumlah_maksimal'] > 0): ?>
                                    <div class="kuota-bar">
                                        <div class="kuota-fill" style="width:<?= $pct ?>%"></div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <a href="voucher.php?action=toggle&id=<?= $v['id_voucher'] ?>&status=<?= $v['status'] ?>"
                                   class="badge <?= $v['status']=='aktif'?'badge-aktif':'badge-nonaktif' ?>"
                                   title="Klik untuk toggle status">
                                     <?= $v['status']=='aktif' ? '&#9679; Aktif' : '&#9675; Nonaktif' ?>
                                </a>
                            </td>
                            <td>
                                <div class="act-group">
                                    <a href="edit_voucher.php?id=<?= $v['id_voucher'] ?>" class="btn-sm">
                                        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        Ubah
                                    </a>
                                    <a href="voucher.php?action=delete&id=<?= $v['id_voucher'] ?>"
                                       class="btn-sm danger"
                                       onclick="return confirm('Hapus voucher <?= htmlspecialchars($v['kode_voucher']) ?>?')">
                                        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                                        Hapus
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div></div><div class="modal-bg" id="modal-tambah" onclick="if(event.target===this)this.classList.remove('open')">
    <div class="modal">
        <div class="modal-header">
            <h2>Buat Voucher Baru</h2>
            <button class="modal-close" onclick="document.getElementById('modal-tambah').classList.remove('open')" aria-label="Tutup">&times;</button>
        </div>
        <form method="POST" action="voucher.php" id="form-tambah">
            <input type="hidden" name="action" value="tambah">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Kode Voucher <span class="req">*</span></label>
                    <input type="text" name="kode_voucher" placeholder="Contoh: HEMAT20"
                           style="text-transform:uppercase;font-family:monospace;font-weight:700;letter-spacing:.05em"
                           required maxlength="30">
                </div>
                <div class="form-group">
                    <label>Tipe Diskon <span class="req">*</span></label>
                    <select name="tipe_diskon" id="sel-tipe" onchange="updatePlaceholder()">
                        <option value="persentase">Persentase (%)</option>
                        <option value="nominal">Nominal (Rp)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Nilai Diskon <span class="req">*</span></label>
                    <input type="number" name="nilai_diskon" id="inp-nilai"
                           placeholder="Contoh: 20" min="0" step="any" required>
                </div>
                <div class="form-group">
                    <label>Tanggal Mulai <span class="req">*</span></label>
                    <input type="date" name="tanggal_mulai" required value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label>Tanggal Berakhir <span class="req">*</span></label>
                    <input type="date" name="tanggal_berakhir" required>
                </div>
                <div class="form-group">
                    <label>Kuota Maksimal</label>
                    <input type="number" name="jumlah_maksimal" placeholder="0 = tidak terbatas" min="0">
                </div>
                <div class="form-group">
                    <label>Keterangan</label>
                    <input type="text" name="keterangan" placeholder="Deskripsi singkat (opsional)">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel"
                        onclick="document.getElementById('modal-tambah').classList.remove('open')">Batal</button>
                <button type="submit" class="btn-primary">Simpan Voucher</button>
            </div>
        </form>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
function filterTable(q) {
    q = q.toLowerCase();
    document.querySelectorAll('#voucher-table tbody tr').forEach(tr => {
        tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}

function updatePlaceholder() {
    const tipe = document.getElementById('sel-tipe').value;
    document.getElementById('inp-nilai').placeholder = tipe === 'persentase' ? 'Contoh: 20 (persen)' : 'Contoh: 50000';
}

let _t;
function showToast(msg, type) {
    const t = document.getElementById('toast');
    t.textContent = msg; t.className = 'toast ' + type + ' show';
    clearTimeout(_t); _t = setTimeout(() => t.classList.remove('show'), 3000);
}

<?php if ($flash_ok): ?>
showToast(<?= json_encode($flash_ok) ?>, 'ok');
<?php elseif ($flash_err): ?>
showToast(<?= json_encode($flash_err) ?>, 'err');
<?php endif; ?>

document.getElementById('form-tambah').addEventListener('submit', function(e) {
    const kode = this.querySelector('[name="kode_voucher"]').value.trim();
    const nilai = parseFloat(this.querySelector('[name="nilai_diskon"]').value);
    const mulai = this.querySelector('[name="tanggal_mulai"]').value;
    const akhir = this.querySelector('[name="tanggal_berakhir"]').value;
    if (!kode || !nilai || !mulai || !akhir) {
        e.preventDefault();
        showToast('Semua kolom wajib diisi!', 'err');
        return;
    }
    if (akhir < mulai) {
        e.preventDefault();
        showToast('Tanggal berakhir tidak boleh sebelum tanggal mulai!', 'err');
        return;
    }
});
</script>
</body>
</html>