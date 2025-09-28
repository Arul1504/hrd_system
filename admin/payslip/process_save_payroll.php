<?php
// ===============================
// payslip/process_save_payroll.php
// ===============================
require '../config.php';

if (session_status() === PHP_SESSION_NONE)
  session_start();

// Cek hak akses
if (!isset($_SESSION['id_karyawan']) || !in_array($_SESSION['role'] ?? '', ['HRD', 'ADMIN', 'Admin', 'admin'])) {
  header("Location: ../../index.php");
  exit();
}

// Definisikan komponen potongan di awal
$POTONGAN = [
  "Biaya Admin",
  "Total tax (PPh21)",
  "BPJS Kesehatan",
  "BPJS Ketenagakerjaan",
  "Dana Pensiun",
  "Keterlambatan Kehadiran",
  "Potongan Lainnya",
  "Potongan Loan (Mobil/Motor/Lainnya/SPPI)"
];

// Ambil dan validasi input
$id_karyawan = (int) ($_POST['id_karyawan'] ?? 0);
$bulan = (int) ($_POST['bulan'] ?? 0);
$tahun = (int) ($_POST['tahun'] ?? 0);

// Ambil nilai total yang sudah dihitung dari JavaScript
$tot_pend = (int) ($_POST['total_pendapatan'] ?? 0);
$tot_pot = (int) ($_POST['total_potongan'] ?? 0);
$tot_all = (int) ($_POST['total_payroll'] ?? 0);

$komponen_in = $_POST['komponen'] ?? [];
$creator = (int) $_SESSION['id_karyawan'];

if ($id_karyawan <= 0 || $bulan < 1 || $bulan > 12 || $tahun < 2000) {
  header("Location: e_payslip_admin.php?status=error&msg=Input tidak valid.");
  exit();
}

// Bersihkan dan filter komponen, pastikan nilainya non-negatif
$clean = [];
foreach ($komponen_in as $k => $v) {
  $val = (int) $v;
  // Hanya simpan komponen yang memiliki nilai > 0
  if ($val > 0) {
    $clean[trim($k)] = $val;
  }
}
$komponen_json = json_encode($clean, JSON_UNESCAPED_UNICODE);

// Cek apakah slip gaji sudah ada
$sql = "SELECT id FROM payroll WHERE id_karyawan = ? AND periode_bulan = ? AND periode_tahun = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $id_karyawan, $bulan, $tahun);
$stmt->execute();
$res = $stmt->get_result();
$exist = $res->fetch_assoc();
$stmt->close();

if ($exist) {
  // Jika ada, lakukan UPDATE
  $update_sql = "UPDATE payroll SET components_json = ?, total_pendapatan = ?, total_potongan = ?, total_payroll = ?, updated_at = NOW() WHERE id = ?";
  $u = $conn->prepare($update_sql);
  $u->bind_param("siiii", $komponen_json, $tot_pend, $tot_pot, $tot_all, $exist['id']);
  $u->execute();
  $u->close();
  $id = (int) $exist['id'];
} else {
  // Jika belum ada, lakukan INSERT
  $insert_sql = "INSERT INTO payroll (id_karyawan, periode_bulan, periode_tahun, components_json, total_pendapatan, total_potongan, total_payroll, created_by, created_at)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
  $i = $conn->prepare($insert_sql);
  $i->bind_param("iiisiiii", $id_karyawan, $bulan, $tahun, $komponen_json, $tot_pend, $tot_pot, $tot_all, $creator);
  $i->execute();
  $id = $i->insert_id;
  $i->close();
}

$conn->close();

// Redirect ke halaman E-Payslip dengan status sukses
header("Location: e_payslip_admin.php?status=success");
exit();
?>