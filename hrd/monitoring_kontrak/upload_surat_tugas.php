<?php
/**
 * File  : upload_surat_tugas.php
 * Folder: C:\laragon\www\hrd_system\hrd\monitoring_kontrak
 * Fungsi: Menerima upload file Surat Tugas (PDF/DOC/DOCX/JPG/PNG),
 *         simpan ke /uploads/surat_tugas, lalu catat/ubah path di tabel `surat_tugas`.
 */

require '../config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Wajib HRD
if (!isset($_SESSION['id_karyawan']) || ($_SESSION['role'] ?? '') !== 'HRD') {
    header('Location: ../../index.php');
    exit();
}

// Helper flash & redirect
function back_with($msg) {
    $_SESSION['flash_msg'] = $msg;
    header('Location: monitoring_kontrak.php');
    exit();
}

// Ambil input minimal
$id_karyawan = (int)($_POST['id_karyawan'] ?? 0);
$no_surat    = trim($_POST['no_surat'] ?? '');

if (!$id_karyawan || $no_surat === '') {
    back_with('Gagal upload: data tidak lengkap.');
}

// Validasi file
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    back_with('Tidak ada berkas atau terjadi error saat upload.');
}

$origName = $_FILES['file']['name'];
$tmpPath  = $_FILES['file']['tmp_name'];
$size     = (int)$_FILES['file']['size'];

$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
$allowed = ['pdf','doc','docx','jpg','jpeg','png'];
if (!in_array($ext, $allowed, true)) {
    back_with('Format file tidak didukung. Gunakan PDF/DOC/DOCX/JPG/PNG.');
}
$maxBytes = 10 * 1024 * 1024; // 10 MB
if ($size <= 0 || $size > $maxBytes) {
    back_with('Ukuran file melebihi 10 MB atau kosong.');
}

// Pastikan tabel metadata ada (idempotent)
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

// Siapkan folder upload
$baseDir   = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'surat_tugas';
if (!is_dir($baseDir)) {
    @mkdir($baseDir, 0777, true);
    // fallback jika gagal mkdir
    if (!is_dir($baseDir)) back_with('Gagal membuat folder upload.');
}

// Nama file aman + timestamp
$slugNo   = preg_replace('/[^a-zA-Z0-9_-]+/','-', $no_surat);
$filename = $slugNo . '-' . date('Ymd_His') . '.' . $ext;
$destAbs  = $baseDir . DIRECTORY_SEPARATOR . $filename;
// Simpan sebagai path relatif untuk ditaruh di DB (dipakai untuk link di aplikasi)
$destRel  = 'uploads/surat_tugas/' . $filename;

// Pindahkan file
if (!move_uploaded_file($tmpPath, $destAbs)) {
    back_with('Gagal menyimpan berkas ke server.');
}

// Upsert baris di `surat_tugas` (jika sudah ada no_surat untuk karyawan ini — update path)
$sel = $conn->prepare("SELECT id FROM surat_tugas WHERE id_karyawan=? AND no_surat=? LIMIT 1");
$sel->bind_param('is', $id_karyawan, $no_surat);
$sel->execute();
$exist = $sel->get_result()->fetch_assoc();
$sel->close();

if ($exist) {
    $upd = $conn->prepare("UPDATE surat_tugas SET file_path=? WHERE id=?");
    $upd->bind_param('si', $destRel, $exist['id']);
    $ok = $upd->execute();
    $upd->close();
} else {
    $ins = $conn->prepare("INSERT INTO surat_tugas (id_karyawan,no_surat,file_path) VALUES (?,?,?)");
    $ins->bind_param('iss', $id_karyawan, $no_surat, $destRel);
    $ok = $ins->execute();
    $ins->close();
}

back_with($ok ? 'Surat tugas berhasil diupload.' : 'Gagal mencatat file ke database.');
