<?php
session_start();
include "koneksi.php";

if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

$role = $_SESSION['role'];
$user = $_SESSION['user'];

// Redirect kalau bukan admin atau pelanggan
if ($role !== 'admin' && $role !== 'pelanggan') {
    header("Location: login.php");
    exit;
}

if ($role === 'admin') {
    $query_user = mysqli_query($conn, "SELECT * FROM admin WHERE username = '" . mysqli_real_escape_string($conn, $user) . "'");
    $data_user  = mysqli_fetch_assoc($query_user);
    $nama_display = !empty($data_user['nama_lengkap']) ? $data_user['nama_lengkap'] : (!empty($data_user['nama']) ? $data_user['nama'] : $user);
    $foto_user    = !empty($data_user['foto']) ? $data_user['foto'] : '';
    $endpoint_profil   = 'update_admin_profile.php';
    $endpoint_password = 'update_admin_password.php';
    $endpoint_get      = 'get_admin_profile.php';
} else {
    $query_user = mysqli_query($conn, "SELECT * FROM pelanggan WHERE username = '" . mysqli_real_escape_string($conn, $user) . "'");
    $data_user  = mysqli_fetch_assoc($query_user);
    $nama_display = !empty($data_user['nama_lengkap']) ? $data_user['nama_lengkap'] : $user;
    $foto_user    = !empty($data_user['foto_profil']) ? $data_user['foto_profil'] : '';
    $endpoint_profil   = 'update_profile.php';
    $endpoint_password = 'update_password_pelanggan.php';
    $endpoint_get      = 'get_pelanggan_profile.php';
}

$current_file = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pengaturan Akun – Perlengkapan Sekolah</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800&display=swap" rel="stylesheet">
<style>
/* ─── RESET & ROOT (disamakan dengan tema utama admin panel) ─── */
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

:root {
    --primary:     #6C63FF;
    --primary-2:   #8B85FF;
    --secondary:   #FF6584;
    --accent:      #43E97B;
    --bg:          #F0F2FF;
    --sidebar-bg:  #1E1B4B;
    --card-bg:     #FFFFFF;
    --text:        #1E1B4B;
    --muted:       #8B8FAD;
    --border:      #E8EAFF;
    --input-bg:    #F8F8FF;
    --danger:      #FF6584;
    --danger-dark: #E54F68;
    --success:     #43E97B;
    --warning:     #FA8231;
    --radius-sm:   8px;
    --radius-md:   12px;
    --radius-lg:   16px;
    --shadow-sm:   0 1px 3px rgba(30,27,75,.06), 0 1px 2px rgba(30,27,75,.04);
    --shadow-md:   0 4px 16px rgba(30,27,75,.07);
}

body {
    font-family: 'Plus Jakarta Sans', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    display: flex;
}

/* ─── SIDEBAR (sama persis dengan dashboard_admin.php / data_barang.php) ─── */
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

.sidebar-menu::-webkit-scrollbar { width: 5px; }
.sidebar-menu::-webkit-scrollbar-track { background: transparent; }
.sidebar-menu::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.1); border-radius: 10px; }
.sidebar-menu:hover::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.25); }

.sidebar-menu a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 14px;
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

.sidebar-footer a:hover { color: white; background: var(--danger); }
.sidebar-footer a:hover .icon { background: rgba(255, 255, 255, 0.2); }

/* ─── MAIN ─── */
.main { margin-left: 220px; flex: 1; display: flex; flex-direction: column; min-height: 100vh; }

/* ─── TOPBAR ─── */
.topbar {
    background: white;
    padding: 0 28px;
    height: 68px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid var(--border);
    position: sticky;
    top: 0;
    z-index: 50;
}
.topbar-left h1 { font-size: 18px; font-weight: 700; }
.topbar-left p  { font-size: 12px; color: var(--muted); margin-top: 1px; }
.topbar-right { display: flex; align-items: center; gap: 14px; }
.avatar {
    width: 38px; height: 38px; border-radius: 10px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    display: flex; align-items: center; justify-content: center;
    color: white; font-weight: 700; font-size: 14px;
    overflow: hidden;
}

/* ─── PAGE CONTENT ─── */
.content { padding: 28px 32px; flex: 1; }

.page-title {
    font-size: 21px; font-weight: 800; color: var(--text);
    margin-bottom: 24px; letter-spacing: -0.4px;
}

/* ─── SETTINGS CARD ─── */
.settings-card {
    background: var(--card-bg);
    border-radius: var(--radius-lg);
    border: 1.5px solid var(--border);
    box-shadow: var(--shadow-sm);
    display: flex;
    flex-direction: column;
    min-height: 600px;
    overflow: hidden;
}

.settings-topnav {
    display: flex;
    gap: 8px;
    padding: 16px 24px 0 24px;
    border-bottom: 1px solid var(--border);
    background: #FAFAFF;
}
.stnav-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    font-family: inherit;
    font-size: 14px;
    font-weight: 600;
    color: var(--muted);
    cursor: pointer;
    transition: all .18s;
}
.stnav-btn:hover { color: var(--primary); }
.stnav-btn.active { color: var(--primary); border-bottom-color: var(--primary); }

.settings-body { flex: 1; padding: 32px 32px 40px; overflow-x: hidden; }

.tab-panel { display: none; }
.tab-panel.active { display: block; }

/* ─── AVATAR UPLOAD ROW ─── */
.avatar-row {
    display: flex; align-items: center; gap: 22px; margin-bottom: 32px;
}
.avatar-wrap { position: relative; flex-shrink: 0; }
.avatar-circle {
    width: 100px; height: 100px; border-radius: 50%;
    background: var(--bg);
    display: flex; align-items: center; justify-content: center;
    font-size: 32px; font-weight: 800; color: var(--primary);
    overflow: hidden;
    border: 3px solid white;
    box-shadow: 0 0 0 2px var(--border);
}
.avatar-circle img { width: 100%; height: 100%; object-fit: cover; }
.cam-badge {
    position: absolute; bottom: 2px; right: 2px;
    width: 30px; height: 30px; border-radius: 50%;
    background: var(--primary); border: 2.5px solid white;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; cursor: pointer; color: white;
    box-shadow: var(--shadow-sm);
    transition: background .18s;
}
.cam-badge:hover { background: var(--primary-2); }
.avatar-btns { display: flex; gap: 10px; flex-wrap: wrap; }

/* ─── BUTTONS ─── */
.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-2));
    color: white; border: none;
    padding: 10px 22px; border-radius: var(--radius-sm);
    font-family: inherit; font-size: 13.5px; font-weight: 700;
    cursor: pointer; transition: all .18s; display: inline-flex; align-items: center; gap: 6px;
    box-shadow: 0 4px 12px rgba(108,99,255,0.2);
}
.btn-primary:hover { transform: translateY(-1px); opacity: .92; }
.btn-primary.large { padding: 13px 36px; font-size: 14px; border-radius: 10px; }

.btn-outline {
    background: white; color: var(--muted);
    border: 1px solid var(--border);
    padding: 10px 22px; border-radius: var(--radius-sm);
    font-family: inherit; font-size: 13.5px; font-weight: 700;
    cursor: pointer; transition: all .18s;
}
.btn-outline:hover { border-color: var(--primary); color: var(--primary); }

.btn-danger {
    background: var(--danger); color: white; border: none;
    padding: 10px 22px; border-radius: var(--radius-sm);
    font-family: inherit; font-size: 13.5px; font-weight: 700;
    cursor: pointer; transition: background .18s;
}
.btn-danger:hover { background: var(--danger-dark); }

/* ─── FORM GRID ─── */
.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px 24px;
    margin-bottom: 8px;
}
.form-field { display: flex; flex-direction: column; gap: 7px; }
.form-field.full { grid-column: 1 / -1; }

.form-field label {
    font-size: 12.5px; font-weight: 700; color: #374151; letter-spacing: 0.01em;
}
.form-field label .req { color: var(--danger); margin-left: 2px; }

.form-field input,
.form-field textarea {
    width: 100%;
    border: 1.5px solid var(--border);
    border-radius: 10px;
    padding: 11px 14px;
    font-size: 14px; font-family: inherit;
    color: var(--text); outline: none;
    background: white;
    transition: border-color .2s, box-shadow .2s;
}
.form-field input:focus,
.form-field textarea:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(108,99,255,.12);
}
.form-field input::placeholder,
.form-field textarea::placeholder { color: #C4C9D4; }
.form-field input[readonly] {
    background: var(--input-bg); color: var(--muted); cursor: not-allowed;
}
.form-field textarea { min-height: 88px; resize: vertical; }

.form-actions { margin-top: 28px; }

/* ─── PASSWORD ─── */
.section-title { font-size: 15px; font-weight: 800; color: var(--text); margin-bottom: 22px; }
.password-form-wrap { max-width: 420px; display: flex; flex-direction: column; gap: 18px; }
.password-toggle-wrap { position: relative; }
.password-toggle-wrap input { padding-right: 44px; }
.pass-eye {
    position: absolute; right: 13px; top: 50%; transform: translateY(-50%);
    font-size: 16px; cursor: pointer; color: var(--muted); user-select: none;
    transition: color .18s;
}
.pass-eye:hover { color: var(--primary); }

/* ─── TOGGLE SWITCHES ─── */
.notif-list { display: flex; flex-direction: column; gap: 0; margin-bottom: 24px; }
.notif-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 16px 0; border-bottom: 1px solid var(--border);
}
.notif-row:first-child { border-top: 1px solid var(--border); }
.notif-info .notif-label { font-size: 14px; font-weight: 700; color: var(--text); margin-bottom: 2px; }
.notif-info .notif-desc { font-size: 12.5px; color: var(--muted); }

.toggle-sw { position: relative; width: 46px; height: 26px; flex-shrink: 0; }
.toggle-sw input { opacity: 0; width: 0; height: 0; position: absolute; }
.toggle-track {
    position: absolute; inset: 0;
    background: #E5E7EB; border-radius: 26px; cursor: pointer; transition: background .25s;
}
.toggle-track::before {
    content: ''; position: absolute;
    width: 20px; height: 20px; left: 3px; top: 3px;
    background: white; border-radius: 50%;
    box-shadow: var(--shadow-sm); transition: transform .25s;
}
.toggle-sw input:checked + .toggle-track { background: var(--primary); }
.toggle-sw input:checked + .toggle-track::before { transform: translateX(20px); }

/* ─── VERIFICATION ─── */
.verify-banner {
    background: #EDFFF5; border: 1.5px solid #B9F5D0; border-radius: var(--radius-md);
    padding: 20px 22px; display: flex; align-items: flex-start; gap: 16px; margin-bottom: 24px;
}
.verify-icon {
    width: 46px; height: 46px; border-radius: 50%;
    background: #D2FAE3;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; flex-shrink: 0; color: var(--success);
}
.verify-banner h4 { font-size: 14.5px; font-weight: 800; color: #1B7A45; margin-bottom: 5px; }
.verify-banner p { font-size: 13px; color: #2A9D5C; line-height: 1.55; }

.danger-zone {
    background: #FFF0F3; border: 1.5px solid #FFD1DC;
    border-radius: var(--radius-md); padding: 22px 24px;
}
.danger-zone .dz-title { font-size: 14.5px; font-weight: 800; color: var(--danger); margin-bottom: 6px; }
.danger-zone .dz-desc { font-size: 13px; color: #C2466A; margin-bottom: 18px; line-height: 1.6; }

/* ─── TOAST ─── */
.toast {
    position: fixed; bottom: 26px; right: 26px;
    background: var(--sidebar-bg); color: white;
    padding: 13px 20px; border-radius: 12px;
    font-size: 13px; font-weight: 700;
    z-index: 999; pointer-events: none;
    transform: translateY(16px); opacity: 0; transition: all .28s;
    display: flex; align-items: center; gap: 8px;
}
.toast.show { transform: translateY(0); opacity: 1; }
.toast.success { background: var(--success); }
.toast.error   { background: var(--danger); }

/* ─── RESPONSIVE ─── */
@media (max-width: 960px) {
    .sidebar { transform: translateX(-100%); transition: transform .3s; }
    .sidebar.open { transform: translateX(0); }
    .main { margin-left: 0; }
    .settings-topnav { padding: 12px 14px 0 14px; flex-wrap: wrap; gap: 2px; }
    .stnav-btn { flex: 1; min-width: 100px; padding: 10px 8px; font-size: 13px; text-align: center; justify-content: center; }
    .settings-body { padding: 24px 18px; }
    .form-grid { grid-template-columns: 1fr; }
    .topbar { padding: 0 16px; }
}
</style>
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-logo">
            <h2>🎒 Perlengkapan Sekolah</h2>
            <span class="admin-meta">
                <?= $role === 'admin' ? 'Admin' : 'Pelanggan' ?>: 
                <span style="color:#8B85FF; font-weight:600;"><?php echo htmlspecialchars($nama_display); ?></span>
            </span>
        </div>

        <div class="sidebar-menu">
        <?php if ($role === 'admin'): ?>
            <a href="dashboard_admin.php"><div class="icon">🏠</div> Dashboard</a>
            <a href="data_barang.php"><div class="icon">📦</div> Data Barang</a>
            <a href="pesanan.php"><div class="icon">🛒</div> Pesanan</a>
            <a href="pembayaran.php"><div class="icon">💳</div> Pembayaran</a>
            <a href="pelanggan.php"><div class="icon">👥</div> Pelanggan</a>
            <a href="laporan.php"><div class="icon">📊</div> Laporan</a>
            <a href="voucher.php"><div class="icon">🎟️</div> Voucher</a>
            <a href="pengaturan.php" class="active"><div class="icon">⚙️</div> Pengaturan</a>
            <a href="logout.php"><div class="icon">🚪</div> Keluar</a>
        <?php else: ?>
            <a href="dashboard_pelanggan.php"><div class="icon">🏠</div> Dashboard</a>
            <a href="dashboard_pelanggan.php#produk"><div class="icon">📦</div> Produk</a>
            <a href="dashboard_pelanggan.php#keranjang"><div class="icon">🛒</div> Keranjang</a>
            <a href="dashboard_pelanggan.php#pesanan"><div class="icon">📋</div> Pesanan Saya</a>
            <a href="dashboard_pelanggan.php#pembayaran"><div class="icon">💳</div> Pembayaran</a>
            <a href="dashboard_pelanggan.php#profil"><div class="icon">👤</div> Profil Saya</a>
            <a href="pengaturan.php" class="active"><div class="icon">⚙️</div> Pengaturan</a>
            <a href="logout.php"><div class="icon">🚪</div> Keluar</a>
        <?php endif; ?>
        </div>
    </div>

    <div class="main">

        <div class="topbar">
            <div class="topbar-left">
                <h1>Pengaturan Akun</h1>
                <p>Kelola profil, keamanan, dan preferensi akun <?= $role === 'admin' ? 'admin' : 'kamu' ?></p>
            </div>
            <div class="topbar-right">
                <div class="avatar" id="topbar-avatar">
                    <?php if (!empty($foto_user)): ?>
                        <img src="uploads/<?= htmlspecialchars($foto_user) ?>" alt="avatar" style="width:100%;height:100%;object-fit:cover;">
                    <?php else: echo strtoupper(substr($user, 0, 1)); endif; ?>
                </div>
            </div>
        </div>

            <div class="settings-card">

                <nav class="settings-topnav">
                    <button type="button" class="stnav-btn active" onclick="switchTab(this,'profile')">Profil</button>
                    <button type="button" class="stnav-btn" onclick="switchTab(this,'password')">Password</button>
                    <button type="button" class="stnav-btn" onclick="switchTab(this,'notifications')">Notifikasi</button>
                    <button type="button" class="stnav-btn" onclick="switchTab(this,'verification')">Verifikasi</button>
                </nav>

                <div class="settings-body">

                    <div class="tab-panel active" id="tab-profile">
                        <div class="avatar-row">
                            <div class="avatar-wrap">
                                <div class="avatar-circle" id="avatar-preview">
                                    <?php if (!empty($foto_user)): ?>
                                        <img src="uploads/<?= htmlspecialchars($foto_user) ?>" alt="avatar" id="avatar-img">
                                    <?php else: ?>
                                        <span id="avatar-initial"><?= strtoupper(substr($user, 0, 2)) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="cam-badge" onclick="document.getElementById('file-foto').click()" title="Ubah foto">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                                </div>
                            </div>
                            <div class="avatar-btns">
                                <input type="file" id="file-foto" style="display:none" accept="image/*" onchange="uploadFoto()">
                                <button type="button" class="btn-primary" onclick="document.getElementById('file-foto').click()">Upload Baru</button>
                                <button type="button" class="btn-outline" onclick="hapusFoto()">Hapus Avatar</button>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-field full">
                                <label for="f-nama-lengkap">Nama Lengkap <span class="req">*</span></label>
                                <input type="text" id="f-nama-lengkap" placeholder="Nama lengkap">
                            </div>
                            <div class="form-field">
                                <label for="f-email">Email <span class="req">*</span></label>
                                <input type="email" id="f-email" placeholder="contoh@email.com">
                            </div>
                            <div class="form-field">
                                <label for="f-phone">Nomor Telepon</label>
                                <input type="text" id="f-phone" placeholder="08xxxxxxxxxx">
                            </div>
                            <div class="form-field full">
                                <label for="f-username">Username</label>
                                <input type="text" id="f-username" value="<?= htmlspecialchars($user) ?>" readonly>
                            </div>
                            <div class="form-field full">
                                <label for="f-address">Alamat Tempat Tinggal</label>
                                <textarea id="f-address" placeholder="Masukkan alamat lengkap Anda..."></textarea>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="btn-primary large" onclick="simpanProfil()">Simpan Perubahan</button>
                        </div>
                    </div>

                    <div class="tab-panel" id="tab-password">
                        <div class="section-title">Ubah Password Akun</div>
                        <div class="password-form-wrap">
                            <div class="form-field">
                                <label for="pass-lama">Password Lama</label>
                                <div class="password-toggle-wrap">
                                    <input type="password" id="pass-lama" placeholder="Masukkan password lama">
                                    <span class="pass-eye" onclick="togglePass('pass-lama',this)">👁</span>
                                </div>
                            </div>
                            <div class="form-field">
                                <label for="pass-baru">Password Baru</label>
                                <div class="password-toggle-wrap">
                                    <input type="password" id="pass-baru" placeholder="Minimal 6 karakter">
                                    <span class="pass-eye" onclick="togglePass('pass-baru',this)">👁</span>
                                </div>
                            </div>
                            <div class="form-field">
                                <label for="pass-konfirm">Konfirmasi Password Baru</label>
                                <div class="password-toggle-wrap">
                                    <input type="password" id="pass-konfirm" placeholder="Ulangi password baru">
                                    <span class="pass-eye" onclick="togglePass('pass-konfirm',this)">👁</span>
                                </div>
                            </div>
                            <div style="margin-top:8px;">
                                <button type="button" class="btn-primary large" onclick="ubahPassword()">Ubah Password</button>
                            </div>
                        </div>
                    </div>

                    <div class="tab-panel" id="tab-notifications">
                        <div class="section-title">Pengaturan Notifikasi</div>
                        <div class="notif-list">
                            <div class="notif-row">
                                <div class="notif-info">
                                    <div class="notif-label">Pesanan Baru Masuk</div>
                                    <div class="notif-desc">Notifikasi setiap ada pesanan baru dari pelanggan</div>
                                </div>
                                <label class="toggle-sw">
                                    <input type="checkbox" checked>
                                    <span class="toggle-track"></span>
                                </label>
                            </div>
                            <div class="notif-row">
                                <div class="notif-info">
                                    <div class="notif-label">Pembayaran Pending</div>
                                    <div class="notif-desc">Notifikasi untuk pembayaran yang belum dikonfirmasi</div>
                                </div>
                                <label class="toggle-sw">
                                    <input type="checkbox" checked>
                                    <span class="toggle-track"></span>
                                </label>
                            </div>
                        </div>
                        <button type="button" class="btn-primary" onclick="showToast('Pengaturan notifikasi disimpan!','success')">Simpan Pengaturan</button>
                    </div>

                    <div class="tab-panel" id="tab-verification">
                        <div class="section-title">Status Verifikasi Akun</div>
                        <div class="verify-banner">
                            <div class="verify-icon">✓</div>
                            <div>
                                <h4>Akun Admin Terverifikasi</h4>
                                <p>Akun Anda terdaftar sebagai administrator sistem Perlengkapan Sekolah dan memiliki akses penuh ke panel admin.</p>
                            </div>
                        </div>
                        <div class="danger-zone">
                            <div class="dz-title">Zona Berbahaya</div>
                            <div class="dz-desc">Keluar dari sesi akan menghapus cookie login Anda. Anda perlu login kembali untuk mengakses panel admin.</div>
                            <button type="button" class="btn-danger" onclick="if(confirm('Yakin ingin keluar?')) window.location='logout.php'">Keluar &amp; Hapus Sesi</button>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

<div class="toast" id="toast-el"></div>

<script>
/* ── Tab switching ── */
function switchTab(btn, name) {
    document.querySelectorAll('.stnav-btn').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(el => el.classList.remove('active'));
    btn.classList.add('active');
    const panel = document.getElementById('tab-' + name);
    if (panel) panel.classList.add('active');
}

/* ── Sinkronisasi Pemetaan Data dari get_admin_profile.php ── */
document.addEventListener('DOMContentLoaded', () => {
    fetch('<?= $endpoint_get ?>')
        .then(async r => {
            const txt = await r.text();
            let d; try { d = JSON.parse(txt); } catch(e) { return; }
            if (d.status !== 'success') return;
            const p = d.data; // Membuka wrapper data.data.*

            // Pemetaan disesuaikan persis dengan mapping JSON file PHP Anda
            document.getElementById('f-nama-lengkap').value  = p.nama || '';
            document.getElementById('f-email').value         = p.email || '';
            document.getElementById('f-phone').value         = p.telepon || '';
            document.getElementById('f-address').value       = p.alamat || '';
        })
        .catch(() => {});
});

/* ── Simpan Profil Teks ── */
function simpanProfil() {
    const nama_lengkap = document.getElementById('f-nama-lengkap').value.trim();
    const email        = document.getElementById('f-email').value.trim();
    const phone        = document.getElementById('f-phone').value.trim();
    const address      = document.getElementById('f-address').value.trim();

    if (!nama_lengkap) { showToast('Nama tidak boleh kosong!', 'error'); return; }
    if (!email) { showToast('Email tidak boleh kosong!', 'error'); return; }

    fetch('<?= $endpoint_profil ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ nama_lengkap, email, phone, address })
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success' || data.nama_lengkap) {
            showToast('Profil berhasil disimpan!', 'success');
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast(data.message || 'Gagal menyimpan profil', 'error');
        }
    })
    .catch(() => showToast('Koneksi gagal, coba lagi.', 'error'));
}

/* ── Upload Foto Profil Baru ── */
function uploadFoto() {
    const input = document.getElementById('file-foto');
    if (!input.files.length) return;

    // Live preview lokal
    const reader = new FileReader();
    reader.onload = e => {
        const prev = document.getElementById('avatar-preview');
        prev.innerHTML = `<img src="${e.target.result}" alt="preview">`;
    };
    reader.readAsDataURL(input.files[0]);

    const fd = new FormData();
    fd.append('foto_profil', input.files[0]);

    fetch('<?= $endpoint_profil ?>', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.status === 'success') {
                showToast('Foto berhasil diperbarui!', 'success');
                setTimeout(() => location.reload(), 1200);
            } else {
                showToast(d.message || 'Gagal unggah foto', 'error');
            }
        })
        .catch(() => showToast('Gagal mengunggah foto.', 'error'));
}

/* ── Hapus Foto Profil ── */
function hapusFoto() {
    if (!confirm('Hapus foto profil?')) return;
    fetch('<?= $endpoint_profil ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete_photo' })
    })
    .then(r => r.json())
    .then(d => {
        if (d.status === 'success') {
            showToast('Foto berhasil dihapus.', 'success');
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast(d.message || 'Gagal menghapus foto', 'error');
        }
    })
    .catch(() => showToast('Gagal menghapus foto.', 'error'));
}

/* ── Ubah Password ── */
function ubahPassword() {
    const baru    = document.getElementById('pass-baru').value;
    const konfirm = document.getElementById('pass-konfirm').value;
    if (baru.length < 6) { showToast('Password minimal 6 karakter!', 'error'); return; }
    if (baru !== konfirm) { showToast('Konfirmasi password tidak cocok!', 'error'); return; }
    const lama = document.getElementById('pass-lama').value;
    fetch('<?= $endpoint_password ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ password_lama: lama, password: baru })
    })
    .then(r => r.json())
    .then(d => {
        if (d.status === 'success') {
            showToast('Password berhasil diubah!', 'success');
            document.getElementById('pass-lama').value = '';
            document.getElementById('pass-baru').value = '';
            document.getElementById('pass-konfirm').value = '';
        } else {
            showToast(d.message || 'Gagal mengubah password', 'error');
        }
    })
    .catch(() => showToast('Koneksi gagal.', 'error'));
}

/* ── Toggle password visibility ── */
function togglePass(id, el) {
    const inp = document.getElementById(id);
    inp.type = inp.type === 'password' ? 'text' : 'password';
    el.textContent = inp.type === 'password' ? '👁' : '🙈';
}

/* ── Toast ── */
let _toastTimer;
function showToast(msg, type = '') {
    const t = document.getElementById('toast-el');
    t.textContent = msg;
    t.className = 'toast ' + type + ' show';
    clearTimeout(_toastTimer);
    _toastTimer = setTimeout(() => t.classList.remove('show'), 3000);
}
</script>
</body>
</html>