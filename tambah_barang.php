<?php
session_start();
include "koneksi.php";

/*
|--------------------------------------------------------------------------
| SIMPAN DATA
|--------------------------------------------------------------------------
*/
if(isset($_POST['submit'])){

    $nama_barang = mysqli_real_escape_string(
        $conn,
        $_POST['nama_barang']
    );

    $kategori = mysqli_real_escape_string(
        $conn,
        $_POST['kategori']
    );

    $harga = mysqli_real_escape_string(
        $conn,
        $_POST['harga']
    );

    $stok = mysqli_real_escape_string(
        $conn,
        $_POST['stok']
    );

    $deskripsi = mysqli_real_escape_string(
        $conn,
        $_POST['deskripsi']
    );

    /*
    |--------------------------------------------------------------------------
    | VALIDASI GAMBAR
    |--------------------------------------------------------------------------
    */
    if($_FILES['gambar']['name'] == ''){

        echo "
        <script>
            alert('Gambar wajib diupload!');
            window.location='tambah_barang.php';
        </script>
        ";

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | UPLOAD GAMBAR
    |--------------------------------------------------------------------------
    */
    $gambar      = $_FILES['gambar']['name'];
    $tmp         = $_FILES['gambar']['tmp_name'];

    // buat nama unik
    $nama_gambar = time() . "_" . $gambar;

    // upload gambar
    move_uploaded_file(
        $tmp,
        "upload/" . $nama_gambar
    );

    /*
    |--------------------------------------------------------------------------
    | INSERT DATABASE
    |--------------------------------------------------------------------------
    */
    $insert = mysqli_query(
        $conn,
        "INSERT INTO barang
        (
            nama_barang,
            kategori,
            harga,
            stok,
            deskripsi,
            gambar
        )
        VALUES
        (
            '$nama_barang',
            '$kategori',
            '$harga',
            '$stok',
            '$deskripsi',
            '$nama_gambar'
        )"
    );

    /*
    |--------------------------------------------------------------------------
    | CEK ERROR
    |--------------------------------------------------------------------------
    */
    if($insert){

        echo "
        <script>
            alert('Barang berhasil ditambahkan!');
            window.location='data_barang.php';
        </script>
        ";

    }else{

        echo "
        <script>
            alert('Gagal menambahkan barang!');
        </script>
        ";

        echo mysqli_error($conn);
    }

}

?>
<!DOCTYPE html>
<html lang="id">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Tambah Barang</title>

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
    max-width:720px;
    background:white;
    padding:35px;
    border-radius:28px;
    box-shadow:0 10px 35px rgba(0,0,0,.05);
}

.title{
    margin-bottom:30px;
}

.title h1{
    font-size:36px;
    font-weight:800;
    margin-bottom:8px;
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
    box-shadow:0 0 0 5px rgba(123,109,255,.12);
}

textarea.form-control{
    resize:none;
    height:120px;
}

/* =========================
UPLOAD GAMBAR
========================= */

.image-note{
    margin-top:10px;
    font-size:12px;
    color:#8B8FAD;
}

/* =========================
BUTTON
========================= */

.button-group{
    display:flex;
    gap:14px;
    margin-top:30px;
}

.btn{
    flex:1;
    border:none;
    padding:16px;
    border-radius:18px;
    font-weight:700;
    font-family:inherit;
    cursor:pointer;
    font-size:15px;
    transition:.3s;
    text-decoration:none;
    text-align:center;
}

.btn-submit{
    background:linear-gradient(
        135deg,
        #5B5FFB,
        #A855F7
    );

    color:white;
}

.btn-submit:hover{
    transform:translateY(-3px);
}

.btn-back{
    background:#EEF1FF;
    color:#5B5FFB;
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

            <h1>Tambah Barang</h1>

            <p>
                Tambahkan perlengkapan sekolah baru
            </p>

        </div>

        <form method="POST" enctype="multipart/form-data">

            <!-- NAMA BARANG -->

            <div class="form-group">

                <label>Nama Barang</label>

                <input
                    type="text"
                    name="nama_barang"
                    class="form-control"
                    placeholder="Contoh: Tas Eiger Hitam"
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

                    <option value="">
                        -- Pilih Kategori --
                    </option>

                    <option value="tas">
                        Tas Sekolah
                    </option>

                    <option value="alat_tulis">
                        Peralatan Sekolah
                    </option>

                    <option value="seragam">
                        Seragam Sekolah
                    </option>

                    <option value="sepatu">
                        Sepatu Sekolah
                    </option>

                    <option value="buku">
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
                    placeholder="Masukkan harga"
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
                    placeholder="Masukkan stok"
                    required
                >

            </div>

            <!-- DESKRIPSI -->

            <div class="form-group">

                <label>Deskripsi</label>

                <textarea
                    name="deskripsi"
                    class="form-control"
                    placeholder="Masukkan deskripsi barang"
                    required
                ></textarea>

            </div>

            <!-- GAMBAR -->

            <div class="form-group">

                <label>Gambar Barang</label>

                <input
                    type="file"
                    name="gambar"
                    class="form-control"
                    accept="image/*"
                    required
                >

                <div class="image-note">
                    Ukuran rekomendasi: 800 × 800 pixel
                </div>

            </div>

            <!-- BUTTON -->

            <div class="button-group">

                <a href="data_barang.php" class="btn btn-back">
                    Kembali
                </a>

                <button
                    type="submit"
                    name="submit"
                    class="btn btn-submit"
                >
                    Simpan Barang
                </button>

            </div>

        </form>

    </div>

</div>

</body>
</html>