<?php
session_start();

// Cek apakah user sudah login
if (!isset($_SESSION['id_karyawan'])) {
  header("Location: ../index.php");
  exit();
}

// Ambil data dari sesi
$id_karyawan = $_SESSION['id_karyawan'];
$nama_karyawan = $_SESSION['nama'];
$role_karyawan = $_SESSION['role'];


// Koneksi ke database dan ambil data karyawan, termasuk surat tugas
$servername = "localhost";
$db_username = "root";
$db_password = "";
$dbname = "db_hrd2";

$conn = new mysqli($servername, $db_username, $db_password, $dbname);
if ($conn->connect_error) {
  die("Koneksi gagal: " . $conn->connect_error);
}

// Tambahkan kolom 'surat_tugas' di query
$sql = "SELECT nik_ktp, jabatan, surat_tugas FROM karyawan WHERE id_karyawan = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_karyawan);
$stmt->execute();
$result = $stmt->get_result();
$karyawan = $result->fetch_assoc();

$conn->close();

?>

<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard Karyawan</title>
  <link rel="stylesheet" href="style.css" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      margin: 0;
      background-color: #f4f6f9;
    }

    .container {
      display: flex;
      min-height: 100vh;
    }

    .sidebar {
      background-color: #212529;
      color: white;
      width: 280px;
      padding: 20px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }

    .company-brand {
      text-align: center;
      margin-bottom: 20px;
    }

    .company-logo {
      width: 80px;
      margin-bottom: 10px;
    }

    .company-name {
      font-weight: 700;
      font-size: 18px;
    }

    .user-info {
      display: flex;
      align-items: center;
      margin-bottom: 20px;
    }

    .user-avatar {
      background-color: #0080ff;
      border-radius: 50%;
      width: 48px;
      height: 48px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 20px;
      margin-right: 12px;
      color: white;
    }

    .user-details {
      flex: 1;
    }

    .user-name {
      margin: 0;
      font-weight: 600;
      font-size: 16px;
    }

    .user-id,
    .user-role {
      margin: 0;
      font-size: 13px;
      color: #adb5bd;
    }

    .sidebar-nav ul {
      list-style: none;
      padding-left: 0;
    }

    .sidebar-nav li {
      margin-bottom: 12px;
    }

    .sidebar-nav a {
      color: #dee2e6;
      text-decoration: none;
      font-weight: 500;
      display: flex;
      align-items: center;
      padding: 10px;
      border-radius: 4px;
      transition: background-color 0.3s;
    }

    .sidebar-nav a i {
      margin-right: 10px;
    }

    .sidebar-nav .active a,
    .sidebar-nav a:hover {
      background-color: #343a40;
      color: #0080ff;
    }

    .logout-link a {
      color: #dee2e6;
      font-weight: 600;
      text-decoration: none;
      display: flex;
      align-items: center;
      margin-top: auto;
      padding: 10px;
      border-radius: 4px;
      transition: background-color 0.3s;
    }

    .logout-link a i {
      margin-right: 10px;
    }

    .logout-link a:hover {
      background-color: #dc3545;
      color: white;
    }

    .main-content {
      flex: 1;
      padding: 25px 40px;
    }

    .main-header {
      margin-bottom: 20px;
    }

    .main-header h1 {
      margin: 0;
      font-weight: 700;
      font-size: 28px;
      color: #212529;
    }

    .current-date {
      color: #6c757d;
      font-size: 14px;
      margin: 4px 0 0;
    }

    .content-placeholder {
      background: white;
      padding: 30px;
      border-radius: 8px;
      box-shadow: 0 1px 4px rgb(0 0 0 / 0.1);
      min-height: 300px;
    }

    .download-card {
      border: 1px solid #e9ecef;
      border-radius: 8px;
      padding: 20px;
      margin-top: 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .download-card p {
      margin: 0;
      font-weight: 600;
    }

    .download-card .download-btn {
      background-color: #007bff;
      color: white;
      padding: 10px 15px;
      text-decoration: none;
      border-radius: 5px;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }

    .download-card .download-btn:hover {
      background-color: #0056b3;
    }

    .disabled-btn {
      background-color: #6c757d;
      cursor: not-allowed;
    }

    .disabled-btn:hover {
      background-color: #6c757d;
    }

    .pulse-highlight {
      box-shadow: 0 0 0 3px rgba(37,99,235,.35) inset;
      transition: box-shadow .3s ease;
    }

  </style>
</head>

<body>
  <div class="container">
    <aside class="sidebar">
      <div class="company-brand">
        <img src="image/manu.png" alt="Logo PT Mandiri Andalan Utama" class="company-logo" />
        <p class="company-name">PT Mandiri Andalan Utama</p>
      </div>
      <div class="user-info">
        <div class="user-avatar"><?= strtoupper(substr($nama_karyawan, 0, 2)) ?></div>
        <div class="user-details">
          <p class="user-name"><?= htmlspecialchars($nama_karyawan) ?></p>
          <p class="user-id"><?= htmlspecialchars($karyawan['nik_ktp']) ?></p>
          <p class="user-role"><?= htmlspecialchars($karyawan['jabatan']) ?></p>
        </div>
      </div>
      <nav class="sidebar-nav">
        <ul>
          <li class="active"><a href="#"><i class="fas fa-chart-line"></i> Dashboard</a></li>
          <li><a href="./absensi/absensi.php"><i class="fas fa-clipboard-list"></i> Absensi</a></li>
          <li><a href="./pengajuan/pengajuan.php"><i class="fas fa-file-invoice"></i> Pengajuan Saya</a></li>
          <li><a href="./slipgaji/slipgaji.php">
            <i class="fas fa-money-check-alt"></i> Slip Gaji
          </a></li>

        </ul>
      </nav>
      <div class="logout-link">
        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a>
      </div>
    </aside>

    <main class="main-content">
      <header class="main-header">
        <h1>Dashboard</h1>
        <p class="current-date"><?= date('l, d F Y'); ?></p>
      </header>
      <div class="content-placeholder">
        <h2>Surat Tugas</h2>
        <p>Unduh surat tugas terbaru Anda di sini.</p>

        <div class="download-card">
          <p>Surat Tugas Kerja</p>
          <?php if (!empty($karyawan['surat_tugas'])): ?>
            <a href="download_surat_tugas.php?file=<?= urlencode($karyawan['surat_tugas']) ?>" class="download-btn">
              <i class="fas fa-download"></i> Unduh File
            </a>
          <?php else: ?>
            <span class="download-btn disabled-btn">
              <i class="fas fa-info-circle"></i> File Tidak Tersedia
            </span>
          <?php endif; ?>
        </div>
      </div>

      

    </main>
  </div>





</body>

</html>