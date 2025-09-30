<?php
require '../config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['id_karyawan']) || $_SESSION['role'] !== 'ADMIN') {
    header("Location: ../../index.php");
    exit();
}

require '../../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : date('n');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date('Y');

// Query data absensi bulan tsb
$sql = "
    SELECT 
        k.nik_ktp, k.nama_karyawan, k.jabatan,
        a.tanggal, a.alamat_masuk, a.jam_masuk, a.alamat_pulang, a.jam_pulang, a.status_absensi
    FROM karyawan k
    LEFT JOIN absensi a 
        ON k.id_karyawan = a.id_karyawan 
        AND MONTH(a.tanggal) = ? AND YEAR(a.tanggal) = ?
    WHERE k.proyek = 'INTERNAL'
    ORDER BY a.tanggal ASC, k.nama_karyawan ASC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $bulan, $tahun);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// hitung total hadir & terlambat
$total_hadir = 0;
$total_terlambat = 0;
foreach ($data as $row) {
    if (!empty($row['jam_masuk'])) {
        $total_hadir++;
        if (strtotime($row['jam_masuk']) > strtotime('08:00:00')) {
            $total_terlambat++;
        }
    }
}

// Buat Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$headers = ['Tanggal','NIK','Nama','Jabatan','Alamat Masuk','Jam Masuk','Alamat Pulang','Jam Pulang','Status','Jumlah Hadir','Jumlah Terlambat'];
$sheet->fromArray($headers, NULL, 'A1');

// Isi data
$rowNum = 2;
foreach ($data as $row) {
    $sheet->setCellValue("A$rowNum", $row['tanggal'] ? date('d-m-Y', strtotime($row['tanggal'])) : '-');
    $sheet->setCellValue("B$rowNum", $row['nik_ktp']);
    $sheet->setCellValue("C$rowNum", $row['nama_karyawan']);
    $sheet->setCellValue("D$rowNum", $row['jabatan']);
    $sheet->setCellValue("E$rowNum", $row['alamat_masuk']);
    $sheet->setCellValue("F$rowNum", $row['jam_masuk']);
    $sheet->setCellValue("G$rowNum", $row['alamat_pulang']);
    $sheet->setCellValue("H$rowNum", $row['jam_pulang']);
    $sheet->setCellValue("I$rowNum", $row['status_absensi'] ?: '-');
    $sheet->setCellValue("J$rowNum", $total_hadir);
    $sheet->setCellValue("K$rowNum", $total_terlambat);
    $rowNum++;
}

// Auto-size
foreach (range('A','K') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

$filename = "Rekap_Absensi_{$bulan}_{$tahun}.xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment;filename=\"$filename\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
