<?php
// ===============================
// payslip/process_save_payroll.php
// ===============================
require '../config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['id_karyawan']) || !in_array($_SESSION['role'] ?? '', ['HRD','ADMIN','Admin','admin'])) {
  header("Location: ../../index.php"); exit();
}

function normKey($s){ return preg_replace('/\s+/',' ', trim($s)); }

$id_karyawan   = (int)($_POST['id_karyawan'] ?? 0);
$bulan         = (int)($_POST['bulan'] ?? 0);
$tahun         = (int)($_POST['tahun'] ?? 0);
$komponen_in   = $_POST['komponen'] ?? [];
$tot_pend      = (int)($_POST['total_pendapatan'] ?? 0);
$tot_pot       = (int)($_POST['total_potongan']  ?? 0);
$tot_all       = (int)($_POST['total_payroll']   ?? 0);
$creator       = (int)$_SESSION['id_karyawan'];

if ($id_karyawan<=0 || $bulan<1 || $bulan>12 || $tahun<2000) {
  header("Location: e_payslip_admin.php?status=error"); exit();
}

// Bersihkan & gabungkan key duplikat
$clean = [];
foreach($komponen_in as $k=>$v){
  $k2 = normKey($k);
  $val = (int)$v;
  if (!isset($clean[$k2])) $clean[$k2]=0;
  $clean[$k2] += max(0,$val);
}

// Cek existing
$sql = "SELECT id, components_json FROM payroll WHERE id_karyawan=? AND periode_bulan=? AND periode_tahun=? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iii",$id_karyawan,$bulan,$tahun);
$stmt->execute(); $res = $stmt->get_result();
$exist = $res->fetch_assoc();
$stmt->close();

if ($exist){
  // merge komponen
  $old = json_decode($exist['components_json'] ?? "{}", true) ?: [];
  foreach($clean as $k=>$v){ $old[$k] = (int)($old[$k] ?? 0) + (int)$v; }

  // hitung ulang total
  $POTONGAN = [
    "Total tax (PPh21)","BPJS Kesehatan","BPJS Ketenagakerjaan","Dana Pensiun",
    "Keterlambatan Kehadiran","Potongan Lainnya","Potongan Loan (Mobil/Motor/Lainnya/SPPI)"
  ];
  $pend=0; $pot=0;
  foreach($old as $k=>$v){
    if (in_array($k,$POTONGAN,true)) $pot += (int)$v; else $pend += (int)$v;
  }
  $tot_pend=$pend; $tot_pot=$pot; $tot_all=$pend-$pot;

  $j = json_encode($old, JSON_UNESCAPED_UNICODE);
  $u = $conn->prepare("UPDATE payroll SET components_json=?, total_pendapatan=?, total_potongan=?, total_payroll=?, updated_at=NOW() WHERE id=?");
  $u->bind_param("siiii",$j,$tot_pend,$tot_pot,$tot_all,$exist['id']);
  $u->execute(); $u->close();
  $id = (int)$exist['id'];
} else {
  $j = json_encode($clean, JSON_UNESCAPED_UNICODE);
  $i = $conn->prepare("INSERT INTO payroll (id_karyawan,periode_bulan,periode_tahun,components_json,total_pendapatan,total_potongan,total_payroll,created_by,created_at)
                       VALUES (?,?,?,?,?,?,?, ?, NOW())");
  $i->bind_param("iiisiiii",$id_karyawan,$bulan,$tahun,$j,$tot_pend,$tot_pot,$tot_all,$creator);
  $i->execute(); $id = $i->insert_id; $i->close();
}

$conn->close();
header("Location: e_payslip_admin.php?status=success#id=$id");
