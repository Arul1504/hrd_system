<?php
session_start();

// Aktifkan error reporting untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- Konfigurasi Database ---
$servername = "localhost";
$db_username = "root";
$db_password = "";
$dbname = "db_hrd2";

$conn = new mysqli($servername, $db_username, $db_password, $dbname);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// --- Logika Login ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']); // tanggal_lahir

    if (empty($email) || empty($password)) {
        $error = "Email dan Tanggal Lahir harus diisi.";
    } else {
        $sql = "SELECT id_karyawan, nama_karyawan, alamat_email, role FROM karyawan 
                WHERE alamat_email = ? AND tanggal_lahir = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("ss", $email, $password);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                session_regenerate_id(true);
                $_SESSION['id_karyawan'] = $user['id_karyawan'];
                $_SESSION['nama'] = $user['nama_karyawan'];
                $_SESSION['email'] = $user['alamat_email'];
                $_SESSION['role'] = $user['role'];

                if ($user['role'] === "HRD") {
                    header("Location: hrd/dashboard_hrd.php"); exit();
                } elseif ($user['role'] === "KARYAWAN") {
                    header("Location: karyawan/dashboard_karyawan.php"); exit();
                } elseif ($user['role'] === "ADMIN") {
                    header("Location: admin/dashboard_admin.php"); exit();
                } else {
                    $error = "Role tidak dikenali.";
                }
            } else {
                $error = "Email atau Tanggal Lahir salah.";
            }
            $stmt->close();
        } else {
            $error = "Terjadi kesalahan pada server.";
        }
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Login Karyawan - PT Mandiri Andalan Utama</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    :root{--bg-color:#6c757d;--card-color:#f8f9fa;--primary-color:#E67E22;--text-color:#333;--light-gray:#E9ECEF;--border-radius:12px;--shadow:0 8px 24px rgba(0,0,0,.2)}
    body{font-family:'Poppins',sans-serif;background-color:var(--bg-color);display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;color:var(--text-color);padding:20px;box-sizing:border-box}
    .login-container{background:var(--card-color);padding:40px;border-radius:var(--border-radius);box-shadow:var(--shadow);width:100%;max-width:420px;text-align:center}
    .company-logo{width:80px;height:80px;margin-bottom:15px;object-fit:cover;border-radius:50%}
    .login-header{margin-bottom:30px}
    .login-header h1{font-weight:700;font-size:1.8rem;color:var(--primary-color);margin:0}
    .login-header p{margin:5px 0 0;font-size:1rem}
    .input-group{margin-bottom:20px;text-align:left;position:relative}
    .input-group i{position:absolute;left:18px;top:50%;transform:translateY(-50%);color:#ADB5BD}
    .input-group input{width:100%;padding:18px 18px 18px 50px;border:1px solid var(--light-gray);border-radius:var(--border-radius);font-size:1rem;box-sizing:border-box;transition:border-color .3s}
    .input-group input:focus{border-color:var(--primary-color);outline:none}
    button{width:100%;padding:18px;background:var(--primary-color);color:#fff;border:none;border-radius:var(--border-radius);font-weight:600;font-size:1.1rem;cursor:pointer;transition:background-color .3s}
    button:hover{background:#D35400}
    .error-message{color:#DC3545;background:#F8D7DA;border:1px solid #F5C6CB;padding:15px;border-radius:var(--border-radius);margin-bottom:20px;font-size:.9rem;text-align:left;display:flex;align-items:center}
    .error-message i{margin-right:10px}
    .footer-text{margin-top:25px;color:#6c757d;font-size:.8rem}
    .or{display:flex;align-items:center;gap:12px;margin:16px 0;color:#777}
    .or::before,.or::after{content:"";flex:1;height:1px;background:#e5e7eb}
    .ghost-btn{width:100%;padding:14px;border:1px dashed #E67E22;background:#fff;color:#E67E22;border-radius:12px;font-weight:600;cursor:pointer}
    .ghost-btn:hover{background:#fff7f0}
</style>
</head>
<body>
<div class="login-container">
    <div class="login-header">
        <img src="hrd/image/manu.png" alt="Logo PT Mandiri Andalan Utama" class="company-logo">
        <h1>PT Mandiri Andalan Utama</h1>
        <p>Aplikasi Manajemen HRD & Karyawan</p>
    </div>

    <?php if (isset($error)): ?>
        <div class="error-message"><i class="fas fa-exclamation-circle"></i><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="post" action="">
        <div class="input-group">
            <i class="fas fa-envelope"></i>
            <input type="text" name="email" id="email" placeholder="Masukkan email" required />
        </div>
        <div class="input-group">
            <i class="fas fa-key"></i>
            <input type="password" name="password" id="password" placeholder="Tanggal lahir (contoh: 2005-10-15)" required />
        </div>
        <button type="submit"><i class="fas fa-sign-in-alt"></i> Masuk</button>
    </form>

    <div class="or">atau</div>

    <!-- TOMBOL PENGAJUAN TANPA LOGIN -->
    <a href="public_pengajuan.php">
        <button class="ghost-btn" type="button">
            <i class="fas fa-file-signature"></i> Ajukan Cuti tanpa Login
        </button>
    </a>
</div>
</body>
</html>
