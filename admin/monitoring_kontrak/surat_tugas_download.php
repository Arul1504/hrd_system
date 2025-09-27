<?php
/**
 * File: surat_tugas_save.php (Versi HRD)
 * Fungsi: Menyimpan metadata surat tugas ke tabel `surat_tugas`
 *         Role akses: HRD
 */

require '../config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// --- CEK SESSION (hanya HRD) ---
if (!isset($_SESSION['id_karyawan']) || ($_SESSION['role'] ?? '') !== 'HRD') {
    header('Location: ../../index.php');
    exit();
}

// Helper
function ymd($v){
    if(!$v) return null;
    $t = strtotime($v);
    return $t ? date('Y-m-d', $t) : null;
}

// Ambil input dari POST
$fields = [
    'id_karyawan','nama','proyek','posisi','penempatan','sales_code',
    'alamat_penempatan','tgl_pembuatan','no_surat'
];
$data = [];
foreach($fields as $f){ $data[$f] = trim($_POST[$f] ?? ''); }

// Validasi minimal
if (($data['id_karyawan'] ?? '') === '' || ($data['no_surat'] ?? '') === '') {
    $_SESSION['flash_msg'] = "Gagal menyimpan: Data ID Karyawan atau Nomor Surat tidak lengkap.";
    header('Location: monitoring_kontrak.php');
    exit;
}

$id_karyawan   = (int)$data['id_karyawan'];
$no_surat      = $data['no_surat'];
$tgl_pembuatan = ymd($data['tgl_pembuatan'] ?: date('Y-m-d'));

// Pastikan tabel ada
$conn->query("
CREATE TABLE IF NOT EXISTS surat_tugas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_karyawan INT NOT NULL,
    no_surat VARCHAR(128) NOT NULL,
    file_path VARCHAR(255) NULL,
    posisi VARCHAR(128) NULL,
    penempatan VARCHAR(128) NULL,
    sales_code VARCHAR(64) NULL,
    alamat_penempatan TEXT NULL,
    tgl_pembuatan DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_st (id_karyawan, no_surat)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Simpan / update data
$up = $conn->prepare("
    INSERT INTO surat_tugas
        (id_karyawan, no_surat, file_path, posisi, penempatan, sales_code, alamat_penempatan, tgl_pembuatan)
    VALUES
        (?,?,NULL,?,?,?,?,?)
    ON DUPLICATE KEY UPDATE
        posisi=VALUES(posisi),
        penempatan=VALUES(penempatan),
        sales_code=VALUES(sales_code),
        alamat_penempatan=VALUES(alamat_penempatan),
        tgl_pembuatan=VALUES(tgl_pembuatan)
");
$up->bind_param(
    'issssss',
    $id_karyawan,
    $no_surat,
    $data['posisi'],
    $data['penempatan'],
    $data['sales_code'],
    $data['alamat_penempatan'],
    $tgl_pembuatan
);
$success = $up->execute();
$up->close();

if ($success) {
    $_SESSION['flash_msg'] = "Data surat tugas **Nomor: {$no_surat}** berhasil disimpan.";
} else {
    $_SESSION['flash_msg'] = "Gagal menyimpan data surat tugas: " . $conn->error;
}

// Redirect ke riwayat surat tugas (HRD)
header('Location: surat_tugas_history.php');
exit;
