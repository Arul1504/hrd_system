<?php
require '../config.php';

// Periksa hak akses ADMIN
if (!isset($_SESSION['id_karyawan']) || $_SESSION['role'] !== 'ADMIN') {
    header("Location: ../../index.php");
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

// Logika untuk menampilkan pesan
$message = '';
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'approved') {
        $message = '<div class="alert success">Pengajuan berhasil disetujui.</div>';
    } elseif ($_GET['status'] == 'rejected') {
        $message = '<div class="alert error">Pengajuan berhasil ditolak.</div>';
    }
}

// Ambil data pengajuan DENGAN MENGHAPUS SEMUA FILTER
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

        .download-btn {
            background-color: #3498db;
            color: #fff;
        }

        .download-btn:hover {
            background-color: #2980b9;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .badge {
            background: #ef4444;
            color: #fff;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 12px;
        }
    </style>
</head>

<body>
    <div class="container">
        <aside class="sidebar">
            <div>
                <div class="company-brand">
                    <img src="../image/manu.png" alt="Logo PT Mandiri Andalan Utama" class="company-logo">
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
                        <li><a href="../dashboard_admin.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
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
                            <a href="#" class="dropdown-link"><i class="fas fa-users"></i> Data Pengajuan<span
                                    class="badge"><?= $total_pending ?></span> <i class="fas fa-caret-down"></i></a>
                            <ul class="dropdown-menu">
                                <li><a href="../pengajuan/pengajuan.php">Pengajuan</a></li>
                                <li class="active"><a href="#"> Kelola Pengajuan<span
                                            class="badge"><?= $total_pending ?></span></a></li>
                            </ul>
                        </li>

                        <li><a href="../monitoring_kontrak/monitoring_kontrak.php"><i class="fas fa-calendar-alt"></i>
                                Monitoring Kontrak</a></li>
                        <li><a href="../monitoring_kontrak/surat_tugas_history.php"><i class="fas fa-file-alt"></i>
                                Riwayat Surat Tugas</a></li>
                        <li><a href="../payslip/e_payslip_admin.php"><i class="fas fa-money-check-alt"></i> E-Pay
                                Slip</a>
                        <li><a href="../invoice/invoice.php"><i class="fas fa-money-check-alt"></i> Invoice</a></li>
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
                                        <?= e(date('d M Y', strtotime($row['tanggal_berakhir']))) ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower($row['status_pengajuan']) ?>">
                                            <?= e($row['status_pengajuan']) ?>
                                        </span>
                                    </td>
                                    <td><?= e($row['sumber_pengajuan']) ?></td>
                                    <td>
                                        <?php
                                        // Tentukan skrip aksi berdasarkan sumber pengajuan
                                        $action_script = ($row['sumber_pengajuan'] === 'TANPA_LOGIN') ? 'process_pengajuan_external.php' : 'process_pengajuan.php';
                                        ?>
                                        <div style="display: flex; gap: 5px;">
                                            

                                            <?php if (!empty($row['dokumen_pendukung'])): ?>
                                                <a href="../../uploads/<?= e($row['dokumen_pendukung']) ?>"
                                                    class="action-btn download-btn" title="Unduh Dokumen" download><i
                                                        class="fas fa-download"></i></a>
                                            <?php endif; ?>

                                            <?php if ($row['status_pengajuan'] === 'Menunggu'): ?>
                                                <a href="#" class="action-btn approve-btn" title="Setujui"
                                                    onclick="showLoadingAndRedirect('<?= e($action_script) ?>?action=approve&id=<?= e($row['id_pengajuan']) ?>'); return false;">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                                <a href="#" class="action-btn reject-btn" title="Tolak"
                                                    onclick="showLoadingAndRedirect('<?= e($action_script) ?>?action=reject&id=<?= e($row['id_pengajuan']) ?>'); return false;">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
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
    <div id="loadingOverlay" style="
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    color: white;
    z-index: 10000;
    justify-content: center;
    align-items: center;
    flex-direction: column;
    gap: 15px;
    font-family: 'Poppins', sans-serif;
">
        <div class="loader-spinner" style="
        width: 50px;
        height: 50px;
        border: 5px solid #f3f3f3;
        border-top: 5px solid #3498db;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    "></div>
        <h3>Mengirim Email Notifikasi...</h3>
        <p>Mohon tunggu sebentar, proses ini mungkin memakan waktu beberapa detik.</p>
    </div>
    <script>
        function showLoadingAndRedirect(url) {
            // Tampilkan overlay loading
            document.getElementById('loadingOverlay').style.display = 'flex';

            // Tunda pengalihan halaman untuk memastikan animasi terlihat
            setTimeout(function () {
                window.location.href = url;
            }, 50); // Penundaan singkat untuk memberi waktu browser merender animasi
        }
    </script>
</body>

</html>
<?php
$conn->close();
?>