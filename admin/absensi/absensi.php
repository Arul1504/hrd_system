<?php
require '../config.php';


// --- PERIKSA HAK AKSES ADMIN ---
if (!isset($_SESSION['id_karyawan']) || $_SESSION['role'] !== 'ADMIN') {
    header("Location: ../../index.php");
    exit();
}
function set_timezone_from_province(string $province): string
{
    $p = mb_strtolower(trim($province));

    $wib = [
        'aceh',
        'sumatera utara',
        'sumatera barat',
        'riau',
        'kepulauan riau',
        'jambi',
        'bengkulu',
        'sumatera selatan',
        'kepulauan bangka belitung',
        'lampung',
        'banten',
        'dki jakarta',
        'jakarta',
        'jawa barat',
        'jawa tengah',
        'di yogyakarta',
        'jawa timur',
        'kalimantan barat',
        'kalimantan tengah'
    ];

    $wita = [
        'bali',
        'nusa tenggara barat',
        'nusa tenggara timur',
        'kalimantan selatan',
        'kalimantan timur',
        'kalimantan utara',
        'sulawesi utara',
        'sulawesi tengah',
        'sulawesi selatan',
        'sulawesi tenggara',
        'gorontalo'
    ];

    $wit = [
        'maluku',
        'maluku utara',
        'papua',
        'papua barat',
        'papua selatan',
        'papua tengah',
        'papua pegunungan',
        'papua barat daya'
    ];

    if (in_array($p, $wib, true)) {
        date_default_timezone_set('Asia/Jakarta'); // WIB
        return 'Asia/Jakarta';
    }
    if (in_array($p, $wita, true)) {
        date_default_timezone_set('Asia/Makassar'); // WITA
        return 'Asia/Makassar';
    }
    if (in_array($p, $wit, true)) {
        date_default_timezone_set('Asia/Jayapura'); // WIT
        return 'Asia/Jayapura';
    }

    // default fallback WIB
    date_default_timezone_set('Asia/Jakarta');
    return 'Asia/Jakarta';
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

// --- LOGIKA STATUS ABSENSI PRIBADI ADMIN HARI INI ---
$today = date('Y-m-d');
$status_absen_hari_ini = "Belum Absen Masuk";
$tombol_absen_text = "Absen Masuk";
$tombol_absen_action = "masuk";
$alamat_terakhir = "Lokasi belum terdeteksi";

$stmt_today = $conn->prepare("SELECT jam_masuk, jam_pulang, alamat_masuk, alamat_pulang FROM absensi WHERE id_karyawan = ? AND tanggal = ?");
$stmt_today->bind_param("is", $id_karyawan_admin, $today);
$stmt_today->execute();
$result_today = $stmt_today->get_result();

if ($result_today->num_rows > 0) {
    $absen_hari_ini = $result_today->fetch_assoc();
    if ($absen_hari_ini['jam_pulang']) {
        $status_absen_hari_ini = "Absensi Hari Ini Selesai";
        $tombol_absen_text = "Selesai";
        $tombol_absen_action = "";
    } else {
        $status_absen_hari_ini = "Sudah Absen Masuk";
        $tombol_absen_text = "Absen Pulang";
        $tombol_absen_action = "pulang";
    }
    $alamat_terakhir = $absen_hari_ini['alamat_pulang'] ?: $absen_hari_ini['alamat_masuk'];
}
$stmt_today->close();

// --- FILTER DATA ABSENSI KARYAWAN LAIN ---
$search_query = $_GET['search'] ?? '';
$filter_tanggal = $_GET['tanggal'] ?? date('Y-m-d');

$sql = "
    SELECT 
        k.id_karyawan,
        k.nik_ktp, 
        k.nama_karyawan, 
        k.jabatan, 
        k.proyek, 
        k.role,
        a.tanggal, 
        a.alamat_masuk, 
        a.jam_masuk, 
        a.alamat_pulang, 
        a.jam_pulang, 
        a.status_absensi
    FROM karyawan k
    LEFT JOIN absensi a 
        ON k.id_karyawan = a.id_karyawan 
        AND a.tanggal = ?
    WHERE k.proyek = 'INTERNAL'
";
$sql_pending_requests = "SELECT COUNT(*) AS total_pending FROM pengajuan WHERE status_pengajuan = 'Menunggu'";
$result_pending_requests = $conn->query($sql_pending_requests);
$total_pending = $result_pending_requests->fetch_assoc()['total_pending'] ?? 0;
$params = [$filter_tanggal];
$types = "s";

if ($search_query) {
    $sql .= " AND (k.nik_ktp LIKE ? OR k.nama_karyawan LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

$sql .= " ORDER BY k.nama_karyawan ASC";

$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $attendance_list = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $attendance_list = [];
}

$conn->close();

// Helper untuk escape HTML

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Absensi Karyawan - Admin</title>
    <link rel="stylesheet" href="../style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .main-content {
            flex: 1;
            background: #f8f9fb;
            padding: 30px;
        }

        .main-header-absensi h1 {
            font-size: 28px;
            font-weight: 600;
            color: #00416A;
        }

        .jam-realtime {
            font-size: 38px;
            font-weight: bold;
            margin: 10px 0 25px;
            color: #00416A;
            text-shadow: 0 0 8px rgba(0, 65, 106, 0.5);
        }

        .card {
            background: #fff;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
            transition: transform 0.2s;
        }

        .card:hover {
            transform: translateY(-3px);
        }

        .btn {
            padding: 10px 18px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
        }

        .btn-primary {
            background: linear-gradient(45deg, #3498db, #2980b9);
            color: #fff;
        }

        .btn-primary:hover {
            background: linear-gradient(45deg, #2980b9, #1f6391);
            transform: scale(1.05);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th,
        td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }

        th {
            background: #00416A;
            color: #fff;
            font-weight: 500;
            text-align: left;
        }

        tr:nth-child(even) {
            background: #f9f9f9;
        }

        tr:hover {
            background: #eef6ff;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            color: #fff;
        }

        .status-badge.hadir {
            background: #2ecc71;
        }

        .status-badge.terlambat {
            background: #f39c12;
        }

        .status-badge.izin {
            background: #3498db;
        }

        .status-badge.sakit {
            background: #9b59b6;
        }

        .status-badge.belum-absen {
            background: #7f8c8d;
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

        .action-button-group {
            display: flex;
            gap: 8px;
        }

        .action-button-group .btn {
            padding: 6px 12px;
        }

        /* Modal Style */
        .modal {
            display: none;
            position: fixed;
            z-index: 10001;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
            border-radius: 10px;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }

        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }

        .modal-content h2 {
            margin-top: 0;
        }

        .modal-content form .form-group {
            margin-bottom: 15px;
        }

        .modal-content form label {
            display: block;
            margin-bottom: 5px;
        }

        .modal-content form input[type="text"],
        .modal-content form select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        .badge {
            background: #ef4444;
            color: #fff;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 12px;
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
                        <li><a href="../dashboard_admin.php"><i class="fas fa-home"></i> Dashboard</a></li>
                        <li class="active"><a href="#"><i class="fas fa-clipboard-list"></i> Absensi</a></li>
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
                        <li><a href="../monitoring_kontrak/surat_tugas_history.php"><i class="fas fa-file-alt"></i>
                                Riwayat Surat Tugas</a></li>
                        <li><a href="../payslip/e_payslip_admin.php"><i class="fas fa-money-check-alt"></i> E-Pay
                                Slip</a></li>
                        <li><a href="../invoice/invoice.php"><i class="fas fa-money-check-alt"></i> Invoice</a></li>
                    </ul>
                </nav>
            </div>
            <div class="logout-link"><a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a></div>
        </aside>

        <main class="main-content">
            <header class="main-header-absensi">
                <h1>Absensi Karyawan - Admin</h1>
                <p><?= date('l, d F Y') ?></p>
                <div id="clock" class="jam-realtime"></div>
            </header>

            <div class="card">
                <h2>Absensi Anda Hari Ini</h2>
                <p>Status: <b style="color:#3498db"><?= $status_absen_hari_ini ?></b></p>
                <p class="location-status"><i class="fas fa-map-marker-alt"></i> <?= $alamat_terakhir ?></p>
                <form method="POST" action="proses_absen.php" id="absenForm">
                    <input type="hidden" name="latitude" id="latitude">
                    <input type="hidden" name="longitude" id="longitude">
                    <input type="hidden" name="alamat" id="alamat">
                    <input type="hidden" name="action" value="<?= $tombol_absen_action ?>">
                    <input type="hidden" name="client_time" id="client_time">
                    <button type="button" onclick="getLocation(true)" id="btnAbsen" class="btn btn-primary"
                        <?= $tombol_absen_action ? '' : 'disabled' ?>>
                        <i class="fas fa-map-marker-alt"></i> <?= $tombol_absen_text ?>
                    </button>
                </form>
            </div>

            <div class="card">
               
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                    <h2>Riwayat Absensi Karyawan Proyek Internal</h2>
                    <form method="GET" action="download_absensi_excel.php"
                        style="display:flex; gap:10px; align-items:center;">
                        <select name="bulan" required>
                            <?php
                            for ($m = 1; $m <= 12; $m++):
                                $sel = (date('n') == $m) ? 'selected' : '';
                                ?>
                                <option value="<?= $m ?>" <?= $sel ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                            <?php endfor; ?>
                        </select>
                        <select name="tahun" required>
                            <?php
                            $tahun_now = date('Y');
                            for ($y = $tahun_now; $y >= ($tahun_now - 5); $y--):
                                $sel = ($tahun_now == $y) ? 'selected' : '';
                                ?>
                                <option value="<?= $y ?>" <?= $sel ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-file-excel"></i> Download Bulanan
                        </button>
                    </form>
                </div>


                <form method="GET" class="toolbar">
                    <input type="date" name="tanggal" value="<?= e($filter_tanggal) ?>" onchange="this.form.submit()">
                    <input type="text" name="search" placeholder="Cari Nama/NIK..." value="<?= e($search_query) ?>">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                </form>
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>NIK</th>
                            <th>Nama</th>
                            <th>Jabatan</th>
                            <th>Alamat Masuk</th>
                            <th>Jam Masuk</th>
                            <th>Alamat Pulang</th>
                            <th>Jam Pulang</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($attendance_list):
                            foreach ($attendance_list as $row):
                                $status_absen = $row['status_absensi'] ?: ($row['jam_masuk'] ? "Hadir" : "Belum Absen");
                                $status_class = strtolower(str_replace(' ', '-', $status_absen));
                                ?>
                                <tr>
                                    <td><?= e($row['tanggal'] ? date('d-m-Y', strtotime($row['tanggal'])) : "-") ?></td>
                                    <td><?= e($row['nik_ktp']) ?></td>
                                    <td><?= e($row['nama_karyawan']) ?></td>
                                    <td><?= e($row['jabatan']) ?></td>
                                    <td><?= e($row['alamat_masuk'] ?? '-') ?></td>
                                    <td><?= e($row['jam_masuk'] ? date('H:i', strtotime($row['jam_masuk'])) : "-") ?></td>
                                    <td><?= e($row['alamat_pulang'] ?? '-') ?></td>
                                    <td><?= e($row['jam_pulang'] ? date('H:i', strtotime($row['jam_pulang'])) : "-") ?></td>
                                    <td>
                                        <span class="status-badge <?= $status_class ?>"><?= e($status_absen) ?></span>
                                    </td>
                                    <td>
                                        <div class="action-button-group">
                                            <button class="btn btn-primary" title="Absen Manual"
                                                onclick="openAbsenModal('<?= e($row['id_karyawan']) ?>', '<?= e($row['nama_karyawan']) ?>', '<?= e($filter_tanggal) ?>')">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                            <button class="btn btn-primary" title="Ubah Status"
                                                onclick="openEditModal('<?= e($row['id_karyawan']) ?>', '<?= e($row['nama_karyawan']) ?>', '<?= e($filter_tanggal) ?>', '<?= e($status_absen) ?>')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                            <tr>
                                <td colspan="10" style="text-align:center;">Tidak ada data absensi</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <div id="absenModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('absenModal')">&times;</span>
            <h2>Absen Manual untuk Karyawan</h2>
            <form action="process_absensi_admin.php" method="POST">
                <input type="hidden" name="id_karyawan" id="absen_id_karyawan">
                <input type="hidden" name="action" value="absen_manual">
                <input type="hidden" name="client_time" id="absen_client_time">
                <div class="form-group">
                    <label for="absen_nama_karyawan">Nama Karyawan</label>
                    <input type="text" id="absen_nama_karyawan" readonly>
                </div>
                <div class="form-group">
                    <label for="absen_tanggal">Tanggal</label>
                    <input type="date" name="tanggal" id="absen_tanggal" value="<?= e($filter_tanggal) ?>" required>
                </div>
                <div class="form-group">
                    <label for="absen_jenis">Jenis Absen</label>
                    <select name="jenis" id="absen_jenis" required>
                        <option value="">Pilih...</option>
                        <option value="masuk">Masuk</option>
                        <option value="pulang">Pulang</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="absen_lokasi">Lokasi</label>
                    <input type="text" name="lokasi" id="absen_lokasi" required>
                </div>
                <button type="submit" class="btn btn-primary">Simpan Absen</button>
            </form>
        </div>
    </div>

    <div id="editStatusModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('editStatusModal')">&times;</span>
            <h2>Ubah Status Absensi</h2>
            <form action="process_absensi_admin.php" method="POST">
                <input type="hidden" name="id_karyawan" id="edit_id_karyawan">
                <input type="hidden" name="action" value="edit_status">
                <input type="hidden" name="tanggal" id="edit_tanggal">
                <input type="hidden" name="client_time" id="edit_client_time">
                <div class="form-group">
                    <label for="edit_nama_karyawan">Nama Karyawan</label>
                    <input type="text" id="edit_nama_karyawan" readonly>
                </div>
                <div class="form-group">
                    <label for="edit_status">Status Absensi</label>
                    <select name="status" id="edit_status" required>
                        <option value="Hadir">Hadir</option>
                        <option value="Terlambat">Terlambat</option>
                        <option value="Izin">Izin</option>
                        <option value="Sakit">Sakit</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </form>
        </div>
    </div>

    <script>
        const LOCATIONIQ_API_KEY = "<?php echo LOCATIONIQ_API_KEY; ?>";
        // Jam realtime
        function updateClock() {
            const now = new Date();
            document.getElementById('clock').innerText =
                now.toLocaleTimeString('id-ID', { hour12: false });
        }
        setInterval(updateClock, 1000);
        updateClock();

        // Modal Functions
        function openAbsenModal(id, nama, tanggal) {
            const now = new Date();
            document.getElementById('absen_id_karyawan').value = id;
            document.getElementById('absen_nama_karyawan').value = nama;
            document.getElementById('absen_tanggal').value = tanggal;
            document.getElementById('absen_client_time').value = now.toISOString();
            document.getElementById('absenModal').style.display = 'block';
        }

        function openEditModal(id, nama, tanggal, status) {
            const now = new Date();
            document.getElementById('edit_id_karyawan').value = id;
            document.getElementById('edit_nama_karyawan').value = nama;
            document.getElementById('edit_tanggal').value = tanggal;
            document.getElementById('edit_status').value = status;
            document.getElementById('edit_client_time').value = now.toISOString();
            document.getElementById('editStatusModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        window.onclick = function (event) {
            if (event.target == document.getElementById('absenModal')) {
                closeModal('absenModal');
            }
            if (event.target == document.getElementById('editStatusModal')) {
                closeModal('editStatusModal');
            }
        }

        // Jalankan otomatis saat halaman dibuka
        window.onload = function () {
            getLocation(false);
        };

        // Ambil lokasi dan waktu dari perangkat pengguna
        function getLocation(autoSubmit) {
            const btn = document.getElementById("btnAbsen");
            btn.disabled = true;

            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (pos) => successCallback(pos, autoSubmit),
                    errorCallback,
                    { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
                );
            } else {
                document.querySelector(".location-status").innerHTML =
                    "<i class='fas fa-times-circle'></i> Browser tidak mendukung Geolocation.";
                btn.disabled = false;
            }
        }

        function successCallback(position, autoSubmit, retry = false) {
            const lat = position.coords.latitude;
            const lon = position.coords.longitude;
            const now = new Date(); // Ambil waktu saat ini dari perangkat pengguna

            // Isi input tersembunyi dengan data lokasi dan waktu
            document.getElementById("latitude").value = lat;
            document.getElementById("longitude").value = lon;
            document.getElementById("client_time").value = now.toISOString();

            const locationStatus = document.querySelector(".location-status");
            locationStatus.innerHTML = '<i class="fas fa-sync fa-spin"></i> Mengambil alamat...';

            // Pakai LocationIQ Reverse Geocoding
            fetch(`https://us1.locationiq.com/v1/reverse?key=${LOCATIONIQ_API_KEY}&lat=${lat}&lon=${lon}&format=json`)
                .then(response => response.json())
                .then(data => {
                    const alamat = data.display_name || null;
                    const provinsi = data.address?.state || data.address?.region || ""; // ambil provinsi

                    if (alamat) {
                        document.getElementById("alamat").value = alamat;

                        // tambahkan hidden input provinsi kalau belum ada
                        let provInput = document.getElementById("provinsi");
                        if (!provInput) {
                            provInput = document.createElement("input");
                            provInput.type = "hidden";
                            provInput.name = "provinsi";
                            provInput.id = "provinsi";
                            document.getElementById("absenForm").appendChild(provInput);
                        }
                        provInput.value = provinsi;

                        locationStatus.innerHTML = `<i class="fas fa-map-marker-alt"></i> ${alamat}`;
                    } else {
                        throw new Error("Alamat tidak ditemukan");
                    }

                    if (autoSubmit) {
                        document.getElementById("absenForm").submit();
                    } else {
                        document.getElementById("btnAbsen").disabled = false;
                    }
                })
                .catch(() => {
                    if (!retry) {
                        // coba ulang sekali lagi
                        successCallback(position, autoSubmit, true);
                    } else {
                        // fallback → pakai koordinat + link Google Maps
                        const alamat = `Lat: ${lat}, Lon: ${lon}`;
                        document.getElementById("alamat").value = alamat;

                        // kalau gagal, provinsi kosong
                        let provInput = document.getElementById("provinsi");
                        if (!provInput) {
                            provInput = document.createElement("input");
                            provInput.type = "hidden";
                            provInput.name = "provinsi";
                            provInput.id = "provinsi";
                            document.getElementById("absenForm").appendChild(provInput);
                        }
                        provInput.value = "";

                        locationStatus.innerHTML = `<i class="fas fa-map-marker-alt"></i> <a href="https://maps.google.com/?q=${lat},${lon}" target="_blank">${alamat}</a>`;
                        if (autoSubmit) {
                            document.getElementById("absenForm").submit();
                        } else {
                            document.getElementById("btnAbsen").disabled = false;
                        }
                    }
                });
        }


        function errorCallback(error) {
            document.querySelector(".location-status").innerHTML =
                "<i class='fas fa-exclamation-circle'></i> Gagal mengambil lokasi: " + error.message;
            document.getElementById("btnAbsen").disabled = false;
        }
    </script>
</body>

</html>