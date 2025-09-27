<?php
// =================================================================
// export_karyawan_nonaktif_pdf.php - Export Data Karyawan Nonaktif ke PDF
// =================================================================

// Tampilkan semua error untuk membantu debugging
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('memory_limit', '256M'); // Tambah batas memori untuk data besar

// Pastikan path ke Dompdf benar
require_once __DIR__ . '/../dompdf/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// --- PERIKSA HAK AKSES ---
// Pastikan hanya admin yang bisa mengakses
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['id_karyawan']) || (($_SESSION['role'] ?? '') !== 'ADMIN')) {
    http_response_code(403);
    die("Akses ditolak. Silakan login sebagai administrator.");
}

// Koneksi DB
require_once __DIR__ . '/../config.php';

// Ambil parameter filter dari URL
$search_query   = $_GET['search']   ?? '';
$filter_dept    = $_GET['departemen'] ?? '';
$filter_jabatan = $_GET['jabatan']  ?? '';

// --- Fungsi Helper untuk label kolom ---
function ucLabel(string $raw): string {
    $t = str_replace('_', ' ', strtolower($raw));
    $t = ucwords($t);
    return str_replace(['Nik', 'Bpjamsostek', 'Umk', 'Ump', 'Pks', 'Id', 'Npwp', 'Nip', 'Hp'], ['NIK', 'BPJamsostek', 'UMK', 'UMP', 'PKS', 'ID', 'NPWP', 'NIP', 'HP'], $t);
}

// Kolom untuk tabel data nonaktif
$display_columns = [
    "nik", "nama", "jabatan", "departemen", "tanggal_akhir_kontrak", "tgl_resign"
];

// --- Query data ---
$sql_select = "
    SELECT
        COALESCE(NULLIF(k.nik_karyawan,''), NULLIF(k.nik_ktp,'')) AS nik,
        k.nama_karyawan AS nama,
        k.jabatan,
        COALESCE(NULLIF(k.cabang,''), NULLIF(k.penempatan,''), NULLIF(k.area,'')) AS departemen,
        COALESCE(k.end_of_contract, k.end_date_pks, k.end_date) AS tanggal_akhir_kontrak,
        k.tgl_resign
    FROM karyawan k
    WHERE UPPER(k.status) = 'TIDAK AKTIF'
";

$params = [];
$types = "";

if ($search_query !== '') {
    $sql_select .= " AND (k.nama_karyawan LIKE ? OR k.nik_karyawan LIKE ? OR k.nik_ktp LIKE ?)";
    $like = "%{$search_query}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sss";
}
if ($filter_dept !== '') {
    $sql_select .= " AND COALESCE(k.cabang, k.penempatan, k.area) = ?";
    $params[] = $filter_dept;
    $types .= "s";
}
if ($filter_jabatan !== '') {
    $sql_select .= " AND k.jabatan = ?";
    $params[] = $filter_jabatan;
    $types .= "s";
}
$sql_select .= " ORDER BY nama ASC";

$stmt = $conn->prepare($sql_select);
if (!$stmt) {
    die("Error preparing statement: " . htmlspecialchars($conn->error));
}

if (!empty($params)) {
    // Penggunaan `...` (spread operator) lebih bersih dari `call_user_func_array`
    $stmt->bind_param($types, ...$params);
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
  <img src="' . __DIR__ . '/../image/manu.png" alt="logo">
  <div>
    <div class="judul">PT Mandiri Andalan Utama</div>
    <div class="alamat">Jl. Sultan Iskandar Muda No.30A 2, RT.2/RW.1, Kby. Lama Sel., Kec. Kby. Lama, Kota Jakarta Selatan, Daerah Khusus Ibukota Jakarta 12240<br>
    Telp: (021) 1234567 | Email: info@mandiriandalanutama.co.id</div>
  </div>
  <div style="clear: both;"></div>
</div>

<h3 style="text-align:center;">Daftar Data Karyawan Nonaktif</h3>

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
            // Format tanggal jika kolomnya berisi tanggal
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
    $html .= '<tr><td colspan="' . count($display_columns) . '" style="text-align:center;">Tidak ada data karyawan nonaktif yang ditemukan.</td></tr>';
}

$html .= '
  </tbody>
</table>
</body>
</html>
';

// Render PDF dan stream ke browser
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream("data_karyawan_nonaktif.pdf", ["Attachment" => 0]);
?>