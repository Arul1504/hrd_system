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
        k.nama_karyawan, k.proyek, k.jabatan, k.id_karyawan
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
    if ($params)
        $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $surat_history = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $surat_history = [];
    // Log error koneksi atau prepare statement jika diperlukan
}

$proyek_surat_tugas = ['ALLO', 'CIMB', 'CNAF'];
$proyek_in_clause = "'" . implode("','", $proyek_surat_tugas) . "'"; // 'ALLO','CIMB','CNAF'

$sql_all_employees = "
    SELECT id_karyawan, nama_karyawan, proyek, jabatan 
    FROM karyawan 
    WHERE proyek IN ({$proyek_in_clause}) 
    ORDER BY nama_karyawan ASC
"; // <--- TAMBAHAN KLAUSA WHERE

$result_all_employees = $conn->query($sql_all_employees);
$all_employees = $result_all_employees->fetch_all(MYSQLI_ASSOC);
$conn->close();
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
            white-space: nowrap;
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

        .filter-toolbar select,
        .filter-toolbar button {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        .filter-toolbar button.filter-btn {
            background: #2ecc71;
            color: white;
            cursor: pointer;
        }

        .filter-toolbar button.add-btn {
            background: #007bff;
            color: white;
        }

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
            max-width: 90%;
            width: 700px;
            padding: 16px;
        }

        .modal h3 {
            margin-top: 0;
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

        .foot {
            margin-top: 12px;
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }

        .btn-modal {
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
        }

        .btn-modal.ghost {
            background: #f0f2f5;
            border: 1px solid #ddd;
        }

        .btn-modal.primary {
            background: #2563eb;
            color: #fff;
            border: none;
        }

        /* Picker Modal */
        #picker_list {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #eee;
            border-radius: 8px;
        }

        .picker-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            transition: background 0.2s;
        }

        .picker-item:hover {
            background: #f5f5f5;
        }

        .picker-meta {
            font-size: 12px;
            color: #666;
        }

        /* Picker Filter Group */
        .picker-filter-group {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
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

            <!-- Filter Posisi dan Tombol Tambah -->
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
                    <button type="submit" class="filter-btn"><i class="fas fa-filter"></i> Terapkan Filter</button>
                </form>

                <!-- TOMBOL TAMBAH SURAT TUGAS -->
                <button class="btn-action add-btn" onclick="openPilihKaryawanModal()">
                    <i class="fas fa-plus-circle"></i> Tambah Surat Tugas
                </button>
            </div>

            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>No Surat</th>
                            <th>Tanggal</th>
                            <th>Nama Karyawan</th>
                            <th>Project</th>
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
                                    <td><?= e($row['proyek']) ?></td>
                                    <td><?= e($row['posisi']) ?></td>
                                    <td><?= e($row['penempatan']) ?></td>
                                    <td>
                                        <?php
                                        // Pilih file tujuan berdasarkan proyek
                                        if (isset($row['proyek'])) {
                                            if (strtoupper($row['proyek']) === 'ALLO') {
                                                $fileView = 'surat_tugas_view_allo.php';
                                            } elseif (strtoupper($row['proyek']) === 'CIMB') {
                                                $fileView = 'surat_tugas_view_cimb.php';
                                            } elseif (strtoupper($row['proyek']) === 'CNAF') {
                                                $fileView = 'surat_tugas_view_cnaf.php';
                                            } else {
                                                // fallback jika proyek lain
                                                $fileView = 'surat_tugas_view_allo.php';
                                            }
                                        } else {
                                            $fileView = 'surat_tugas_view_allo.php';
                                        }
                                        ?>
                                        <a href="<?= $fileView ?>?id=<?= e($row['id']) ?>" target="_blank" class="btn-action"
                                            title="Lihat Surat Tugas">
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

    <!-- MODAL PILIH KARYAWAN -->
    <div id="modalPilihKaryawan" class="modal">
        <div class="card" style="max-width: 500px;">
            <h3>Pilih Karyawan untuk Surat Tugas</h3>

            <div class="picker-filter-group">
                <!-- Filter Project Dinamis -->
                <select id="picker_filter_proyek" onchange="filterKaryawan()">
                    <option value="">Semua Proyek</option>
                    <option value="ALLO">ALLO</option>
                    <option value="CIMB">CIMB</option>
                    <option value="CNAF">CNAF</option>
                </select>
            </div>

            <div class="form-group">
                <input type="text" id="picker_search" placeholder="Cari Nama Karyawan..." onkeyup="filterKaryawan()">
            </div>

            <div id="picker_list">
                <!-- Daftar Karyawan akan diisi oleh JS -->
            </div>
            <div class="foot">
                <button type="button" class="btn-modal ghost" onclick="closePilihKaryawanModal()">Batal</button>
            </div>
        </div>
    </div>

    <!-- MODAL 1: Buat Surat Tugas (Generate) -->
    <div id="modalSurat" class="modal">
        <div class="card">
            <h3>Buat Surat Tugas (Generate)</h3>
            <div class="grid">
                <!-- FIELD STATIS -->
                <div class="form-group" style="grid-column:1/3">
                    <label>Nama Karyawan</label>
                    <input type="text" id="st_nama" readonly>
                </div>
                <div class="form-group">
                    <label>Proyek</label>
                    <input type="text" id="st_proyek" readonly>
                </div>
                <!-- Tanggal dan Nomor Surat (tetap statis di modal) -->
                <div class="form-group">
                    <label>Tgl Pembuatan</label>
                    <input type="date" id="st_tanggal" value="<?= e(date('Y-m-d')) ?>">
                </div>
                <div class="form-group" style="grid-column:1/3">
                    <label>No Surat (otomatis)</label>
                    <input type="text" id="st_nosurat" readonly>
                </div>
            </div>

            <!-- CONTAINER UNTUK FIELD DINAMIS -->
            <div id="dynamic_surat_fields" class="grid" style="margin-top: 10px;">
                <!-- Fields Posisi, Penempatan, Sales Code, dll. akan dirender di sini oleh JS -->
            </div>

            <div class="foot" style="justify-content:flex-end; display:flex; gap:8px;">
                <button type="button" class="btn-modal ghost" onclick="closeSurat()">Tutup</button>
                <form id="formGenerate" method="post" action="surat_tugas_download.php" target="_blank">
                    <!-- HIDDEN INPUTS STANDAR -->
                    <input type="hidden" name="id_karyawan" id="g_id">
                    <input type="hidden" name="nama" id="g_nama">
                    <input type="hidden" name="proyek" id="g_proyek">
                    <input type="hidden" name="tgl_pembuatan" id="g_tanggal">
                    <input type="hidden" name="no_surat" id="g_nosurat">

                    <!-- HIDDEN INPUTS DINAMIS -->
                    <input type="hidden" name="posisi" id="g_posisi">
                    <input type="hidden" name="penempatan" id="g_penempatan">
                    <input type="hidden" name="sales_code" id="g_sales">
                    <input type="hidden" name="alamat_penempatan" id="g_alamat">
                    <input type="hidden" name="nik_karyawan" id="g_nik_karyawan">
                    <input type="hidden" name="alamat_domisili" id="g_alamat_domisili">

                    <button class="btn-modal primary" type="submit" onclick="closeSurat()"><i
                            class="fa fa-download"></i> Generate & Unduh</button>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL 2: Upload Surat Tugas Manual -->
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
                    <input type="hidden" name="posisi" id="up_posisi_hidden">
                    <input type="hidden" name="penempatan" id="up_penempatan_hidden">
                    <input type="hidden" name="sales_code" id="up_sales_hidden">
                    <input type="hidden" name="alamat_penempatan" id="up_alamat_hidden">

                    <div class="form-group" style="grid-column:1/3">
                        <label>Pilih File (.pdf, .doc, .jpg, dll.)</label>
                        <input type="file" name="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                    </div>
                </div>

                <div class="foot">
                    <button type="button" class="btn-modal ghost" onclick="closeUpload()">Batal</button>
                    <button class="btn-modal primary" type="submit"><i class="fa fa-upload"></i> Upload &
                        Simpan</button>
                </div>
            </form>
        </div>
    </div>


    <script>
        const SURAT_TUGAS_FIELDS = {
            // Field standar (selalu ada di form utama)
            STANDARD: [
                { slug: 'st_posisi', label: 'Posisi', placeholder: 'Posisi', type: 'text' },
                { slug: 'st_penempatan', label: 'Penempatan', placeholder: 'Nama Cabang/Unit', type: 'text' },
                { slug: 'st_sales', label: 'Sales Code', placeholder: 'Sales Code (opsional)', type: 'text' },
                { slug: 'st_alamat', label: 'Alamat (Penempatan)', placeholder: 'Alamat lengkap penempatan', type: 'textarea', span: 2 },
            ],
            // Field tambahan spesifik CIMB
            CIMB_ADDITIONAL: [
                { slug: 'st_nik_karyawan', label: 'No NIK', placeholder: 'Nomor NIK Karyawan', type: 'text' },
                { slug: 'st_alamat_domisili', label: 'Alamat Domisili', placeholder: 'Alamat tinggal karyawan', type: 'textarea', span: 2 },
            ],
        };

        // Fungsi autoNoSurat dan pad3 tidak berubah
        function pad3(n) { return String(n).padStart(3, '0'); }
        function autoNoSurat(proyek) {
            const now = new Date();
            const yyyy = now.getFullYear();
            const mm = String(now.getMonth() + 1).padStart(2, '0');
            // Generate nomor surat acak, perlu mekanisme backend yang lebih baik
            return `ST/${(proyek || 'GEN')}/${yyyy}/${mm}/${pad3(Math.floor(Math.random() * 999) + 1)}`;
        }
        const ALL_EMPLOYEES = <?= json_encode($all_employees); ?>;

        // --- MODAL UTILITY FUNCTIONS ---
        function openPilihKaryawanModal() {
            renderKaryawanList(ALL_EMPLOYEES);
            document.getElementById('modalPilihKaryawan').style.display = 'flex';
            document.getElementById('picker_search').value = '';
            // Reset filter proyek di modal ke nilai default (Semua Proyek)
            document.getElementById('picker_filter_proyek').value = '';
        }
        function closePilihKaryawanModal() {
            document.getElementById('modalPilihKaryawan').style.display = 'none';
        }

        function openSurat(id, nama, proyek, jabatan) {
            const defaultPosisi = jabatan || '';
            const defaultTanggal = (new Date()).toISOString().slice(0, 10);
            const noSurat = autoNoSurat(proyek);
            const dynamicFieldsContainer = document.getElementById('dynamic_surat_fields');
            let fieldsToRender = SURAT_TUGAS_FIELDS.STANDARD;

            // Cek jika proyek adalah CIMB, tambahkan field tambahan
            if (proyek.toUpperCase() === 'CIMB') {
                fieldsToRender = fieldsToRender.concat(SURAT_TUGAS_FIELDS.CIMB_ADDITIONAL);
            }

            // --- 1. RENDER DYNAMIC FIELDS ---
            dynamicFieldsContainer.innerHTML = '';

            fieldsToRender.forEach(field => {
                let inputHtml;
                if (field.type === 'textarea') {
                    inputHtml = `<textarea id="${field.slug}" name="${field.slug.replace('st_', '')}" rows="2" placeholder="${field.placeholder}"></textarea>`;
                } else {
                    inputHtml = `<input type="${field.type}" id="${field.slug}" name="${field.slug.replace('st_', '')}" placeholder="${field.placeholder}">`;
                }

                const wrapper = document.createElement('div');
                wrapper.className = 'form-group';
                if (field.span) {
                    wrapper.style.gridColumn = `1 / ${field.span + 1}`;
                }
                wrapper.innerHTML = `<label>${field.label}</label>${inputHtml}`;
                dynamicFieldsContainer.appendChild(wrapper);

                // Inisialisasi nilai default untuk CIMB (jika ada)
                if (proyek.toUpperCase() === 'CIMB' && field.slug === 'st_nik_karyawan') {
                    // Ganti dengan NIK KTP atau NIK Karyawan dari data karyawan (jika tersedia)
                    wrapper.querySelector('input').value = 'NIK KARYAWAN';
                }
                if (proyek.toUpperCase() === 'CIMB' && field.slug === 'st_alamat_domisili') {
                    // Ganti dengan data alamat domisili dari database (jika tersedia)
                    wrapper.querySelector('textarea').value = 'Alamat lengkap domisili karyawan';
                }
            });


            // --- 2. ISI INPUT UTAMA (HARDCODED DI MODAL) ---
            document.getElementById('st_nama').value = nama;
            document.getElementById('st_proyek').value = proyek;
            document.getElementById('st_tanggal').value = defaultTanggal;
            document.getElementById('st_nosurat').value = noSurat;

            // --- 3. SINKRONISASI KE HIDDEN INPUTS ---
            // Panggil sinkronisasi setelah DOM dirender agar elemen dinamis ditemukan
            syncHiddenInputs(id, nama, proyek, defaultPosisi, defaultTanggal, noSurat, fieldsToRender);

            document.getElementById('modalSurat').style.display = 'flex';
            closePilihKaryawanModal();
        }

        /**
         * Menyinkronkan semua nilai input (st_...) ke hidden fields (g_...)
         * dan membuat event listener untuk update dinamis.
         */
        function syncHiddenInputs(id, nama, proyek, jabatan, tgl, noSurat, renderedFields) {
            // --- A. Set nilai dasar (Statik) ---
            document.getElementById('g_id').value = id;
            document.getElementById('g_nama').value = nama;
            document.getElementById('g_proyek').value = proyek;
            document.getElementById('g_tanggal').value = tgl;
            document.getElementById('g_nosurat').value = noSurat;

            // --- B. Reset dan Sinkronisasi Dynamic/Mandatory Fields ---

            // Daftar semua potential dynamic/mandatory fields
            const allPotentialFields = ['posisi', 'penempatan', 'sales_code', 'alamat_penempatan', 'nik_karyawan', 'alamat_domisili'];

            // 1. Reset semua potential hidden fields ke kosong
            allPotentialFields.forEach(slug => {
                const hiddenEl = document.getElementById(`g_${slug}`);
                if (hiddenEl) hiddenEl.value = '';
            });

            // 2. Loop melalui field yang BARU SAJA di render dan set nilainya
            renderedFields.forEach(field => {
                const inputEl = document.getElementById(field.slug);
                let hiddenSlug = field.slug.replace('st_', 'g_');

                // Perbaikan untuk field yang namanya tidak standar (NIK, Domisili)
                if (field.slug === 'st_nik_karyawan') hiddenSlug = 'g_nik_karyawan';
                if (field.slug === 'st_alamat_domisili') hiddenSlug = 'g_alamat_domisili';

                const hiddenEl = document.getElementById(hiddenSlug);

                if (inputEl && hiddenEl) {
                    // Set initial value
                    hiddenEl.value = inputEl.value || jabatan; // Gunakan jabatan sebagai default posisi

                    // Add listener for continuous sync
                    inputEl.oninput = (e) => {
                        hiddenEl.value = e.target.value;
                    };
                }
            });
        }


        function closeSurat() { document.getElementById('modalSurat').style.display = 'none'; }

        function openUpload(id, nama, proyek) {
            // Asumsi data posisi, penempatan dll. TIDAK diisi saat upload manual
            document.getElementById('up_id').value = id;
            document.getElementById('up_nama_display').value = nama + ' - ' + proyek;

            // Isi hidden fields default (karena mandatory di DB)
            document.getElementById('up_posisi_hidden').value = '';
            document.getElementById('up_penempatan_hidden').value = '';
            document.getElementById('up_sales_hidden').value = '';
            document.getElementById('up_alamat_hidden').value = '';

            closePilihKaryawanModal(); // Tutup picker
            document.getElementById('modalUpload').style.display = 'flex';
        }

        function closeUpload() { document.getElementById('modalUpload').style.display = 'none'; }


        // --- KARYAWAN PICKER LOGIC ---
        function renderKaryawanList(employees) {
            const listContainer = document.getElementById('picker_list');
            listContainer.innerHTML = '';

            employees.forEach(emp => {
                const item = document.createElement('div');
                item.className = 'picker-item';
                item.innerHTML = `
                <div>
                    <strong>${emp.nama_karyawan}</strong><br>
                    <span class="picker-meta">${emp.proyek} / ${emp.jabatan || '—'}</span>
                </div>
                <div>
                    <button class="btn-modal primary" onclick="handlePilihAction('${emp.id_karyawan}', '${emp.nama_karyawan}', '${emp.proyek}', '${emp.jabatan || ''}')">Pilih Aksi</button>
                </div>
            `;
                listContainer.appendChild(item);
            });
            if (employees.length === 0) {
                listContainer.innerHTML = '<p style="text-align:center; padding: 20px; color:#777;">Tidak ada karyawan yang ditemukan.</p>';
            }
        }

        function filterKaryawan() {
            const query = document.getElementById('picker_search').value.toLowerCase();
            const projectFilter = document.getElementById('picker_filter_proyek').value.toUpperCase(); // Ambil filter proyek

            const filtered = ALL_EMPLOYEES.filter(emp => {
                const matchesSearch = emp.nama_karyawan.toLowerCase().includes(query) ||
                    emp.proyek.toLowerCase().includes(query) ||
                    (emp.jabatan && emp.jabatan.toLowerCase().includes(query));

                const matchesProject = (projectFilter === '' || emp.proyek.toUpperCase() === projectFilter);

                return matchesSearch && matchesProject;
            });

            renderKaryawanList(filtered);
        }

        // Fungsi untuk memunculkan pilihan Generate atau Upload setelah karyawan dipilih
        function handlePilihAction(id, nama, proyek, jabatan) {
            const isEligibleForGenerate = ['ALLO', 'CIMB', 'CNAF'].includes(proyek.toUpperCase());

            if (isEligibleForGenerate) {
                if (confirm(`Pilih aksi untuk ${nama} (${proyek}):\n\n- OK untuk Buat Surat Tugas (Generate)\n- CANCEL untuk Upload Surat Manual`)) {
                    openSurat(id, nama, proyek, jabatan);
                } else {
                    openUpload(id, nama, proyek);
                }
            } else {
                // Jika proyek tidak memenuhi syarat generate (selain ALLO, CIMB, CNAF)
                if (confirm(`Karyawan ${nama} (${proyek}) tidak memenuhi syarat untuk Generate Surat Tugas Otomatis. Apakah Anda ingin Lanjut ke Upload Manual?`)) {
                    openUpload(id, nama, proyek);
                }
            }
        }


        // --- UTILITIES ---
        // Sinkronisasi data dari input terlihat (st_) ke input tersembunyi (g_)
        // Menambahkan listener untuk fields yang sudah ada dari awal.
        ['st_tanggal', 'st_nosurat'].forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            el.addEventListener('input', () => {
                const map = { st_tanggal: 'g_tanggal', st_nosurat: 'g_nosurat' };
                if (map[id]) document.getElementById(map[id]).value = el.value;
            });
        });

        // Penutup modal saat klik di luar atau tekan Escape
        ['modalPilihKaryawan', 'modalSurat', 'modalUpload'].forEach(mid => {
            const m = document.getElementById(mid);
            m.addEventListener('click', (e) => {
                if (e.target === m) {
                    m.style.display = 'none';
                }
            });
        });
        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closePilihKaryawanModal();
                closeSurat();
                closeUpload();
            }
        });
    </script>
</body>

</html>