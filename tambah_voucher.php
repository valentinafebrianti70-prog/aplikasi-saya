<?php
// Mengaktifkan file koneksi database
session_start();
include 'koneksi.php';

if (!isset($_SESSION['login']) || $_SESSION['role'] != "admin") {
    header("Location: login.php");
    exit;
}

$pesan = "";
$tipe_pesan = "";

// Proses ketika tombol simpan ditekan
if (isset($_POST['simpan'])) {
    // Ambil data dari form dan bersihkan input
    $kode_voucher     = mysqli_real_escape_string($conn, strtoupper(trim($_POST['kode_voucher'])));
    $tipe_diskon      = mysqli_real_escape_string($conn, $_POST['tipe_diskon']);
    $nilai_diskon     = floatval($_POST['nilai_diskon']);
    $tanggal_mulai    = mysqli_real_escape_string($conn, $_POST['tanggal_mulai']);
    $tanggal_berakhir = mysqli_real_escape_string($conn, $_POST['tanggal_berakhir']);
    $jumlah_maksimal  = intval($_POST['jumlah_maksimal']);
    $status           = mysqli_real_escape_string($conn, $_POST['status']);
    $keterangan       = mysqli_real_escape_string($conn, trim($_POST['keterangan']));

    // Cek apakah kode voucher sudah terdaftar sebelumnya (Unique Key)
    $cek_kode = mysqli_query($conn, "SELECT id_voucher FROM voucher WHERE kode_voucher = '$kode_voucher'");
    
    if (mysqli_num_rows($cek_kode) > 0) {
        $pesan = "Gagal! Kode voucher <strong>$kode_voucher</strong> sudah digunakan. Buat kode lain.";
        $tipe_pesan = "danger";
    } elseif ($tanggal_berakhir < $tanggal_mulai) {
        $pesan = "Gagal! Tanggal berakhir tidak boleh lebih mendahului tanggal mulai.";
        $tipe_pesan = "danger";
    } else {
        // Query Simpan Data ke Database
        $query_input = "INSERT INTO voucher 
                        (kode_voucher, tipe_diskon, nilai_diskon, tanggal_mulai, tanggal_berakhir, jumlah_maksimal, status, keterangan) 
                        VALUES 
                        ('$kode_voucher', '$tipe_diskon', '$nilai_diskon', '$tanggal_mulai', '$tanggal_berakhir', '$jumlah_maksimal', '$status', '$keterangan')";
        
        if (mysqli_query($conn, $query_input)) {
            echo "<script>
                    alert('Voucher berhasil ditambahkan!');
                    window.location='voucher.php';
                  </script>";
            exit;
        } else {
            $pesan = "Terjadi kesalahan sistem saat menyimpan data: " . mysqli_error($conn);
            $tipe_pesan = "danger";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Buat Voucher Baru - Admin Perlengkapan Sekolah</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            
            <div class="mb-3">
                <a href="voucher.php" class="btn btn-link text-decoration-none p-0 text-secondary">
                    <i class="bi bi-arrow-left"></i> Kembali ke Daftar Voucher
                </a>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom">
                    <h4 class="mb-0 fw-bold text-dark">Buat Voucher Baru</h4>
                    <p class="text-muted small mb-0">Tambahkan kupon potongan harga baru untuk pelanggan</p>
                </div>
                <div class="card-body p-4">

                    <?php if ($pesan != ""): ?>
                        <div class="alert alert-<?= $tipe_pesan; ?> alert-dismissible fade show" role="alert">
                            <?= $pesan; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form action="" method="POST">
                        <div class="row g-3">
                            
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Kode Voucher</label>
                                <input type="text" name="kode_voucher" class="form-control" placeholder="Contoh: BACKTOSCHOOL" required style="text-transform: uppercase;">
                                <div class="form-text">Gunakan huruf kapital dan angka tanpa spasi.</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Status Awal</label>
                                <select name="status" class="form-select">
                                    <option value="aktif">Aktif (Langsung Bisa Dipakai)</option>
                                    <option value="nonaktif">Nonaktif</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Tipe Potongan</label>
                                <select name="tipe_diskon" id="tipe_diskon" class="form-select" onchange="sesuaikanPlaceholder()">
                                    <option value="persentase">Persentase (%)</option>
                                    <option value="nominal">Nominal Rupiah (Rp)</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Besar Potongan</label>
                                <div class="input-group">
                                    <span class="input-group-text" id="simbol_diskon">%</span>
                                    <input type="number" name="nilai_diskon" id="nilai_diskon" class="form-control" placeholder="Contoh: 10" min="1" required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Tanggal Mulai Berlaku</label>
                                <input type="date" name="tanggal_mulai" class="form-control" value="<?= date('Y-m-d'); ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Tanggal Kedaluwarsa</label>
                                <input type="date" name="tanggal_berakhir" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')); ?>" required>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-semibold">Kuota Pemakaian (Maksimal)</label>
                                <input type="number" name="jumlah_maksimal" class="form-control" value="0" min="0" required>
                                <div class="form-text text-warning"><i class="bi bi-info-circle"></i> Isi angka <strong>0</strong> jika voucher ini ingin dibuat tanpa batas pemakaian.</div>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-semibold">Keterangan / Deskripsi</label>
                                <textarea name="keterangan" class="form-control" rows="3" placeholder="Contoh: Diskon khusus perlengkapan tulis menyambut tahun ajaran baru..."></textarea>
                            </div>

                        </div>

                        <hr class="my-4">

                        <div class="d-flex justify-content-end gap-2">
                            <a href="voucher.php" class="btn btn-light px-4">Batal</a>
                            <button type="submit" name="simpan" class="btn btn-primary px-4">Simpan Voucher</button>
                        </div>
                    </form>

                </div>
            </div>

        </div>
    </div>
</div>

<script>
function sesuaikanPlaceholder() {
    var tipe = document.getElementById('tipe_diskon').value;
    var simbol = document.getElementById('simbol_diskon');
    var input = document.getElementById('nilai_diskon');

    if (tipe === 'persentase') {
        simbol.innerText = '%';
        input.placeholder = 'Contoh: 10';
        input.max = '100'; // Diskon persen maksimal 100%
    } else {
        simbol.innerText = 'Rp';
        input.placeholder = 'Contoh: 25000';
        input.removeAttribute('max');
    }
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>