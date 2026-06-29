<?php
session_start();
include "koneksi.php";

/*
|--------------------------------------------------------------------------
| AMBIL ID
|--------------------------------------------------------------------------
*/

if(!isset($_GET['id'])){

    header("Location: data_barang.php");
    exit;

}

$id = $_GET['id'];

/*
|--------------------------------------------------------------------------
| AMBIL DATA BARANG
|--------------------------------------------------------------------------
*/

$query = mysqli_query(
    $conn,
    "SELECT * FROM barang WHERE id_barang='$id'"
);

$data = mysqli_fetch_assoc($query);

if(!$data){

    header("Location: data_barang.php");
    exit;

}

/*
|--------------------------------------------------------------------------
| UPDATE DATA
|--------------------------------------------------------------------------
*/

if(isset($_POST['update'])){

    $nama       = $_POST['nama_barang'];
    $kategori   = $_POST['kategori'];
    $harga      = $_POST['harga'];
    $stok       = $_POST['stok'];
    $deskripsi  = $_POST['deskripsi'];

    $gambar_lama = $data['gambar'];

    /*
    |--------------------------------------------------------------------------
    | CEK GAMBAR BARU
    |--------------------------------------------------------------------------
    */

    if($_FILES['gambar']['name'] != ''){

        $namaFile  = $_FILES['gambar']['name'];
        $tmp       = $_FILES['gambar']['tmp_name'];

        $gambarBaru = time() . '_' . $namaFile;

        move_uploaded_file(
            $tmp,
            "upload/" . $gambarBaru
        );

        // hapus gambar lama
        if(file_exists("upload/" . $gambar_lama)){
            unlink("upload/" . $gambar_lama);
        }

    }else{

        $gambarBaru = $gambar_lama;

    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE DATABASE
    |--------------------------------------------------------------------------
    */

    mysqli_query(
        $conn,
        "UPDATE barang SET

            nama_barang='$nama',
            kategori='$kategori',
            harga='$harga',
            stok='$stok',
            deskripsi='$deskripsi',
            gambar='$gambarBaru'

            WHERE id_barang='$id'
        "
    );

    header("Location: data_barang.php");
    exit;

}

?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Edit Barang</title>

<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    font-family:'Plus Jakarta Sans',sans-serif;
    background:#F3F5FF;
    color:#1E1B4B;
}

.container{
    width:100%;
    min-height:100vh;

    display:flex;
    justify-content:center;
    align-items:center;

    padding:30px;
}

.card{

    width:100%;
    max-width:750px;

    background:white;

    border-radius:30px;

    padding:40px;

    box-shadow:0 15px 40px rgba(0,0,0,.06);

}

.title{

    margin-bottom:35px;

}

.title h1{

    font-size:36px;
    font-weight:800;

    margin-bottom:10px;

}

.title p{

    color:#8B8FAD;

}

/* =========================
   FORM
========================= */

.form-group{

    margin-bottom:22px;

}

.form-group label{

    display:block;

    margin-bottom:10px;

    font-weight:700;

    font-size:14px;

}

.form-control{

    width:100%;

    padding:16px 18px;

    border:2px solid #E5E7FF;

    border-radius:18px;

    outline:none;

    font-family:inherit;

    font-size:14px;

    transition:.3s;

    background:white;

}

.form-control:focus{

    border-color:#7B6DFF;

    box-shadow:0 0 0 4px rgba(123,109,255,.10);

}

textarea.form-control{

    resize:none;

    height:130px;

}

/* =========================
   IMAGE PREVIEW
========================= */

.preview{

    width:180px;
    height:180px;

    border-radius:20px;

    overflow:hidden;

    border:2px solid #E5E7FF;

    background:#F8F9FF;

    display:flex;
    justify-content:center;
    align-items:center;

    margin-top:10px;

    padding:10px;

}

.preview img{

    width:100%;
    height:100%;

    object-fit:contain;

}

/* =========================
   BUTTON
========================= */

.button-group{

    display:flex;

    gap:15px;

    margin-top:35px;

}

.btn{

    flex:1;

    border:none;

    padding:16px;

    border-radius:18px;

    font-family:inherit;

    font-size:15px;

    font-weight:700;

    cursor:pointer;

    transition:.3s;

}

.btn-update{

    background:linear-gradient(
        135deg,
        #5B5FFB,
        #A855F7
    );

    color:white;

}

.btn-update:hover{

    transform:translateY(-2px);

}

.btn-back{

    background:#EEF1FF;

    color:#5B5FFB;

    text-decoration:none;

    display:flex;
    justify-content:center;
    align-items:center;

}

.btn-back:hover{

    background:#dfe5ff;

}

/* =========================
   RESPONSIVE
========================= */

@media(max-width:600px){

    .card{

        padding:25px;

    }

    .title h1{

        font-size:28px;

    }

    .button-group{

        flex-direction:column;

    }

}

</style>
</head>
<body>

<div class="container">

    <div class="card">

        <div class="title">

            <h1>Edit Barang</h1>

            <p>
                Perbarui data perlengkapan sekolah
            </p>

        </div>

        <form method="POST" enctype="multipart/form-data">

            <!-- NAMA -->

            <div class="form-group">

                <label>Nama Barang</label>

                <input
                    type="text"
                    name="nama_barang"
                    class="form-control"
                    value="<?php echo $data['nama_barang']; ?>"
                    required
                >

            </div>

            <!-- KATEGORI -->

            <div class="form-group">

                <label>Kategori</label>

                <select
                    name="kategori"
                    class="form-control"
                    required
                >

                    <option value="">-- Pilih Kategori --</option>

                    <option value="tas"
                        <?php if($data['kategori']=='tas') echo 'selected'; ?>>
                        Tas Sekolah
                    </option>

                    <option value="alat_tulis"
                        <?php if($data['kategori']=='alat_tulis') echo 'selected'; ?>>
                        Peralatan Sekolah
                    </option>

                    <option value="seragam"
                        <?php if($data['kategori']=='seragam') echo 'selected'; ?>>
                        Seragam Sekolah
                    </option>

                    <option value="sepatu"
                        <?php if($data['kategori']=='sepatu') echo 'selected'; ?>>
                        Sepatu Sekolah
                    </option>

                    <option value="buku"
                        <?php if($data['kategori']=='buku') echo 'selected'; ?>>
                        Buku Pelajaran
                    </option>

                </select>

            </div>

            <!-- HARGA -->

            <div class="form-group">

                <label>Harga</label>

                <input
                    type="number"
                    name="harga"
                    class="form-control"
                    value="<?php echo $data['harga']; ?>"
                    required
                >

            </div>

            <!-- STOK -->

            <div class="form-group">

                <label>Stok</label>

                <input
                    type="number"
                    name="stok"
                    class="form-control"
                    value="<?php echo $data['stok']; ?>"
                    required
                >

            </div>

            <!-- DESKRIPSI -->

            <div class="form-group">

                <label>Deskripsi</label>

                <textarea
                    name="deskripsi"
                    class="form-control"
                    required
                ><?php echo $data['deskripsi']; ?></textarea>

            </div>

            <!-- GAMBAR -->

            <div class="form-group">

                <label>Ganti Gambar</label>

                <input
                    type="file"
                    name="gambar"
                    class="form-control"
                >

                <div class="preview">

                    <img src="upload/<?php echo $data['gambar']; ?>">

                </div>

            </div>

            <!-- BUTTON -->

            <div class="button-group">

                <button
                    type="submit"
                    name="update"
                    class="btn btn-update"
                >
                    Simpan Perubahan
                </button>

                <a href="data_barang.php" class="btn btn-back">
                    Kembali
                </a>

            </div>

        </form>

    </div>

</div>

</body>
</html>