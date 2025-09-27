<?php
session_start();
require_once 'config.php'; // Sesuaikan path jika perlu

if (!isset($_SESSION['id_karyawan'])) {
    header("Location: ../index.php");
    exit();
}

$id_karyawan = (int)$_SESSION['id_karyawan'];
$filename_requested = $_GET['file'] ?? '';

if (empty($filename_requested)) {
    die("File tidak ditemukan.");
}

// Pastikan file yang diminta adalah milik karyawan yang sedang login
$stmt = $conn->prepare("SELECT surat_tugas FROM karyawan WHERE id_karyawan = ? AND surat_tugas = ?");
$stmt->bind_param("is", $id_karyawan, $filename_requested);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Akses ditolak atau file tidak valid.");
}

$file_path = '../uploads/surat_tugas/' . $filename_requested;

if (file_exists($file_path)) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . basename($filename_requested) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file_path));
    readfile($file_path);
    exit();
} else {
    die("File tidak ditemukan di server.");
}
?>