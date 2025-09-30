<?php
// ===========================
// rekap_absensi.php
// ===========================
require '../config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// --- CEK SESSION (ADMIN / HRD) ---
if (!isset($_SESSION['id_karyawan']) || !in_array($_SESSION['role'] ?? '', ['HRD','ADMIN'])) {
    header("Location: ../../index.php");
    exit();
}

$id_karyawan = isset($_GET['id_karyawan']) ? (int)$_GET['id_karyawan'] : 0;
$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : date('m');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date('Y');

// --- Ambil nama karyawan ---
$nama_karyawan = '';
if ($id_karyawan > 0) {
    $q = $conn->prepare("SELECT nama_karyawan FROM karyawan WHERE id_karyawan=?");
    $q->bind_param("i", $id_karyawan);
    $q->execute();
    $res = $q->get_result()->fetch_assoc();
    $nama_karyawan = $res['nama_karyawan'] ?? '';
    $q->close();
}

// --- Ambil semua absensi karyawan di bulan-tahun ini ---
$stmt = $conn->prepare("
    SELECT * FROM absensi 
    WHERE id_karyawan=? AND MONTH(tanggal)=? AND YEAR(tanggal)=?
    ORDER BY tanggal ASC
");
$stmt->bind_param("iii", $id_karyawan, $bulan, $tahun);
$stmt->execute();
$data_absensi = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// --- Hitung rekap ---
$total_hari_kerja = cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun);
$hadir = 0;
$tidak_hadir = 0;
$terlambat = 0;

$hari_absen = [];
foreach ($data_absensi as $row) {
    $tgl = date('Y-m-d', strtotime($row['tanggal']));
    $hari_absen[$tgl] = true;
    $hadir++;

    // Cek keterlambatan (contoh: jam masuk lewat 08:00:00)
    if (!empty($row['jam_masuk']) && strtotime($row['jam_masuk']) > strtotime('08:00:00')) {
        $terlambat++;
    }
}

// --- Jumlah tidak hadir = hari kerja - hadir (abaikan weekend jika perlu) ---
$tidak_hadir = $total_hari_kerja - $hadir;

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Absensi</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .card {background:#fff;padding:20px;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.1);margin-bottom:20px;}
        .card h2 {margin:0 0 15px;font-size:20px;}
        .rekap-box {display:flex;gap:20px;flex-wrap:wrap;}
        .rekap-item {flex:1;min-width:180px;background:#f9f9f9;padding:15px;border-radius:8px;text-align:center;}
        .rekap-item h3 {margin:0;font-size:16px;color:#555;}
        .rekap-item p {font-size:24px;font-weight:bold;margin:8px 0 0;}
    </style>
</head>
<body>
<div class="container">
    <main class="main-content">
        <div class="card">
            <h2>Rekap Absensi: <?= htmlspecialchars($nama_karyawan) ?> (<?= $bulan.'/'.$tahun ?>)</h2>
            <div class="rekap-box">
                <div class="rekap-item" style="background:#e0f7fa;">
                    <h3>Total Hari Kerja</h3>
                    <p><?= $total_hari_kerja ?></p>
                </div>
                <div class="rekap-item" style="background:#c8e6c9;">
                    <h3>Hadir</h3>
                    <p><?= $hadir ?></p>
                </div>
                <div class="rekap-item" style="background:#ffcdd2;">
                    <h3>Tidak Hadir</h3>
                    <p><?= $tidak_hadir ?></p>
                </div>
                <div class="rekap-item" style="background:#fff9c4;">
                    <h3>Terlambat</h3>
                    <p><?= $terlambat ?></p>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>
