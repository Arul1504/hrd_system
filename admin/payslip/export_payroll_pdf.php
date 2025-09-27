<?php
// ===========================
// payslip/export_payroll_pdf.php
// ===========================
require '../config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Akses hanya HRD/ADMIN
if (!isset($_SESSION['id_karyawan']) || !in_array($_SESSION['role'] ?? '', ['HRD','ADMIN','Admin','admin'])) {
  exit('Unauthorized');
}

$id = (int)($_GET['id'] ?? 0);
$q = $conn->prepare("SELECT p.*, k.nama_karyawan, k.nik_ktp, k.jabatan, k.proyek, k.status_karyawan, k.cabang, k.nomor_rekening, k.nama_bank, k.npwp
                     FROM payroll p JOIN karyawan k ON k.id_karyawan=p.id_karyawan
                     WHERE p.id=? LIMIT 1");
$q->bind_param("i",$id); 
$q->execute(); 
$r = $q->get_result()->fetch_assoc(); 
$q->close();
if (!$r) exit('Slip tidak ditemukan');
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
  body{font-family:'Poppins',Arial,Helvetica,sans-serif;background:#f5f6fa;margin:0;padding:20px}
  .slip{max-width:800px;margin:auto;background:#fff;border-radius:10px;padding:25px;box-shadow:0 2px 6px rgba(0,0,0,0.1)}
  .header{display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid #eee;padding-bottom:10px;margin-bottom:20px}
  .header h1{margin:0;font-size:22px;color:#333;display:flex;align-items:center}
  .header h1:before{content:"";display:inline-block;width:30px;height:30px;background:#d00;border-radius:6px;margin-right:10px}
  .company{text-align:right;font-size:14px;color:#555}
  .company-info { text-align: right; font-size: 0.8rem; color: #555; line-height: 1.4; }
  .company-name-bold { font-weight: 600; font-size: 1rem; margin-bottom: 5px; }
  .info{display:flex;justify-content:space-between;font-size:14px;margin-bottom:20px}
  .info b{color:#333}
  .box-container{display:flex;gap:20px}
  .box{flex:1;background:#fafafa;padding:15px;border-radius:10px}
  .box h3{margin:0 0 10px;font-size:16px;color:#333;border-bottom:1px solid #eee;padding-bottom:5px}
  .row{display:flex;justify-content:space-between;margin:5px 0;font-size:14px}
  .total{font-weight:bold;border-top:1px solid #ccc;padding-top:8px;margin-top:8px}
  .thp{text-align:center;margin-top:25px}
  .thp h2{margin:5px 0;color:#333}
  .thp .amount{font-size:26px;font-weight:bold;color:#0a0}
  .footer{margin-top:25px;font-size:13px;color:#555;border-top:1px dashed #ddd;padding-top:10px}
  .logo {
  width: 50px;   /* atur sesuai kebutuhan, misalnya 40px, 50px, atau 60px */
  height: auto;  /* biar proporsional */
  margin-right: 10px;
}
.left {
  display: flex;
  align-items: center;
  gap: 10px;     /* kasih jarak antara logo dan judul */
}
.left .title h2 {
  margin: 0;
  font-size: 22px;
  color: #333;
}

</style>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body>
<div class="slip">
  <div class="header">
  <div class="left">
    <img src="../image/manu.png" alt="Logo Perusahaan" class="logo">
    <div class="title">
      <h2>Slip Gaji</h2>
    </div>
  </div>
  <div class="company">
    <b>PT Mandiri Andalan Utama</b><br>
    Jl. Sultan Iskandar Muda No.30 A-B <br>
    Kebayoran Lama Selatan - Kebayoran Lama Jakarta Selatan <br>
    Telp : (021) 275 18 306<br>
    www.manu.co.id
  </div>
</div>


  <div class="info">
    <div>
      <div><b>NIK   :</b> <?= htmlspecialchars($r['nik_ktp']) ?></div>
      <div><b>Nama  :</b> <?= htmlspecialchars($r['nama_karyawan']) ?> </div>
      <div><b>Jabatan:</b> <?= htmlspecialchars($r['jabatan']) ?></div>
      <div><b>Penempatan:</b> <?= htmlspecialchars($r['proyek']) ?> - <?= htmlspecialchars($r['cabang']) ?></div>
      <div><b>Join Date:</b> <?= date('d/m/Y', strtotime($r['tgl_mulai'] ?? $r['created_at'])) ?></div>
    </div>
    <div>
      <div><b>Status Pegawai:</b> <?= htmlspecialchars($r['status_karyawan'] ?? '-') ?></div>
      <div><b>No. Rekening:</b> <?= htmlspecialchars($r['nomor_rekening'] ?? '-') ?></div>
      <div><b>Bank:</b> <?= htmlspecialchars($r['nama_bank'] ?? '-') ?></div>
      <div><b>NPWP:</b> <?= htmlspecialchars($r['npwp'] ?? '-') ?></div>
      <div><b>Bulan:</b> <?= $periode ?></div>
    </div>
  </div>

  <div class="box-container">
    <div class="box">
      <h3>Pendapatan</h3>
      <?php foreach(($comp['pendapatan'] ?? []) as $k=>$v): ?>
        <div class="row"><span><?= htmlspecialchars($k) ?></span><span>Rp. <?= number_format($v,0,',','.') ?></span></div>
      <?php endforeach; ?>
      <div class="row total"><span>Total Pendapatan</span><span>Rp. <?= number_format($r['total_pendapatan'],0,',','.') ?></span></div>
    </div>
    <div class="box">
      <h3>Potongan</h3>
      <?php foreach(($comp['potongan'] ?? []) as $k=>$v): ?>
        <div class="row"><span><?= htmlspecialchars($k) ?></span><span>Rp. <?= number_format($v,0,',','.') ?></span></div>
      <?php endforeach; ?>
      <div class="row total"><span>Total Potongan</span><span>Rp. <?= number_format($r['total_potongan'],0,',','.') ?></span></div>
    </div>
  </div>

  <div class="thp">
    <h2>Total Penerimaan (Take-Home Pay)</h2>
    <div class="amount">Rp. <?= number_format($r['total_payroll'],0,',','.') ?></div>
  </div>

  <div class="footer">
    Pembayaran gaji telah ditransfer ke rekening karyawan:<br>
    <b><?= htmlspecialchars($r['nama_bank'] ?? '-') ?> - <?= htmlspecialchars($r['nomor_rekening'] ?? '-') ?></b>
  </div>
</div>
</body>
</html>
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
