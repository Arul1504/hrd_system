<?php
require '../config.php';

// Periksa hak akses HRD
if (!isset($_SESSION['id_karyawan']) || $_SESSION['role'] !== 'HRD') {
    header("Location: ../../index.php");
    exit();
}

// Ambil data user dari sesi
$id_karyawan_hrd = $_SESSION['id_karyawan'];
$nama_user_hrd = $_SESSION['nama'];
$role_user_hrd = $_SESSION['role'];

$stmt_hrd_info = $conn->prepare("SELECT nik_ktp, jabatan FROM karyawan WHERE id_karyawan = ?");
$stmt_hrd_info->bind_param("i", $id_karyawan_hrd);
$stmt_hrd_info->execute();
$result_hrd_info = $stmt_hrd_info->get_result();
$hrd_info = $result_hrd_info->fetch_assoc();

if ($hrd_info) {
    $nik_user_hrd = $hrd_info['nik_ktp'];
    $jabatan_user_hrd = $hrd_info['jabatan'];
} else {
    $nik_user_hrd = 'Tidak Ditemukan';
    $jabatan_user_hrd = 'Tidak Ditemukan';
}
$stmt_hrd_info->close();

// Logika untuk menampilkan pesan
$message = '';
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'approved') {
        $message = '<div class="alert success">Pengajuan berhasil disetujui.</div>';
    } elseif ($_GET['status'] == 'rejected') {
        $message = '<div class="alert error">Pengajuan berhasil ditolak.</div>';
    }
}

// Ambil data pengajuan dengan LEFT JOIN untuk menyertakan pengajuan tanpa login
$sql_pengajuan = "
    SELECT 
        p.*, k.nama_karyawan, k.role
    FROM pengajuan p
    LEFT JOIN karyawan k ON p.id_karyawan = k.id_karyawan
    ORDER BY p.tanggal_diajukan DESC
";

$result_pengajuan = $conn->query($sql_pengajuan);

// Ambil data untuk badge di sidebar
$sql_pending_requests = "SELECT COUNT(*) AS total_pending FROM pengajuan WHERE status_pengajuan = 'Menunggu'";
$result_pending_requests = $conn->query($sql_pending_requests);
$total_pending = $result_pending_requests->fetch_assoc()['total_pending'] ?? 0;

// Fungsi untuk escape output

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pengajuan</title>
    <link rel="stylesheet" href="../style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .data-table-container {
            margin-top: 20px;
        }

        .action-buttons button {
            padding: 6px 10px;
            font-size: 13px;
            border-radius: 4px;
            cursor: pointer;
            border: none;
        }

        .approve-btn {
            background-color: #2ecc71;
            color: #fff;
        }

        .approve-btn:hover {
            background-color: #27ae60;
        }

        .reject-btn {
            background-color: #e74c3c;
            color: #fff;
        }

        .reject-btn:hover {
            background-color: #c0392b;
        }

        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            color: #fff;
        }

        .status-menunggu {
            background-color: #f1c40f;
        }

        .status-disetujui {
            background-color: #2ecc71;
        }

        .status-ditolak {
            background-color: #e74c3c;
        }

        .sidebar-nav .dropdown-trigger {
            position: relative;
        }

        .sidebar-nav .dropdown-link {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .sidebar-nav .dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            min-width: 200px;
            background-color: #2c3e50;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            padding: 0;
            margin: 0;
            list-style: none;
            z-index: 1000;
            border-radius: 0 0 8px 8px;
            overflow: hidden;
        }

        .sidebar-nav .dropdown-menu li a {
            padding: 12px 20px;
            display: block;
            color: #ecf0f1;
            text-decoration: none;
            transition: background-color 0.3s;
        }

        .sidebar-nav .dropdown-menu li a:hover {
            background-color: #34495e;
        }

        .sidebar-nav .dropdown-trigger:hover .dropdown-menu {
            display: block;
        }

        /* New styles for download button */
        .download-btn {
            background-color: #3498db;
            color: #fff;
        }

        .download-btn:hover {
            background-color: #2980b9;
        }
        .badge { background:#ef4444; color:#fff; padding:2px 8px; border-radius:999px; font-size:12px; }
    </style>
</head>

<body>
    <div class="container">
        <aside class="sidebar">
           <div>
                <div class="company-brand">
                    <img src="../image/manu.png" class="company-logo">
                    <p class="company-name">PT Mandiri Andalan Utama</p>
                </div>
                <div class="user-info">
                    <div class="user-avatar"><?= strtoupper(substr($nama_user_hrd, 0, 2)) ?></div>
                    <div>
                        <p><b><?= $nama_user_hrd ?></b></p>
                        <small><?= $nik_user_hrd ?> | <?= $jabatan_user_hrd ?></small>
                    </div>
                </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="../dashboard_hrd.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="../absensi/absensi.php"><i class="fas fa-edit"></i> Absensi </span></a></li>
                    <li class="dropdown-trigger">
                        <a href="#" class="dropdown-link"><i class="fas fa-users"></i> Data Karyawan <i
                                class="fas fa-caret-down"></i></a>
                        <ul class="dropdown-menu">
                            <li><a href="../data_karyawan/all_employees.php">Semua Karyawan</a></li>
                            <li><a href="../data_karyawan/karyawan_nonaktif.php">Non-Aktif</a></li>
                        </ul>
                    </li>
                    <li class="dropdown-trigger">
                        <a href="#" class="dropdown-link"><i class="fas fa-users"></i> Data Pengajuan<span class="badge"><?= $total_pending ?></span> <i
                                class="fas fa-caret-down"></i></a>
                        <ul class="dropdown-menu">
                            <li><a href="../pengajuan/pengajuan.php"> Pengajuan </a></li>
                            <li class="active"><a href="#"><i class="fas fa-edit"></i> Kelola Pengajuan<span class="badge"><?= $total_pending ?></span> </span></a></li>
                        </ul>
                    </li>
                    
                    <li><a href="../monitoring_kontrak/monitoring_kontrak.php"><i class="fas fa-calendar-alt"></i>
                            Monitoring Kontrak</a></li>
                    <li><a href="../slipgaji/slipgaji.php"><i class="fas fa-money-check-alt"></i> Slip Gaji</a></li> 
                    </li>
                     
                </ul>
            </nav>
            <div class="logout-link">
                <a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a>
            </div>
        </aside>

        <main class="main-content">
            <header class="main-header">
                <h1>Kelola Pengajuan</h1>
                <p class="current-date"><?= date('l, d F Y'); ?></p>
            </header>

            <?= $message ?>

            <div class="data-table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Karyawan</th>
                            <th>Jenis</th>
                            <th>Tanggal Pengajuan</th>
                            <th>Mulai - Selesai</th>
                            <th>Status</th>
                            <th>Sumber</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result_pengajuan->num_rows > 0): ?>
                            <?php while ($row = $result_pengajuan->fetch_assoc()): ?>
                                <tr>
                                    <td><?= e($row['id_pengajuan']) ?></td>
                                    <td><?= e($row['nama_karyawan'] ?? $row['nama_pengaju']) ?></td>
                                    <td><?= e($row['jenis_pengajuan']) ?></td>
                                    <td><?= e(date('d M Y', strtotime($row['tanggal_diajukan']))) ?></td>
                                    <td><?= e(date('d M Y', strtotime($row['tanggal_mulai']))) ?> -
                                        <?= e(date('d M Y', strtotime($row['tanggal_berakhir']))) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower($row['status_pengajuan']) ?>">
                                            <?= e($row['status_pengajuan']) ?>
                                        </span>
                                    </td>
                                    <td><?= e($row['sumber_pengajuan']) ?></td>
                                    <td>
                                        <?php
                                        $action_script = ($row['sumber_pengajuan'] === 'TANPA_LOGIN') ? 'process_pengajuan_external.php' : 'process_pengajuan.php';
                                        ?>
                                        <?php if ($row['role'] === 'HRD'): ?>
                                            <span>Di Proses Oleh Admin</span>
                                        <?php else: ?>
                                            <a href="process_pengajuan.php?action=view&id=<?= e($row['id_pengajuan']) ?>"
                                                class="action-btn view-btn" title="Lihat Detail"><i class="fas fa-eye"></i></a>

                                            <?php if (!empty($row['dokumen_pendukung'])): ?>
                                                <a href="../../uploads/<?= e($row['dokumen_pendukung']) ?>"
                                                    class="action-btn download-btn" title="Unduh Dokumen" download><i
                                                        class="fas fa-download"></i></a>
                                            <?php endif; ?>

                                            <?php if ($row['status_pengajuan'] === 'Menunggu'): ?>
                                                <a href="<?= e($action_script) ?>?action=approve&id=<?= e($row['id_pengajuan']) ?>"
                                                    class="action-btn approve-btn" title="Setujui"><i class="fas fa-check"></i></a>
                                                <a href="<?= e($action_script) ?>?action=reject&id=<?= e($row['id_pengajuan']) ?>"
                                                    class="action-btn reject-btn" title="Tolak"><i class="fas fa-times"></i></a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align:center;">Tidak ada pengajuan ditemukan.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>

</html>
<?php
$conn->close();
?>