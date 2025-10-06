<?php
// ==========================
// all_employees.php — Data Semua Karyawan (FINAL)
// ==========================

require '../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Flash utk modal Add
$add_error = $_SESSION['add_error'] ?? '';
$add_old = $_SESSION['add_old'] ?? [];
unset($_SESSION['add_error'], $_SESSION['add_old']);

// --- PERIKSA HAK AKSES ---
if (!isset($_SESSION['id_karyawan']) || (($_SESSION['role'] ?? '') !== 'ADMIN')) {
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

// --- Auto non-aktif jika end_date lewat (PKWT) ---
$auto_sql = "
    UPDATE karyawan
    SET status = 'TIDAK AKTIF'
    WHERE status_karyawan = 'PKWT'
      AND (status IS NULL OR status = '' OR UPPER(status) <> 'TIDAK AKTIF')
      AND end_date IS NOT NULL
      AND end_date <> ''
      AND DATE(end_date) < CURDATE()
";
$conn->query($auto_sql);

// Menampilkan pesan dari URL
$message = '';
if (isset($_GET['status'])) {
    $map = [
        'success' => 'Karyawan berhasil ditambahkan!',
        'deleted' => 'Karyawan berhasil dihapus!',
        'updated' => 'Data karyawan berhasil diperbarui!',
        'upload_success' => 'Surat tugas berhasil diunggah!',
        'upload_error' => 'Gagal mengunggah surat tugas.',
        'absen_success' => 'Absensi berhasil dicatat!',
        'absen_failed' => 'Gagal mencatat absensi. Karyawan sudah absen hari ini.',
        'nik_duplicate' => 'NIK sudah digunakan. Tidak bisa disimpan/diperbarui.',
        'error' => 'Terjadi kesalahan saat menyimpan data.',
    ];
    if (isset($map[$_GET['status']])) {
        $cls = in_array($_GET['status'], ['absen_failed', 'nik_duplicate', 'error', 'upload_error']) ? 'error' : 'success';
        $message = '<div class="alert ' . $cls . '">' . e($map[$_GET['status']]) . '</div>';
    }
}

// Ambil data untuk badge di sidebar, KECUALI Reimburse
$sql_pending_requests = "SELECT COUNT(*) AS total_pending FROM pengajuan WHERE status_pengajuan = 'Menunggu' AND jenis_pengajuan != 'Reimburse'";
$result_pending_requests = $conn->query($sql_pending_requests);
$total_pending = $result_pending_requests->fetch_assoc()['total_pending'] ?? 0;

// Query BARU untuk MENGHITUNG HANYA Reimburse yang Menunggu
$sql_pending_reimburse = "SELECT COUNT(*) AS total_pending FROM pengajuan WHERE jenis_pengajuan = 'Reimburse' AND status_pengajuan = 'Menunggu'";
$result_pending_reimburse = $conn->query($sql_pending_reimburse);
$total_pending_reimburse = $result_pending_reimburse->fetch_assoc()['total_pending'] ?? 0;

// Filter
$search_query = $_GET['search'] ?? '';
$filter_proyek = $_GET['proyek'] ?? '';
$filter_jabatan = $_GET['jabatan'] ?? '';
$filter_status_karyawan = $_GET['status_karyawan'] ?? '';
$filter_status = $_GET['status_aktif'] ?? '';
$filter_sub_cnaf = $_GET['sub_cnaf'] ?? ''; // <--- TAMBAHAN INI
// Query
$sql = "SELECT *, role FROM karyawan WHERE 1=1";
$params = [];
$types = '';

if ($search_query !== '') {
    $sql .= " AND (nik_karyawan LIKE ? OR nama_karyawan LIKE ?)";
    $like = "%{$search_query}%";
    $params[] = &$like;
    $params[] = &$like;
    $types .= "ss";
}
if ($filter_proyek !== '') {
    $sql .= " AND proyek = ?";
    $params[] = &$filter_proyek;
    $types .= "s";
}
if ($filter_proyek === 'CNAF' && $filter_sub_cnaf !== '') {
    $sql .= " AND sub_project_cnaf = ?";
    $params[] = &$filter_sub_cnaf;
    $types .= "s";
}
if ($filter_jabatan !== '') {
    $sql .= " AND jabatan = ?";
    $params[] = &$filter_jabatan;
    $types .= "s";
}
if ($filter_status_karyawan !== '') {
    $sql .= " AND status_karyawan = ?";
    $params[] = &$filter_status_karyawan;
    $types .= "s";
}
if ($filter_status !== '') {
    $sql .= " AND UPPER(status) = UPPER(?)";
    $params[] = &$filter_status;
    $types .= "s";
} else {
    // default tampilkan yang aktif / belum diisi
    $sql .= " AND (status IS NULL OR status = '' OR UPPER(status) <> 'TIDAK AKTIF')";
}
$sql .= " ORDER BY nama_karyawan ASC";

$employees = [];
$stmt = $conn->prepare($sql);
if ($stmt) {
    if (!empty($params))
        $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $employees = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    error_log("SQL error: " . $conn->error);
}

// Options filter
$all_proyek = ($r = $conn->query("SELECT DISTINCT proyek FROM karyawan WHERE proyek IS NOT NULL AND proyek<>'' ORDER BY proyek")) ? $r->fetch_all(MYSQLI_ASSOC) : [];
$all_jabatan = ($r = $conn->query("SELECT DISTINCT jabatan FROM karyawan WHERE jabatan IS NOT NULL AND jabatan<>'' ORDER BY jabatan")) ? $r->fetch_all(MYSQLI_ASSOC) : [];
$all_status_karyawan = ($r = $conn->query("SELECT DISTINCT status_karyawan FROM karyawan WHERE status_karyawan IS NOT NULL AND status_karyawan<>'' ORDER BY status_karyawan")) ? $r->fetch_all(MYSQLI_ASSOC) : [];
$all_status = ($r = $conn->query("SELECT DISTINCT status FROM karyawan WHERE status IS NOT NULL AND status<>'' ORDER BY status")) ? $r->fetch_all(MYSQLI_ASSOC) : [];

$isProjectChosen = ($filter_proyek !== '');

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Data Semua Karyawan</title>
    <link rel="stylesheet" href="../style.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <style>
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin: 12px 0;
            font-weight: 600
        }

        .alert.success {
            background: #2ecc71;
            color: #fff
        }

        .alert.error {
            background: #e74c3c;
            color: #fff
        }

        .absen-btn {
            background: #2ecc71;
            color: #fff
        }

        .absen-btn:hover {
            background: #27ae60
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            align-items: center
        }

        .download-btn {
            padding: 8px 14px;
            border-radius: 6px;
            background: #3498db;
            color: #fff;
            text-decoration: none;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: background .2s
        }

        .download-btn:hover {
            background: #2980b9
        }

        .download-btn i {
            font-size: 16px
        }

        .download-btn:nth-child(1) {
            background: #03931eff
        }

        .download-btn:nth-child(1):hover {
            background: #c0392b
        }

        .download-btn:nth-child(2) {
            background: #27ae60
        }

        .download-btn:nth-child(2):hover {
            background: #1e8449
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

        #addEmployeeModal,
        #viewEmployeeModal,
        #editEmployeeModal {
            z-index: 9999
        }

        .tag {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 999px;
            border: 1px solid #ddd;
            color: #333;
            font-size: 12px
        }

        .muted {
            color: #777;
            font-size: 12px;
            margin-top: 4px;
            display: block
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            position: relative;
            z-index: 30
        }

        .view-btn {
            background: #17a2b8;
            color: #fff
        }

        .edit-btn {
            background: #f1c40f;
            color: #fff
        }

        .delete-btn {
            background: #e74c3c;
            color: #fff
        }

        .action-btn i {
            pointer-events: none
        }

        .main-content {
            position: relative;
            z-index: 20
        }

        .modal .modal-content {
            max-width: 980px;
            border-radius: 16px;
            overflow: hidden
        }

        .modal-head {
            display: flex;
            align-items: center;
            gap: 14px;
            background: #f7f9fc;
            padding: 16px 20px;
            border-bottom: 1px solid #eef0f3
        }

        .modal-title {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 600
        }

        .modal-body {
            padding: 20px
        }

        .emp-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 16px
        }

        .emp-avatar {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: #eef2f7;
            color: #4a5568;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 18px;
            border: 1px solid #e8edf3
        }

        .emp-meta {
            display: flex;
            flex-direction: column;
            gap: 4px
        }

        .emp-name {
            font-weight: 700;
            font-size: 1.05rem;
            margin: 0
        }

        .emp-sub {
            color: #6b7280;
            font-size: .9rem;
            margin: 0
        }

        .badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 6px
        }

        .badge {
            font-size: .75rem;
            padding: 4px 10px;
            border-radius: 999px;
            border: 1px solid #e6eaf0;
            background: #f9fafb
        }

        .badge.green {
            border-color: #d1fae5;
            background: #ecfdf5;
            color: #047857
        }

        .badge.amber {
            border-color: #fef3c7;
            background: #fffbeb;
            color: #92400e
        }

        .tabs {
            display: flex;
            gap: 6px;
            border-bottom: 1px solid #eef0f3;
            margin: 12px 0
        }

        .tab-btn {
            border: none;
            background: transparent;
            padding: 10px 12px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            color: #6b7280
        }

        .tab-btn.active {
            background: #eef2ff;
            color: #1d4ed8
        }

        .tab-panel {
            display: none;
            padding-top: 6px
        }

        .tab-panel.active {
            display: block
        }

        .grid-2 {
            display: grid;
            gap: 12px;
            grid-template-columns: 1fr 1fr
        }

        .grid-3 {
            display: grid;
            gap: 12px;
            grid-template-columns: 1fr 1fr 1fr
        }

        @media(max-width:900px) {
            .grid-3 {
                grid-template-columns: 1fr 1fr
            }
        }

        @media(max-width:720px) {

            .grid-2,
            .grid-3 {
                grid-template-columns: 1fr
            }
        }

        .detail-card {
            border: 1px solid #eef0f3;
            border-radius: 12px;
            padding: 14px 14px 10px;
            background: #fff
        }

        .detail-label {
            font-size: .75rem;
            color: #6b7280;
            margin: 0 0 6px
        }

        .detail-value {
            font-weight: 600;
            color: #111827;
            margin: 0;
            word-break: break-word
        }

        .modal-foot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 20px;
            border-top: 1px solid #eef0f3;
            background: #fafbfc
        }

        .primary {
            background: #2563eb;
            color: #fff;
            border: none;
            padding: 10px 14px;
            border-radius: 10px;
            cursor: pointer
        }

        .primary:hover {
            background: #1d4ed8
        }

        .ghost {
            background: transparent;
            border: 1px solid #e5e7eb;
            padding: 10px 14px;
            border-radius: 10px;
            cursor: pointer
        }

        #editEmployeeModal .modal-content {
            max-width: 980px
        }

        #edit_dynamic_fields .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px
        }

        #edit_dynamic_fields input,
        #edit_dynamic_fields select,
        #edit_dynamic_fields textarea,
        #dynamic_fields input,
        #dynamic_fields select,
        #dynamic_fields textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            font: inherit
        }

        .badge.red {
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
                    <li><a href="../dashboard_admin.php"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="../absensi/absensi.php"><i class="fas fa-edit"></i> Absensi </a></li>
                    <li class="active dropdown-trigger">
                        <a href="#" class="dropdown-link"><i class="fas fa-users"></i> Data Karyawan <i
                                class="fas fa-caret-down"></i></a>
                        <ul class="dropdown-menu">
                            <li><a href="all_employees.php">Semua Karyawan</a></li>
                            <li><a href="karyawan_nonaktif.php">Non-Aktif</a></li>
                        </ul>
                    </li>
                   <li class="dropdown-trigger">
                            <a href="#" class="dropdown-link"><i class="fas fa-users"></i> Data Pengajuan <i class="fas fa-caret-down"><span class="badge"><?= $total_pending ?></span></i></a>
                            <ul class="dropdown-menu">
                                <li><a href="../pengajuan/pengajuan.php">Pengajuan</a></li>
                                <li><a href="../pengajuan/kelola_pengajuan.php">Kelola Pengajuan<span class="badge"><?= $total_pending ?></span></a></li>
                                <li><a href="../pengajuan/kelola_reimburse.php">Kelola Reimburse<span class="badge"><?= $total_pending ?></span></a></li>
                            </ul>
                        </li>
                    <li><a href="../monitoring_kontrak/monitoring_kontrak.php"><i class="fas fa-calendar-alt"></i>
                            Monitoring Kontrak</a></li>
                    <li><a href="../monitoring_kontrak/surat_tugas_history.php"><i class="fas fa-file-alt"></i> Riwayat
                            Surat Tugas</a></li>
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
            <header class="main-header">
                <h1>Data Semua Karyawan</h1>
                <p class="current-date"><?= date('l, d F Y'); ?></p>
            </header>

            <?= $message ?>
            <div class="toolbar">
                <div class="search-filter-container">
                    <form action="all_employees.php" method="GET" class="search-form">
                        <div class="search-box">
                            <input type="text" id="searchInput" name="search" placeholder="Cari NIK atau Nama..."
                                value="<?= e($search_query) ?>" />
                            <button type="submit"><i class="fas fa-search"></i></button>
                        </div>
                        <div class="filter-box">
                            <select name="proyek" onchange="this.form.submit()">
                                <option value="">Semua Project</option>
                                <?php
                                $proyek_list = ['ALLO', 'MOLADIN', 'NOBU', 'CIMB', 'CNAF', 'BNIF', 'SMBCI', 'INTERNAL'];
                                foreach ($proyek_list as $p) {
                                    $sel = ($filter_proyek === $p) ? 'selected' : '';
                                    echo "<option value='" . e($p) . "' $sel>" . e($p) . "</option>";
                                }
                                ?>
                            </select>
                            <select name="jabatan" onchange="this.form.submit()">
                                <option value="">Semua Jabatan</option>
                                <?php foreach ($all_jabatan as $pos) {
                                    $val = $pos['jabatan'] ?? '';
                                    $selected = ($filter_jabatan === $val) ? 'selected' : '';
                                    echo "<option value='" . e($val) . "' $selected>" . e($val) . "</option>";
                                } ?>
                            </select>
                            <select name="status_karyawan" onchange="this.form.submit()">
                                <option value="">Semua Status</option>
                                <?php foreach (['MITRA', 'PKWT'] as $sk) {
                                    $sel = ($filter_status_karyawan === $sk) ? 'selected' : '';
                                    echo "<option value='" . e($sk) . "' $sel>" . e($sk) . "</option>";
                                } ?>
                            </select>
                            <?php if ($filter_proyek === 'CNAF'): ?>
                                <select name="sub_cnaf" onchange="this.form.submit()">
                                    <option value="">Semua Bagian CNAF</option>
                                    <?php
                                    $cnaf_sub_list = [
                                        'SPR&BRO',
                                        'FORM BEDA TTD',
                                        'SBI ZONA B',
                                        'SBI ZOBA A',
                                        'SPR&BRO SENIOR',
                                        'REFINANCING',
                                        'recovery',
                                        'RC ZONA A',
                                        'RC ZONA B',
                                        'DC ZONA A',
                                        'DC ZONA B',
                                        'RC ROLL BACK',
                                        'RC QC'
                                    ];
                                    foreach ($cnaf_sub_list as $cs) {
                                        $sel = ($filter_sub_cnaf === $cs) ? 'selected' : '';
                                        echo "<option value='" . e($cs) . "' $sel>" . e($cs) . "</option>";
                                    }
                                    ?>
                                </select>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                <div class="action-buttons">

                    <a class="download-btn"
                        href="export_karyawan_excel.php?search=<?= urlencode($search_query) ?>&proyek=<?= urlencode($filter_proyek) ?>&jabatan=<?= urlencode($filter_jabatan) ?>&status_karyawan=<?= urlencode($filter_status_karyawan) ?>&status_aktif=<?= urlencode($filter_status) ?>">
                        <i class="fas fa-file-excel"></i> Unduh Excel
                    </a>
                    <button class="add-button" onclick="openModal()"><i class="fas fa-plus-circle"></i> Tambah
                        Karyawan</button>
                </div>
            </div>

            <div class="data-table-container">
                <table id="employeeTable">
                    <thead>
                        <tr>
                            <th>NIK Karyawan</th>
                            <th>Nama Lengkap</th>
                            <th>Project</th>
                            <th>Sub Project</th>
                            <th>Jabatan</th>
                            <th>Status Karyawan</th>

                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($employees)): ?>
                            <tr>
                                <td colspan="7" style="text-align:center;">Tidak ada data karyawan yang ditemukan.</td>
                            </tr>
                        <?php else:
                            foreach ($employees as $employee): ?>
                                <tr>
                                    <td><?= e($employee['nik_ktp'] ?? $employee['nik_karyawan'] ?? '') ?></td>
                                    <td><?= e($employee['nama_karyawan'] ?? '') ?></td>
                                    <td><?= e($employee['proyek'] ?? '') ?></td>
                                    <td><?= e($employee['sub_project_cnaf'] ?? '') ?></td>
                                    <td><?= e($employee['jabatan'] ?? '') ?></td>
                                    <td><?= e($employee['status_karyawan'] ?? '') ?></td>

                                    <td>
                                        <div class="action-btn-group">
                                            <a href="#" class="action-btn view-btn" data-id="<?= e($employee['id_karyawan']) ?>"
                                                title="Lihat"
                                                onclick="openViewModal('<?= e($employee['id_karyawan']) ?>'); return false;"><i
                                                    class="fas fa-eye"></i></a>
                                            <a href="#" class="action-btn edit-btn" data-id="<?= e($employee['id_karyawan']) ?>"
                                                data-proyek="<?= e($employee['proyek'] ?? '') ?>" title="Ubah"
                                                onclick="openEditModal('<?= e($employee['id_karyawan']) ?>','<?= e($employee['proyek'] ?? '') ?>'); return false;"><i
                                                    class="fas fa-edit"></i></a>
                                            <?php if (($employee['proyek'] ?? '') === 'CNAF'): ?>
                                                <a href="./surat/view_surat_rekening.php?id=<?= e($employee['id_karyawan']) ?>"
                                                    class="action-btn"
                                                    style="background:#007bff; color:white; width: 42px; height: 36px;"
                                                    title="Lihat Surat Rekening" target="_blank">
                                                    <i class="fas fa-file-invoice"></i>
                                                </a>
                                            <?php endif; ?>
                                            <button type="button" class="action-btn delete-btn"
                                                data-id="<?= e($employee['id_karyawan']) ?>" title="Hapus"
                                                onclick="confirmDelete('<?= e($employee['id_karyawan']) ?>')"><i
                                                    class="fas fa-trash-alt"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Modal Tambah -->
            <div id="addEmployeeModal" class="modal" style="display:none;">
                <div class="modal-content">
                    <span class="close-btn" onclick="closeModal('addEmployeeModal')">&times;</span>
                    <h2>Form Tambah Karyawan</h2>
                    <?php if (!empty($add_error)): ?>
                        <div class="alert error" id="addErrorAlert"><?= e($add_error) ?></div>
                    <?php endif; ?>
                    <form action="process_add_employee.php" method="POST" enctype="multipart/form-data"
                        id="projectAddForm" autocomplete="off">
                        <div class="form-section" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                            <div class="form-group">
                                <label for="project_select">Project</label>
                                <select id="project_select" name="proyek" required>
                                    <option value="">— Pilih Project —</option>
                                    <option value="ALLO">ALLO</option>
                                    <option value="MOLADIN">MOLADIN</option>
                                    <option value="NOBU">NOBU</option>
                                    <option value="CIMB">CIMB</option>
                                    <option value="CNAF">CNAF</option>
                                    <option value="BNIF">BNIF</option>
                                    <option value="SMBCI">SMBCI</option>
                                    <option value="INTERNAL">INTERNAL</option>
                                </select>
                                <small class="muted">Form akan tampil setelah project dipilih.</small>
                            </div>
                            <div class="form-group" id="sub_project_cnaf_wrap" style="display:none;">
                                <label>Bagian CNAF</label>
                                <select id="sub_project_cnaf" name="sub_project_cnaf">
                                    <option value="">— Pilih Bagian —</option>
                                    <option value="SPR&BRO">SPR&BRO</option>
                                    <option value="FORM BEDA TTD">FORM BEDA TTD</option>
                                    <option value="SBI ZONA B">SBI ZONA B</option>
                                    <option value="SBI ZONA A">SBI ZONA A</option>
                                    <option value="SPR&BRO SENIOR">SPR&BRO SENIOR</option>
                                    <option value="REFINANCING">REFINANCING</option>
                                    <option value="recovery">RECOVERY</option>
                                    <option value="RC ZONA A">RC ZONA A</option>
                                    <option value="RC ZONA B">RC ZONA B</option>
                                    <option value="DC ZONA A">DC ZONA A</option>
                                    <option value="DC ZONA B">DC ZONA B</option>
                                    <option value="RC ROLL BACK">RC ROLL BACK</option>
                                    <option value="RC QC">RC QC</option>
                                </select>
                                <small class="muted">Wajib diisi jika project CNAF.</small>
                            </div>

                            <div class="form-group">
                                <label>&nbsp;</label>
                                <input type="text" id="proyek_input" name="proyek_otomatis" readonly
                                    style="background-color:#e9ecef;cursor:not-allowed;" />
                                <small id="proyek_tag" class="muted" style="display:block;visibility:hidden;">Project:
                                    —</small>
                            </div>
                        </div>
                        <input type="hidden" name="__labels" id="__labels_map" />
                        <div id="dynamic_fields" class="form-section"
                            style="display:none;grid-template-columns:1fr 1fr;gap:12px"></div>
                        <div class="form-buttons" id="submit_container" style="display:none;margin-top:10px;">
                            <button type="submit" class="submit-btn"><i class="fas fa-save"></i> Simpan
                                Karyawan</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal View -->
            <div id="viewEmployeeModal" class="modal" style="display:none;">
                <div class="modal-content">
                    <div class="modal-head">
                        <h3 class="modal-title"><i class="fas fa-id-card"></i>&nbsp; Detail Karyawan</h3>
                    </div>
                    <div class="modal-body">
                        <div class="emp-header">
                            <div class="emp-avatar" id="empAvatar">KR</div>
                            <div class="emp-meta">
                                <p class="emp-name" id="empName">Nama</p>
                                <p class="emp-sub" id="empSub">NIK • Jabatan</p>
                                <div class="badges">
                                    <span class="badge" id="badgeProyek">Project</span>
                                    <span class="badge green" id="badgeStatus">Status</span>
                                    <span class="badge amber" id="badgeStatusKar">Status Karyawan</span>
                                </div>
                            </div>
                        </div>

                        <div class="tabs">
                            <button class="tab-btn active" data-tab="tab-pribadi">Data Pribadi</button>
                            <button class="tab-btn" data-tab="tab-pekerjaan">Pekerjaan</button>
                            <button class="tab-btn" data-tab="tab-kontak">Kontak & Admin</button>
                            <button class="tab-btn" data-tab="tab-lainnya">Lainnya</button>
                        </div>

                        <div id="tab-pribadi" class="tab-panel active">
                            <div class="grid-2" id="gridPribadi"></div>
                        </div>
                        <div id="tab-pekerjaan" class="tab-panel">
                            <div class="grid-3" id="gridPekerjaan"></div>
                        </div>
                        <div id="tab-kontak" class="tab-panel">
                            <div class="grid-2" id="gridKontak"></div>
                        </div>
                        <div id="tab-lainnya" class="tab-panel">
                            <div class="grid-2" id="gridLain"></div>
                        </div>
                    </div>
                    <div class="modal-foot">
                        <small class="muted">Tekan Esc atau klik di luar untuk menutup</small>
                        <button class="primary" onclick="closeViewModal()">Selesai</button>
                    </div>
                </div>
            </div>

            <!-- Modal Edit -->
            <div id="editEmployeeModal" class="modal" style="display:none;">
                <div class="modal-content">
                    <div class="modal-head">
                        <h3 class="modal-title"><i class="fas fa-pen-to-square"></i>&nbsp; Ubah Data Karyawan</h3>
                    </div>
                    <div class="modal-body">
                        <form action="process_edit_employee.php" method="POST" id="editForm" autocomplete="off">
                            <input type="hidden" name="id_karyawan" id="edit_id_karyawan">
                            <input type="hidden" name="__labels" id="edit__labels_map">
                            <div class="form-section" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                                <div class="form-group">
                                    <label>Project</label>
                                    <input type="text" id="edit_proyek_text" readonly
                                        style="background:#eef2f7;cursor:not-allowed">
                                    <input type="hidden" name="proyek" id="edit_proyek">
                                </div>
                                <div class="form-group"><label>&nbsp;</label><small class="muted">Field akan
                                        menyesuaikan project di kiri.</small></div>
                            </div>
                            <div id="edit_dynamic_fields" class="form-section"
                                style="display:none;grid-template-columns:1fr 1fr;gap:12px"></div>
                            <div class="form-buttons" style="margin-top:10px;">
                                <button type="submit" class="submit-btn"><i class="fas fa-save"></i> Simpan
                                    Perubahan</button>
                                <button type="button" class="ghost" onclick="closeEditModal()">Batal</button>
                            </div>
                        </form>
                    </div>
                    <div class="modal-foot" style="display:none"></div>
                </div>
            </div>

            <!-- Modal Upload Surat Tugas -->
            <div id="uploadSuratTugasModal" class="modal" style="display:none;">
                <div class="modal-content">
                    <span class="close-btn" onclick="closeModal('uploadSuratTugasModal')">&times;</span>
                    <h2>Upload Surat Tugas</h2>
                    <form action="process_upload_surat_tugas.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="id_karyawan" id="upload_id_karyawan">
                        <div class="form-group">
                            <label>Nama Karyawan</label>
                            <input type="text" id="upload_nama_karyawan" readonly>
                        </div>
                        <div class="form-group">
                            <label for="surat_tugas_file">Pilih File Surat Tugas (PDF)</label>
                            <input type="file" name="surat_tugas" id="surat_tugas_file" accept=".pdf" required>
                        </div>
                        <button type="submit" class="primary">Upload</button>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        // =======================================================
        // DEKLARASI KONSTANTA DAN FUNGSI UTILITY (GLOBAL SCOPE)
        // =======================================================

        // Pastikan variabel PHP ini di-parse di Global Scope JS
        const OLD = <?= json_encode($add_old ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const OPEN_ADD = <?= isset($_GET['open_add']) ? 'true' : 'false' ?>;

        // --- Data Field Lengkap (untuk form Edit) ---
        const ALL_FIELDS = [
            "nama_karyawan", "jabatan", "jenis_kelamin", "tempat_lahir", "tanggal_lahir",
            "alamat", "alamat_tinggal", "rt_rw", "kelurahan", "kecamatan", "kota_kabupaten",
            "nik_ktp", "pendidikan_terakhir", "no_hp", "alamat_email", "no_kk",
            "nama_ayah", "nama_ibu", "nik_karyawan", "nip", "penempatan",
            "nama_user", "kota", "area", "nomor_kontrak", "tanggal_pembuatan_pks",
            "nomor_surat_tugas", "masa_penugasan", "tgl_aktif_masuk", "join_date", "end_date",
            "end_date_pks", "end_of_contract", "status_karyawan", "status", "tgl_resign",
            "cabang", "job", "channel", "tgl_rmu", "nomor_rekening",
            "nama_bank", "gapok", "umk_ump", "tanggal_pernyataan", "npwp",
            "status_pajak", "recruitment_officer", "team_leader", "recruiter", "tl",
            "manager", "nama_sm", "nama_sh", "sales_code", "nomor_reff",
            "no_bpjamsostek", "no_bpjs_kes", "role", "proyek", "surat_tugas",
            "sub_project_cnaf", "no", "tanggal_pembuatan", "spr_bro", "nama_bm_sm",
            "nama_tl", "level", "tanggal_pkm", "nama_sm_cm", "sbi",
            "tanggal_sign_kontrak", "nama_oh", "jabatan_sebelumnya", "nama_bm", "allowance",
            "tunjangan_kesehatan", "om", "nama_cm"
        ];

        // Map PROJECT_FIELDS agar semua project default ke ALL_FIELDS untuk EDIT yang lengkap
        const PROJECT_FIELDS = {
            "CIMB": [
                "nama_karyawan", "cabang", "nomor_kontrak", "tanggal_pembuatan_pks", "tempat_lahir", "tanggal_lahir", "alamat", "rt_rw", "kelurahan", "kecamatan", "kota_kabupaten", "nik_ktp", "pendidikan_terakhir", "no_hp", "jabatan", "alamat_email",
                "nama_sm", "nama_sh", "job", "channel", "tgl_rmu",
                "jenis_kelamin", "no_kk", "nama_ayah", "nama_ibu", "team_leader", "recruiter", "join_date", "status_karyawan", "status"
            ],
            "NOBU": [
                "nama_karyawan", "cabang", "nomor_kontrak", "tanggal_pembuatan_pks", "tempat_lahir", "tanggal_lahir", "alamat", "rt_rw", "kelurahan", "kecamatan", "kota_kabupaten", "nik_ktp", "pendidikan_terakhir", "no_hp", "jabatan", "tgl_aktif_masuk", "alamat_email", "jenis_kelamin", "no_kk", "nama_ayah", "nama_ibu", "team_leader", "recruiter", "join_date", "status_karyawan", "status"
            ],
            "MOLADIN": [
                "nama_karyawan", "jabatan", "cabang", "nomor_kontrak", "tanggal_pembuatan_pks", "tempat_lahir", "tanggal_lahir", "alamat", "rt_rw", "kelurahan", "kecamatan", "kota_kabupaten", "nik_ktp", "pendidikan_terakhir", "no_hp", "alamat_email", "jenis_kelamin", "no_kk", "nama_ayah", "nama_ibu", "team_leader", "recruiter", "join_date", "status_karyawan", "status"
            ],
            "ALLO": [
                "nama_karyawan", "jabatan", "penempatan", "kota", "area", "nomor_kontrak", "tanggal_pembuatan_pks", "tempat_lahir", "tanggal_lahir", "alamat", "rt_rw", "kelurahan", "kecamatan", "kota_kabupaten", "nik_ktp", "pendidikan_terakhir", "no_hp", "alamat_email", "jenis_kelamin", "no_kk", "nama_ayah", "nama_ibu", "recruitment_officer", "team_leader", "join_date", "status_karyawan", "status"
            ],
            "CNAF": [
                "nama_karyawan", "cabang", "nomor_kontrak", "tanggal_pembuatan_pks", "tempat_lahir", "tanggal_lahir", "alamat", "rt_rw", "kelurahan", "kecamatan", "kota_kabupaten", "nik_ktp", "pendidikan_terakhir", "no_hp", "jabatan", "join_date", "end_date", "end_date_pks", "umk_ump", "jenis_kelamin", "alamat_email", "alamat_tinggal", "npwp", "status_pajak", "no_kk", "nama_ayah", "nama_ibu", "recruiter", "team_leader", "nik_karyawan", "status", "nomor_rekening", "nama_bank", "no_bpjamsostek", "no_bpjs_kes"
            ],
            "BNIF": [
                "nama_karyawan", "cabang", "nomor_kontrak", "tanggal_pembuatan_pks", "tempat_lahir", "tanggal_lahir", "alamat", "rt_rw", "kelurahan", "kecamatan", "kota_kabupaten", "nik_ktp", "pendidikan_terakhir", "no_hp", "jabatan", "join_date", "end_date", "end_date_pks", "umk_ump", "jenis_kelamin", "alamat_email", "alamat_tinggal", "nip", "npwp", "status_pajak", "no_kk", "nama_ayah", "nama_ibu", "recruiter", "team_leader", "nik_karyawan", "status", "no_bpjamsostek", "no_bpjs_kes"
            ],
            "SMBCI": [
                "nomor_kontrak", "tanggal_pembuatan_pks", "tanggal_lahir", "nama_karyawan", "tempat_lahir", "jabatan", "nik_ktp", "alamat", "rt_rw", "kelurahan", "kecamatan", "kota", "no_hp", "pendidikan_terakhir", "nama_user", "penempatan", "join_date", "end_date", "umk_ump", "tanggal_pernyataan", "nik_karyawan", "nomor_surat_tugas", "masa_penugasan", "alamat_email", "nomor_reff", "jenis_kelamin", "npwp", "status_pajak", "no_kk", "nama_ayah", "nama_ibu", "recruitment_officer", "team_leader", "status", "no_bpjamsostek", "no_bpjs_kes"
            ],
            "INTERNAL": [
                "nama_karyawan", "cabang", "nomor_kontrak", "tanggal_pembuatan_pks", "tempat_lahir", "tanggal_lahir", "alamat", "rt_rw", "kelurahan", "kecamatan", "kota_kabupaten", "nik_ktp", "pendidikan_terakhir", "no_hp", "jabatan", "join_date", "end_date", "gapok", "jenis_kelamin", "alamat_email", "alamat_tinggal", "tl", "manager", "npwp", "status_pajak", "no_kk", "nama_ayah", "nama_ibu", "status", "role"
            ]
        };
        // CNAF_SUB_FIELDS tetap spesifik (untuk form Tambah)
        const CNAF_SUB_FIELDS = {
            "SPR&BRO": ["no", "nama_karyawan", "cabang", "nomor_kontrak", "tanggal_pembuatan", "tempat_lahir", "tanggal_lahir", "alamat", "rt_rw", "kelurahan", "kecamatan", "kota_kabupaten", "nik_ktp", "pendidikan_terakhir", "jabatan", "spr_bro", "tanggal_pembuatan_pks", "nama_bm_sm", "nama_tl", "alamat_email", "jenis_kelamin", "level", "nama_ayah", "nama_ibu", "status_karyawan"],
            "FORM BEDA TTD": ["nama_karyawan", "tempat_lahir", "tanggal_lahir", "alamat", "rt_rw", "kelurahan", "kecamatan", "kota_kabupaten", "nik_ktp", "tanggal_pkm", "jabatan", "nama_sm_cm", "status_karyawan"],
            "SBI ZONA A": ["no", "nama_karyawan", "cabang", "nomor_kontrak", "tanggal_pembuatan", "tempat_lahir", "tanggal_lahir", "alamat", "rt_rw", "kelurahan", "kecamatan", "kota_kabupaten", "nik_ktp", "pendidikan_terakhir", "no_hp", "jabatan", "sbi", "tanggal_pembuatan_pks", "tanggal_sign_kontrak", "join_date", "nama_oh", "alamat_email", "jenis_kelamin", "status_karyawan"],
            "SBI ZONA B": ["no", "nama_karyawan", "cabang", "nomor_kontrak", "tanggal_pembuatan", "tempat_lahir", "tanggal_lahir", "alamat", "rt_rw", "kelurahan", "kecamatan", "kota_kabupaten", "nik_ktp", "pendidikan_terakhir", "no_hp", "jabatan", "sbi", "tanggal_pembuatan_pks", "tanggal_sign_kontrak", "join_date", "nama_oh", "alamat_email", "jenis_kelamin", "status_karyawan"],
            "SPR&BRO SENIOR": ["no", "nama_karyawan", "cabang", "nomor_kontrak", "tanggal_pembuatan", "tempat_lahir", "tanggal_lahir", "alamat", "rt_rw", "kelurahan", "kecamatan", "kota_kabupaten", "nik_ktp", "pendidikan_terakhir", "no_hp", "jabatan", "spr_bro", "tanggal_pembuatan_pks", "jabatan_sebelumnya", "nama_bm", "nama_tl", "alamat_email", "jenis_kelamin", "level", "status_karyawan"],
            "REFINANCING": ["no", "nama_karyawan", "cabang", "nomor_kontrak", "tanggal_pembuatan", "tempat_lahir", "tanggal_lahir", "alamat", "rt_rw", "kelurahan", "kecamatan", "kota_kabupaten", "nik_ktp", "pendidikan_terakhir", "no_hp", "jabatan", "spr_bro", "tanggal_pembuatan_pks", "nama_bm", "alamat_email", "jenis_kelamin", "status_karyawan"],
            "recovery": ["no", "nama_karyawan", "cabang", "nomor_kontrak", "tanggal_pembuatan", "tempat_lahir", "tanggal_lahir", "alamat", "rt_rw", "kelurahan", "kecamatan", "kota_kabupaten", "nik_ktp", "pendidikan_terakhir", "no_hp", "jabatan", "allowance", "tanggal_pembuatan_pks", "alamat_email", "jenis_kelamin", "status_karyawan"],
            "RC ZONA A": ["no", "nama_karyawan", "cabang", "nomor_kontrak", "tanggal_pembuatan", "tempat_lahir", "tanggal_lahir", "alamat", "rt_rw", "kelurahan", "kecamatan", "kota_kabupaten", "nik_ktp", "pendidikan_terakhir", "no_hp", "tanggal_pembuatan_pks", "alamat_email", "jenis_kelamin", "nik_karyawan", "om", "status_karyawan"],
            "RC ZONA B": ["no", "nama_karyawan", "cabang", "nomor_kontrak", "tanggal_pembuatan", "tempat_lahir", "tanggal_lahir", "alamat", "rt_rw", "kelurahan", "kecamatan", "kota_kabupaten", "nik_ktp", "pendidikan_terakhir", "no_hp", "jabatan", "tanggal_pembuatan_pks", "alamat_email", "jenis_kelamin", "nama_cm", "status_karyawan"],
            "DC ZONA A": ["no", "nama_karyawan", "cabang", "nomor_kontrak", "tanggal_pembuatan", "tempat_lahir", "tanggal_lahir", "alamat", "rt_rw", "kelurahan", "kecamatan", "kota_kabupaten", "nik_ktp", "pendidikan_terakhir", "no_hp", "jabatan", "allowance", "tanggal_pembuatan_pks", "tunjangan_kesehatan", "alamat_email", "jenis_kelamin", "nik_karyawan", "status", "status_karyawan"],
            "DC ZONA B": ["no", "nama_karyawan", "cabang", "nomor_kontrak", "tanggal_pembuatan", "tempat_lahir", "tanggal_lahir", "alamat", "rt_rw", "kelurahan", "kecamatan", "kota_kabupaten", "nik_ktp", "pendidikan_terakhir", "no_hp", "jabatan", "allowance", "tanggal_pembuatan_pks", "tunjangan_kesehatan", "alamat_email", "jenis_kelamin", "nik_karyawan", "status", "status_karyawan"],
            "RC ROLL BACK": ["no", "nama_karyawan", "cabang", "nomor_kontrak", "tanggal_pembuatan", "tempat_lahir", "tanggal_lahir", "alamat", "rt_rw", "kelurahan", "kecamatan", "kota_kabupaten", "nik_ktp", "pendidikan_terakhir", "no_hp", "tanggal_pembuatan_pks", "tunjangan_kesehatan", "alamat_email", "jenis_kelamin", "nik_karyawan", "om", "join_date", "status_karyawan", "status", "end_date", "nomor_rekening", "nama_bank", "jabatan"],
            "RC QC": ["no", "nama_karyawan", "cabang", "nomor_kontrak", "tanggal_pembuatan", "tempat_lahir", "tanggal_lahir", "alamat", "rt_rw", "kelurahan", "kecamatan", "kota_kabupaten", "nik_ktp", "pendidikan_terakhir", "no_hp", "jabatan", "tanggal_pembuatan_pks", "alamat_email", "jenis_kelamin", "nik_karyawan", "status", "join_date", "status_karyawan", "end_date", "nomor_rekening", "nama_bank"]
        };

        // --- Fungsi Utility Dasar ---
        function ensureStatusAndEndDate(fieldsArr) {
            const hasStatus = fieldsArr.some(f => f.toLowerCase() === 'status_karyawan');
            const hasEnd = fieldsArr.some(f => f.toLowerCase() === 'end_date');
            if (!hasStatus) fieldsArr.push('status_karyawan');
            if (!hasEnd) fieldsArr.push('end_date');
            return fieldsArr;
        }
        function esc(v) { return (v ?? '').toString().replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') }
        function fmtDate(v) { if (!v) return '-'; const d = new Date(v); if (isNaN(d)) return v; return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' }) }
        function toDateInput(v) { if (!v) return ''; if (/^\d{4}-\d{2}-\d{2}$/.test(v)) return v; const d = new Date(v); if (isNaN(d)) return v; const iso = new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString(); return iso.slice(0, 10) }
        function card(label, value) { return `<div class="detail-card"><p class="detail-label">${esc(label)}</p><p class="detail-value">${esc(value ?? '-')}</p></div>` }
        function setText(id, value) { var el = document.getElementById(id); if (el) el.textContent = value ?? '-' }
        function ucLabel(raw) {
            const t = (raw || '').toString().replace(/_/g, ' ').toLowerCase().replace(/\b\w/g, c => c.toUpperCase());
            return t.replace(/\bNik\b/g, 'NIK').replace(/\bBpjs\b/g, 'BPJS').replace(/\bBpjamsostek\b/g, 'BPJamsostek')
                .replace(/\bUmk\b/g, 'UMK').replace(/\bUmp\b/g, 'UMP').replace(/\bPks\b/g, 'PKS')
                .replace(/\bId\b/g, 'ID').replace(/\bNpwp\b/g, 'NPWP').replace(/\bNip\b/g, 'NIP');
        }
        function slugify(label) { return label.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').replace(/_{2,}/g, '_') }


        // --- FUNGSI UTAMA: MENENTUKAN INPUT DENGAN LOGIKA REQUIRED (Mode Edit Opsional vs Mode Tambah Wajib) ---
        function inputFor(label, isEdit = false) {
            const name = slugify(label);
            const lc = label.toLowerCase().trim();
            const slug = name;

            let req = '';

            // Field Wajib di Edit (Minimum Essential Data)
            if (slug === 'nama_karyawan' || slug === 'nik_ktp' || slug === 'proyek' || slug === 'jabatan' || slug === 'status_karyawan') {
                req = ' required ';
            }
            // Field Wajib di Tambah (Jika bukan mode Edit)
            else if (!isEdit) {
                // Di mode Tambah, hampir semua field lain wajib kecuali NIP, Role, Sub-Project
                if (slug !== 'nip' && slug !== 'role' && slug !== 'sub_project_cnaf') {
                    req = ' required ';
                }
            }

            // Tipe input spesifik
            const plain = lc.replace(/[^a-z0-9]/g, '');
            if (slug === "status_karyawan") {
                return `<label>${label}
        <select name="${name}" ${req}>
            <option value="">— Pilih —</option>
            <option value="MITRA">MITRA</option>
            <option value="PKWT">PKWT</option>
        </select>
    </label>`;
            }
            if (slug.includes("email")) {
                return `<label>${label}<input type="email" name="${name}" placeholder="${label}" ${req}></label>`;
            }
            if (slug === "role") {
                const selectedRole = OLD[name] || '';
                return `<label>${label}<select name="${name}" ${req}><option value="">— Pilih —</option><option value="KARYAWAN" ${selectedRole === 'KARYAWAN' ? 'selected' : ''}>KARYAWAN</option><option value="HRD" ${selectedRole === 'HRD' ? 'selected' : ''}>HRD</option><option value="ADMIN" ${selectedRole === 'ADMIN' ? 'selected' : ''}>ADMIN</option></select></label>`;
            }
            if (slug.includes("tanggal") || slug.includes("date") || slug.includes("tgl") || lc === "tanggal" || slug.includes("pks")) {
                return `<label>${label}<input type="date" name="${name}" placeholder="${label}" ${req}></label>`;
            }
            if (slug === "jenis_kelamin" || plain.includes("gender")) {
                return `<label>${label}
            <select name="${name}" ${req}>
                <option value="">— Pilih —</option>
                <option value="Laki-laki">Laki-laki</option>
                <option value="Perempuan">Perempuan</option>
            </select>
        </label>`;
            }

            // Status Karyawan sudah dihandle di awal
            if (slug === "status") {
                return `<label>${label}<select name="${name}" ${req}><option value="">— Pilih —</option><option value="AKTIF">AKTIF</option><option value="TIDAK AKTIF">TIDAK AKTIF</option></select></label>`;
            }

            if (slug === "rt_rw") {
                const rtReq = isEdit ? '' : req;
                return `<label>${label}
            <input type="text" name="${name}" placeholder="000/000" pattern="\\d{3}/\\d{3}" ${rtReq}>
        </label><small class="muted">Format: 3/3 angka. Contoh: 005/012</small>`;
            }

            // Logika NIK & Angka
            const isNumeric = slug.startsWith("no_") || slug.includes("nomor") || slug.includes("nik") || slug.includes("kk") || slug.includes("npwp") || slug.includes("bpjs") || slug.includes("bpjamsostek") || slug.includes("norek") || slug.includes("rekening") || slug.includes("nip") || slug.includes("sales_code") || slug.includes("reff") || slug.includes("no_kontrak") || slug.includes("nomor_kontrak") || slug === "no_hp";

            if (isNumeric) {
                if (slug.includes('nik')) {
                    const nikReq = slug === 'nik_ktp' ? 'required' : req;
                    return `<label>${label}<input type="text" name="${name}" placeholder="${label}" inputmode="numeric" pattern="\\d{16}" minlength="16" maxlength="16" ${nikReq}></label><small class="muted">Masukkan tepat 16 digit angka.</small>`;
                }
                return `<label>${label}<input type="text" name="${name}" placeholder="${label}" ${req}></label>`;
            }

            if (slug.includes("umk") || slug.includes("ump") || slug.includes("gapok") || slug.includes("allowance") || slug.includes("tunjangan")) {
                return `<label>${label}<input type="number" name="${name}" step="1" min="0" placeholder="${label}" ${req}></label>`;
            }

            if (slug.includes("alamat")) {
                return `<label>${label}<textarea rows="2" name="${name}" placeholder="${label}" ${req}></textarea></label>`;
            }

            return `<label>${label}<input type="text" name="${name}" placeholder="${label}" ${req}></label>`;
        }


        // --- FUNGSI PENDUKUNG LOGIKA END DATE (PKWT) ---
        function showHideEndDate(container, isPKWT) {
            const endInput = container.querySelector('[name="end_date"]');
            if (!endInput) return;
            const group = endInput.closest('.form-group') || endInput.parentElement;
            if (isPKWT) {
                group.style.display = '';
                endInput.required = true;
            } else {
                group.style.display = 'none';
                endInput.required = false;
                endInput.value = '';
            }
        }
        function applyStatusEndDateLogic(container) {
            const sel = container.querySelector('select[name="status_karyawan"]');
            const end = container.querySelector('[name="end_date"]');
            if (!sel || !end) return;

            const initPKWT = (sel.value || '').toUpperCase() === 'PKWT';
            showHideEndDate(container, initPKWT);

            sel.addEventListener('change', function () {
                const isPKWT = (this.value || '').toUpperCase() === 'PKWT';
                showHideEndDate(container, isPKWT);
            });
        }


        // --- FUNGSI UNTUK MENGISI NILAI FORM (Edit) ---
        function fillFormValues(container, data) {
            const fields = container.querySelectorAll('input,select,textarea');
            fields.forEach(el => {
                const name = el.name; if (!name) return;
                let v = data[name];
                if (v === undefined) {
                    const target = name.replace(/_/g, '');
                    for (const k in data) { if (k.replace(/_/g, '') === target) { v = data[k]; break; } }
                }
                if (v === undefined || v === null) { el.value = ''; return; }
                if (el.type === 'date') el.value = toDateInput(v);
                else el.value = v;
            });
        }


        // --- RENDER FORM UNTUK MODAL TAMBAH (Menggunakan field spesifik project dan Wajib Isi) ---
        function renderFormForProject(proj, subProj = null) {
            const dynamicBox = document.getElementById('dynamic_fields');
            const labelsHidden = document.getElementById('__labels_map');
            const tag = document.getElementById('proyek_tag');
            const submitBox = document.getElementById('submit_container');
            const proyekInput = document.getElementById('proyek_input');

            if (!dynamicBox || !labelsHidden || !tag || !submitBox || !proyekInput) return;
            proyekInput.value = proj || '';
            dynamicBox.innerHTML = ''; labelsHidden.value = '';

            let fields = [];
            let isCnafSub = false;

            if (proj === 'CNAF' && subProj && CNAF_SUB_FIELDS[subProj]) {
                fields = CNAF_SUB_FIELDS[subProj].slice();
                isCnafSub = true;
            } else if (PROJECT_FIELDS[proj]) {
                fields = PROJECT_FIELDS[proj].slice();
            }

            if (!proj || fields.length === 0) { dynamicBox.style.display = 'none'; tag.style.visibility = 'hidden'; submitBox.style.display = 'none'; return; }

            if (!isCnafSub) {
                fields = ensureStatusAndEndDate(fields);
            }

            const displayTag = proj + (isCnafSub ? ` - ${subProj}` : '');
            tag.textContent = `Project: ${displayTag}`; tag.style.visibility = 'visible';

            const map = {};

            fields.forEach(raw => {
                const label = ucLabel(raw);
                const el = document.createElement('div');
                el.className = 'form-group';
                // Mode Tambah: isEdit=false (Default) -> Required
                el.innerHTML = inputFor(label);
                dynamicBox.appendChild(el);
                map[slugify(raw)] = label;
            });
            labelsHidden.value = JSON.stringify(map);

            dynamicBox.style.display = 'grid'; dynamicBox.style.gridTemplateColumns = '1fr 1fr'; submitBox.style.display = 'block';
            applyStatusEndDateLogic(dynamicBox);
        }


        // --- RENDER FORM UNTUK MODAL EDIT (Menggunakan ALL_FIELDS dan Opsional) ---
        function renderEditFormForProject(proj) {
            const editBox = document.getElementById('edit_dynamic_fields');
            const editLabelsHidden = document.getElementById('edit__labels_map');

            if (!editBox || !editLabelsHidden) return;
            editBox.innerHTML = ''; editLabelsHidden.value = '';

            if (!proj) {
                editBox.innerHTML = '<p>Project tidak terdefinisi.</p>';
                editBox.style.display = 'block';
                return;
            }

            const fields = ALL_FIELDS.slice();
            const map = {};

            fields.forEach(raw => {
                const label = ucLabel(raw);
                const el = document.createElement('div');
                el.className = 'form-group';

                // Mode Edit: isEdit=true -> Opsional (kecuali NIK KTP, Nama, Jabatan, Status Karyawan)
                el.innerHTML = inputFor(label, true);

                editBox.appendChild(el);
                map[slugify(raw)] = label;
            });

            editLabelsHidden.value = JSON.stringify(map);
            editBox.style.display = 'grid';
            editBox.style.gridTemplateColumns = '1fr 1fr';

            applyStatusEndDateLogic(editBox);
        }


        // =======================================================
        // FUNGSI HANDLER YANG DIPANGGIL DARI HTML (GLOBAL)
        // =======================================================

        // Fungsi untuk Membuka Modal Tambah
        window.openModal = function () {
            const addModal = document.getElementById("addEmployeeModal");
            const projectSelect = document.getElementById('project_select');
            const subCnafSelect = document.getElementById('sub_project_cnaf');

            if (addModal) {
                addModal.style.display = "block";
                const proj = projectSelect.value;
                const subProj = proj === 'CNAF' ? subCnafSelect.value : null;
                renderFormForProject(proj, subProj);
            }
        };

        // Fungsi untuk Membuka Modal Lihat (VIEW)
        window.openViewModal = function (id_karyawan) {
            const viewModal = document.getElementById("viewEmployeeModal");
            const gridPribadi = document.getElementById("gridPribadi");
            const gridPekerjaan = document.getElementById("gridPekerjaan");
            const gridKontak = document.getElementById("gridKontak");
            const gridLain = document.getElementById("gridLain");

            // Reset dan tampilkan loading
            if (gridPribadi) gridPribadi.innerHTML = "<p>Memuat...</p>";
            if (gridPekerjaan) gridPekerjaan.innerHTML = "";
            if (gridKontak) gridKontak.innerHTML = "";
            if (gridLain) gridLain.innerHTML = "";
            setText("empName", "Memuat..."); setText("empSub", "..."); setText("badgeProyek", "-"); setText("badgeStatus", "-"); setText("badgeStatusKar", "-");
            document.getElementById("empAvatar").textContent = "KR";

            var xhr = new XMLHttpRequest();
            xhr.open("GET", "get_employee_details.php?id=" + encodeURIComponent(id_karyawan), true);
            xhr.onload = function () {
                if (this.status == 200) {
                    let data;
                    try { data = JSON.parse(this.responseText); } catch (e) { data = null; }

                    if (!data || data.error) {
                        if (gridPribadi) gridPribadi.innerHTML = "<p>Data tidak ditemukan.</p>";
                    } else {
                        // Mengisi header modal
                        setText("empName", data.nama_karyawan || '-');
                        setText("empSub", ((data.nik_karyawan || data.nik_ktp || '-') + " • " + (data.jabatan || '-')));
                        document.getElementById("empAvatar").textContent = (data.nama_karyawan || 'KR').substr(0, 2).toUpperCase();
                        setText("badgeProyek", data.proyek || '-'); setText("badgeStatus", data.status || '-'); setText("badgeStatusKar", data.status_karyawan || '-');

                        // Menyiapkan konten per tab
                        const pribadi = [
                            ["NIK KTP", data.nik_ktp], ["NIK Karyawan", data.nik_karyawan], ["Tempat Lahir", data.tempat_lahir], ["Tanggal Lahir", fmtDate(data.tanggal_lahir)], ["Jenis Kelamin", data.jenis_kelamin], ["Alamat", data.alamat], ["Kelurahan", data.kelurahan], ["Kecamatan", data.kecamatan], ["Kota/Kabupaten", data.kota_kabupaten], ["RT/RW", data.rt_rw], ["Pendidikan Terakhir", data.pendidikan_terakhir], ["No. KK", data.no_kk], ["Nama Ayah", data.nama_ayah], ["Nama Ibu", data.nama_ibu]
                        ];
                        if (gridPribadi) gridPribadi.innerHTML = pribadi.map(([l, v]) => card(l, v)).join("");

                        const pekerjaan = [
                            ["Project", data.proyek], ["Jabatan", data.jabatan], ["Penempatan/Cabang", data.penempatan || data.cabang], ["Area/Kota", data.area || data.kota], ["Team Leader", data.team_leader], ["Recruiter/RO", data.recruiter || data.recruitment_officer], ["Sales Code", data.sales_code], ["Join Date", fmtDate(data.join_date)], ["End Date", fmtDate(data.end_date)], ["End Date PKS", fmtDate(data.end_date_pks)], ["Nomor Kontrak", data.nomor_kontrak], ["Tanggal PKS", fmtDate(data.tanggal_pembuatan_pks)], ["Nomor Reff", data.nomor_reff], ["UMK/UMP", data.umk_ump], ["Gapok (Internal)", data.gapok], ["Status Karyawan", data.status_karyawan], ["Status Aktif", data.status], ["Tanggal Resign", fmtDate(data.tgl_resign)], ["JOB (CIMB)", data.job], ["CHANNEL (CIMB)", data.channel], ["TGL RMU (CIMB)", fmtDate(data.tgl_rmu)], ["Nama SM (CIMB)", data.nama_sm], ["Nama SH (CIMB)", data.nama_sh], ["Tanggal Pernyataan (SMBCI)", fmtDate(data.tanggal_pernyataan)], ["Nomor Surat Tugas (SMBCI)", data.nomor_surat_tugas], ["Masa Penugasan (SMBCI)", data.masa_penugasan], ["Nama User (SMBCI)", data.nama_user], ["Tanggal Pembuatan", data.tanggal_pembuatan], ["SPR & BRO", data.spr_bro], ["BM/SM", data.nama_bm_sm], ["TL", data.nama_tl], ["level", data.level], ["Tanggal PKM", data.tanggal_pkm], ["SM / CM", data.nama_sm_cm], ["SBI", data.sbi], ["Tanggal SIGN KONTRAK", data.tanggal_sign_kontrak], ["Nama OH", data.nama_oh], ["Jabatan Sebelumnya", data.jabatan_sebelumnya], ["BM", data.nama_bm], ["Allowance", data.allowance], ["Tunjangan Kesehatan", data.tunjangan_kesehatan], ["OM", data.om], ["Nama CM", data.nama_cm]
                        ];
                        if (gridPekerjaan) gridPekerjaan.innerHTML = pekerjaan.map(([l, v]) => card(l, v)).join("");

                        const kontak = [
                            ["Email", data.alamat_email], ["No. HP", data.no_hp], ["Alamat Tinggal", data.alamat_tinggal], ["NPWP", data.npwp], ["Status Pajak", data.status_pajak], ["No. BPJamsostek", data.no_bpjamsostek], ["No. BPJS Kesehatan", data.no_bpjs_kes], ["Nomor Rekening", data.nomor_rekening], ["Nama Bank", data.nama_bank]
                        ];
                        if (gridKontak) gridKontak.innerHTML = kontak.map(([l, v]) => card(l, v)).join("");

                        const lainnya = [
                            ["NIP", data.nip], ["Role Aplikasi", data.role], ["End Of Contract", fmtDate(data.end_of_contract)], ["Manager (Internal)", data.manager], ["TL (Internal)", data.tl]
                        ];
                        if (gridLain) gridLain.innerHTML = lainnya.map(([l, v]) => card(l, v)).join("");
                    }
                } else {
                    if (gridPribadi) gridPribadi.innerHTML = "<p>Error saat mengambil data.</p>";
                }
                viewModal.style.display = "block";
                // Panggil bindTabs di dalam DOMContentLoaded, namun karena sudah dipindahkan 
                // ke bawah, kita biarkan saja bagian ini. Cukup pastikan tabs bekerja.
                // Anda mungkin perlu memastikan logika binding tabs ada di DOMContentLoaded
            };
            xhr.send();
        };

        // Fungsi untuk Membuka Modal Edit
        window.openEditModal = function (id_karyawan, proyekHint) {
            const editId = document.getElementById('edit_id_karyawan');
            const editProyekText = document.getElementById('edit_proyek_text');
            const editProyek = document.getElementById('edit_proyek');
            const editBox = document.getElementById('edit_dynamic_fields');
            const editModal = document.getElementById("editEmployeeModal");

            editId.value = id_karyawan;
            const hint = (proyekHint || '').toUpperCase();

            // Render form menggunakan hint proyek saat ini
            if (hint && PROJECT_FIELDS[hint]) {
                editProyekText.value = hint;
                editProyek.value = hint;
                renderEditFormForProject(hint);
            } else {
                editProyekText.value = '-';
                editProyek.value = '';
                editBox.innerHTML = '<p>Project tidak dikenali.</p>';
                editBox.style.display = 'block';
            }

            // Ambil data melalui AJAX
            var xhr = new XMLHttpRequest();
            xhr.open("GET", "get_employee_details.php?id=" + encodeURIComponent(id_karyawan), true);
            xhr.onload = function () {
                if (this.status == 200) {
                    let data;
                    try { data = JSON.parse(this.responseText); } catch (e) { data = null; }

                    if (!data || data.error) {
                        editBox.innerHTML = '<p>Data tidak ditemukan atau terjadi kesalahan.</p>';
                    } else {
                        const proj = (data.proyek || hint || '').toUpperCase();
                        if (editProyek.value !== proj) {
                            editProyekText.value = proj;
                            editProyek.value = proj;
                            renderEditFormForProject(proj);
                        }
                        fillFormValues(editBox, data);
                        applyStatusEndDateLogic(editBox);
                    }
                } else { editBox.innerHTML = '<p>Error saat mengambil data.</p>'; }
                editModal.style.display = 'block';
            };
            xhr.send();
        };

        // Fungsi untuk Menutup Modal
        window.closeModal = function (modalId = 'addEmployeeModal') { document.getElementById(modalId).style.display = "none"; };
        window.closeViewModal = function () { document.getElementById("viewEmployeeModal").style.display = "none"; };
        window.closeEditModal = function () { document.getElementById("editEmployeeModal").style.display = "none"; };

        // Fungsi untuk Upload
        window.openUploadModal = function (id, nama) {
            document.getElementById('upload_id_karyawan').value = id;
            document.getElementById('upload_nama_karyawan').value = nama;
            document.getElementById('uploadSuratTugasModal').style.display = 'block';
        };

        // Fungsi untuk Delete
        window.confirmDelete = function (id) {
            if (!id) return;
            if (confirm('Yakin ingin menghapus karyawan ini? Tindakan ini tidak bisa dibatalkan.')) {
                window.location.href = 'delete_employee.php?id=' + encodeURIComponent(id);
            }
        };


        // =======================================================
        // DOM CONTENT LOADED (HANYA INI YANG BERJALAN SAAT DOM SIAP)
        // =======================================================
        document.addEventListener('DOMContentLoaded', function () {
            const addModal = document.getElementById("addEmployeeModal");
            const viewModal = document.getElementById("viewEmployeeModal");
            const editModal = document.getElementById("editEmployeeModal");
            const uploadModal = document.getElementById("uploadSuratTugasModal");

            const projectSelect = document.getElementById('project_select');
            const subCnafSelect = document.getElementById('sub_project_cnaf');

            // Event listener untuk memilih Project (Modal Tambah)
            projectSelect.addEventListener('change', function () {
                if (this.value === 'CNAF') {
                    document.getElementById('sub_project_cnaf_wrap').style.display = 'block';
                    subCnafSelect.setAttribute('required', 'required');
                    renderFormForProject(this.value, subCnafSelect.value);
                } else {
                    document.getElementById('sub_project_cnaf_wrap').style.display = 'none';
                    subCnafSelect.removeAttribute('required');
                    subCnafSelect.value = '';
                    renderFormForProject(this.value);
                }
            });

            // Event listener untuk Sub Project CNAF (Modal Tambah)
            subCnafSelect.addEventListener('change', function () {
                if (projectSelect.value === 'CNAF') {
                    renderFormForProject(projectSelect.value, this.value);
                }
            });

            // Binding tabs untuk modal view
            const viewTabs = viewModal ? viewModal.querySelectorAll('.tab-btn') : [];
            viewTabs.forEach(btn => {
                btn.onclick = function () {
                    viewTabs.forEach(b => b.classList.remove('active'));
                    document.querySelectorAll('#viewEmployeeModal .tab-panel').forEach(p => p.classList.remove('active'));
                    btn.classList.add('active');
                    const panel = document.getElementById(btn.dataset.tab);
                    if (panel) panel.classList.add('active');
                };
            });

            // Mengaktifkan esc dan klik luar modal
            window.addEventListener('click', function (event) {
                if (event.target === addModal) closeModal();
                if (event.target === viewModal) closeViewModal();
                if (event.target === editModal) closeEditModal();
                if (event.target === uploadModal) closeModal('uploadSuratTugasModal');
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') { closeModal(); closeViewModal(); closeEditModal(); closeModal('uploadSuratTugasModal'); }
            });


            // Inisialisasi awal (terutama untuk prefill error)
            if (OPEN_ADD) {
                if (typeof openModal === 'function') openModal();
                if (OLD && OLD.proyek && projectSelect) {
                    projectSelect.value = OLD.proyek;
                    if (OLD.proyek === 'CNAF') {
                        document.getElementById('sub_project_cnaf_wrap').style.display = 'block';
                        subCnafSelect.value = OLD.sub_project_cnaf || '';
                    }
                    if (typeof renderFormForProject === 'function') renderFormForProject(projectSelect.value, OLD.sub_project_cnaf);
                }
                setTimeout(() => {
                    const form = document.getElementById('projectAddForm');
                    if (!form || !OLD) return;
                    fillFormValues(form, OLD);
                    applyStatusEndDateLogic(document.getElementById('dynamic_fields'));
                }, 0);
            }
        });
    </script>
</body>

</html>