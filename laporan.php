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


$dari   = isset($_GET['dari'])   ? $_GET['dari']   : date('Y-01-01');
$sampai = isset($_GET['sampai']) ? $_GET['sampai'] : date('Y-m-d');

$total_pendapatan = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT SUM(pb.jumlah_bayar) as total
     FROM pembayaran pb
     WHERE pb.status_pembayaran='lunas'
     AND pb.tanggal_pembayaran BETWEEN '$dari' AND '$sampai'"))['total'] ?? 0;

$total_pesanan = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as total FROM pesanan
     WHERE tanggal_pesanan BETWEEN '$dari' AND '$sampai'"))['total'] ?? 0;

$total_terjual = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT SUM(dp.jumlah) as total
     FROM detail_pesanan dp
     LEFT JOIN pesanan p ON dp.id_pesanan = p.id_pesanan
     WHERE p.tanggal_pesanan BETWEEN '$dari' AND '$sampai'"))['total'] ?? 0;

$total_pelanggan_baru = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as total FROM pelanggan
     WHERE DATE(created_at) BETWEEN '$dari' AND '$sampai'"))['total'] ?? 0;

$status_list = ['diproses', 'dikirim', 'selesai', 'dibatalkan'];
$status_data = [];
foreach ($status_list as $s) {
    $res = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) as total FROM pesanan
         WHERE status_pesanan='$s'
         AND tanggal_pesanan BETWEEN '$dari' AND '$sampai'"));
    $status_data[$s] = $res['total'] ?? 0;
}

$produk_terlaris = mysqli_query($conn,
    "SELECT b.nama_barang, b.gambar, SUM(dp.jumlah) as total_terjual,
            SUM(dp.jumlah * dp.harga) as total_pendapatan
     FROM detail_pesanan dp
     LEFT JOIN barang b ON dp.id_barang = b.id_barang
     LEFT JOIN pesanan p ON dp.id_pesanan = p.id_pesanan
     WHERE p.tanggal_pesanan BETWEEN '$dari' AND '$sampai'
     GROUP BY dp.id_barang
     ORDER BY total_terjual DESC
     LIMIT 5");

$riwayat = mysqli_query($conn,
    "SELECT p.*, pl.username, pb.metode_pembayaran, pb.status_pembayaran, pb.jumlah_bayar
     FROM pesanan p
     LEFT JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan
     LEFT JOIN pembayaran pb ON p.id_pesanan = pb.id_pesanan
     WHERE p.tanggal_pesanan BETWEEN '$dari' AND '$sampai'
     ORDER BY p.id_pesanan DESC
     LIMIT 10");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Laporan Admin - Perlengkapan Sekolah</title>
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

/* ── FILTER ── */
.filter-card { background: white; border-radius: 16px; padding: 20px 24px; border: 1.5px solid var(--border); margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
.filter-card h3 { font-size: 15px; font-weight: 700; }
.filter-card form { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.filter-card label { font-size: 13px; color: var(--muted); font-weight: 500; }
.filter-card input[type="date"] { padding: 10px 14px; border: 1.5px solid var(--border); border-radius: 10px; font-family: inherit; font-size: 13.5px; outline: none; color: var(--text); transition: all 0.2s; }
.filter-card input[type="date"]:focus { border-color: var(--primary); }

.btn-filter { padding: 10px 20px; background: linear-gradient(135deg, var(--primary), var(--primary-2)); color: white; border: none; border-radius: 10px; font-family: inherit; font-size: 13.5px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
.btn-filter:hover { opacity: 0.85; transform: translateY(-1px); }

.btn-pdf { padding: 10px 20px; background: linear-gradient(135deg, #FF6584, #FF8FA3); color: white; border: none; border-radius: 10px; font-family: inherit; font-size: 13.5px; font-weight: 600; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 6px; }
.btn-pdf:hover { opacity: 0.88; transform: translateY(-1px); }
.btn-pdf.loading { opacity: 0.7; cursor: not-allowed; }

/* ── TABLE ── */
.table-card { background: white; border-radius: 16px; border: 1.5px solid var(--border); overflow: hidden; }
.table-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
.table-title { font-size: 15px; font-weight: 700; }
table { width: 100%; border-collapse: collapse; }
thead th { background: var(--bg); padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }
tbody tr { border-bottom: 1px solid var(--border); transition: all 0.2s; }
tbody tr:last-child { border-bottom: none; }
tbody tr:hover { background: #F8F9FF; }
tbody td { padding: 14px 20px; font-size: 13.5px; }

/* ── BADGE ── */
.badge { padding: 5px 12px; border-radius: 20px; font-size: 11.5px; font-weight: 700; display: inline-block; }
.badge-diproses   { background: #FFF5EC; color: #FA8231; }
.badge-dikirim    { background: #EEF0FF; color: #6C63FF; }
.badge-selesai    { background: #EDFFF5; color: #22c55e; }
.badge-dibatalkan { background: #FFF0F3; color: #FF6584; }
.badge-lunas      { background: #EDFFF5; color: #22c55e; }
.badge-pending    { background: #FFF5EC; color: #FA8231; }
.badge-gagal      { background: #FFF0F3; color: #FF6584; }

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
            <h1>Laporan Penjualan</h1>
            <p><?php echo date('l, d F Y'); ?></p>
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
            <h3>📅 Filter Periode</h3>
            <form method="GET">
                <label>Dari:</label>
                <input type="date" name="dari" value="<?php echo $dari; ?>">
                <label>Sampai:</label>
                <input type="date" name="sampai" value="<?php echo $sampai; ?>">
                <button type="submit" class="btn-filter">🔍 Tampilkan</button>
            </form>
            <button onclick="cetakPDF()" class="btn-pdf" id="btn-pdf">
                📄 Cetak / Export PDF
            </button>
        </div>

        <div class="table-card">
            <div class="table-header">
                <div class="table-title">📋 Riwayat Transaksi</div>
                <div style="font-size:13px; color:var(--muted);">
                    <?php echo date('d M Y', strtotime($dari)); ?> - <?php echo date('d M Y', strtotime($sampai)); ?>
                </div>
            </div>
            <?php if (mysqli_num_rows($riwayat) > 0) { ?>
            <table>
                <thead>
                    <tr>
                        <th>ID Pesanan</th>
                        <th>Pelanggan</th>
                        <th>Tanggal</th>
                        <th>Total Harga</th>
                        <th>Metode Bayar</th>
                        <th>Status Pesanan</th>
                        <th>Status Bayar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($r = mysqli_fetch_assoc($riwayat)) { ?>
                    <tr>
                        <td><a href="detail_pesanan.php?id=<?php echo $r['id_pesanan']; ?>" style="color:var(--primary);font-weight:700;text-decoration:none;">#<?php echo $r['id_pesanan']; ?></a></td>
                        <td><strong><?php echo htmlspecialchars($r['username']); ?></strong></td>
                        <td><?php echo date('d M Y', strtotime($r['tanggal_pesanan'])); ?></td>
                        <td><strong style="color:var(--primary);">Rp <?php echo number_format($r['total_harga'], 0, ',', '.'); ?></strong></td>
                        <td><?php echo $r['metode_pembayaran'] ? ucfirst($r['metode_pembayaran']) : '-'; ?></td>
                        <td><span class="badge badge-<?php echo $r['status_pesanan']; ?>"><?php echo ucfirst($r['status_pesanan']); ?></span></td>
                        <td>
                            <?php if ($r['status_pembayaran']) { ?>
                            <span class="badge badge-<?php echo $r['status_pembayaran']; ?>"><?php echo ucfirst($r['status_pembayaran']); ?></span>
                            <?php } else { ?>
                            <span style="color:var(--muted);font-size:12px;">-</span>
                            <?php } ?>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
            <?php } else { ?>
            <div class="empty-state">
                <div class="empty-icon">📋</div>
                <p>Belum ada transaksi pada periode ini</p>
            </div>
            <?php } ?>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>

<script>
function cetakPDF() {
    const btn = document.getElementById('btn-pdf');
    btn.classList.add('loading');
    btn.textContent = '⏳ Memproses Dokumen...';

    setTimeout(() => {
        try {
            const { jsPDF } = window.jspdf;
            // 'p' bermakna Portrait (Tegak), lebar area cetak menjadi 210mm
            const doc = new jsPDF('p', 'mm', 'a4');

            const dari   = '<?php echo date("d M Y", strtotime($dari)); ?>';
            const sampai = '<?php echo date("d M Y", strtotime($sampai)); ?>';

            // ── 1. HEADER KOP SURAT RESMI ──
            doc.setTextColor(0, 0, 0);
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(14); // Diperkecil sedikit agar pas dengan lebar Portrait
            doc.text('LAPORAN PENJUALAN PERLENGKAPAN SEKOLAH', 14, 15);
            
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(9.5);
            doc.text('Sistem Informasi Administrasi Manajemen Penjualan Barang', 14, 20);
            doc.text('Periode Laporan: ' + dari + ' s/d ' + sampai, 14, 25);
            
            // Garis pembatas KOP Surat ganda (disesuaikan batas kanannya ke 196mm)
            doc.setLineWidth(0.8);
            doc.line(14, 28, 196, 28);
            doc.setLineWidth(0.2);
            doc.line(14, 29.5, 196, 29.5);

            // ── 2. DAFTAR RIWAYAT TRANSAKSI PENUH ──
            const transaksiRows = [
                <?php
                mysqli_data_seek($riwayat, 0);
                while ($r = mysqli_fetch_assoc($riwayat)) {
                    $metode   = $r['metode_pembayaran'] ? ucfirst($r['metode_pembayaran']) : '-';
                    $spesanan = ucfirst($r['status_pesanan']);
                    $sbayar   = $r['status_pembayaran'] ? ucfirst($r['status_pembayaran']) : '-';
                    echo "['" . addslashes('#'.$r['id_pesanan']) . "', '" . addslashes($r['username']) . "', '" . date('d M Y', strtotime($r['tanggal_pesanan'])) . "', 'Rp " . number_format($r['total_harga'],0,',','.') . "', '" . addslashes($metode) . "', '" . addslashes($spesanan) . "', '" . addslashes($sbayar) . "'],\n";
                }
                if(mysqli_num_rows($riwayat) == 0){
                    echo "['-', '-', '-', '-', '-', '-', '-']\n";
                }
                ?>
            ];

            doc.autoTable({
                startY: 35,
                head: [['ID', 'Nama Pelanggan', 'Tanggal', 'Total Harga', 'Metode', 'Status Pesanan', 'Status Bayar']],
                body: transaksiRows,
                theme: 'grid',
                styles: { font: 'helvetica', fontSize: 8.5, cellPadding: 4, textColor: 0 },
                headStyles: { fillColor: [230, 230, 230], textColor: 0, fontStyle: 'bold', halign: 'center' },
                columnStyles: {
                    0: { halign: 'center', cellWidth: 14, fontStyle: 'bold' },
                    2: { halign: 'center', cellWidth: 28 },
                    3: { halign: 'right', cellWidth: 28 },
                    4: { halign: 'center' },
                    5: { halign: 'center' },
                    6: { halign: 'center' },
                },
                margin: { left: 14, right: 14 },
            });

            // ── BARIS TOTAL PENDAPATAN ──
            const totalLunas = <?php
                $t = mysqli_fetch_assoc(mysqli_query($conn,
                    "SELECT COALESCE(SUM(pb.jumlah_bayar),0) as total
                     FROM pembayaran pb
                     LEFT JOIN pesanan p ON pb.id_pesanan = p.id_pesanan
                     WHERE pb.status_pembayaran='lunas'
                     AND p.tanggal_pesanan BETWEEN '$dari' AND '$sampai'"));
                echo (int)$t['total'];
            ?>;
            const totalSemua = <?php
                $t2 = mysqli_fetch_assoc(mysqli_query($conn,
                    "SELECT COALESCE(SUM(total_harga),0) as total FROM pesanan
                     WHERE tanggal_pesanan BETWEEN '$dari' AND '$sampai'"));
                echo (int)$t2['total'];
            ?>;

            const afterTableY = doc.lastAutoTable.finalY;
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(8.5);
            doc.setTextColor(100, 100, 100);
            doc.text('Total Nilai Seluruh Pesanan:', 14, afterTableY + 7);
            doc.setFont('helvetica', 'bold');
            doc.setTextColor(0, 0, 0);
            doc.text('Rp ' + totalSemua.toLocaleString('id-ID'), 80, afterTableY + 7);

            doc.setFont('helvetica', 'normal');
            doc.setTextColor(100, 100, 100);
            doc.text('Total Pendapatan Lunas:', 14, afterTableY + 13);
            doc.setFont('helvetica', 'bold');
            doc.setTextColor(34, 197, 94);
            doc.text('Rp ' + totalLunas.toLocaleString('id-ID'), 80, afterTableY + 13);

            // ── 3. KOLOM TANDA TANGAN VERIFIKASI (Posisi X digeser ke kiri agar pas di Portrait) ──
            const finalY = doc.lastAutoTable.finalY + 30; // +30 beri ruang untuk baris total
            let signY = finalY;
            if (finalY > 240) { // Batas halaman Portrait lebih tinggi (sekitar 240mm)
                doc.addPage();
                signY = 25;
            }

            const formatOpsiCetak = { day: '2-digit', month: 'long', year: 'numeric' };
            const tglCetakFormat = new Date().toLocaleDateString('id-ID', formatOpsiCetak);

            doc.setFontSize(9.5);
            doc.setFont('helvetica', 'normal');
            doc.text('Makassar, ' + tglCetakFormat, 135, signY);
            doc.text('Petugas Verifikator Sistem,', 135, signY + 5);
            
            doc.setFont('helvetica', 'bold');
            doc.text('<?php echo htmlspecialchars($user); ?>', 135, signY + 30);
            doc.setLineWidth(0.3);
            doc.line(135, signY + 31, 190, signY + 31);

            // ── 4. FOOTER NOMOR HALAMAN DINAMIS ──
            const pageCount = doc.internal.getNumberOfPages();
            for (let i = 1; i <= pageCount; i++) {
                doc.setPage(i);
                doc.setDrawColor(200, 200, 200);
                doc.setLineWidth(0.3);
                doc.line(14, doc.internal.pageSize.height - 12, 196, doc.internal.pageSize.height - 12);
                
                doc.setFontSize(8);
                doc.setTextColor(100, 100, 100);
                doc.setFont('helvetica', 'normal');
                doc.text(
                    'Dokumen Arsip Penjualan Internal  |  Dicetak oleh: <?php echo htmlspecialchars($user); ?>  |  Halaman ' + i + ' dari ' + pageCount,
                    105,
                    doc.internal.pageSize.height - 7,
                    { align: 'center' }
                );
            }

            // ── 5. PROSES PRINT LANGSUNG (BROWSER PRINT INTEGRATION) ──
            const stringBlob = doc.output('bloburl');
            const printFrame = document.createElement('iframe');
            printFrame.style.position = 'fixed';
            printFrame.style.right = '0';
            printFrame.style.bottom = '0';
            printFrame.style.width = '0';
            printFrame.style.height = '0';
            printFrame.style.border = '0';
            printFrame.src = stringBlob;
            
            document.body.appendChild(printFrame);
            
            printFrame.onload = function() {
                printFrame.contentWindow.focus();
                printFrame.contentWindow.print();
            };

            const fileName = 'Laporan_Resmi_Penjualan_' + dari.replace(/ /g, '-') + '_sd_' + sampai.replace(/ /g, '-') + '.pdf';
            doc.save(fileName);

        } catch(err) {
            alert('Gagal memproses cetak dokumen: ' + err.message);
            console.error(err);
        }

        btn.classList.remove('loading');
        btn.innerHTML = '📄 Cetak / Export PDF';
    }, 400);
}
</script>

</body>
</html>