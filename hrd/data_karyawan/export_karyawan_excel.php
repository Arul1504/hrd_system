<?php
require_once __DIR__ . '/../PHPExcel/Classes/PHPExcel.php';

// Koneksi database
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "db_hrd2"; // Nama database tetap
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Query data
// Perbaikan: Ubah nama kolom agar sesuai dengan skema tabel
$sql = "SELECT nik, nama, jenis_kelamin, alamat, no_hp, jabatan, departemen, tanggal_masuk_kerja 
        FROM karyawan";
$result = $conn->query($sql);

// Buat object PHPExcel
$objPHPExcel = new PHPExcel();
$objPHPExcel->getProperties()
    ->setCreator("HRD System")
    ->setTitle("Data Karyawan")
    ->setDescription("Export Data Karyawan PT Mandiri Andalan Utama");

// Aktifkan sheet pertama
$sheet = $objPHPExcel->setActiveSheetIndex(0);

// Judul di atas
$sheet->setCellValue('A1', 'PT MANDIRI ANDALAN UTAMA');
$sheet->mergeCells('A1:H1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);

$sheet->setCellValue('A2', 'Data Karyawan');
$sheet->mergeCells('A2:H2');
$sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
$sheet->getStyle('A2')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);

// Header tabel
// Perbaikan: Ubah header agar sesuai dengan nama kolom baru
$header = ['NIK', 'Nama', 'Jenis Kelamin', 'Alamat', 'No HP', 'Jabatan', 'Departemen', 'Tanggal Masuk'];
$col = 'A';
$rowHeader = 4;
foreach ($header as $h) {
    $sheet->setCellValue($col . $rowHeader, $h);
    $sheet->getStyle($col . $rowHeader)->getFont()->setBold(true);
    $sheet->getStyle($col . $rowHeader)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle($col . $rowHeader)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)
          ->getStartColor()->setRGB('D9E1F2'); // warna biru muda
    $col++;
}

// Isi data
$row = 5;
if ($result && $result->num_rows > 0) {
    while ($r = $result->fetch_assoc()) {
        // Perbaikan: Sesuaikan nama kolom yang diambil dari database
        $sheet->setCellValue("A$row", $r['nik']);
        $sheet->setCellValue("B$row", $r['nama']);
        $sheet->setCellValue("C$row", $r['jenis_kelamin']);
        $sheet->setCellValue("D$row", $r['alamat']);
        $sheet->setCellValue("E$row", $r['no_hp']);
        $sheet->setCellValue("F$row", $r['jabatan']);
        $sheet->setCellValue("G$row", $r['departemen']);
        $sheet->setCellValue("H$row", $r['tanggal_masuk_kerja']);
        $row++;
    }
} else {
    $sheet->setCellValue("A$row", "Tidak ada data karyawan");
    $sheet->mergeCells("A$row:H$row");
    $sheet->getStyle("A$row")->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
}

// Auto width kolom
foreach (range('A', 'H') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Border tabel
$lastRow = $row - 1;
$sheet->getStyle("A4:H$lastRow")->getBorders()->getAllBorders()->setBorderStyle(PHPExcel_Style_Border::BORDER_THIN);

// Nama sheet
$sheet->setTitle("Data Karyawan");

// Output ke Excel 2007 (xlsx)
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="data_karyawan.xlsx"');
header('Cache-Control: max-age=0');

$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
$objWriter->save('php://output');
exit;