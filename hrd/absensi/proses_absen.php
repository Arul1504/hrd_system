<?php
// Debug error (matikan di production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require '../config.php';
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
        date_default_timezone_set('Asia/Jakarta');
        return 'Asia/Jakarta';
    }
    if (in_array($p, $wita, true)) {
        date_default_timezone_set('Asia/Makassar');
        return 'Asia/Makassar';
    }
    if (in_array($p, $wit, true)) {
        date_default_timezone_set('Asia/Jayapura');
        return 'Asia/Jayapura';
    }
    date_default_timezone_set('Asia/Jakarta');
    return 'Asia/Jakarta';
}

// 1. Pastikan user login
if (!isset($_SESSION['id_karyawan'])) {
    header("Location: ../index.php");
    exit();
}

// 2. Pastikan request POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id_karyawan = $_SESSION['id_karyawan'];

    // --- Ambil NIK dari database ---
    $stmt_karyawan = $conn->prepare("SELECT nik_ktp FROM karyawan WHERE id_karyawan = ?");
    $stmt_karyawan->bind_param("i", $id_karyawan);
    $stmt_karyawan->execute();
    $result_karyawan = $stmt_karyawan->get_result();
    $data_karyawan = $result_karyawan->fetch_assoc();

    if (!$data_karyawan) {
        die("Error: Data karyawan tidak ditemukan. Mohon login ulang.");
    }

    $nik_user = $data_karyawan['nik_ktp'];

    // --- Data dari form ---
    $action = $_POST['action'] ?? '';
    $client_time = $_POST['client_time'] ?? date('c');
    $latitude = $_POST['latitude'] ?? "0";
    $longitude = $_POST['longitude'] ?? "0";
    $alamat = $_POST['alamat'] ?? "Lokasi tidak terdeteksi";

    // --- LUNGIK UNTUK ZONA WAKTU ---
    // Periksa apakah ada data provinsi yang dikirim
    $provinsi = $_POST['provinsi'] ?? '';

    // Pastikan fungsi set_timezone_from_province tersedia
    if (function_exists('set_timezone_from_province')) {
        // Atur zona waktu berdasarkan provinsi yang dikirim
        set_timezone_from_province($provinsi);
    }

    // Konversi tanggal dan jam setelah zona waktu diatur
    $tanggal = date('Y-m-d', strtotime($client_time));
    $jam_now = date('H:i:s', strtotime($client_time));

    // --- PROSES ABSEN ---
    if ($action === 'masuk') {
        // Cek apakah sudah absen masuk
        $stmt_check = $conn->prepare("SELECT id_absensi FROM absensi WHERE nik_karyawan = ? AND tanggal = ?");
        $stmt_check->bind_param("ss", $nik_user, $tanggal);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows == 0) {
            // Tentukan status (Hadir/Terlambat)
            $jam_normal = strtotime('08:00:00');
            $jam_absen = strtotime($jam_now);
            $status_absensi = ($jam_absen > $jam_normal) ? 'Terlambat' : 'Hadir';

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
                $provinsi,
                date_default_timezone_get(),
                $status_absensi
            );


            if (!$stmt_insert->execute()) {
                die("Error absen masuk: " . $stmt_insert->error);
            }
        } else {
            header("Location: absensi.php?status=error&msg=Anda sudah absen masuk hari ini.");
            exit();
        }

    } elseif ($action === 'pulang') {
        // Update absen pulang
        $stmt_update = $conn->prepare("
            UPDATE absensi 
            SET jam_pulang = ?, lat_pulang = ?, lon_pulang = ?, alamat_pulang = ?
            WHERE nik_karyawan = ? AND tanggal = ? AND jam_pulang IS NULL
        ");
        $stmt_update->bind_param(
            "ssssss",
            $jam_now,
            $latitude,
            $longitude,
            $alamat,
            $nik_user,
            $tanggal
        );

        if (!$stmt_update->execute()) {
            die("Error absen pulang: " . $stmt_update->error);
        }

        if ($stmt_update->affected_rows === 0) {
            header("Location: absensi.php?status=error&msg=Belum ada absen masuk hari ini.");
            exit();
        }

    } else {
        header("Location: absensi.php?status=error&msg=Aksi tidak valid.");
        exit();
    }

    header("Location: absensi.php?status=sukses");
    exit();
} else {
    die("Metode tidak diizinkan.");
}
