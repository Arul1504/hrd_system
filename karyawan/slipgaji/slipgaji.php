<?php
// karyawan/slipgaji/slipgaji.php
session_start();

// Wajib login sebagai KARYAWAN
if (!isset($_SESSION['id_karyawan'])) {
  header("Location: ../index.php"); exit();
}

$id_karyawan   = (int)$_SESSION['id_karyawan'];
$nama_karyawan = $_SESSION['nama'] ?? '';
$role_karyawan = $_SESSION['role'] ?? '';

// config.php satu level di atas folder ini
require __DIR__ . '/../config.php';

// Ambil info dasar karyawan (untuk header/sidebar)
$stmt = $conn->prepare("SELECT nik_ktp, jabatan FROM karyawan WHERE id_karyawan=? LIMIT 1");
$stmt->bind_param("i", $id_karyawan);
$stmt->execute();
$info = $stmt->get_result()->fetch_assoc() ?: ['nik_ktp'=>'', 'jabatan'=>''];
$stmt->close();

// Filter tahun (opsional)
$filter_tahun = isset($_GET['year']) && ctype_digit($_GET['year']) ? (int)$_GET['year'] : 0;

// Daftar tahun yang tersedia milik karyawan ini (untuk dropdown)
$years = [];
$yrq = $conn->prepare("SELECT DISTINCT periode_tahun FROM payroll WHERE id_karyawan=? ORDER BY periode_tahun DESC");
$yrq->bind_param("i",$id_karyawan);
$yrq->execute();
$yrres = $yrq->get_result();
while($row = $yrres->fetch_assoc()){ $years[] = (int)$row['periode_tahun']; }
$yrq->close();

// Ambil slip gaji
if ($filter_tahun > 0) {
  $q = $conn->prepare("SELECT * FROM payroll WHERE id_karyawan=? AND periode_tahun=? ORDER BY periode_tahun DESC, periode_bulan DESC");
  $q->bind_param("ii",$id_karyawan,$filter_tahun);
} else {
  $q = $conn->prepare("SELECT * FROM payroll WHERE id_karyawan=? ORDER BY periode_tahun DESC, periode_bulan DESC");
  $q->bind_param("i",$id_karyawan);
}
$q->execute();
$res = $q->get_result();
$slips = $res->fetch_all(MYSQLI_ASSOC);
$q->close();
$conn->close();

function bulanNama($b){
  $bulan = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
  $b = (int)$b; return $bulan[$b] ?? $b;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Slip Gaji - Karyawan</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
  <link rel="stylesheet" href="../style.css" />
  <style>
    body{font-family:'Poppins',sans-serif;background:#f4f6f9;margin:0}
    .container{display:flex;min-height:100vh}
    .sidebar{background:#212529;color:#fff;width:280px;padding:20px;display:flex;flex-direction:column;justify-content:space-between}
    .company-brand{text-align:center;margin-bottom:20px}
    .company-logo{width:80px;margin-bottom:10px}
    .company-name{font-weight:700;font-size:18px}
    .user-info{display:flex;align-items:center;margin-bottom:20px}
    .user-avatar{background:#0080ff;border-radius:50%;width:48px;height:48px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:20px;margin-right:12px;color:#fff}
    .user-name{margin:0;font-weight:600;font-size:16px}
    .user-id,.user-role{margin:0;font-size:13px;color:#adb5bd}
    .sidebar-nav ul{list-style:none;padding-left:0}
    .sidebar-nav li{margin-bottom:12px}
    .sidebar-nav a{color:#dee2e6;text-decoration:none;font-weight:500;display:flex;align-items:center;padding:10px;border-radius:4px;transition:background-color .3s}
    .sidebar-nav a i{margin-right:10px}
    .sidebar-nav .active a, .sidebar-nav a:hover{background:#343a40;color:#0080ff}
    .logout-link a{color:#dee2e6;font-weight:600;text-decoration:none;display:flex;align-items:center;margin-top:auto;padding:10px;border-radius:4px;transition:background-color .3s}
    .logout-link a:hover{background:#dc3545;color:#fff}
    .main-content{flex:1;padding:25px 40px}
    .main-header{margin-bottom:20px}
    .main-header h1{margin:0;font-weight:700;font-size:28px;color:#212529}
    .current-date{color:#6c757d;font-size:14px;margin:4px 0 0}
    .card{background:#fff;border-radius:8px;box-shadow:0 1px 4px rgb(0 0 0 / 0.1);padding:24px}
    .card h2{margin:0 0 8px}
    .muted{color:#6c757d;margin:0 0 14px}
    .toolbar{display:flex;gap:10px;align-items:center;margin:10px 0 16px}
    .back-btn{background:#6c757d;color:#fff;border:none;border-radius:6px;padding:6px 12px;cursor:pointer}
    .select{padding:6px 10px;border:1px solid #e5e7eb;border-radius:6px;background:#fff}
    table{width:100%;border-collapse:collapse;background:#fff}
    th,td{border:1px solid #e5e7eb;padding:10px;text-align:left}
    th{background:#f1f5f9}
    .btn{padding:6px 12px;border-radius:6px;background:#2563eb;color:#fff;text-decoration:none;display:inline-block}
    .btn:hover{background:#1d4ed8}
  </style>
</head>
<body>
<div class="container">
  <!-- Sidebar -->
  <aside class="sidebar">
    <div>
      <div class="company-brand">
        <img src="../image/manu.png" alt="Logo" class="company-logo">
        <p class="company-name">PT Mandiri Andalan Utama</p>
      </div>
      <div class="user-info">
        <div class="user-avatar"><?= strtoupper(substr($nama_karyawan,0,2)) ?></div>
        <div>
          <p class="user-name"><?= htmlspecialchars($nama_karyawan) ?></p>
          <p class="user-id"><?= htmlspecialchars($info['nik_ktp'] ?? '') ?></p>
          <p class="user-role"><?= htmlspecialchars($info['jabatan'] ?? '') ?></p>
        </div>
      </div>
      <nav class="sidebar-nav">
        <ul>
          <li><a href="../dashboard_karyawan.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
          <li><a href="../absensi/absensi.php"><i class="fas fa-clipboard-list"></i> Absensi</a></li>
          <li><a href="../pengajuan/pengajuan.php"><i class="fas fa-file-invoice"></i> Pengajuan Saya</a></li>
          <li class="active"><a href="./slipgaji.php"><i class="fas fa-money-check-alt"></i> Slip Gaji</a></li>
        </ul>
      </nav>
    </div>
    <div class="logout-link">
      <a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a>
    </div>
  </aside>

  <!-- Content -->
  <main class="main-content">
    <header class="main-header">
      <h1>Slip Gaji</h1>
      <p class="current-date"><?= date('l, d F Y') ?></p>
    </header>

    <section class="card">
      <h2>Slip Gaji Saya</h2>
      <p class="muted">Halo, <b><?= htmlspecialchars($nama_karyawan) ?></b>. Berikut daftar slip gaji Anda:</p>

      <div class="toolbar">
        <form method="get" style="margin-left:auto">
          <label for="year">Filter Tahun:&nbsp;</label>
          <select id="year" name="year" class="select" onchange="this.form.submit()">
            <option value="">-- Semua Tahun --</option>
            <?php foreach($years as $y): ?>
              <option value="<?= $y ?>" <?= $filter_tahun===$y?'selected':'' ?>><?= $y ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>

      <div style="overflow:auto">
        <table>
          <thead>
            <tr>
              <th>Periode</th>
              <th>Total Pendapatan</th>
              <th>Total Potongan</th>
              <th>Take Home Pay</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
          <?php if(empty($slips)): ?>
            <tr><td colspan="5" style="text-align:center">Belum ada slip gaji</td></tr>
          <?php else: foreach($slips as $s): ?>
            <tr>
              <td><?= bulanNama($s['periode_bulan']).' '.$s['periode_tahun'] ?></td>
              <td><?= number_format((int)$s['total_pendapatan'],0,',','.') ?></td>
              <td><?= number_format((int)$s['total_potongan'],0,',','.') ?></td>
              <td><b><?= number_format((int)$s['total_payroll'],0,',','.') ?></b></td>
              <td>
                <a class="btn" href="../../admin/payslip/export_payroll_pdf.php?id=<?= (int)$s['id'] ?>" target="_blank">Unduh PDF</a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</div>
</body>
</html>
