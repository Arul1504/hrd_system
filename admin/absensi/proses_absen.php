<?php
// Debug error (matikan di production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


require '../config.php';

// =======================
// Fungsi helper timezone
// =======================
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

    if (in_array($p, $wib, true))
        return 'Asia/Jakarta';
    if (in_array($p, $wita, true))
        return 'Asia/Makassar';
    if (in_array($p, $wit, true))
        return 'Asia/Jayapura';
    return 'Asia/Jakarta'; // default fallback
}

// =======================
// 1. Pastikan user login
// =======================
if (!isset($_SESSION['id_karyawan'])) {
    header("Location: ../index.php");
    exit();
}

// =======================
// 2. Pastikan request POST
// =======================
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Metode tidak diizinkan.");
}

$id_karyawan = $_SESSION['id_karyawan'];

// --- Ambil NIK dari database ---
$stmt_karyawan = $conn->prepare("SELECT nik_ktp FROM karyawan WHERE id_karyawan = ?");
$stmt_karyawan->bind_param("i", $id_karyawan);
$stmt_karyawan->execute();
$result_karyawan = $stmt_karyawan->get_result();
$data_karyawan = $result_karyawan->fetch_assoc();
$stmt_karyawan->close();

if (!$data_karyawan) {
    header("Location: absensi.php?status=error&msg=Data karyawan tidak ditemukan.");
    exit();
}

$nik_user = $data_karyawan['nik_ktp'];

// =======================
// 3. Data dari form
// =======================
$action = $_POST['action'] ?? '';
$province = $_POST['provinsi'] ?? '';
$tz = set_timezone_from_province($province);

// Waktu lokal sesuai provinsi
$now = new DateTime('now', new DateTimeZone($tz));
$tanggal = $now->format('Y-m-d');
$jam_now = $now->format('H:i:s');

// Untuk cek telat
$threshold = new DateTime($tanggal . ' 09:04:00', new DateTimeZone($tz));
$status_absensi = ($now > $threshold) ? 'Terlambat' : 'Hadir';

// Lokasi dari form
$latitude = $_POST['latitude'] ?? null;
$longitude = $_POST['longitude'] ?? null;
$alamat = $_POST['alamat'] ?? "Lokasi tidak terdeteksi";

// =======================
// 4. PROSES ABSEN MASUK
// =======================
if ($action === 'masuk') {
    $stmt_check = $conn->prepare("SELECT id_absensi FROM absensi WHERE id_karyawan = ? AND tanggal = ?");
    $stmt_check->bind_param("is", $id_karyawan, $tanggal);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows == 0) {
        // Insert absen masuk
        $stmt_insert = $conn->prepare("
    INSERT INTO absensi 
    (id_karyawan, nik_karyawan, tanggal, jam_masuk, lat_masuk, lon_masuk, alamat_masuk, provinsi, zona_waktu, status_absensi) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

        $stmt_insert->bind_param(
            "isssssssss",
            $id_karyawan,
            $nik_user,
            $tanggal,
            $jam_now,
            $latitude,
            $longitude,
            $alamat,
            $province,
            $tz,
            $status_absensi
        );

        if (!$stmt_insert->execute()) {
            error_log("Error absen masuk: " . $stmt_insert->error);
            header("Location: absensi.php?status=error&msg=Gagal mencatat absen masuk.");
            exit();
        }

        $stmt_insert->close();
        header("Location: absensi.php?status=sukses_masuk");
        exit();

    } else {
        $stmt_check->close();
        header("Location: absensi.php?status=error&msg=Anda sudah absen masuk hari ini.");
        exit();
    }
}

// =======================
// 5. PROSES ABSEN PULANG
// =======================
elseif ($action === 'pulang') {
    $stmt_update = $conn->prepare("
        UPDATE absensi 
        SET jam_pulang = ?, lat_pulang = ?, lon_pulang = ?, alamat_pulang = ?, updated_at = NOW() 
        WHERE id_karyawan = ? AND tanggal = ? AND jam_pulang IS NULL
    ");
    $stmt_update->bind_param(
        "ssssss",
        $jam_now,
        $latitude,
        $longitude,
        $alamat,
        $id_karyawan,
        $tanggal
    );

    if (!$stmt_update->execute()) {
        error_log("Error absen pulang: " . $stmt_update->error);
        header("Location: absensi.php?status=error&msg=Gagal mencatat absen pulang.");
        exit();
    }

    if ($stmt_update->affected_rows === 0) {
        $stmt_update->close();
        header("Location: absensi.php?status=error&msg=Belum ada absen masuk hari ini.");
        exit();
    }

    $stmt_update->close();
    header("Location: absensi.php?status=sukses_pulang");
    exit();
}

// =======================
// 6. Aksi tidak valid
// =======================
else {
    header("Location: absensi.php?status=error&msg=Aksi tidak valid.");
    exit();
}
