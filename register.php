<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';
include "koneksi.php";

$pesan = "";
$tipe  = "";

if (isset($_POST['daftar'])) {

    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email    = mysqli_real_escape_string($conn, $_POST['email']);
    $password = md5($_POST['password']);
    $role     = $_POST['role'];
    $token    = md5(uniqid(rand(), true)); 

    if ($role == "admin") {

        $sql = "INSERT INTO admin (username, password)
                VALUES ('$username','$password')";

        if (mysqli_query($conn, $sql)) {
            $tipe  = "success";
            $pesan = "Admin berhasil dibuat!";
        } else {
            $tipe  = "error";
            $pesan = "Gagal: " . mysqli_error($conn);
        }
    }

    else {

        $cek = mysqli_query($conn, "SELECT * FROM pelanggan WHERE username='$username'");
        if (mysqli_num_rows($cek) > 0) {
            $tipe  = "error";
            $pesan = "Username sudah terdaftar!";
        } else {

            $sql = "INSERT INTO pelanggan (username, email, password, status_verifikasi, token_verifikasi)
                    VALUES ('$username', '$email', '$password', 0, '$token')";

            if (mysqli_query($conn, $sql)) {

                $link = "http://localhost/Perlengkapan_Sekolah/verifikasi.php?token=$token";

                $mail = new PHPMailer(true);

                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'valentinafebriantisapan@gmail.com';
                    $mail->Password   = 'nwlecqhydlntmixn';
                    $mail->SMTPSecure = 'tls';
                    $mail->Port       = 587;

                    $mail->setFrom('valentinafebriantisapan@gmail.com', 'Sistem Sekolah');
                    $mail->addAddress($email);

                    $mail->isHTML(true);
                    $mail->Subject = 'Verifikasi Akun';
                    $mail->Body    = "
                        <h3>Halo, $username!</h3>
                        <p>Terima kasih sudah mendaftar.</p>
                        <p>Silakan klik tombol berikut untuk verifikasi akun Anda:</p>
                        <a href='$link' 
                           style='padding:10px 20px; background:#2196f3; 
                                  color:white; border-radius:5px; 
                                  text-decoration:none;'>
                            Verifikasi Akun
                        </a>
                        <br><br>
                        <small>Atau copy link ini: $link</small>
                    ";

                    $mail->send();

                    $tipe  = "success";
                    $pesan = "Register berhasil! Cek email $email untuk verifikasi.";

                } catch (Exception $e) {
                    $tipe  = "error";
                    $pesan = "Email gagal dikirim: {$mail->ErrorInfo}";
                }

            } else {
                $tipe  = "error";
                $pesan = "Gagal simpan data: " . mysqli_error($conn);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Register</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>

:root {
    --primary:   #6C63FF;
    --primary-2: #8B85FF;
    --secondary: #FF6584;
    --sidebar-bg:#1E1B4B;
    --border:    #E8EAFF;
    --muted:     #8B8FAD;
}

body {
    margin: 0;
    padding: 0;
    font-family: 'Plus Jakarta Sans', sans-serif;
    background: url('images/alat.jpg') no-repeat center center;
    background-size: cover;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
}

.box {
    width: 380px;
    padding: 35px 30px;
    border-radius: 20px;
    text-align: center;
    background: rgba(30, 27, 75, 0.92);
    backdrop-filter: blur(15px);
    color: white;
    box-shadow: 0 20px 60px rgba(0,0,0,0.4);
    border: 1px solid rgba(108,99,255,0.3);
}

.box h2 {
    font-size: 22px;
    font-weight: 800;
    margin-bottom: 20px;
    color: white;
    letter-spacing: 0.5px;
}

input, select {
    width: 100%;
    padding: 12px 14px;
    margin: 8px 0;
    border: 1.5px solid rgba(108,99,255,0.3);
    border-radius: 10px;
    background: rgba(108,99,255,0.1);
    color: white;
    outline: none;
    box-sizing: border-box;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 13.5px;
    transition: all 0.2s;
}

input:focus {
    border-color: var(--primary);
    background: rgba(108,99,255,0.2);
}

input::placeholder { color: var(--muted); }

select {
    color: white;
    cursor: pointer;
}

select option { color: black; background: white; }

.btn-group {
    display: flex;
    gap: 10px;
    margin-top: 16px;
}

.btn-group button {
    width: 100%;
    padding: 12px;
    border: none;
    border-radius: 10px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 14px;
}

.btn-daftar {
    background: linear-gradient(135deg, var(--primary), var(--primary-2));
    color: white;
}

.btn-daftar:hover {
    opacity: 0.85;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(108,99,255,0.4);
}

.link {
    margin-top: 18px;
    font-size: 13px;
    color: var(--muted);
}

.link a {
    color: var(--primary-2);
    font-weight: 600;
    text-decoration: none;
}

.link a:hover { text-decoration: underline; }

.notif {
    padding: 12px 15px;
    border-radius: 10px;
    margin-bottom: 15px;
    font-size: 13.5px;
    font-weight: 600;
    text-align: left;
}

.notif.success {
    background: rgba(67, 233, 123, 0.15);
    border-left: 4px solid #43E97B;
    color: #43E97B;
}

.notif.error {
    background: rgba(255, 101, 132, 0.15);
    border-left: 4px solid var(--secondary);
    color: var(--secondary);
}

</style>
</head>
<body>
<div class="box">
<h2>🎒 Register</h2>

<?php if (!empty($pesan)) { ?>
    <div class="notif <?php echo $tipe; ?>">
        <?php
            if ($tipe == "success") echo "✅ ";
            if ($tipe == "error")   echo "❌ ";
            echo $pesan;
        ?>
    </div>
    <?php if ($tipe == "success") { ?>
        <script>
            setTimeout(function() {
                window.location = 'login.php';
            }, 3000);
        </script>
    <?php } ?>
<?php } ?>

<form method="POST" action="register.php">
    <input type="text"     name="username" placeholder="Username" required>
    <input type="email"    name="email"    placeholder="Email" required>
    <input type="password" name="password" placeholder="Password" required>

    <select name="role">
        <option value="pelanggan">Pelanggan</option>
        <option value="admin">Admin</option>
    </select>

    <div class="btn-group">
        <button type="submit" name="daftar" class="btn-daftar">Daftar</button>
    </div>
</form>

<div class="link">
    Sudah punya akun? <a href="login.php">Login</a>
</div>
</div>
</body>
</html>