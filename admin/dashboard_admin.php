<?php
require './config.php';

// --- PERIKSA HAK AKSES ---
if (!isset($_SESSION['id_karyawan']) || $_SESSION['role'] !== 'ADMIN') {
    header("Location: ../index.php");
    exit();
}

// Ambil data user dari sesi untuk sidebar
$id_karyawan_admin = $_SESSION['id_karyawan'];
$nama_user_admin = $_SESSION['nama'];
$role_user_admin = $_SESSION['role'];

$stmt_admin_info = $conn->prepare("SELECT nik_ktp, jabatan FROM karyawan WHERE id_karyawan = ?");
$stmt_admin_info->bind_param("i", $id_karyawan_admin);
$stmt_admin_info->execute();
$result_admin_info = $stmt_admin_info->get_result();
$admin_info = $result_admin_info->fetch_assoc();

if ($admin_info) {
    $nik_user_admin = $admin_info['nik_ktp'];
    $jabatan_user_admin = $admin_info['jabatan'];
} else {
    $nik_user_admin = 'Tidak Ditemukan';
    $jabatan_user_admin = 'Tidak Ditemukan';
}
$stmt_admin_info->close();

// --- AMBIL DATA DARI DATABASE UNTUK DASHBOARD ---
// 1. Total Karyawan
$sql_total_karyawan = "SELECT COUNT(*) AS total_karyawan FROM karyawan";
$result_total_karyawan = $conn->query($sql_total_karyawan);
$total_karyawan = $result_total_karyawan->fetch_assoc()['total_karyawan'] ?? 0;

// 1.a. Karyawan Aktif
$sql_karyawan_aktif = "SELECT COUNT(*) AS total_aktif FROM karyawan WHERE status = 'AKTIF'";
$result_karyawan_aktif = $conn->query($sql_karyawan_aktif);
$total_aktif = $result_karyawan_aktif->fetch_assoc()['total_aktif'] ?? 0;

// 1.b. Karyawan Nonaktif
$sql_karyawan_nonaktif = "SELECT COUNT(*) AS total_nonaktif FROM karyawan WHERE status = 'TIDAK AKTIF'";
$result_karyawan_nonaktif = $conn->query($sql_karyawan_nonaktif);
$total_nonaktif = $result_karyawan_nonaktif->fetch_assoc()['total_nonaktif'] ?? 0;

// 2. Pengajuan Menunggu Persetujuan
$sql_pending_requests = "SELECT COUNT(*) AS total_pending FROM pengajuan WHERE status_pengajuan = 'Menunggu'";
$result_pending_requests = $conn->query($sql_pending_requests);
$total_pending = $result_pending_requests->fetch_assoc()['total_pending'] ?? 0;

// 3. Ringkasan Absensi Bulan Ini (Semua Karyawan)
$bulan_ini = date('Y-m');
$sql_absensi_summary = "
    SELECT 
        SUM(CASE WHEN a.status_absensi = 'Hadir' THEN 1 ELSE 0 END) AS hadir,
        SUM(CASE WHEN a.status_absensi = 'Terlambat' THEN 1 ELSE 0 END) AS terlambat,

        (SELECT COUNT(*) FROM pengajuan p 
         WHERE p.jenis_pengajuan = 'Izin' 
         AND DATE_FORMAT(p.tanggal_mulai, '%Y-%m') = ?) AS izin,

        (SELECT COUNT(*) FROM pengajuan p 
         WHERE p.jenis_pengajuan = 'Sakit' 
         AND DATE_FORMAT(p.tanggal_mulai, '%Y-%m') = ?) AS sakit,

        (SELECT COUNT(*) FROM pengajuan p 
         WHERE p.jenis_pengajuan = 'Cuti' 
         AND DATE_FORMAT(p.tanggal_mulai, '%Y-%m') = ?) AS cuti
    FROM absensi a
    WHERE DATE_FORMAT(a.tanggal, '%Y-%m') = ?
";

$stmt_absensi_summary = $conn->prepare($sql_absensi_summary);
$stmt_absensi_summary->bind_param("ssss", $bulan_ini, $bulan_ini, $bulan_ini, $bulan_ini);
$stmt_absensi_summary->execute();
$absensi_summary = $stmt_absensi_summary->get_result()->fetch_assoc();

$hadir = $absensi_summary['hadir'] ?? 0;
$terlambat = $absensi_summary['terlambat'] ?? 0;
$izin = $absensi_summary['izin'] ?? 0;
$sakit = $absensi_summary['sakit'] ?? 0;
$cuti = $absensi_summary['cuti'] ?? 0;


$conn->close();

// helper untuk mencegah XSS

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin</title>
    <link rel="stylesheet" href="./style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .dashboard-content { padding: 20px; border-radius: 12px; }
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .card {
            background-color: #fff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .card-icon {
            width: 60px;
            height: 60px;
            background-color: #f0f4f7;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        .card-content { flex-grow: 1; }
        .card-title {
            font-size: 14px;
            color: #6c757d;
            margin: 0;
        }
        .card-value {
            font-size: 2rem;
            font-weight: 700;
            color: #333;
            margin: 5px 0 0;
        }
        .summary-absensi {
            background-color: #fff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        .data-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }
        .data-box {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .data-box h3 {
            font-size: 2rem;
            font-weight: 700;
            margin: 0 0 5px;
        }
        .data-box p {
            font-size: 0.9rem;
            color: #888;
            margin: 0;
        
        }
         .sidebar-nav .dropdown-trigger {
            position: relative
        }

        .sidebar-nav .dropdown-link {
            display: flex;
            justify-content: space-between;
            align-items: center
        }

        .sidebar-nav .dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            min-width: 200px;
            background: #2c3e50;
            box-shadow: 0 4px 8px rgba(0, 0, 0, .2);
            padding: 0;
            margin: 0;
            list-style: none;
            z-index: 11;
            border-radius: 0 0 8px 8px;
            overflow: hidden
        }

        .sidebar-nav .dropdown-menu li a {
            padding: 12px 20px;
            display: block;
            color: #ecf0f1;
            text-decoration: none;
            transition: background-color .3s
        }

        .sidebar-nav .dropdown-menu li a:hover {
            background: #34495e
        }

        .sidebar-nav .dropdown-trigger:hover .dropdown-menu {
            display: block
        }
.badge { background:#ef4444; color:#fff; padding:2px 8px; border-radius:999px; font-size:12px; }
    </style>
</head>
<body>
    <div class="container">
        <aside class="sidebar">
            <div>
                <div class="company-brand">
                    <img src="./image/manu.png" alt="Logo PT Mandiri Andalan Utama" class="company-logo">
                    <p class="company-name">PT Mandiri Andalan Utama</p>
                </div>
                <div class="user-info">
                    <div class="user-avatar"><?= e(strtoupper(substr($nama_user_admin, 0, 2))) ?></div>
                    <div class="user-details">
                        <p class="user-name"><?= e($nama_user_admin) ?></p>
                        <p class="user-id"><?= e($nik_user_admin) ?></p>
                        <p class="user-role"><?= e($role_user_admin) ?></p>
                    </div>
                </div>
                <nav class="sidebar-nav">
                    <ul>
                        <li class="active"><a href="#"><i class="fas fa-home"></i> Dashboard</a></li>
                        <li><a href="./absensi/absensi.php"><i class="fas fa-edit"></i> Absensi </a></li>
                        <li class="dropdown-trigger">
                            <a href="#" class="dropdown-link"><i class="fas fa-users"></i> Data Karyawan <i class="fas fa-caret-down"></i></a>
                            <ul class="dropdown-menu">
                                <li><a href="./data_karyawan/all_employees.php">Semua Karyawan</a></li>
                                <li><a href="./data_karyawan/karyawan_nonaktif.php">Non-Aktif</a></li>
                            </ul>
                        </li>
                        <li class="dropdown-trigger">
                            <a href="#" class="dropdown-link"><i class="fas fa-users"></i> Data Pengajuan <i class="fas fa-caret-down"><span class="badge"><?= $total_pending ?></span></i></a>
                            <ul class="dropdown-menu">
                                <li><a href="./pengajuan/pengajuan.php">Pengajuan</a></li>
                                <li><a href="./pengajuan/kelola_pengajuan.php">Kelola Pengajuan<span class="badge"><?= $total_pending ?></span></a></li>
                            </ul>
                        </li>
                        <li><a href="./monitoring_kontrak/monitoring_kontrak.php"><i class="fas fa-calendar-alt"></i> Monitoring Kontrak</a></li>
                        <li><a href="./payslip/e_payslip_admin.php"><i class="fas fa-money-check-alt"></i> E-Pay Slip</a></li>
                        <li><a href="./invoice/invoice.php"><i class="fas fa-money-check-alt"></i> Invoice</a></li>
                    </ul>
                </nav>
                <div class="logout-link">
                    <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a>
                </div>
            </div>
        </aside>

        <main class="main-content">
            <header class="main-header">
                <h1>Dashboard Admin</h1>
                <p class="current-date"><?= date('l, d F Y'); ?></p>
            </header>
            
            <div class="dashboard-content">
                <div class="summary-cards">
                    <div class="card">
                        <div class="card-icon"><i class="fas fa-users" style="color:#2ecc71;"></i></div>
                        <div class="card-content">
                            <p class="card-title">Total Karyawan</p>
                            <h3 class="card-value"><?= e($total_karyawan) ?></h3>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-icon"><i class="fas fa-user-check" style="color:#27ae60;"></i></div>
                        <div class="card-content">
                            <p class="card-title">Karyawan Aktif</p>
                            <h3 class="card-value"><?= e($total_aktif) ?></h3>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-icon"><i class="fas fa-user-times" style="color:#e74c3c;"></i></div>
                        <div class="card-content">
                            <p class="card-title">Karyawan Nonaktif</p>
                            <h3 class="card-value"><?= e($total_nonaktif) ?></h3>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-icon"><i class="fas fa-inbox" style="color:#f1c40f;"></i></div>
                        <div class="card-content">
                            <p class="card-title">Pengajuan Menunggu</p>
                            <h3 class="card-value"><?= e($total_pending) ?></h3>
                        </div>
                    </div>
                </div>

                <div class="summary-absensi">
                    <h2>Ringkasan Absensi Bulan Ini</h2>
                    <p>Statistik kehadiran semua karyawan bulan <?= date('F Y') ?></p>
                    <div class="data-grid">
                        <div class="data-box">
                            <h3><?= e($hadir) ?></h3>
                            <p>Hadir</p>
                        </div>
                        <div class="data-box">
                            <h3><?= e($terlambat) ?></h3>
                            <p>Terlambat</p>
                        </div>
                        <div class="data-box">
                            <h3><?= e($izin) ?></h3>
                            <p>Izin</p>
                        </div>
                        <div class="data-box">
                            <h3><?= e($sakit) ?></h3>
                            <p>Sakit</p>
                        </div>
                         <div class="data-box">
                            <h3><?= e($cuti) ?></h3>
                            <p>Cuti</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
