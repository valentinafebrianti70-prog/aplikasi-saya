<?php
session_start();
include "koneksi.php";

$pesan = "";
$tipe  = "";

if (isset($_POST['login'])) {

    $user     = mysqli_real_escape_string($conn, $_POST['user']);
    $email    = mysqli_real_escape_string($conn, $_POST['email']);
    $password = md5($_POST['password']);
    $role     = $_POST['role'];

    // ================= ADMIN =================
    if ($role == "admin") {

        $sql   = "SELECT * FROM admin WHERE username='$user' AND password='$password'";
        $query = mysqli_query($conn, $sql);

        if (mysqli_num_rows($query) > 0) {

            $data = mysqli_fetch_assoc($query);

            $_SESSION['login'] = true;
            $_SESSION['user']  = $data['username'];
            $_SESSION['role']  = "admin";

            $tipe  = "success";
            $pesan = "Selamat datang, " . $data['username'] . "! Login sebagai Admin berhasil.";

        } else {
            $tipe  = "error";
            $pesan = "Username atau Password Admin salah!";
        }
    }

    // ================= PELANGGAN =================
    else {

        $sql   = "SELECT * FROM pelanggan WHERE username='$user' AND email='$email' AND password='$password'";
        $query = mysqli_query($conn, $sql);

        if (mysqli_num_rows($query) > 0) {

            $data = mysqli_fetch_assoc($query);

            if ($data['status_verifikasi'] == 0) {
                $tipe  = "warning";
                $pesan = "Anda belum melakukan verifikasi! Silakan cek email Anda.";

            } else {
                $_SESSION['login'] = true;
                $_SESSION['user']  = $data['username'];
                $_SESSION['role']  = "pelanggan";

                $tipe  = "success";
                $pesan = "Selamat datang, " . $data['username'] . "! Login berhasil.";
            }

        } else {
            $tipe  = "error";
            $pesan = "Username, Email atau Password salah!";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Login</title>
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
select { color: white; cursor: pointer; }
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

.btn-login {
    background: linear-gradient(135deg, var(--primary), var(--primary-2));
    color: white;
}

.btn-login:hover {
    opacity: 0.85;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(108,99,255,0.4);
}

.btn-batal {
    background: transparent;
    color: white;
    border: 1.5px solid rgba(108,99,255,0.5) !important;
}

.btn-batal:hover {
    background: rgba(108,99,255,0.15);
    transform: translateY(-2px);
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

.notif.warning {
    background: rgba(255, 215, 64, 0.15);
    border-left: 4px solid #ffd740;
    color: #ffd740;
}

</style>
</head>
<body>
<div class="box">
<h2>🔐 Login</h2>

<?php if (!empty($pesan)) { ?>
    <div class="notif <?php echo $tipe; ?>">
        <?php
            if ($tipe == "success") echo "✅ ";
            if ($tipe == "error")   echo "❌ ";
            if ($tipe == "warning") echo "⚠️ ";
            echo $pesan;
        ?>
    </div>
    <?php if ($tipe == "success") { ?>
        <script>
            setTimeout(function() {
                <?php if ($_SESSION['role'] == 'admin') { ?>
                    window.location = 'http://localhost/Perlengkapan_Sekolah/dashboard_admin.php';
                <?php } else { ?>
                    window.location = 'http://localhost/Perlengkapan_Sekolah/dashboard_pelanggan.php';
                <?php } ?>
            }, 2000);
        </script>
    <?php } ?>
<?php } ?>

<form method="POST">
    <input type="text"     name="user"     placeholder="Username" required>
    <input type="email"    name="email"    placeholder="Email" required>
    <input type="password" name="password" placeholder="Password" required>

    <select name="role">
        <option value="pelanggan">Pelanggan</option>
        <option value="admin">Admin</option>
    </select>

    <div class="btn-group">
        <button type="submit" name="login" class="btn-login">Login</button>
        <button type="reset" class="btn-batal">Batal</button>
    </div>
</form>

<div class="link">
    Belum punya akun? <a href="register.php">Register</a>
</div>
</div>
</body>
</html>