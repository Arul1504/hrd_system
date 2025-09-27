<?php
require '../config.php';
if (session_status() === PHP_SESSION_NONE)
    session_start();

if (!isset($_SESSION['id_karyawan']) || ($_SESSION['role'] ?? '') !== 'ADMIN') {
    header('Location: ../../index.php');
    exit();
}


function dmy($v)
{
    if (!$v)
        return '-';
    $t = strtotime($v);
    return $t ? date('d M Y', $t) : $v;
}

// --- AMBIL DATA USER & PENDING REQUESTS ---
$id_karyawan_admin = $_SESSION['id_karyawan'];
$nama_user_admin = $_SESSION['nama'];
$role_user_admin = $_SESSION['role'];

$stmt_admin_info = $conn->prepare("SELECT nik_ktp, jabatan FROM karyawan WHERE id_karyawan = ?");
$stmt_admin_info->bind_param("i", $id_karyawan_admin);
$stmt_admin_info->execute();
$result_admin_info = $stmt_admin_info->get_result();
$admin_info = $result_admin_info->fetch_assoc();
$nik_user_admin = $admin_info['nik_ktp'] ?? 'Tidak Ditemukan';
$jabatan_user_admin = $admin_info['jabatan'] ?? 'Tidak Ditemukan';
$stmt_admin_info->close();

$sql_pending_requests = "SELECT COUNT(*) AS total_pending FROM pengajuan WHERE status_pengajuan = 'Menunggu'";
$result_pending_requests = $conn->query($sql_pending_requests);
$total_pending = $result_pending_requests->fetch_assoc()['total_pending'] ?? 0;

// --- LOGIKA FILTER POSISI ---
$filter_posisi = $_GET['posisi'] ?? '';

$sql_posisi = "SELECT DISTINCT posisi FROM surat_tugas WHERE posisi IS NOT NULL AND posisi <> '' ORDER BY posisi";
$result_posisi = $conn->query($sql_posisi);
$all_posisi = $result_posisi->fetch_all(MYSQLI_ASSOC);

$sql = "
    SELECT 
        st.id, st.no_surat, st.tgl_pembuatan, st.file_path, st.posisi, st.penempatan, st.sales_code, st.alamat_penempatan,
        k.nama_karyawan, k.proyek, k.jabatan
    FROM surat_tugas st
    JOIN karyawan k ON st.id_karyawan = k.id_karyawan
    WHERE 1=1
";

$params = [];
$types = '';

if (!empty($filter_posisi)) {
    $sql .= " AND st.posisi = ?";
    $params[] = $filter_posisi;
    $types .= 's';
}

$sql .= " ORDER BY st.tgl_pembuatan DESC, st.created_at DESC";

// Eksekusi Query
$rows = [];
$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $surat_history = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $surat_history = [];
    // Log error koneksi atau prepare statement jika diperlukan
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Surat Tugas</title>
    <link rel="stylesheet" href="../style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        .table-container {
            padding: 16px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            border-bottom: 1px solid #eef0f3;
            padding: 12px;
            text-align: left;
        }

        .table th {
            background: #f0f2f5;
            font-weight: 600;
        }

        .table tr:hover {
            background: #f9fafb;
        }

        .btn-action {
            background: #2563eb;
            color: #fff;
            border: none;
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
        }
        
        .filter-toolbar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            align-items: center;
        }

        .filter-toolbar select, .filter-toolbar button {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        .filter-toolbar button {
            background: #2ecc71;
            color: white;
            cursor: pointer;
        }

        /* Sidebar styles (dibiarkan karena ini adalah salinan file Anda) */
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
            background: #2c3e50;
            box-shadow: 0 4px 8px rgba(0, 0, 0, .2);
            padding: 0;
            margin: 0;
            list-style: none;
            z-index: 11;
            border-radius: 0 0 8px 8px;
            overflow: hidden;
        }

        .sidebar-nav .dropdown-menu li a {
            padding: 12px 20px;
            display: block;
            color: #ecf0f1;
            text-decoration: none;
            transition: background-color .3s;
        }

        .sidebar-nav .dropdown-menu li a:hover {
            background: #34495e;
        }

        .sidebar-nav .dropdown-trigger:hover .dropdown-menu {
            display: block;
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
            <div class="company-brand">
                <img src="../image/manu.png" class="company-logo">
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
                    <li><a href="../dashboard_admin.php"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="../absensi/absensi.php"><i class="fas fa-edit"></i> Absensi </a></li>
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
                            <li><a href="../pengajuan/kelola_pengajuan.php">Kelola Pengajuan<span
                                        class="badge"><?= $total_pending ?></span></a></li>
                        </ul>
                    </li>
                    <li><a href="../monitoring_kontrak/monitoring_kontrak.php"><i class="fas fa-calendar-alt"></i>
                            Monitoring Kontrak</a></li>
                    <li class="active"><a href="surat_tugas_history.php"><i class="fas fa-file-alt"></i> Riwayat Surat
                            Tugas</a></li>
                    <li><a href="../payslip/e_payslip_admin.php"><i class="fas fa-money-check-alt"></i> E-Pay Slip</a>
                    </li>
                    <li><a href="../invoice/invoice.php"><i class="fas fa-money-check-alt"></i> Invoice</a></li>
                </ul>
            </nav>
            <div class="logout-link">
                <a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a>
            </div>
        </aside>

        <main class="main-content">
            <div class="main-header">
                <h1>Riwayat Surat Tugas</h1>
            </div>

            <!-- Filter Posisi -->
            <div class="filter-toolbar">
                <form method="get" action="surat_tugas_history.php">
                    <select name="posisi">
                        <option value="">Semua Posisi</option>
                        <?php foreach ($all_posisi as $p): 
                            $selected = ($filter_posisi === $p['posisi']) ? 'selected' : '';
                        ?>
                            <option value="<?= e($p['posisi']) ?>" <?= $selected ?>><?= e($p['posisi']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit"><i class="fas fa-filter"></i> Terapkan Filter</button>
                </form>
            </div>

            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>No Surat</th>
                            <th>Tanggal</th>
                            <th>Nama Karyawan</th>
                            <th>Posisi</th>
                            <th>Penempatan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($surat_history)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">Tidak ada riwayat surat tugas.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($surat_history as $row): ?>
                                <tr>
                                    <td><?= e($row['no_surat']) ?></td>
                                    <td><?= e(dmy($row['tgl_pembuatan'])) ?></td>
                                    <td><?= e($row['nama_karyawan']) ?></td>
                                    <td><?= e($row['posisi']) ?></td>
                                    <td><?= e($row['penempatan']) ?></td>
                                    <td>
                                        <!-- Perubahan: Tombol Lihat dengan simbol mata -->
                                        <a href="surat_tugas_view.php?id=<?= e($row['id']) ?>" target="_blank"
                                            class="btn-action" title="Lihat Surat Tugas">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>

</html>
