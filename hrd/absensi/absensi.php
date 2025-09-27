<?php
// Mulai sesi dan sertakan file konfigurasi

require '../config.php';

// --- PERIKSA HAK AKSES ---
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

    // Default fallback WIB
    date_default_timezone_set('Asia/Jakarta');
    return 'Asia/Jakarta';
}

// --- LOGIKA STATUS ABSENSI HRD HARI INI ---
$today = date('Y-m-d');
$status_absen_hari_ini = "Belum Absen Masuk";
$tombol_absen_text = "Absen Masuk";
$tombol_absen_action = "masuk";
$alamat_terakhir = "Lokasi belum terdeteksi";

$stmt_today = $conn->prepare("SELECT jam_masuk, jam_pulang, alamat_masuk, alamat_pulang 
                              FROM absensi 
                              WHERE id_karyawan = ? AND tanggal = ?");
$stmt_today->bind_param("is", $id_karyawan_hrd, $today);
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

// --- FILTER DATA ABSENSI ---
$search_query = $_GET['search'] ?? '';
$filter_proyek = $_GET['proyek'] ?? '';
$filter_tanggal = $_GET['tanggal'] ?? date('Y-m-d');

$sql = "
    SELECT k.nik_ktp, k.nama_karyawan, k.jabatan, k.proyek, k.role,
           a.tanggal, a.alamat_masuk, a.jam_masuk, a.alamat_pulang, a.jam_pulang, a.status_absensi
    FROM karyawan k
    LEFT JOIN absensi a 
        ON k.id_karyawan = a.id_karyawan 
       AND a.tanggal = ?
    WHERE k.role IN ('HRD', 'KARYAWAN', 'ADMIN')
";
$params = [$filter_tanggal];
$types = "s";

// filter pencarian
if ($search_query) {
    $sql .= " AND (k.nik_ktp LIKE ? OR k.nama_karyawan LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

// filter proyek
if ($filter_proyek) {
    $sql .= " AND k.proyek = ?";
    $params[] = $filter_proyek;
    $types .= "s";
}

$sql .= " ORDER BY k.nama_karyawan ASC";

$stmt = $conn->prepare($sql);

// kalau ada parameter baru bind
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$attendance_list = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$sql_pending_requests = "SELECT COUNT(*) AS total_pending FROM pengajuan WHERE status_pengajuan = 'Menunggu'";
$result_pending_requests = $conn->query($sql_pending_requests);
$total_pending = $result_pending_requests->fetch_assoc()['total_pending'] ?? 0;

// Ambil daftar proyek
$proyek_result = $conn->query("SELECT DISTINCT proyek FROM karyawan WHERE proyek IS NOT NULL ORDER BY proyek");
$all_proyek = $proyek_result ? $proyek_result->fetch_all(MYSQLI_ASSOC) : [];

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Absensi Karyawan - HRD</title>
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
        <!-- SIDEBAR -->
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
                        <li><a href="../dashboard_hrd.php"><i class="fas fa-home"></i> Dashboard</a></li>
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
                        </li>
                    </ul>
                </nav>
            </div>
            <div class="logout-link"><a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a></div>
        </aside>

        <!-- MAIN -->
        <main class="main-content">
            <header class="main-header-absensi">
                <h1>Absensi Karyawan - HRD</h1>
                <p><?= date('l, d F Y') ?></p>
                <div id="clock" class="jam-realtime"></div>
            </header>

            <!-- CARD ABSENSI PRIBADI -->
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

            <!-- TABEL ABSENSI -->
            <div class="card">
                <h2>Riwayat Absensi Seluruh Karyawan</h2>
                <form method="GET" class="toolbar">
                    <input type="date" name="tanggal" value="<?= $filter_tanggal ?>" onchange="this.form.submit()">
                    <select name="proyek" onchange="this.form.submit()">
                        <option value="">Semua Proyek</option>
                        <?php foreach ($all_proyek as $p): ?>
                            <option value="<?= $p['proyek'] ?>" <?= $filter_proyek == $p['proyek'] ? 'selected' : '' ?>>
                                <?= $p['proyek'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="search" placeholder="Cari Nama/NIK..." value="<?= $search_query ?>">
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
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($attendance_list):
                            foreach ($attendance_list as $row):
                                $status_absen = "Belum Absen";
                                $status_class = "belum-absen";
                                if ($row['jam_masuk']) {
                                    $status_absen = "Hadir";
                                    $status_class = "hadir";
                                    if ($row['status_absensi'] && $row['status_absensi'] != "Hadir") {
                                        $status_absen = $row['status_absensi'];
                                        $status_class = strtolower($row['status_absensi']);
                                    }
                                } ?>
                                <tr>
                                    <td><?= $row['tanggal'] ? date('d-m-Y', strtotime($row['tanggal'])) : "-" ?></td>
                                    <td><?= $row['nik_ktp'] ?></td>
                                    <td><?= $row['nama_karyawan'] ?></td>
                                    <td><?= $row['jabatan'] ?></td>
                                    <td><?= $row['alamat_masuk'] ?></td>
                                    <td><?= $row['jam_masuk'] ? date('H:i', strtotime($row['jam_masuk'])) : "-" ?></td>
                                    <td><?= $row['alamat_pulang'] ?></td>
                                    <td><?= $row['jam_pulang'] ? date('H:i', strtotime($row['jam_pulang'])) : "-" ?></td>
                                    <td><span class="status-badge <?= $status_class ?>"><?= $status_absen ?></span></td>
                                </tr>
                            <?php endforeach; else: ?>
                            <tr>
                                <td colspan="8" style="text-align:center;">Tidak ada data absensi</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
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
                    const provinsi = data.address?.state || data.address?.region || ""; // Tambahan: Ambil nama provinsi

                    if (alamat) {
                        document.getElementById("alamat").value = alamat;

                        // Tambahan: Tambahkan hidden input untuk provinsi
                        let provInput = document.getElementById("provinsi");
                        if (!provInput) {
                            provInput = document.createElement("input");
                            provInput.type = "hidden";
                            provInput.name = "provinsi";
                            provInput.id = "provinsi";
                            document.getElementById("absenForm").appendChild(provInput);
                        }
                        provInput.value = provinsi; // Tetapkan nilai provinsi

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

                        // Tambahan: Tetapkan nilai provinsi menjadi kosong jika gagal
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