<?php
// ===========================
// payslip/get_payroll_id.php
// ===========================
require '../config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_karyawan']) || !in_array($_SESSION['role'] ?? '', ['HRD','ADMIN','Admin','admin'])) {
  echo json_encode(['error' => 'unauthorized']); exit;
}

$id_karyawan = (int)($_GET['id_karyawan'] ?? 0);
$bulan = (int)($_GET['bulan'] ?? 0);
$tahun = (int)($_GET['tahun'] ?? 0);

$out = ['id' => null];

if ($id_karyawan>0 && $bulan>=1 && $bulan<=12 && $tahun>=2000){
  $q = $conn->prepare("SELECT id FROM payroll WHERE id_karyawan=? AND periode_bulan=? AND periode_tahun=? LIMIT 1");
  $q->bind_param("iii",$id_karyawan,$bulan,$tahun);
  $q->execute();
  $r = $q->get_result()->fetch_assoc();
  if ($r) $out['id'] = (int)$r['id'];
  $q->close();
}
$conn->close();
echo json_encode($out, JSON_UNESCAPED_UNICODE);
