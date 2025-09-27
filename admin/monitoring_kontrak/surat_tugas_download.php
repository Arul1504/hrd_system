<?php
/**
 * File: surat_tugas_save.php
 * Fungsi: Menyimpan metadata surat tugas ke tabel `surat_tugas` dan mengalihkan kembali.
 */

require '../config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// --- CEK SESSION (hanya ADMIN) ---
if (!isset($_SESSION['id_karyawan']) || ($_SESSION['role'] ?? '') !== 'ADMIN') {
    header('Location: ../../index.php');
    exit();
}

// Helper

function ymd($v){
    if(!$v) return null;
    $t = strtotime($v);
    return $t ? date('Y-m-d', $t) : null;
}

// Ambil input dari POST (karena akan dipanggil dari form di halaman view)
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

// Lakukan koneksi dan pastikan tabel ada (aman diulang)
$conn->query("
CREATE TABLE IF NOT EXISTS surat_tugas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_karyawan INT NOT NULL,
    no_surat VARCHAR(128) NOT NULL,
    file_path VARCHAR(255) NULL, /* Kolom ini akan diisi dengan path file PDF jika diunggah */
    posisi VARCHAR(128) NULL,
    penempatan VARCHAR(128) NULL,
    sales_code VARCHAR(64) NULL,
    alamat_penempatan TEXT NULL,
    tgl_pembuatan DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_st (id_karyawan, no_surat)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Upsert metadata
// Kami asumsikan saat generate, file_path dikosongkan/null karena belum ada file fisik
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
    $_SESSION['flash_msg'] = "Data surat tugas **Nomor: {$no_surat}** berhasil disimpan. Silakan unduh/upload file fisiknya di Riwayat Surat Tugas.";
} else {
    $_SESSION['flash_msg'] = "Gagal menyimpan data surat tugas: " . $conn->error;
}

// Redirect ke halaman riwayat atau monitoring
header('Location: surat_tugas_history.php');
exit;
