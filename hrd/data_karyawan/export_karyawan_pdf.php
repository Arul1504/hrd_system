<?php
require_once __DIR__ . '/../dompdf/autoload.inc.php';
use Dompdf\Dompdf;

// Inisialisasi Dompdf
$dompdf = new Dompdf();
$dompdf->set_option('isHtml5ParserEnabled', true);

// Koneksi DB
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "hrd";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Ambil data ringkas
// Perbaikan: Ubah nama kolom agar sesuai dengan skema tabel
$sql = "SELECT nik, nama, jenis_kelamin, tanggal_lahir, alamat, no_hp, departemen, jabatan, status_kerja, tanggal_masuk_kerja FROM karyawan";
$result = $conn->query($sql);

// HTML
$html = '
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
  @page { margin: 18mm 10mm; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 10px; margin: 0; }
  .kop {
    text-align: center;
    border-bottom: 3px solid #000;
    padding-bottom: 10px;
    margin-bottom: 20px;
  }
  .kop img { float: left; width: 80px; height: 80px; margin-right: 15px; }
  .kop .judul { font-size: 16px; font-weight: bold; text-transform: uppercase; }
  .kop .alamat { font-size: 11px; margin-top: 5px; }
  table {
    width: 100%;
    border-collapse: collapse;
    font-size: 9px;
  }
  thead { display: table-header-group; }
  th, td {
    border: 1px solid #000;
    padding: 4px;
    text-align: left;
    vertical-align: top;
  }
  th { background: #f2f2f2; }
</style>
</head>
<body>

<div class="kop">
  <img src="../image/manu.png" alt="logo">
  <div>
    <div class="judul">PT Mandiri Andalan Utama</div>
    <div class="alamat">Jl. Sultan Iskandar Muda No.30A 2, RT.2/RW.1, Kby. Lama Sel., Kec. Kby. Lama, Kota Jakarta Selatan, Daerah Khusus Ibukota Jakarta 12240<br>
    Telp: (021) 1234567 | Email: info@mandiriandalanutama.co.id</div>
  </div>
  <div style="clear: both;"></div>
</div>

<h3 style="text-align:center;">Daftar Data Karyawan</h3>

<table>
  <thead>
    <tr>
      <th>NIK</th>
      <th>Nama</th>
      <th>Jenis Kelamin</th>
      <th>Tanggal Lahir</th>
      <th>Alamat</th>
      <th>No HP</th>
      <th>Departemen</th>
      <th>Jabatan</th>
      <th>Status Kerja</th>
      <th>Tgl Masuk</th>
    </tr>
  </thead>
  <tbody>';

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $html .= '<tr>
            <td>'.htmlspecialchars($row['nik']).'</td>
            <td>'.htmlspecialchars($row['nama']).'</td>
            <td>'.htmlspecialchars($row['jenis_kelamin']).'</td>
            <td>'.htmlspecialchars($row['tanggal_lahir']).'</td>
            <td>'.nl2br(htmlspecialchars($row['alamat'])).'</td>
            <td>'.htmlspecialchars($row['no_hp']).'</td>
            <td>'.htmlspecialchars($row['departemen']).'</td>
            <td>'.htmlspecialchars($row['jabatan']).'</td>
            <td>'.htmlspecialchars($row['status_kerja']).'</td>
            <td>'.htmlspecialchars($row['tanggal_masuk_kerja']).'</td>
        </tr>';
    }
} else {
    $html .= '<tr><td colspan="10" style="text-align:center;">Tidak ada data karyawan</td></tr>';
}

$html .= '
  </tbody>
</table>
</body>
</html>
';

// Tutup koneksi
$conn->close();

// Render PDF
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream("data_karyawan.pdf", ["Attachment" => 0]);