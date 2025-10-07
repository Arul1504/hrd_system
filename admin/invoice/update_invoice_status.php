<?php
// update_invoice_status.php (enum-safe)
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Akses & CSRF
if (!isset($_SESSION['id_karyawan']) || (($_SESSION['role'] ?? '') !== 'ADMIN')) {
  header('Location: ../login.php'); exit;
}
if (empty($_POST['csrf_token']) || ($_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? ''))) {
  http_response_code(400);
  exit('CSRF token tidak valid.');
}

// Input
$id = (int)($_POST['id'] ?? 0);
$raw = trim((string)($_POST['status_pembayaran'] ?? ''));

// Normalisasi alias ke enum DB
function norm_status($s) {
  $s = strtolower(trim($s));
  switch ($s) {
    case 'selesai':
    case 'lunas':
    case 'sudah dibayar':
      return 'Sudah Dibayar';
    case 'belum bayar':
    case 'belum dibayar':
    case 'proses': // jika ada "Proses", treat as Belum Dibayar
      return 'Belum Dibayar';
    case 'batal':
    case 'cancel':
      return 'Batal';
    default:
      return ''; // invalid
  }
}
$status = norm_status($raw);

$allowed = ['Belum Dibayar','Sudah Dibayar','Batal'];
if ($id <= 0 || !in_array($status, $allowed, true)) {
  http_response_code(400);
  exit('Input tidak valid untuk status_pembayaran.');
}

// Update
$stmt = $conn->prepare("UPDATE invoices SET status_pembayaran = ? WHERE id_invoice = ?");
if (!$stmt) {
  http_response_code(500);
  exit('Gagal menyiapkan query.');
}
$stmt->bind_param("si", $status, $id);
$ok = $stmt->execute();
$err = $stmt->error;
$stmt->close();

$qs = $ok ? 'status_updated=1' : 'status_updated=0&reason='.urlencode($err);
header("Location: invoice.php?$qs");
exit;
