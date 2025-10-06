<?php
// ===========================
// payslip/export_payroll_pdf.php
// ===========================
session_start();
require '../config.php';

// Ambil ID slip gaji yang diminta dan data user yang login
$id_slip = (int)($_GET['id'] ?? 0);
$user_id = $_SESSION['id_karyawan'] ?? 0;
$user_role = strtoupper($_SESSION['role'] ?? '');

// 1. Cek apakah pengguna login
if ($user_id === 0) {
    exit('Unauthorized: Sesi Kedaluwarsa');
}

// 2. Query untuk mengambil data slip gaji, dan juga ID Karyawan pemiliknya
$q = $conn->prepare("SELECT p.*, k.nama_karyawan, k.nik_ktp, k.jabatan, k.proyek, k.status_karyawan, k.cabang, k.nomor_rekening, k.nama_bank, k.npwp, p.id_karyawan
                     FROM payroll p 
                     JOIN karyawan k ON k.id_karyawan=p.id_karyawan
                     WHERE p.id=? LIMIT 1");
$q->bind_param("i", $id_slip); 
$q->execute(); 
$r = $q->get_result()->fetch_assoc(); 
$q->close();

if (!$r) {
    exit('Slip tidak ditemukan');
}

// 3. LOGIKA OTORISASI BARU
$is_admin = in_array($user_role, ['HRD', 'ADMIN']);
$is_owner = $user_id === (int)$r['id_karyawan'];

if (!$is_admin && !$is_owner) {
    // Jika bukan Admin/HRD DAN bukan pemilik slip, tolak akses.
    http_response_code(401);
    exit('Unauthorized: Anda tidak memiliki akses ke slip gaji ini.');
}

// Data slip sudah ada di $r, lanjutkan dengan DOMPDF
$comp = json_decode($r['components_json'],true) ?: [];
$conn->close();

// Format bulan dan tahun
$periode = date('F Y', strtotime($r['periode_tahun'].'-'.$r['periode_bulan'].'-01'));

// --- HTML tampilan view_payroll (copy dari view_payroll.php) ---
ob_start();
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Slip Gaji</title>
<style>
/* ... (Style CSS Anda tetap di sini) ... */
</style>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body>
<div class="slip">
<?php
$html = ob_get_clean();

// --- Load DOMPDF ---
require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;


$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4','portrait');
$dompdf->render();

// Nama file
$filename = 'slip_gaji_'.$r['nama_karyawan'].'_'.$r['periode_bulan'].'_'.$r['periode_tahun'].'.pdf';
$dompdf->stream($filename, ["Attachment"=>1]);
exit;