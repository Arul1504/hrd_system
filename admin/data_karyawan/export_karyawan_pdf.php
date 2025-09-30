<?php
// ==================================================
// export_karyawan_pdf.php - Export Data Karyawan ke PDF (Perbaikan Final)
// ==================================================

// PERBAIKAN: Gunakan autoloader yang benar dari direktori vendor Dompdf.
// Sesuaikan path ini agar menunjuk ke lokasi file 'vendor/autoload.php' di dalam folder dompdf.
require_once __DIR__ . '/../dompdf/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// --- PERIKSA HAK AKSES ---


// Koneksi DB (menggunakan config.php yang sudah ada)
require_once __DIR__ . '/../config.php';

// Ambil parameter filter dari URL
$proyek_filter = $_GET['proyek'] ?? '';
$search_query = $_GET['search'] ?? '';
$jabatan_filter = $_GET['jabatan'] ?? '';
$status_karyawan_filter = $_GET['status_karyawan'] ?? '';
$status_aktif_filter = $_GET['status_aktif'] ?? '';

// --- Tentukan kolom yang akan ditampilkan ---
function ucLabel(string $raw): string {
    $t = str_replace('_', ' ', strtolower($raw));
    $t = ucwords($t);
    return str_replace(['Nik', 'Bpjs', 'Bpjamsostek', 'Umk', 'Ump', 'Pks', 'Id', 'Npwp', 'Nip'], ['NIK', 'BPJS', 'BPJamsostek', 'UMK', 'UMP', 'PKS', 'ID', 'NPWP', 'NIP'], $t);
}

// Kolom default jika tidak ada filter proyek yang spesifik
$default_columns = [
    "nik_ktp", "nama_karyawan", "jabatan", "proyek", "status_karyawan", "status", "join_date", "tgl_resign"
];
$display_columns = $default_columns;

// --- Query data ---
$sql_select = "SELECT " . implode(', ', array_map(function($col) { return "`" . $col . "`"; }, $display_columns)) . " FROM karyawan WHERE 1=1";
$params = [];
$types = "";

if ($proyek_filter) {
    $sql_select .= " AND proyek = ?";
    $params[] = &$proyek_filter;
    $types .= "s";
}
if ($search_query) {
    $sql_select .= " AND (nama_karyawan LIKE ? OR nik_karyawan LIKE ?)";
    $like = "%{$search_query}%";
    $params[] = &$like;
    $params[] = &$like;
    $types .= "ss";
}
if ($jabatan_filter) {
    $sql_select .= " AND jabatan = ?";
    $params[] = &$jabatan_filter;
    $types .= "s";
}
if ($status_karyawan_filter) {
    $sql_select .= " AND status_karyawan = ?";
    $params[] = &$status_karyawan_filter;
    $types .= "s";
}
if ($status_aktif_filter) {
    $sql_select .= " AND status = ?";
    $params[] = &$status_aktif_filter;
    $types .= "s";
} else {
    $sql_select .= " AND (status IS NULL OR status = '' OR UPPER(status) <> 'TIDAK AKTIF')";
}

$sql_select .= " ORDER BY nama_karyawan ASC";

$stmt = $conn->prepare($sql_select);
if (!$stmt) {
    die("Error preparing statement: " . $conn->error);
}

if (!empty($params)) {
    call_user_func_array([$stmt, 'bind_param'], array_merge([$types], $params));
}
$stmt->execute();
$result = $stmt->get_result();
$employees = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

// --- Inisialisasi Dompdf ---
$options = new Options();
$options->set('isHtml5ParserEnabled', true);

$dompdf = new Dompdf($options);

// --- HTML untuk PDF ---
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

<h3 style="text-align:center;">Daftar Data Karyawan' . (!empty($proyek_filter) ? ' Proyek ' . $proyek_filter : '') . '</h3>

<table>
  <thead>
    <tr>';
      foreach ($display_columns as $col) {
          $html .= '<th>' . ucLabel($col) . '</th>';
      }
$html .= '
    </tr>
  </thead>
  <tbody>';

if (!empty($employees)) {
    foreach ($employees as $row) {
        $html .= '<tr>';
        foreach ($display_columns as $col) {
            $value = $row[$col] ?? '-';
            if (strpos($col, 'tanggal') !== false || strpos($col, 'date') !== false || strpos($col, 'tgl') !== false) {
                if (!empty($value) && $value != '0000-00-00') {
                    $value = date('d F Y', strtotime($value));
                }
            }
            $html .= '<td>'.htmlspecialchars($value).'</td>';
        }
        $html .= '</tr>';
    }
} else {
    $html .= '<tr><td colspan="' . count($display_columns) . '" style="text-align:center;">Tidak ada data karyawan</td></tr>';
}

$html .= '
  </tbody>
</table>
</body>
</html>
';

// Render PDF
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream("data_karyawan.pdf", ["Attachment" => 0]);
?>