<?php
/**
 * File  : process_perpanjang.php
 * Folder: C:\laragon\www\hrd_system\hrd\monitoring_kontrak
 * Fungsi: Memproses perpanjangan kontrak dari modal "Perpanjang Kontrak"
 *         - Update karyawan.join_date & karyawan.end_date
 *         - Catat riwayat ke tabel kontrak_history
 */

require '../config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Wajib HRD
if (!isset($_SESSION['id_karyawan']) || ($_SESSION['role'] ?? '') !== 'HRD') {
    header('Location: ../../index.php');
    exit();
}

// Helper
function back_with($msg) {
    $_SESSION['flash_msg'] = $msg;
    header('Location: monitoring_kontrak.php');
    exit();
}
function valid_date($d) {
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
}

// Ambil input
$id   = (int)($_POST['id_karyawan'] ?? 0);
$sNew = trim($_POST['start_new'] ?? '');
$eNew = trim($_POST['end_new']   ?? '');
$note = trim($_POST['note']      ?? '');

// Validasi dasar
if (!$id || $sNew === '' || $eNew === '') {
    back_with('Gagal: data tidak lengkap.');
}
if (!valid_date($sNew) || !valid_date($eNew)) {
    back_with('Gagal: format tanggal harus YYYY-MM-DD.');
}
if (strtotime($eNew) < strtotime($sNew)) {
    back_with('Gagal: Tanggal akhir harus >= tanggal mulai.');
}

// Pastikan karyawan ada
$cek = $conn->prepare("SELECT id_karyawan FROM karyawan WHERE id_karyawan=? LIMIT 1");
$cek->bind_param('i', $id);
$cek->execute();
$exists = $cek->get_result()->fetch_assoc();
$cek->close();
if (!$exists) back_with('Gagal: Karyawan tidak ditemukan.');

// Buat tabel riwayat jika belum ada (ringan & idempotent)
$conn->query("
    CREATE TABLE IF NOT EXISTS kontrak_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_karyawan INT NOT NULL,
        start_new DATE NULL,
        end_new DATE NULL,
        note TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Transaksi untuk konsistensi
$conn->begin_transaction();
try {
    // Update tanggal kontrak pada tabel karyawan
    $up = $conn->prepare("UPDATE karyawan SET join_date=?, end_date=? WHERE id_karyawan=?");
    $up->bind_param('ssi', $sNew, $eNew, $id);
    if (!$up->execute()) {
        throw new Exception('Gagal menyimpan perpanjangan (update karyawan).');
    }
    $up->close();

    // Catat riwayat
    $ins = $conn->prepare("INSERT INTO kontrak_history (id_karyawan, start_new, end_new, note) VALUES (?, ?, ?, ?)");
    $ins->bind_param('isss', $id, $sNew, $eNew, $note);
    if (!$ins->execute()) {
        throw new Exception('Gagal mencatat riwayat perpanjangan.');
    }
    $ins->close();

    $conn->commit();
    back_with('Perpanjangan kontrak tersimpan.');
} catch (Throwable $e) {
    $conn->rollback();
    // Catat error ke log server, tapi tampilkan pesan ramah ke user
    error_log('[process_perpanjang] ' . $e->getMessage());
    back_with('Gagal menyimpan perpanjangan.');
}
