<?php
include "koneksi.php";

$status = "";
$pesan  = "";

if (!isset($_GET['token']) || empty($_GET['token'])) {
    $status = "error";
    $pesan  = "Token tidak ditemukan!";
} else {

    $token = mysqli_real_escape_string($conn, $_GET['token']);

    $cek = mysqli_query($conn, "SELECT * FROM pelanggan WHERE token_verifikasi='$token'");

    if (mysqli_num_rows($cek) > 0) {

        $data = mysqli_fetch_assoc($cek);

        if ($data['status_verifikasi'] == 1) {  // ← DIUBAH
            $status = "info";
            $pesan  = "Akun sudah pernah diverifikasi!";
        } else {

            $update = mysqli_query($conn, "UPDATE pelanggan 
                                          SET status_verifikasi=1
                                          WHERE token_verifikasi='$token'");  // ← DIUBAH

            if ($update) {
                $status = "success";
                $pesan  = "Akun berhasil diverifikasi!";
            } else {
                $status = "error";
                $pesan  = "Gagal verifikasi!";
            }
        }

    } else {
        $status = "error";
        $pesan  = "Token tidak valid!";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Verifikasi Akun</title>

<style>

/* BACKGROUND */
body {
    margin: 0;
    font-family: Arial;
    background: linear-gradient(135deg, #667eea, #764ba2);
    height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
}

/* CARD */
.box {
    width: 400px;
    padding: 40px;
    border-radius: 20px;
    text-align: center;

    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(15px);

    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
    color: white;
}

/* ICON */
.icon {
    font-size: 50px;
    margin-bottom: 15px;
}

/* TEXT */
h2 {
    margin-bottom: 10px;
}

p {
    margin-bottom: 25px;
    color: #ddd;
}

/* BUTTON */
.btn {
    display: inline-block;
    padding: 12px 25px;
    border-radius: 25px;
    background: white;
    color: #333;
    text-decoration: none;
    font-weight: bold;
    transition: 0.3s;
}

.btn:hover {
    background: #f1f1f1;
    transform: scale(1.05);
}

/* WARNA STATUS */
.success { color: #00e676; }
.error   { color: #ff5252; }
.info    { color: #ffd740; }

</style>
</head>
<body>

<div class="box">

    <?php if ($status == "success") { ?>
        <div class="icon">✅</div>
        <h2 class="success">Berhasil</h2>
    <?php } elseif ($status == "error") { ?>
        <div class="icon">❌</div>
        <h2 class="error">Gagal</h2>
    <?php } else { ?>
        <div class="icon">⚠️</div>
        <h2 class="info">Info</h2>
    <?php } ?>

    <p><?php echo $pesan; ?></p>

    <a href="login.php" class="btn">Ke Login</a>

</div>

</body>
</html>