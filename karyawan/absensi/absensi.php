<?php
session_start();

// Cek apakah user sudah login
if (!isset($_SESSION['id_karyawan'])) {
    header("Location: ../index.php");
    exit();
}

require '../config.php';
// --- FUNGSI UNTUK MENGATUR ZONA WAKTU BERDASARKAN PROVINSI ---
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
// Ambil data user dari sesi untuk digunakan di halaman ini
$id_karyawan = $_SESSION['id_karyawan'];
$nama_user = $_SESSION['nama'];

// Ambil NIK dan Jabatan dari database berdasarkan ID karyawan
$stmt_user_info = $conn->prepare("SELECT nik_ktp, jabatan FROM karyawan WHERE id_karyawan = ?");
$stmt_user_info->bind_param("i", $id_karyawan);
$stmt_user_info->execute();
$result_user_info = $stmt_user_info->get_result();
$user_info = $result_user_info->fetch_assoc();

if (!$user_info) {
    // Jika data user tidak ditemukan, arahkan kembali ke login
    header("Location: ../../index.php");
    exit();
}

$nik_user = $user_info['nik_ktp'];
$jabatan_user = $user_info['jabatan'];


// 2. LOGIKA UNTUK STATUS ABSENSI HARI INI
$today = date('Y-m-d');
$status_absen_hari_ini = "Belum Absen Masuk";
$tombol_absen_text = "Absen Masuk";
$tombol_absen_action = "masuk";
$alamat_terakhir = "Lokasi belum terdeteksi";
$waktu_masuk = null;
$waktu_pulang = null;

$stmt_today = $conn->prepare("SELECT jam_masuk, jam_pulang, alamat_masuk, alamat_pulang FROM absensi WHERE id_karyawan = ? AND tanggal = ?");
$stmt_today->bind_param("is", $id_karyawan, $today);
$stmt_today->execute();
$result_today = $stmt_today->get_result();

if ($result_today->num_rows > 0) {
    $absen_hari_ini = $result_today->fetch_assoc();
    $waktu_masuk = $absen_hari_ini['jam_masuk'];
    $waktu_pulang = $absen_hari_ini['jam_pulang'];

    if ($waktu_pulang !== null) {
        $status_absen_hari_ini = "Absensi Hari Ini Selesai";
        $tombol_absen_text = "Selesai";
        $tombol_absen_action = "";
    } else {
        $status_absen_hari_ini = "Sudah Absen Masuk";
        $tombol_absen_text = "Absen Pulang";
        $tombol_absen_action = "pulang";
    }

    if (!empty($absen_hari_ini['alamat_pulang'])) {
        $alamat_terakhir = $absen_hari_ini['alamat_pulang'];
    } elseif (!empty($absen_hari_ini['alamat_masuk'])) {
        $alamat_terakhir = $absen_hari_ini['alamat_masuk'];
    }
}

// 3. RINGKASAN ABSENSI BULAN INI
$bulan_ini = date('Y-m');
$stmt_summary = $conn->prepare("
    SELECT 
        COUNT(CASE WHEN status_absensi = 'Hadir' THEN 1 END) AS hadir,
        COUNT(CASE WHEN status_absensi = 'Terlambat' THEN 1 END) AS terlambat,
        COUNT(CASE WHEN status_absensi = 'Izin' THEN 1 END) AS izin,
        COUNT(CASE WHEN status_absensi = 'Sakit' THEN 1 END) AS sakit
    FROM absensi
    WHERE id_karyawan = ? AND DATE_FORMAT(tanggal, '%Y-%m') = ?
");
$stmt_summary->bind_param("ss", $id_karyawan, $bulan_ini);
$stmt_summary->execute();
$summary = $stmt_summary->get_result()->fetch_assoc();

$total_jam_kerja_detik = 0;
$total_terlambat_detik = 0;

$stmt_total_jam = $conn->prepare("SELECT jam_masuk, jam_pulang, status_absensi FROM absensi WHERE id_karyawan = ? AND DATE_FORMAT(tanggal, '%Y-%m') = ?");
$stmt_total_jam->bind_param("ss", $id_karyawan, $bulan_ini);
$stmt_total_jam->execute();
$result_total_jam = $stmt_total_jam->get_result();

while ($row = $result_total_jam->fetch_assoc()) {
    if ($row['jam_masuk'] && $row['jam_pulang']) {
        $jam_masuk = strtotime($row['jam_masuk']);
        $jam_pulang = strtotime($row['jam_pulang']);
        $total_jam_kerja_detik += $jam_pulang - $jam_masuk;
    }
    if (isset($row['status_absensi']) && $row['status_absensi'] == 'Terlambat') {
        $jam_masuk_asli = strtotime($row['jam_masuk']);
        $jam_normal = strtotime('08:00:00');
        if ($jam_masuk_asli > $jam_normal) {
            $total_terlambat_detik += $jam_masuk_asli - $jam_normal;
        }
    }
}

$jam = floor($total_jam_kerja_detik / 3600);
$menit = floor(($total_jam_kerja_detik % 3600) / 60);
$total_jam_kerja_format = "{$jam}j {$menit}m";

$terlambat_jam = floor($total_terlambat_detik / 3600);
$terlambat_menit = floor(($total_terlambat_detik % 3600) / 60);
$total_terlambat_format = "{$terlambat_jam}j {$terlambat_menit}m";

// Ambil riwayat absensi
$stmt_history = $conn->prepare("SELECT * FROM absensi WHERE id_karyawan = ? ORDER BY tanggal DESC LIMIT 15");
$stmt_history->bind_param("s", $id_karyawan);
$stmt_history->execute();
$history_list = $stmt_history->get_result();

$conn->close();

// Pastikan fungsi e() sudah dideklarasikan di config.php
// Jika belum, pindahkan fungsi di bawah ini ke config.php
// dan hapus dari file ini.
if (!function_exists('e')) {
    function e($string)
    {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Absensi Karyawan</title>
    <link rel="stylesheet" href="../style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* CSS yang disempurnakan */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: #f5f7fb;
            color: #333;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            padding: 30px;
        }

        .main-header-absensi h1 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .main-header-absensi p {
            color: #666;
            font-size: 14px;
        }

        .content-wrapper {
            margin-top: 20px;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media (max-width: 768px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background: #fff;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }

        .card h2 {
            font-size: 18px;
            margin-bottom: 5px;
        }

        .card p {
            font-size: 13px;
            color: #666;
            margin-bottom: 10px;
        }

        .current-date {
            font-size: 14px;
            font-weight: 500;
            color: #888;
        }

        .current-time {
            font-size: 36px;
            font-weight: bold;
            margin: 10px 0;
            color: #222;
        }

        .location-status {
            font-size: 14px;
            margin: 8px 0;
            color: #999;
        }

        .attendance-status {
            font-size: 14px;
            font-weight: 600;
            margin: 10px 0;
            color: #e74c3c;
        }

        .btn {
            display: block;
            width: 100%;
            padding: 12px 18px;
            font-size: 14px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-primary {
            background: #3498db;
            color: #fff;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 10px;
            margin: 15px 0;
        }

        .summary-box {
            padding: 15px;
            border-radius: 10px;
            color: #fff;
            text-align: center;
            font-size: 18px;
            font-weight: 600;
        }

        .summary-box small {
            display: block;
            font-size: 13px;
            font-weight: normal;
            margin-top: 5px;
        }

        .summary-box.green {
            background: #2ecc71;
        }

        .summary-box.yellow {
            background: #f1c40f;
        }

        .summary-box.blue {
            background: #3498db;
        }

        .summary-box.purple {
            background: #9b59b6;
        }

        .extra-info {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            color: #444;
            margin-top: 15px;
        }

        .extra-info span {
            text-align: center;
        }

        .extra-info span b {
            display: block;
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-top: 3px;
        }

        .history-section {
            margin-top: 20px;
        }

        .history-list {
            list-style: none;
            padding: 0;
        }

        .history-item {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .history-item .details {
            flex: 1;
        }

        .history-item .status {
            padding: 6px 12px;
            border-radius: 20px;
            color: #fff;
            font-size: 12px;
            font-weight: 600;
        }

        .status.hadir {
            background: #2ecc71;
        }

        .status.terlambat {
            background: #f1c40f;
        }

        .status.izin {
            background: #3498db;
        }

        .status.sakit {
            background: #9b59b6;
        }

        .history-item p {
            margin: 0;
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
                <div class="user-avatar"><?= e(strtoupper(substr($nama_user, 0, 2))) ?></div>
                <div class="user-details">
                    <p class="user-name"><?= e($nama_user) ?></p>
                    <p class="user-id"><?= e($nik_user) ?></p>
                    <p class="user-role"><?= e($jabatan_user) ?></p>
                </div>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="../dashboard_karyawan.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
                    <li class="active"><a href="#"><i class="fas fa-clipboard-list"></i> Absensi</a></li>
                    <li><a href="../pengajuan/pengajuan.php"><i class="fas fa-file-invoice"></i> Pengajuan Saya</a></li>
                    <li><a href="../slipgaji/slipgaji.php"><i class="fas fa-money-check-alt"></i> Slip Gaji</a></li>
                </ul>
            </nav>
            <div class="logout-link">
                <a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a>
            </div>
        </aside>

        <main class="main-content">
            <header class="main-header-absensi">
                <h1>Absensi</h1>
                <p>Kelola kehadiran dan lihat riwayat absensi Anda</p>
            </header>

            <div class="content-wrapper grid-2">
                <div class="card">
                    <h2>Absensi Hari Ini</h2>
                    <p class="current-date"><?= date('l, d F Y') ?></p>
                    <div class="current-time" id="clock">00:00:00</div>
                    <p class="location-status"><i class="fas fa-map-marker-alt"></i>
                        <?= htmlspecialchars($alamat_terakhir) ?></p>
                    <div class="attendance-status">
                        <?= $status_absen_hari_ini ?>
                    </div>
                    <form method="POST" action="proses_absen.php" id="absenForm">
                        <input type="hidden" id="latitude" name="latitude">
                        <input type="hidden" id="longitude" name="longitude">
                        <input type="hidden" id="alamat" name="alamat">
                        <input type="hidden" id="action" name="action" value="<?= $tombol_absen_action ?>">
                        <input type="hidden" id="client_time" name="client_time">
                        <button type="button" onclick="getLocation(true)" id="btnAbsen" class="btn btn-primary"
                            <?= $tombol_absen_action ? '' : 'disabled' ?>>
                            <i class="fas fa-map-marker-alt"></i> <?= $tombol_absen_text ?>
                        </button>
                    </form>
                </div>

                <div class="card">
                    <h2>Ringkasan Kehadiran Bulan Ini</h2>
                    <p>Statistik kehadiran Anda bulan <?= date('F Y') ?></p>
                    <div class="summary-grid">
                        <div class="summary-box green"><?= $summary['hadir'] ?? 0 ?> <small>Hadir</small></div>
                        <div class="summary-box yellow"><?= $summary['terlambat'] ?? 0 ?> <small>Terlambat</small></div>
                        <div class="summary-box blue"><?= $summary['izin'] ?? 0 ?> <small>Izin</small></div>
                        <div class="summary-box purple"><?= $summary['sakit'] ?? 0 ?> <small>Sakit</small></div>
                    </div>
                    <div class="extra-info">
                        <span>Jam Kerja<br><b><?= $total_jam_kerja_format ?></b></span>
                        <span>Keterlambatan<br><b><?= $total_terlambat_format ?></b></span>
                    </div>
                </div>
            </div>

            <div class="history-section">
                <div class="card">
                    <h2>Riwayat Absensi Terakhir</h2>
                    <ul class="history-list">
                        <?php if ($history_list->num_rows > 0): ?>
                            <?php while ($row = $history_list->fetch_assoc()): ?>
                                <li class="history-item">
                                    <div class="details">
                                        <p><strong><?= date('d F Y', strtotime($row['tanggal'])) ?></strong></p>
                                        <p style="font-size:12px;color:#888;">
                                            Masuk: <?= $row['jam_masuk'] ? date('H:i', strtotime($row['jam_masuk'])) : '-' ?>
                                            | Pulang:
                                            <?= $row['jam_pulang'] ? date('H:i', strtotime($row['jam_pulang'])) : '-' ?>
                                        </p>
                                    </div>
                                    <div class="status <?= strtolower($row['status_absensi']) ?>"><?= $row['status_absensi'] ?>
                                    </div>
                                </li>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p style="text-align:center;color:#888;">Belum ada riwayat absensi.</p>
                        <?php endif; ?>
                    </ul>
                </div>
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
                
                // Tambahan: Tambahkan hidden input untuk provinsi kalau belum ada
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