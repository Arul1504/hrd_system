<?php
// ============= karyawan_nonaktif.php (pakai sesi login) =============
// Menampilkan Karyawan TIDAK AKTIF dari tabel `karyawan` + sidebar user login

require_once __DIR__ . '/../config.php';   // pakai koneksi $conn dan helper e($v)
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// --- Akses hanya HRD ---
if (!isset($_SESSION['id_karyawan']) || (($_SESSION['role'] ?? '') !== 'HRD')) {
    header('Location: ../login.php'); // sesuaikan jika perlu
    exit;
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

// --- Flash message dari URL (opsional) ---
$message = '';
if (isset($_GET['status'])) {
    $map = [
        'deleted' => 'Data karyawan nonaktif berhasil dihapus!',
        'updated' => 'Data karyawan nonaktif berhasil diperbarui!',
        'activated' => 'Karyawan berhasil diaktifkan kembali!',
    ];
    if (isset($map[$_GET['status']])) {
        $message = '<div class="alert success">'.e($map[$_GET['status']]).'</div>';
    }
}

// --- Parameter filter/pencarian ---
$search_query   = $_GET['search']      ?? '';
$filter_dept    = $_GET['departemen']  ?? '';
$filter_jabatan = $_GET['jabatan']     ?? '';

// Helper bind by-reference
function bind_params_ref(mysqli_stmt $stmt, string $types, array &$params): void {
    $bind = array_merge([$types], $params);
    $refs = [];
    foreach ($bind as $k => &$v) { $refs[$k] = &$bind[$k]; }
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

// ======================= Ambil data nonaktif dari karyawan ======================
$sql = "
    SELECT
        COALESCE(NULLIF(k.nik_karyawan,''), NULLIF(k.nik_ktp,'')) AS nik,
        k.nama_karyawan                                        AS nama,
        k.jabatan,
        COALESCE(NULLIF(k.cabang,''), NULLIF(k.penempatan,''), NULLIF(k.area,'')) AS departemen,
        COALESCE(k.end_of_contract, k.end_date_pks, k.end_date) AS tanggal_akhir_kontrak,
        NULL AS surat_paklaring
    FROM karyawan k
    WHERE UPPER(k.status) = 'TIDAK AKTIF'
";

$params = [];
$types  = '';

if ($search_query !== '') {
    $sql .= " AND (COALESCE(k.nik_karyawan, k.nik_ktp) LIKE ? OR k.nama_karyawan LIKE ?)";
    $like = "%{$search_query}%";
    $params[] = &$like; $params[] = &$like; $types .= "ss";
}
if ($filter_dept !== '') {
    $sql .= " AND COALESCE(k.cabang, k.penempatan, k.area) = ?";
    $params[] = &$filter_dept; $types .= "s";
}

if ($filter_jabatan !== '') {
    $sql .= " AND k.jabatan = ?";
    $params[] = &$filter_jabatan; $types .= "s";
}
$sql .= " ORDER BY k.nama_karyawan ASC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    if (!empty($params)) bind_params_ref($stmt, $types, $params);
    $stmt->execute();
    $result    = $stmt->get_result();
    $employees = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $employees = [];
    error_log("SQL error nonaktif: ".$conn->error);
}

// ======================= Data dropdown =======================
$departments_result = $conn->query("
    SELECT DISTINCT
        COALESCE(NULLIF(cabang,''), NULLIF(penempatan,''), NULLIF(area,'')) AS departemen
    FROM karyawan
    WHERE UPPER(status) = 'TIDAK AKTIF'
    ORDER BY departemen
");

$jabatan_result = $conn->query("
    SELECT DISTINCT jabatan
    FROM karyawan
    WHERE UPPER(status) = 'TIDAK AKTIF' AND jabatan IS NOT NULL AND jabatan <> ''
    ORDER BY jabatan
");
$all_departments = $departments_result ? $departments_result->fetch_all(MYSQLI_ASSOC) : [];
$all_jabatan     = $jabatan_result ? $jabatan_result->fetch_all(MYSQLI_ASSOC) : [];

?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Data Karyawan Nonaktif</title>
<link rel="stylesheet" href="../style.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
.toolbar{display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap}
.search-filter-container{display:flex;flex-direction:column;gap:10px;flex:1}
.search-form{display:flex;flex-direction:column;gap:10px}
.filter-box{display:flex;gap:10px;flex-wrap:wrap}
.filter-box select{
    appearance:none;-webkit-appearance:none;-moz-appearance:none;
    padding:8px 14px;border:1px solid #d1d5db;border-radius:8px;background:#f9fafb;
    font-size:14px;font-family:'Poppins',sans-serif;color:#374151;cursor:pointer;transition:.2s;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='gray' viewBox='0 0 20 20'%3E%3Cpath fill-rule='evenodd' d='M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.94a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z' clip-rule='evenodd'/%3E%3C/svg%3E");
    background-repeat:no-repeat;background-position:right 12px center;background-size:16px;padding-right:32px;
}
.filter-box select:hover{border-color:#2563eb;background:#fff;box-shadow:0 0 0 2px rgba(37,99,235,.15)}
.filter-box select:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 2px rgba(37,99,235,.25)}
.action-buttons{display:flex;gap:10px;align-items:center}
.download-btn{padding:8px 14px;border-radius:6px;color:#fff;text-decoration:none;font-size:14px;display:inline-flex;align-items:center;gap:6px;transition:background .2s}
.download-btn.pdf{background:#e74c3c}.download-btn.pdf:hover{background:#c0392b}
.download-btn.excel{background:#27ae60}.download-btn.excel:hover{background:#1e8449}
.alert{padding:12px 16px;border-radius:6px;margin:12px 0;font-weight:600;background:#ecfdf5;color:#047857}
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
                    <div class="user-avatar"><?= strtoupper(substr($nama_user_hrd, 0, 2)) ?></div>
                    <div>
                        <p><b><?= $nama_user_hrd ?></b></p>
                        <small><?= $nik_user_hrd ?> | <?= $jabatan_user_hrd ?></small>
                    </div>
                </div>

        <nav class="sidebar-nav">
            <ul>
                <li><a href="../dashboard_hrd.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li class="active dropdown-trigger">
                    <a href="#" class="dropdown-link"><i class="fas fa-users"></i> Data Karyawan <i class="fas fa-caret-down"></i></a>
                    <ul class="dropdown-menu">
                        <li><a href="all_employees.php">Semua Karyawan</a></li>
                        <li><a href="karyawan_nonaktif.php">Non-aktif</a></li>
                    </ul>
                </li>
                <li class="dropdown-trigger">
                        <a href="#" class="dropdown-link"><i class="fas fa-users"></i> Data Pengajuan <i
                                class="fas fa-caret-down"></i></a>
                        <ul class="dropdown-menu">
                            <li><a href="../pengajuan/pengajuan.php">Pengajuan</a></li>
                            <li><a href="../pengajuan/kelola_pengajuan.php">Kelola Pengajuan</a></li>
                        </ul>
                    </li>
                <li><a href="../monitoring_kontrak/monitoring_kontrak.php"><i class="fas fa-calendar-alt"></i> Monitoring Kontrak</a></li>
            </ul>
        </nav>

        <div class="logout-link">
            <a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a>
        </div>
    </aside>

    <main class="main-content">
        <header class="main-header">
            <h1>Data Karyawan Nonaktif</h1>
            <p class="current-date"><?= date('l, d F Y'); ?></p>
        </header>

        <?= $message ?>

        <div class="toolbar">
            <div class="search-filter-container">
                <form action="karyawan_nonaktif.php" method="GET" class="search-form">
                    <div class="search-box">
                        <input type="text" name="search" placeholder="Cari NIK atau Nama..." value="<?= e($search_query) ?>">
                        <button type="submit"><i class="fas fa-search"></i></button>
                    </div>
                    <div class="filter-box">
                        <select name="departemen" onchange="this.form.submit()">
                            <option value="">Semua Departemen</option>
                            <?php foreach ($all_departments as $dept):
                                $dep = $dept['departemen'] ?? '';
                                $sel = ($filter_dept === $dep) ? 'selected' : ''; ?>
                                <option value="<?= e($dep) ?>" <?= $sel ?>><?= e($dep) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="jabatan" onchange="this.form.submit()">
                            <option value="">Semua Jabatan</option>
                            <?php foreach ($all_jabatan as $pos):
                                $jb = $pos['jabatan'] ?? '';
                                $sel = ($filter_jabatan === $jb) ? 'selected' : ''; ?>
                                <option value="<?= e($jb) ?>" <?= $sel ?>><?= e($jb) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>

            <div class="action-buttons">
                <a class="download-btn pdf"
                   href="export_nonaktif_pdf.php?search=<?= urlencode($search_query) ?>&departemen=<?= urlencode($filter_dept) ?>&jabatan=<?= urlencode($filter_jabatan) ?>">
                    <i class="fas fa-file-pdf"></i> Unduh PDF
                </a>
                <a class="download-btn excel"
                   href="export_nonaktif_excel.php?search=<?= urlencode($search_query) ?>&departemen=<?= urlencode($filter_dept) ?>&jabatan=<?= urlencode($filter_jabatan) ?>">
                    <i class="fas fa-file-excel"></i> Unduh Excel
                </a>
            </div>
        </div>

        <div class="data-table-container">
            <table>
                <thead>
                    <tr>
                        <th>NIK</th>
                        <th>Nama Lengkap</th>
                        <th>Jabatan Terakhir</th>
                        <th>Departemen Terakhir</th>
                        <th>Tgl. Akhir Kontrak</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($employees)): ?>
                    <tr><td colspan="6" style="text-align:center;">Tidak ada data karyawan nonaktif yang ditemukan.</td></tr>
                <?php else: foreach ($employees as $row): ?>
                    <tr>
                        <td><?= e($row['nik'] ?? '-') ?></td>
                        <td><?= e($row['nama'] ?? '-') ?></td>
                        <td><?= e($row['jabatan'] ?? '-') ?></td>
                        <td><?= e($row['departemen'] ?? '-') ?></td>
                        <td><?= !empty($row['tanggal_akhir_kontrak']) ? date('d F Y', strtotime($row['tanggal_akhir_kontrak'])) : '-' ?></td>
                        <td class="action-buttons">
                            <button class="action-btn view-btn" title="Lihat" onclick="openViewModal('<?= e($row['nik'] ?? '') ?>')">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="action-btn" style="background:#3498db" title="Aktifkan"
                                    onclick="confirmActivate('<?= e($row['nik'] ?? '') ?>')">
                                <i class="fas fa-user-check"></i>
                            </button>
                            <button class="action-btn delete-btn" title="Hapus Permanen"
                                    onclick="confirmDelete('<?= e($row['nik'] ?? '') ?>')">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Modal detail sederhana -->
        <div id="viewEmployeeModal" class="modal" style="display:none;">
            <div class="modal-content">
                <span class="close-btn" onclick="closeViewModal()">&times;</span>
                <h2>Detail Karyawan Nonaktif</h2>
                <div id="employeeDetails">Memuat data…</div>
            </div>
        </div>
    </main>
</div>

<script>
function confirmActivate(nik){
    if(confirm("Aktifkan kembali karyawan ini?")) {
        window.location.href = "process_activate_employee.php?nik=" + encodeURIComponent(nik);
    }
}
function confirmDelete(nik){
    if(confirm("PERHATIAN!\nHapus permanen arsip karyawan ini?")) {
        window.location.href = "process_delete_nonaktif.php?nik=" + encodeURIComponent(nik);
    }
}

const viewModal = document.getElementById("viewEmployeeModal");
function openViewModal(nik){
    const box = document.getElementById("employeeDetails");
    box.innerHTML = "Memuat data…";
    viewModal.style.display = "block";
    // Jika ada endpoint detail, sesuaikan:
    fetch("get_nonaktif_details.php?nik=" + encodeURIComponent(nik))
      .then(r=>r.json()).then(d=>{
        if(d && !d.error){
          box.innerHTML = `
            <p><strong>NIK:</strong> ${d.nik || '-'}</p>
            <p><strong>Nama:</strong> ${d.nama || '-'}</p>
            <p><strong>Jabatan Terakhir:</strong> ${d.jabatan || '-'}</p>
            <p><strong>Departemen Terakhir:</strong> ${d.departemen || '-'}</p>
            <hr>
            <p><strong>Tanggal Masuk:</strong> ${d.tanggal_masuk_kerja || '-'}</p>
            <p><strong>Tanggal Akhir Kontrak:</strong> ${d.tanggal_akhir_kontrak || '-'}</p>
            <p><strong>Lokasi Kerja Terakhir:</strong> ${d.lokasi_kerja || '-'}</p>
            <hr>
            <p><strong>Email:</strong> ${d.email || '-'}</p>
            <p><strong>No. HP:</strong> ${d.no_hp || '-'}</p>
            <p><strong>Alamat:</strong> ${d.alamat || '-'}</p>`;
        } else { box.innerHTML = "Gagal memuat data atau data tidak ditemukan."; }
      })
      .catch(()=> box.innerHTML = "Terjadi kesalahan saat mengambil data.");
}
function closeViewModal(){ viewModal.style.display = "none"; }
window.onclick = function(e){ if(e.target === viewModal) closeViewModal(); }
</script>
</body>
</html>
