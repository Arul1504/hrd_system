<?php
/**
 * PDF generator: Surat Tugas
 * Lokasi: C:\laragon\www\hrd_system\hrd\monitoring_kontrak\surat_tugas_download.php
 * Catatan:
 * - Membuat PDF pakai Dompdf
 * - Simpan metadata ke tabel `surat_tugas`
 */

require_once '../config.php';
// cari autoload dari Composer di root project
$autoloadCandidates = [
    __DIR__ . '/../../vendor/autoload.php',  // vendor di hrd_system/vendor
    __DIR__ . '/../vendor/autoload.php',     // vendor di hrd/vendor (kalau ada)
];

$autoloadFound = false;
foreach ($autoloadCandidates as $path) {
    if (is_file($path)) {
        require_once $path;
        $autoloadFound = true;
        break;
    }
}

if (!$autoloadFound) {
    http_response_code(500);
    exit('Composer autoload.php tidak ditemukan. Pastikan sudah jalankan "composer require dompdf/dompdf".');
}

use Dompdf\Dompdf;
use Dompdf\Options;

if (session_status() === PHP_SESSION_NONE) session_start();

// Guard helper e() agar tidak bentrok walau config.php juga punya
if (!function_exists('e')) {
  function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

// Wajib ADMIN (DIUBAH DARI HRD)
if (!isset($_SESSION['id_karyawan']) || ($_SESSION['role'] ?? '') !== 'ADMIN') {
  header('Location: ../../index.php');
  exit();
}

// Helper date
function ymd($v){
  if(!$v) return null;
  $t = strtotime($v);
  return $t ? date('Y-m-d', $t) : null;
}

// Ambil input
$fields = [
  'id_karyawan','nama','proyek','posisi','penempatan','sales_code',
  'alamat_penempatan','tgl_pembuatan','no_surat'
];
$data = [];
foreach($fields as $f){ $data[$f] = trim($_POST[$f] ?? ''); }

// Validasi minimal
if (($data['id_karyawan'] ?? '') === '' || ($data['nama'] ?? '') === '' || ($data['no_surat'] ?? '') === '') {
  http_response_code(400);
  echo "Data tidak lengkap (id_karyawan, nama, no_surat wajib).";
  exit;
}

$id_karyawan   = (int)$data['id_karyawan'];
$no_surat      = $data['no_surat'];
$tgl_pembuatan = ymd($data['tgl_pembuatan'] ?: date('Y-m-d'));

// Buat tabel metadata (aman dipanggil berulang)
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

// Upsert metadata (tanpa file)
$up = $conn->prepare("
  INSERT INTO surat_tugas
    (id_karyawan, no_surat, posisi, penempatan, sales_code, alamat_penempatan, tgl_pembuatan)
  VALUES
    (?,?,?,?,?,?,?)
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
$up->execute();
$up->close();

// Teks2 tampilan
$nama      = $data['nama'];
$proyek    = $data['proyek'];
$posisi    = $data['posisi'];
$penempatan = $data['penempatan'];
$sales      = $data['sales_code'];
$alamatHTML = nl2br(e($data['alamat_penempatan']));

$fmtTanggal = function($ymd){
  if(!$ymd) return '-';
  $t = strtotime($ymd);
  // Format manual biar stabil di Windows (lc_time id_ID sering nggak aktif)
  $bulan = [
    1=>'Januari','Februari','Maret','April','Mei','Juni',
    'Juli','Agustus','September','Oktober','November','Desember'
  ];
  $d = (int)date('d',$t);
  $m = (int)date('n',$t);
  $y = (int)date('Y',$t);
  return $d.' '.$bulan[$m].' '.$y;
};
$tanggalDisplay = $fmtTanggal($tgl_pembuatan ?: date('Y-m-d'));

// HTML dokumen
$html = '
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<style>
  @page { margin: 28mm 20mm; }
  body{ font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size:12pt; line-height:1.5; color:#111; }
  .kop{ display:flex; justify-content:space-between; align-items:center; gap:12px; }
  .brand h2{ margin:0; font-size:18pt; }
  .small{ font-size:9pt; color:#444; }
  hr{ border:none; border-top:2px solid #000; margin:10px 0 16px; }
  h3.title{ text-align:center; text-decoration:underline; margin:12px 0 6px; font-size:14pt; }
  table.meta{ border-collapse:collapse; width:100%; margin-top:8px; }
  table.meta td{ padding:6px 8px; vertical-align:top; }
  .label{ width:180px; }
  .ttd{ margin-top:40px; display:flex; justify-content:flex-end; }
  .ttd .box{ text-align:center; min-width:260px; }
</style>
</head>
<body>
  <div class="kop">
    <div class="brand">
      <h2>PT Mandiri Andalan Utama</h2>
      <div class="small">Jl. .................................... Telp: ..................... • Email: .....................</div>
    </div>
    <div class="meta" style="text-align:right; font-size:11pt">
      <div><strong>No: '.e($no_surat).'</strong></div>
      <div>Proyek: '.e($proyek).'</div>
    </div>
  </div>

  <hr>

  <h3 class="title">SURAT TUGAS</h3>
  <p>Yang bertanda tangan di bawah ini, manajemen <strong>PT Mandiri Andalan Utama</strong> menugaskan:</p>

  <table class="meta">
    <tr><td class="label">Nama</td><td>: <strong>'.e($nama).'</strong></td></tr>
    <tr><td class="label">Posisi</td><td>: '.e($posisi).'</td></tr>
    <tr><td class="label">Penempatan</td><td>: '.e($penempatan).'</td></tr>
    <tr><td class="label">Sales Code</td><td>: '.e($sales).'</td></tr>
    <tr><td class="label">Alamat Penempatan</td><td>: '.$alamatHTML.'</td></tr>
    <tr><td class="label">Tanggal Pembuatan</td><td>: '.e($tanggalDisplay).'</td></tr>
  </table>

  <p>Demikian surat tugas ini dibuat untuk dipergunakan sebagaimana mestinya.</p>

  <div class="ttd">
    <div class="box">
      <div>Jakarta, '.e($tanggalDisplay).'</div>
      <div><strong>PT Mandiri Andalan Utama</strong></div>
      <div style="height:72px"></div>
      <div><u><strong>Nama Pemberi Tugas</strong></u></div>
      <div class="small">Jabatan Pemberi Tugas</div>
    </div>
  </div>
</body>
</html>
';

// Konfigurasi Dompdf
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true); // kalau nanti pakai gambar/logo eksternal

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Nama file download
$pdfName = 'surat_tugas_'.preg_replace('/[^A-Za-z0-9_-]+/','-', $no_surat).'.pdf';

// Stream ke browser (Attachment=1 => langsung download)
$dompdf->stream($pdfName, ['Attachment' => true]);

exit;