<?php
// ===========================
// hrd/slipgaji/api_list.php
// ===========================

session_start();

// Wajib login sebagai HRD atau ADMIN
if (!isset($_SESSION['id_karyawan']) || !in_array($_SESSION['role'] ?? '', ['HRD','ADMIN'])) {
  http_response_code(401);
  echo json_encode(["status" => "error", "message" => "Unauthorized"]);
  exit();
}

require __DIR__ . '/../config.php';

$id_karyawan   = isset($_GET['id_karyawan']) ? (int)$_GET['id_karyawan'] : 0;
$filter_tahun  = isset($_GET['year']) && ctype_digit($_GET['year']) ? (int)$_GET['year'] : 0;

if ($id_karyawan <= 0) {
  http_response_code(400);
  echo json_encode(["status" => "error", "message" => "Parameter id_karyawan wajib diisi"]);
  exit();
}

// Query slip gaji
if ($filter_tahun > 0) {
  $q = $conn->prepare("SELECT * FROM payroll WHERE id_karyawan=? AND periode_tahun=? ORDER BY periode_tahun DESC, periode_bulan DESC");
  $q->bind_param("ii",$id_karyawan,$filter_tahun);
} else {
  $q = $conn->prepare("SELECT * FROM payroll WHERE id_karyawan=? ORDER BY periode_tahun DESC, periode_bulan DESC");
  $q->bind_param("i",$id_karyawan);
}
$q->execute();
$res = $q->get_result();
$slips = $res->fetch_all(MYSQLI_ASSOC);
$q->close();

// Ambil nama karyawan
$nama = "";
$stmt = $conn->prepare("SELECT nama_karyawan FROM karyawan WHERE id_karyawan=? LIMIT 1");
$stmt->bind_param("i", $id_karyawan);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
if($r) $nama = $r['nama_karyawan'];
$stmt->close();

$conn->close();

// Helper nama bulan
function bulanNama($b){
  $bulan = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
  $b = (int)$b; return $bulan[$b] ?? $b;
}

// Format data
$output = [];
foreach($slips as $s){
  $output[] = [
    "id"              => (int)$s['id'],
    "id_karyawan"     => (int)$s['id_karyawan'],
    "nama_karyawan"   => $nama,
    "periode"         => bulanNama($s['periode_bulan']).' '.$s['periode_tahun'],
    "periode_bulan"   => (int)$s['periode_bulan'],
    "periode_tahun"   => (int)$s['periode_tahun'],
    "total_pendapatan"=> (int)$s['total_pendapatan'],
    "total_potongan"  => (int)$s['total_potongan'],
    "total_payroll"   => (int)$s['total_payroll'],
    "pdf_url"         => "../../admin/payslip/export_payroll_pdf.php?id=".(int)$s['id']
  ];
}

// Response JSON
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
  "status" => "success",
  "count" => count($output),
  "data" => $output
], JSON_PRETTY_PRINT);
