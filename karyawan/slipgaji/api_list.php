<?php
// karyawan/slipgaji/api_list.php
require __DIR__ . '/../../karyawan/config.php'; // atau ../../config.php sesuai struktur kamu
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');

// Wajib karyawan login
if (!isset($_SESSION['id_karyawan']) || (strtoupper($_SESSION['role'] ?? '') !== 'KARYAWAN')) {
  http_response_code(403);
  echo json_encode(['error'=>'unauthorized']); exit;
}

$id_karyawan = (int)$_SESSION['id_karyawan'];
$mode  = $_GET['mode'] ?? '';
$tahun = isset($_GET['tahun']) && $_GET['tahun'] !== '' ? (int)$_GET['tahun'] : null;

if ($mode === 'years') {
  // kembalikan daftar tahun unik milik user
  $stmt = $conn->prepare("SELECT DISTINCT periode_tahun FROM payroll WHERE id_karyawan=? ORDER BY periode_tahun DESC");
  $stmt->bind_param("i",$id_karyawan);
  $stmt->execute();
  $rs = $stmt->get_result();
  $years = [];
  while($row = $rs->fetch_assoc()){ $years[] = (int)$row['periode_tahun']; }
  $stmt->close();
  echo json_encode($years, JSON_UNESCAPED_UNICODE);
  exit;
}

// data slip (opsional filter tahun)
if ($tahun){
  $stmt = $conn->prepare("SELECT id, periode_bulan, periode_tahun, total_pendapatan, total_potongan, total_payroll
                          FROM payroll WHERE id_karyawan=? AND periode_tahun=?
                          ORDER BY periode_tahun DESC, periode_bulan DESC");
  $stmt->bind_param("ii",$id_karyawan,$tahun);
} else {
  $stmt = $conn->prepare("SELECT id, periode_bulan, periode_tahun, total_pendapatan, total_potongan, total_payroll
                          FROM payroll WHERE id_karyawan=?
                          ORDER BY periode_tahun DESC, periode_bulan DESC");
  $stmt->bind_param("i",$id_karyawan);
}
$stmt->execute();
$rs = $stmt->get_result();
$out = $rs->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode($out, JSON_UNESCAPED_UNICODE);
