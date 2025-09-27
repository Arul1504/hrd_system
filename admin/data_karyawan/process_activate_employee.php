<?php
// ===================================
// process_activate_employee.php
// ===================================

session_start();
require_once '../config.php';

// --- Periksa Hak Akses ADMIN ---
if (!isset($_SESSION['id_karyawan']) || ($_SESSION['role'] ?? '') !== 'ADMIN') {
    header("Location: ../../index.php");
    exit();
}

// Ambil NIK karyawan dari parameter URL
$nik = $_GET['nik'] ?? '';

if (empty($nik)) {
    // Redirect dengan pesan error jika NIK tidak valid
    header("Location: karyawan_nonaktif.php?status=error");
    exit();
}

// Perbarui status karyawan di database
$new_status = 'AKTIF';
$stmt = $conn->prepare("UPDATE karyawan SET status = ? WHERE nik_karyawan = ?");

if (!$stmt) {
    // Redirect jika ada kesalahan SQL
    header("Location: karyawan_nonaktif.php?status=error");
    exit();
}

$stmt->bind_param("ss", $new_status, $nik);

if ($stmt->execute()) {
    // Jika berhasil, redirect dengan pesan sukses
    header("Location: karyawan_nonaktif.php?status=activated");
    exit();
} else {
    // Jika gagal, redirect dengan pesan error
    header("Location: karyawan_nonaktif.php?status=error");
    exit();
}

$stmt->close();
$conn->close();
?>