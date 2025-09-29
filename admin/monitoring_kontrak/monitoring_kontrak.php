<?php
/**
 * File: monitoring_kontrak.php (FINAL, aman STRICT MODE)
 */

require '../config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// --- CEK SESSION (hanya ADMIN) ---
if (!isset($_SESSION['id_karyawan']) || ($_SESSION['role'] ?? '') !== 'ADMIN') {
    header('Location: ../../index.php');
    exit();
}


// Helper


function dmy($v){ if(!$v) return '-'; $t=strtotime($v); return $t?date('d M Y',$t):$v; }
function safe_iso($v){
    if(!$v) return null;
    $d = date_create($v);
    return $d ? $d->format('Y-m-d') : null;
}

// Ambil filter
$filter_proyek  = $_GET['proyek'] ?? '';
$filter_status  = $_GET['status'] ?? 'AKTIF'; // default tampilkan AKTIF
$due_in_days    = (int)($_GET['due_in_days'] ?? 60);
$search         = trim($_GET['s'] ?? '');

$today     = date('Y-m-d');
$due_limit = date('Y-m-d', strtotime("+$due_in_days days"));

// Ambil data user dari sesi (diubah menjadi ADMIN)
$id_karyawan_admin = $_SESSION['id_karyawan'];
$nama_user_admin = $_SESSION['nama'];
$role_user_admin = $_SESSION['role'];

$stmt_admin_info = $conn->prepare("SELECT nik_ktp, jabatan FROM karyawan WHERE id_karyawan = ?");
$stmt_admin_info->bind_param("i", $id_karyawan_admin);
$stmt_admin_info->execute();
$result_admin_info = $stmt_admin_info->get_result();
$admin_info = $result_admin_info->fetch_assoc();

$sql_pending_requests = "SELECT COUNT(*) AS total_pending FROM pengajuan WHERE status_pengajuan = 'Menunggu'";
$result_pending_requests = $conn->query($sql_pending_requests);
$total_pending = $result_pending_requests->fetch_assoc()['total_pending']??0;

if ($admin_info) {
    $nik_user_admin = $admin_info['nik_ktp'];
    $jabatan_user_admin = $admin_info['jabatan'];
} else {
    $nik_user_admin = 'Tidak Ditemukan';
    $jabatan_user_admin = 'Tidak Ditemukan';
}
$stmt_admin_info->close();

// ====================== QUERY (BERSIH & AMAN) ======================
$sql = "
SELECT
    id_karyawan, nama_karyawan, jabatan, proyek, status, status_karyawan,
    join_date, end_date, end_of_contract, penempatan, cabang, kota, area,
    /* due_date aman: convert -> nullif -> str_to_date */
    COALESCE(
        STR_TO_DATE(NULLIF(CONVERT(end_date           , CHAR), ''), '%Y-%m-%d'),
        STR_TO_DATE(NULLIF(CONVERT(end_of_contract, CHAR), ''), '%Y-%m-%d')
    ) AS due_date
FROM karyawan
WHERE 1=1
";
$params = []; $types = '';

// filter status (kalau kosong = semua)
if ($filter_status !== '') {
    $sql .= " AND UPPER(status)=UPPER(?)";
    $params[] = $filter_status; $types .= 's';
}
// filter proyek
if ($filter_proyek !== '') {
    $sql .= " AND proyek=?";
    $params[] = $filter_proyek; $types .= 's';
}
// search
if ($search !== '') {
    $sql .= " AND (nama_karyawan LIKE ? OR nik_karyawan LIKE ? OR nik_ktp LIKE ?)";
    $like = "%$search%";
    $params[]=$like; $params[]=$like; $params[]=$like; $types.='sss';
}

/* Ambil yang jatuh tempo s.d due_limit (pakai kolom yang sudah dikonversi aman) */
$sql .= "
    AND (
        (
            STR_TO_DATE(NULLIF(CONVERT(end_date           , CHAR), ''), '%Y-%m-%d') IS NOT NULL
            AND STR_TO_DATE(CONVERT(end_date           , CHAR), '%Y-%m-%d') <= ?
        )
        OR
        (
            STR_TO_DATE(NULLIF(CONVERT(end_of_contract, CHAR), ''), '%Y-%m-%d') IS NOT NULL
            AND STR_TO_DATE(CONVERT(end_of_contract, CHAR), '%Y-%m-%d') <= ?
        )
    )
";

$params[] = $due_limit;
$params[] = $due_limit;
$types   .= 'ss';

/* Urutkan pakai due_date yang sudah aman */
$sql .= " ORDER BY due_date ASC, nama_karyawan ASC";

/* Eksekusi */
$rows = [];
$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Ambil distinct proyek untuk filter dropdown
$all_proyek = [];
$r_proyek = $conn->query("SELECT DISTINCT proyek FROM karyawan WHERE proyek IS NOT NULL AND proyek<>'' ORDER BY proyek");
if ($r_proyek) $all_proyek = array_column($r_proyek->fetch_all(MYSQLI_ASSOC), 'proyek');

// Flash
$flash = $_SESSION['flash_msg'] ?? ''; unset($_SESSION['flash_msg']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Monitoring Kontrak</title>
<link rel="stylesheet" href="../style.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
body {
    font-family: Poppins, system-ui, Arial, sans-serif;
    background: #f7f9fc;
    margin: 0;
}

/* Layout utama */
.container {
    display: grid;
    grid-template-columns: 260px 1fr;
    gap: 0;
    min-height: 100vh;
}

.main {
    padding: 18px 24px 18px 18px;
    overflow-x: auto;
    box-sizing: border-box;
    min-width: 0;
}

/* Button */
.btn {
    display: inline-flex;
    gap: 6px;
    align-items: center;
    border: none;
    border-radius: 8px;
    padding: 9px 12px;
    cursor: pointer;
    font-weight: 500;
}
.btn.primary {
    background: #2563eb;
    color: #fff;
}
.btn.ghost {
    background: #fff;
    border: 1px solid #e5e7eb;
}

/* Table */
.table-container {
    width: 100%;
    overflow-x: visible; /* Ini bisa menyebabkan pemotongan */
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
.table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
    min-width: 800px;
}
.table th,
.table td {
    border-bottom: 1px solid #eef0f3;
    padding: 10px;
    text-align: left;
    white-space: nowrap;
     position: relative; 
}
.table th {
    background: #f0f2f5;
    font-weight: 600;
}
.table tr:hover {
    background: #f9fafb;
}

/* Modal */
.modal {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, .35);
    display: none;
    align-items: center;
    justify-content: center;
    padding: 16px;
    z-index: 9999;
}
.modal .card {
    background: #fff;
    border-radius: 14px;
    max-width: 700px;
    width: 100%;
    padding: 16px;
}
.card h3 {
    margin: 0 0 8px;
}
.grid {
    display: grid;
    gap: 10px;
    grid-template-columns: 1fr 1fr;
}
.grid .form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 6px;
}
.grid input,
.grid textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
}
.card .foot {
    margin-top: 12px;
    display: flex;
    gap: 8px;
    justify-content: flex-end;
}

.action-buttons {
    display: flex;
    gap: 8px; /* Memberi jarak antar tombol */
    flex-wrap: nowrap; /* Mencegah tombol turun ke baris baru */
    align-items: center;
}

.action-buttons .btn.action-btn {
    padding: 6px 10px; /* Atur padding yang lebih kecil agar tidak terlalu besar */
    font-size: 13px;
    white-space: nowrap; /* Mencegah teks terpotong */
}


.action-menu-trigger {
    background: #e0e0e0;
    color: #444;
    border: none;
    border-radius: 8px;
    padding: 8px 12px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    transition: background 0.2s;
}

.action-menu-trigger:hover {
    background: #d4d4d4;
}

.action-menu {
    position: absolute;
    right: 0;
    /* Ganti 'top' dengan 'bottom' */
    bottom: 100%; /* Ini yang membuat dropdown ke atas */
    z-index: 10;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    min-width: 180px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    list-style: none;
    padding: 0;
    margin-bottom: 5px; /* Memberi sedikit jarak */
    display: none;
}

.action-menu.active {
    display: block;
}

.action-menu li a {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 15px;
    text-decoration: none;
    color: #333;
    white-space: nowrap;
    transition: background 0.2s;
}

.action-menu li:hover a {
    background: #f0f2f5;
    color: #2563eb;
}

.action-menu li:first-child a { border-top-left-radius: 8px; border-top-right-radius: 8px; }
.action-menu li:last-child a { border-bottom-left-radius: 8px; border-bottom-right-radius: 8px; }
/* --- End Aksi Dropdown Style --- */

/* Sidebar dropdown */
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
.badge { background:#ef4444; color:#fff; padding:2px 8px; border-radius:999px; font-size:12px; }

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
                    <a href="#" class="dropdown-link"><i class="fas fa-users"></i> Data Karyawan <i class="fas fa-caret-down"></i></a>
                    <ul class="dropdown-menu">
                        <li><a href="../data_karyawan/all_employees.php">Semua Karyawan</a></li>
                        <li><a href="../data_karyawan/karyawan_nonaktif.php">Non-Aktif</a></li>
                    </ul>
                </li>
                <li class="dropdown-trigger">
                    <a href="#" class="dropdown-link"><i class="fas fa-users"></i> Data Pengajuan<span class="badge"><?= $total_pending ?></span> <i class="fas fa-caret-down"></i></a>
                    <ul class="dropdown-menu">
                        <li><a href="../pengajuan/pengajuan.php">Pengajuan</a></li>
                        <li><a href="../pengajuan/kelola_pengajuan.php">Kelola Pengajuan<span class="badge"><?= $total_pending ?></span></a></li>
                    </ul>
                </li>
                <li class="active"><a href="../monitoring_kontrak/monitoring_kontrak.php"><i class="fas fa-calendar-alt"></i> Monitoring Kontrak</a></li>
                <li><a href="surat_tugas_history.php"><i class="fas fa-file-alt"></i> Riwayat Surat Tugas</a></li>
                <li><a href="../payslip/e_payslip_admin.php"><i class="fas fa-money-check-alt"></i> E-Pay Slip</a></li>
                <li><a href="../invoice/invoice.php"><i class="fas fa-money-check-alt"></i> Invoice</a></li>
            </ul>
        </nav>
        <div class="logout-link">
            <a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a>
        </div>
    </aside>

    <main class="main-content">
        <div class="main-header">
            <h1>Monitoring Kontrak</h1>
            <span class="current-date">
                Menampilkan kontrak yang jatuh tempo s.d <b><?= e(dmy($due_limit)) ?></b>
            </span>
        </div>

        <div class="toolbar">
            <form class="search-form" method="get">
                <div class="search-filter-container">
                    <div class="filter-box">
                        <select name="proyek">
                            <option value="">Semua Proyek</option>
                            <?php foreach($all_proyek as $p): $sel = ($filter_proyek===$p)?'selected':''; ?>
                                <option value="<?= e($p) ?>" <?= $sel ?>><?= e($p) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-box">
                        <select name="status">
                            <?php foreach(['AKTIF','TIDAK AKTIF',''] as $s): 
                                $label = $s ?: 'Semua Status'; 
                                $sel = ($filter_status===$s)?'selected':''; ?>
                                <option value="<?= e($s) ?>" <?= $sel ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-box">
                        <input type="number" name="due_in_days" min="1"
                                value="<?= e($due_in_days) ?>" placeholder="Hari jatuh tempo">
                    </div>
                    <div class="search-box">
                        <input type="text" name="s" placeholder="Cari nama/NIK" value="<?= e($search) ?>">
                        <button type="submit"><i class="fas fa-search"></i></button>
                    </div>
                    <button class="add-button" type="submit">
                        <i class="fas fa-filter"></i> Terapkan
                    </button>
                </div>
            </form>
        </div>

        <?php if($flash): ?>
            <div class="alert success"><?= e($flash) ?></div>
        <?php endif; ?>

        <div class="data-table-container">
            <table>
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Proyek</th>
                        <th>Jabatan</th>
                        <th>Mulai</th>
                        <th>Jatuh Tempo</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(!$rows): ?>
                        <tr><td colspan="7" style="text-align:center">Tidak ada data.</td></tr>
                    <?php else: foreach($rows as $r):
                        $due = safe_iso($r['due_date']);
                        $daysLeft = $due ? (int)floor((strtotime($due)-strtotime($today))/86400) : null;
                        $badgeClass = ($daysLeft!==null && $daysLeft<=14) ? 'badge due' : 'badge warn';
                    ?>
                        <tr>
                            <td><?= e($r['nama_karyawan']) ?></td>
                            <td><?= e($r['proyek']) ?></td>
                            <td><?= e($r['jabatan']) ?></td>
                            <td><?= e(dmy($r['join_date'])) ?></td>
                            <td>
                                <?= e(dmy($due)) ?>
                                <?php if($daysLeft!==null): ?>
                                    <span class="<?= $badgeClass ?>" title="Sisa hari"><?= e($daysLeft) ?>h</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge"><?= e($r['status']) ?></span></td>
                            <td>
                                <div class="action-buttons">
    <a href="#" class="btn action-btn" onclick="event.preventDefault(); openPerpanjang(<?= (int)$r['id_karyawan'] ?>,'<?= e($r['nama_karyawan']) ?>','<?= e($r['proyek']) ?>');">
        <i class="fas fa-calendar-plus"></i> Perpanjang
    </a>
    <a href="#" class="btn action-btn" onclick="event.preventDefault(); openSurat(<?= (int)$r['id_karyawan'] ?>,'<?= e($r['nama_karyawan']) ?>','<?= e($r['proyek']) ?>','<?= e($r['jabatan']) ?>');">
        <i class="fas fa-file-signature"></i> Surat Tugas
    </a>
    
</div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </main>


</div>

<!-- Modal Perpanjang Kontrak -->
<div id="modalPerpanjang" class="modal">
    <div class="card">
        <h3>Perpanjang Kontrak</h3>
        <form method="post" action="process_perpanjang.php" class="grid">
            <input type="hidden" name="id_karyawan" id="pp_id">
            <div class="form-group" style="grid-column:1/3">
                <label>Nama Karyawan</label>
                <input type="text" id="pp_nama" readonly>
            </div>
            <div class="form-group">
                <label>Tanggal Mulai Baru</label>
                <input type="date" name="start_new" required>
            </div>
            <div class="form-group">
                <label>Tanggal Akhir Baru</label>
                <input type="date" name="end_new" required>
            </div>
            <div class="form-group" style="grid-column:1/3">
                <label>Keterangan</label>
                <textarea name="note" rows="2" placeholder="Opsional"></textarea>
            </div>
            <div class="foot">
                <button type="button" class="btn ghost" onclick="closePerpanjang()">Batal</button>
                <button class="btn primary" type="submit"><i class="fa fa-save"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Buat Surat Tugas (Generate) -->
<div id="modalSurat" class="modal">
    <div class="card">
        <h3>Buat Surat Tugas (Generate)</h3>
        <div class="grid">
            <div class="form-group" style="grid-column:1/3">
                <label>Nama Karyawan</label>
                <input type="text" id="st_nama" readonly>
            </div>
            <div class="form-group">
                <label>Proyek</label>
                <input type="text" id="st_proyek" readonly>
            </div>
            <div class="form-group">
                <label>Posisi</label>
                <input type="text" id="st_posisi" placeholder="Posisi">
            </div>
            <div class="form-group">
                <label>Penempatan</label>
                <input type="text" id="st_penempatan" placeholder="Nama Cabang/Unit">
            </div>
            <div class="form-group">
                <label>Sales Code</label>
                <input type="text" id="st_sales" placeholder="Sales Code (opsional)">
            </div>
            <div class="form-group" style="grid-column:1/3">
                <label>Alamat (Penempatan)</label>
                <textarea id="st_alamat" rows="2" placeholder="Alamat lengkap penempatan"></textarea>
            </div>
            <div class="form-group">
                <label>Tgl Pembuatan</label>
                <input type="date" id="st_tanggal" value="<?= e(date('Y-m-d')) ?>">
            </div>
            <div class="form-group">
                <label>No Surat (otomatis)</label>
                <input type="text" id="st_nosurat" readonly>
            </div>
        </div>

        <div class="foot" style="justify-content:flex-end; display:flex; gap:8px;">
            <button type="button" class="btn ghost" onclick="closeSurat()">Tutup</button>
            <form id="formGenerate" method="post" action="surat_tugas_download.php" target="_blank">
                <input type="hidden" name="id_karyawan" id="g_id">
                <input type="hidden" name="nama" id="g_nama">
                <input type="hidden" name="proyek" id="g_proyek">
                <input type="hidden" name="posisi" id="g_posisi">
                <input type="hidden" name="penempatan" id="g_penempatan">
                <input type="hidden" name="sales_code" id="g_sales">
                <input type="hidden" name="alamat_penempatan" id="g_alamat">
                <input type="hidden" name="tgl_pembuatan" id="g_tanggal">
                <input type="hidden" name="no_surat" id="g_nosurat">
                <button class="btn primary" type="submit" onclick="closeSurat()"><i class="fa fa-download"></i> Generate & Unduh</button>
            </form>
        </div>
    </div>
</div>

<!-- Modal Upload Surat Tugas -->
<div id="modalUpload" class="modal">
    <div class="card">
        <h3>Upload Surat Tugas Manual</h3>
        <form id="formUpload" method="post" action="upload_surat_tugas.php" enctype="multipart/form-data">
            <input type="hidden" name="id_karyawan" id="up_id">
            
            <div class="grid">
                <div class="form-group" style="grid-column:1/3">
                    <label>Karyawan</label>
                    <input type="text" id="up_nama_display" readonly>
                </div>
                <div class="form-group">
                    <label>Nomor Surat</label>
                    <input type="text" name="no_surat" placeholder="Cth: ST/MANU/2024/09/001" required>
                </div>
                <div class="form-group">
                    <label>Tanggal Pembuatan</label>
                    <input type="date" name="tgl_pembuatan" value="<?= e(date('Y-m-d')) ?>" required>
                </div>
                <!-- Hidden inputs for mandatory fields in surat_tugas table -->
                <input type="hidden" name="posisi" value="">
                <input type="hidden" name="penempatan" value="">
                <input type="hidden" name="sales_code" value="">
                <input type="hidden" name="alamat_penempatan" value="">

                <div class="form-group" style="grid-column:1/3">
                    <label>Pilih File (.pdf, .doc, .jpg, dll.)</label>
                    <input type="file" name="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                </div>
            </div>
            
            <div class="foot">
                <button type="button" class="btn ghost" onclick="closeUpload()">Batal</button>
                <button class="btn primary" type="submit"><i class="fa fa-upload"></i> Upload & Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
    
   function repositionDropdown() {
    const menus = document.querySelectorAll('.action-menu.active');
    menus.forEach(menu => {
        const rect = menu.getBoundingClientRect();
        const viewportHeight = window.innerHeight;

        // Reset posisi dulu
        menu.classList.remove('dropup');
        menu.style.removeProperty('top');
        menu.style.removeProperty('bottom');

        // kalau bagian bawah dropdown melebihi viewport, pakai dropup
        if (rect.bottom > viewportHeight) {
            menu.classList.add('dropup');
        }
    });
}

// Panggil fungsi reposisi saat dropdown dibuka
document.querySelectorAll('.action-menu-trigger').forEach(trigger => {
    trigger.addEventListener('click', () => {
        // Tunggu sedikit agar menu muncul dan posisinya bisa dihitung
        setTimeout(repositionDropdown, 0); 
    });
});

// Panggil juga saat pengguna scroll atau resize jendela
window.addEventListener('scroll', repositionDropdown);
window.addEventListener('resize', repositionDropdown);
// --- Dropdown Menu Logic ---
function toggleMenu(trigger) {
    document.querySelectorAll('.action-menu.active').forEach(menu => {
        if (menu !== trigger.nextElementSibling) {
            menu.classList.remove('active');
        }
    });
    trigger.nextElementSibling.classList.toggle('active');
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.action-dropdown')) {
        document.querySelectorAll('.action-menu.active').forEach(menu => {
            menu.classList.remove('active');
        });
    }
});
// --- End Dropdown Menu Logic ---

function openPerpanjang(id, nama, proyek){
    document.getElementById('pp_id').value = id;
    document.getElementById('pp_nama').value = nama + ' — ' + proyek;
    document.getElementById('modalPerpanjang').style.display='flex';
}
function closePerpanjang(){ document.getElementById('modalPerpanjang').style.display='none'; }

function pad3(n){ return String(n).padStart(3,'0'); }
function autoNoSurat(proyek){
    const now=new Date();
    const yyyy=now.getFullYear();
    const mm=String(now.getMonth()+1).padStart(2,'0');
    // Generate nomor surat acak, perlu mekanisme backend yang lebih baik
    return `ST/${(proyek||'GEN')}/${yyyy}/${mm}/${pad3(Math.floor(Math.random()*999)+1)}`;
}

// FIX: Fungsi openSurat sekarang mengisi SEMUA input tersembunyi (g_) dengan nilai default
function openSurat(id, nama, proyek, jabatan){
    const defaultPosisi = jabatan||'';
    const defaultPenempatan = '';
    const defaultSales = '';
    const defaultAlamat = '';
    const defaultTanggal = (new Date()).toISOString().slice(0,10);
    const noSurat = autoNoSurat(proyek);

    // 1. Isi Input Terlihat (st_)
    document.getElementById('st_nama').value = nama;
    document.getElementById('st_proyek').value = proyek;
    document.getElementById('st_posisi').value = defaultPosisi;
    document.getElementById('st_penempatan').value = defaultPenempatan;
    document.getElementById('st_sales').value = defaultSales;
    document.getElementById('st_alamat').value = defaultAlamat;
    document.getElementById('st_tanggal').value = defaultTanggal;
    document.getElementById('st_nosurat').value = noSurat;

    // 2. Isi Input Tersembunyi (g_) untuk Form Generate (FIX PENTING)
    document.getElementById('g_id').value = id;
    document.getElementById('g_nama').value = nama;
    document.getElementById('g_proyek').value = proyek;
    document.getElementById('g_posisi').value = defaultPosisi;
    document.getElementById('g_penempatan').value = defaultPenempatan;
    document.getElementById('g_sales').value = defaultSales;
    document.getElementById('g_alamat').value = defaultAlamat;
    document.getElementById('g_tanggal').value = defaultTanggal;
    document.getElementById('g_nosurat').value = noSurat;

    document.getElementById('modalSurat').style.display='flex';
}
function closeSurat(){ document.getElementById('modalSurat').style.display='none'; }

// Fungsi untuk Modal Upload
function openUpload(id, nama, proyek) {
    document.getElementById('up_id').value = id;
    document.getElementById('up_nama_display').value = nama + ' - ' + proyek;
    document.getElementById('modalUpload').style.display = 'flex';
}

function closeUpload() { 
    document.getElementById('modalUpload').style.display = 'none'; 
}


// Sinkronisasi data dari input terlihat (st_) ke input tersembunyi (g_)
// Ini tetap diperlukan jika user MENGUBAH nilai di input (st_...)
['st_posisi','st_penempatan','st_sales','st_alamat','st_tanggal','st_nosurat'].forEach(id=>{
    const el=document.getElementById(id);
    if(!el) return;
    el.addEventListener('input', ()=>{
        const map={st_posisi:'g_posisi',st_penempatan:'g_penempatan',st_sales:'g_sales',st_alamat:'g_alamat',st_tanggal:'g_tanggal',st_nosurat:'g_nosurat'};
        if(map[id]) document.getElementById(map[id]).value = el.value;
    });
});

// Penutup modal saat klik di luar atau tekan Escape
['modalPerpanjang','modalSurat', 'modalUpload'].forEach(mid=>{
    const m=document.getElementById(mid);
    m.addEventListener('click',(e)=>{ if(e.target===m) {
        m.style.display='none'; 
        document.querySelectorAll('.action-menu.active').forEach(menu => menu.classList.remove('active'));
    } });
});
window.addEventListener('keydown',(e)=>{ if(e.key==='Escape'){ 
    closePerpanjang(); 
    closeSurat(); 
    closeUpload(); 
} });
</script>
</body>
</html>
