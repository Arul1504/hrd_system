<?php
// ==================================================
// export_karyawan_excel.php - Export Data Karyawan ke Excel (Dinamis dengan PhpSpreadsheet)
// ==================================================
require '../../vendor/autoload.php'; // sesuaikan path ke vendor/autoload.php

require_once __DIR__ . '/../PhpSpreadsheet/Autoload.php';
require_once __DIR__ . '/../config.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

// --- DAFTAR LENGKAP SEMUA FIELDS DARI TABEL KARYAWAN ---
// Definisi ini HARUS sesuai dengan semua kolom yang ada di tabel 'karyawan' Anda.
const ALL_FIELDS = [
    "id_karyawan", "nama_karyawan", "jabatan", "jenis_kelamin", "tempat_lahir", "tanggal_lahir", 
    "alamat", "alamat_tinggal", "rt_rw", "kelurahan", "kecamatan", "kota_kabupaten", "nik_ktp", 
    "pendidikan_terakhir", "no_hp", "alamat_email", "no_kk", "nama_ayah", "nama_ibu", 
    "nik_karyawan", "nip", "penempatan", "nama_user", "kota", "area", "nomor_kontrak", 
    "tanggal_pembuatan_pks", "nomor_surat_tugas", "masa_penugasan", "tgl_aktif_masuk", 
    "join_date", "end_date", "end_date_pks", "end_of_contract", "status_karyawan", "status", 
    "tgl_resign", "cabang", "job", "channel", "tgl_rmu", "nomor_rekening", "nama_bank", 
    "gapok", "umk_ump", "tanggal_pernyataan", "npwp", "status_pajak", "recruitment_officer", 
    "team_leader", "recruiter", "tl", "manager", "nama_sm", "nama_sh", "sales_code", 
    "nomor_reff", "no_bpjamsostek", "no_bpjs_kes", "role", "proyek", "surat_tugas", 
    "sub_project_cnaf", "no", "tanggal_pembuatan", "spr_bro", "nama_bm_sm", "nama_tl", 
    "level", "tanggal_pkm", "nama_sm_cm", "sbi", "tanggal_sign_kontrak", "nama_oh", 
    "jabatan_sebelumnya", "nama_bm", "allowance", "tunjangan_kesehatan", "om", "nama_cm"
];

// Daftar field per proyek (untuk mode filter)
const PROJECT_FIELDS = [
    // Jika Anda ingin semua proyek menampilkan SEMUA fields, ganti isinya dengan ALL_FIELDS
    "CIMB" => [
        "nama_karyawan","cabang","nomor_kontrak","tanggal_pembuatan_pks","tempat_lahir","tanggal_lahir","alamat","rt_rw","kelurahan","kecamatan","kota_kabupaten","nik_ktp","pendidikan_terakhir","no_hp","jabatan","alamat_email",
        "nama_sm","nama_sh","job","channel","tgl_rmu",
        "jenis_kelamin","no_kk","nama_ayah","nama_ibu","team_leader","recruiter","sales_code","join_date","status_karyawan","nomor_rekening","nama_bank","status","tgl_resign"
    ],
    "NOBU" => [
        "nama_karyawan","cabang","nomor_kontrak","tanggal_pembuatan_pks","tempat_lahir","tanggal_lahir","alamat","rt_rw","kelurahan","kecamatan","kota_kabupaten","nik_ktp","pendidikan_terakhir","no_hp","jabatan","tgl_aktif_masuk","alamat_email","jenis_kelamin","no_kk","nama_ayah","nama_ibu","team_leader","recruiter","sales_code","join_date","status_karyawan","nomor_rekening","nama_bank","status","tgl_resign"
    ],
    "MOLADIN" => [
        "nama_karyawan","jabatan","cabang","nomor_kontrak","tanggal_pembuatan_pks","tempat_lahir","tanggal_lahir","alamat","rt_rw","kelurahan","kecamatan","kota_kabupaten","nik_ktp","pendidikan_terakhir","no_hp","alamat_email","jenis_kelamin","no_kk","nama_ayah","nama_ibu","team_leader","recruiter","sales_code","join_date","status_karyawan","nomor_rekening","nama_bank","status","tgl_resign"
    ],
    "ALLO" => [
        "nama_karyawan","jabatan","penempatan","kota","area","nomor_kontrak","tanggal_pembuatan_pks","tempat_lahir","tanggal_lahir","alamat","rt_rw","kelurahan","kecamatan","kota_kabupaten","nik_ktp","pendidikan_terakhir","no_hp","alamat_email","jenis_kelamin","no_kk","nama_ayah","nama_ibu","recruitment_officer","team_leader","join_date","status_karyawan","nomor_rekening","nama_bank","status","tgl_resign"
    ],
    "CNAF" => [
        "nama_karyawan","cabang","nomor_kontrak","tanggal_pembuatan_pks","tempat_lahir","tanggal_lahir","alamat","rt_rw","kelurahan","kecamatan","kota_kabupaten","nik_ktp","pendidikan_terakhir","no_hp","jabatan","join_date","end_date","end_date_pks","umk_ump","jenis_kelamin","alamat_email","alamat_tinggal","npwp","status_pajak","no_kk","nama_ayah","nama_ibu","recruiter","team_leader","nik_karyawan","status","nomor_rekening","nama_bank","no_bpjamsostek","no_bpjs_kes","end_of_contract"
    ],
    "BNIF" => [
        "nama_karyawan","cabang","nomor_kontrak","tanggal_pembuatan_pks","tempat_lahir","tanggal_lahir","alamat","rt_rw","kelurahan","kecamatan","kota_kabupaten","nik_ktp","pendidikan_terakhir","no_hp","jabatan","join_date","end_date","end_date_pks","umk_ump","jenis_kelamin","alamat_email","alamat_tinggal","nip","npwp","status_pajak","no_kk","nama_ayah","nama_ibu","recruiter","team_leader","nik_karyawan","status","nomor_rekening","nama_bank","no_bpjamsostek","no_bpjs_kes","end_of_contract"
    ],
    "SMBCI" => [
        "nomor_kontrak","tanggal_pembuatan_pks","tanggal_lahir","nama_karyawan","tempat_lahir","jabatan","nik_ktp","alamat","rt_rw","kelurahan","kecamatan","kota","no_hp","pendidikan_terakhir","nama_user","penempatan","join_date","end_date","umk_ump","tanggal_pernyataan","nik_karyawan","nomor_surat_tugas","masa_penugasan","alamat_email","nomor_reff","jenis_kelamin","npwp","status_pajak","no_kk","nama_ayah","nama_ibu","recruitment_officer","team_leader","status","nomor_rekening","nama_bank","no_bpjamsostek","no_bpjs_kes","end_of_contract"
    ],
    "INTERNAL" => [
        "nama_karyawan","cabang","nomor_kontrak","tanggal_pembuatan_pks","tempat_lahir","tanggal_lahir","alamat","rt_rw","kelurahan","kecamatan","kota_kabupaten","nik_ktp","pendidikan_terakhir","no_hp","jabatan","join_date","end_date","gapok","jenis_kelamin","alamat_email","alamat_tinggal","tl","manager","npwp","status_pajak","no_kk","nama_ayah","nama_ibu","status","nomor_rekening","nama_bank","end_of_contract","role"
    ]
];

// Fungsi bikin label header rapi
function ucLabel(string $raw): string {
    $t = str_replace('_', ' ', strtolower($raw));
    $t = ucwords($t);
    return str_replace(
        ['Nik', 'Bpjs', 'Bpjamsostek', 'Umk', 'Ump', 'Pks', 'Id', 'Npwp', 'Nip', 'Rmu', 'Tl', 'Cm', 'Sm', 'Sh', 'Oh', 'Sbi', 'Pkm'],
        ['NIK', 'BPJS', 'BPJamsostek', 'UMK', 'UMP', 'PKS', 'ID', 'NPWP', 'NIP', 'RMU', 'TL', 'CM', 'SM', 'SH', 'OH', 'SBI', 'PKM'],
        $t
    );
}

// Ambil parameter proyek
$proyek = strtoupper($_GET['proyek'] ?? '');

// Tentukan kolom yang diexport
$columns_to_fetch = [];
if (!empty($proyek) && isset(PROJECT_FIELDS[$proyek])) {
    // Mode Filter Proyek: Gunakan field spesifik proyek
    $columns_to_fetch = array_merge(['id_karyawan', 'proyek'], PROJECT_FIELDS[$proyek]);
} else {
    // Mode TANPA Filter (ATAU filter tidak valid): Gunakan SEMUA fields
    $columns_to_fetch = ALL_FIELDS;
}
$columns_to_fetch = array_unique($columns_to_fetch);

// Hapus id_karyawan dan proyek dari header Excel, tapi tetap di SQL
$sql_select = implode(', ', array_map(function($col) { return "`" . $col . "`"; }, $columns_to_fetch));
$excel_headers = array_map('ucLabel', array_diff($columns_to_fetch, ['id_karyawan', 'proyek']));

$sql = "SELECT $sql_select FROM karyawan WHERE 1=1";
$params = [];
$types = "";

if (!empty($proyek)) {
    $sql .= " AND `proyek` = ?";
    $params[] = $proyek;
    $types .= "s";
}

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Error preparing statement: " . $conn->error);
}
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Buat object Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Judul perusahaan
$sheet->setCellValue('A1', 'PT MANDIRI ANDALAN UTAMA');
$sheet->mergeCells('A1:' . Coordinate::stringFromColumnIndex(count($excel_headers)) . '1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Subjudul proyek
$sheet->setCellValue('A2', 'Data Karyawan ' . ($proyek ?: 'Semua Proyek'));
$sheet->mergeCells('A2:' . Coordinate::stringFromColumnIndex(count($excel_headers)) . '2');
$sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Header tabel
$rowHeader = 4;
$colIndex = 1;
foreach ($excel_headers as $h) {
    $cell = Coordinate::stringFromColumnIndex($colIndex) . $rowHeader;
    $sheet->setCellValue($cell, $h);
    $sheet->getStyle($cell)->getFont()->setBold(true);
    $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)
             ->getStartColor()->setRGB('D9E1F2');
    $colIndex++;
}

// Isi data
$row = 5;
if ($result && $result->num_rows > 0) {
    while ($r = $result->fetch_assoc()) {
        $colIndex = 1;
        // Kita gunakan $columns_to_fetch sebagai daftar kolom dari DB
        foreach ($columns_to_fetch as $db_col) {
            // Kita lewati kolom 'id_karyawan' dan 'proyek' dari output Excel
            if ($db_col === 'id_karyawan' || $db_col === 'proyek') continue;
            
            $value = $r[$db_col] ?? '';
            // Logika format tanggal
            if (preg_match('/tanggal|date|tgl/i', $db_col)) {
                if (!empty($value) && $value != '0000-00-00') {
                    $value = date('d-m-Y', strtotime($value));
                } else {
                    $value = '';
                }
            }
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex) . $row, $value);
            $colIndex++;
        }
        $row++;
    }
} else {
    $sheet->setCellValue('A' . $row, "Tidak ada data karyawan");
    $sheet->mergeCells('A' . $row . ':' . Coordinate::stringFromColumnIndex(count($excel_headers)) . $row);
    $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
}

// Auto width
for ($i = 1; $i <= count($excel_headers); $i++) {
    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
}

// Border tabel
$lastRow = $row - 1;
$sheet->getStyle('A4:' . Coordinate::stringFromColumnIndex(count($excel_headers)) . $lastRow)
    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// Nama sheet
$sheet->setTitle("Data Karyawan");

// Output ke Excel 2007 (xlsx)
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="data_karyawan_'.($proyek ?: 'semua_proyek').'.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;