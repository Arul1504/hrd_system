<?php
// 1. NONAKTIFKAN ERROR REPORTING AGAR TIDAK MERUSAK HEADER PDF
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0); 

// Mulai sesi (diperlukan untuk cek role admin)
session_start();

require '../config.php'; // Sertakan koneksi database
require '../../vendor/autoload.php'; // Ganti dengan path ke autoload Dompdf

use Dompdf\Dompdf;
use Dompdf\Options;

// Pastikan user admin
if (!isset($_SESSION['id_karyawan']) || $_SESSION['role'] !== 'ADMIN') {
    die("Akses ditolak. Hanya Admin yang dapat mencetak dokumen ini.");
}

// 1. Ambil ID Pengajuan
$id_pengajuan = isset($_GET['id']) ? (int)$_GET['id'] : die('ID Pengajuan tidak ditemukan.');

// 2. Ambil Semua Data yang Diperlukan dari Database
$sql = "
    SELECT 
        p.*, k.nama_karyawan, k.proyek, k.nik_ktp, k.jabatan,
        k.nama_bank, k.nomor_rekening
    FROM pengajuan p
    LEFT JOIN karyawan k ON p.id_karyawan = k.id_karyawan
    WHERE p.id_pengajuan = ? AND p.jenis_pengajuan = 'Reimburse'";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_pengajuan);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
$stmt->close();
$conn->close(); // Tutup koneksi setelah selesai ambil data

if (!$data) {
    die("Data pengajuan tidak ditemukan.");
}

// 3. Proses Data Item dari JSON (Defensive Coding)
$json_data = $data['detail_reimburse_json'] ?? '[]';
$reimburse_details = json_decode($json_data, true);

$items_html = '';

// Ambil Grand Total dari kolom baru
$grand_total = floatval($data['nominal_total'] ?? 0); 
$main_category = htmlspecialchars(strtoupper($data['category'] ?? 'UMUM'));
// Ambil Lokasi dari kolom yang diisi saat pengajuan (lokasi di form utama)
$lokasi_disp = htmlspecialchars($data['lokasi'] ?? $data['proyek']); 

if (!is_array($reimburse_details)) {
    $reimburse_details = []; 
}


foreach ($reimburse_details as $item) {
    
    // Ambil data dengan nilai default yang aman
    $deskripsi = htmlspecialchars($item['deskripsi'] ?? 'Deskripsi Kosong');
    $nominal = floatval($item['nominal'] ?? 0);
    $tanggal_transaksi = $item['tanggal_transaksi'] ?? $data['tanggal_mulai'];
    
    // Format tanggal
    $tgl_day = date('d', strtotime($tanggal_transaksi));
    $tgl_month = date('M', strtotime($tanggal_transaksi));
    
    // Baris di tabel
    $items_html .= '
        <tr>
            <td>' . $tgl_day . '</td>
            <td>' . $tgl_month . '</td>
            <td>' . $main_category . '</td> 
            <td>' . $deskripsi . '</td>
            <td>' . $lokasi_disp . '</td>
            <td class="nominal">Rp</td>
            <td class="nominal">' . number_format($nominal, 2, ',', '.') . '</td>
        </tr>';
}

// 4. Susun HTML Template
$html = '
<!DOCTYPE html>
<html>
<head>
    <title>Reimbursement ID ' . $data['id_pengajuan'] . '</title>
    <style>
        /* CSS untuk Dompdf, harus inline atau di dalam tag style */
        body { font-family: Arial, sans-serif; font-size: 10pt; margin: 0; padding: 0; }
        .container { width: 100%; margin: 0 auto; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { padding: 5px; border: 1px solid #000; text-align: left; }
        th { background-color: #eee; text-align: center; }
        h1 { text-align: right; font-size: 16pt; margin-top: 0; }
        .header-box { float: left; width: 60%; }
        .info-box { float: right; width: 35%; }
        .info-box table th, .info-box table td { border: 1px solid #000; padding: 3px; }
        .clear { clear: both; }
        .details-table th { background-color: #a42222ff; color: white; }
        .details-table td:nth-child(1), .details-table td:nth-child(2) { text-align: center; width: 3%; }
        .details-table td.nominal { text-align: right; width: 8%; }
        .grand-total td { font-weight: bold; }
        
        .bank-info table { width: 40%; margin-top: 20px; }
        .bank-info td:first-child { width: 40%; }
        
        .signature-area { margin-top: 50px; }
        .signature-area table { width: 100%; table-layout: fixed; }
        .signature-area th, .signature-area td { height: 70px; vertical-align: bottom; border: 1px solid #000; }
        .signature-area th { height: 20px; background-color: #eee; }
        .signature-details { font-size: 8pt; text-align: center; margin-top: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div style="float: left; font-weight: bold; font-size: 14pt;">PT Mandiri Andalan Utama</div>
        <h1>REIMBURSEMENT</h1>
        
        <div class="clear"></div>
        
        <div style="width: 100%;">
            <div style="float: left; width: 55%; font-size: 8pt;">
                Jl. Sultan Iskandar Muda NO. 30 A-B<br>
                Kebayoran Lama Selatan, Kebayoran Lama<br>
                Jakarta Selatan 12240
            </div>
            <div style="float: right; width: 40%;">
                <table>
                    <tr>
                        <td style="width: 30%; font-weight: bold;">Tanggal</td>
                        <td style="width: 5%;">:</td>
                        <td>' . date('d-M-Y', strtotime($data['tanggal_diajukan'])) . '</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold;">Project</td>
                        <td>:</td>
                        <td>' . htmlspecialchars($data['proyek']) . '</td>
                    </tr>
                </table>
            </div>
        </div>
        <div class="clear" style="margin-bottom: 20px;"></div>

        <table class="details-table">
            <thead>
                <tr>
                    <th colspan="2">Date</th>
                    <th>Category</th>
                    <th>Description</th>
                    <th>Location</th>
                    <th colspan="2">Nominal</th>
                </tr>
            </thead>
            <tbody>
                ' . $items_html . '
                <tr class="grand-total">
                    <td colspan="5" style="text-align: right; border-right: none;">Grand Total</td>
                    <td class="nominal" style="border-left: none;">Rp</td>
                    <td class="nominal">' . number_format($grand_total, 2, ',', '.') . '</td>
                </tr>
            </tbody>
        </table>

        <div class="bank-info">
            <p style="font-weight: bold;">Harap ditransfer kepada:</p>
            <table>
                <tr>
                    <td style="width: 30%;">Nama</td>
                    <td style="width: 70%;">' . htmlspecialchars($data['nama_rekening'] ?? $data['nama_karyawan']) . '</td>
                </tr>
                <tr>
                    <td>Bank</td>
                    <td>' . htmlspecialchars($data['nama_bank'] ?? 'N/A') . '</td>
                </tr>
                <tr>
                    <td>No. Rekening</td>
                    <td>' . htmlspecialchars($data['nomor_rekening'] ?? 'N/A') . '</td>
                </tr>
            </table>
        </div>

        <div class="signature-area">
            <table>
                <thead>
                    <tr>
                        <th>Create.</th>
                        <th>Acknowledge.</th>
                        <th>Approved.</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <div class="signature-details">
                                <p>' . htmlspecialchars($data['nama_karyawan']) . '</p>
                                <p>' . htmlspecialchars($data['jabatan'] ?? 'Karyawan') . '</p>
                            </div>
                        </td>
                        <td>
                            <div class="signature-details">
                                <p>__________________</p>
                                <p>Jabatan</p>
                            </div>
                        </td>
                        <td>
                            <div class="signature-details">
                                <p>Yuli Siswanto</p>
                                <p>Director</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div style="margin-top: 20px; border: 1px solid #000; padding: 10px; width: 40%; font-size: 9pt;">
            <p style="margin: 0; padding-bottom: 5px; font-weight: bold;">Checklist</p>
            <input type="checkbox" style="margin-right: 5px;"> Bukti Transfer<br>
            <input type="checkbox" style="margin-right: 5px;"> Bukti Tayang Iklan<br>
            <input type="checkbox" checked style="margin-right: 5px;"> Bukti Tagihan/Invoice
        </div>
        
    </div>
</body>
</html>';

// 5. Generate dan Stream PDF
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);

// Sesuaikan ukuran kertas
$dompdf->setPaper('A4', 'portrait');

$dompdf->render();

// Output PDF ke browser
$filename = 'Reimburse_' . str_replace('-', '', $data['id_pengajuan']) . '_' . date('Ymd') . '.pdf';
$dompdf->stream($filename, array("Attachment" => true));

exit(0);