<?php
// ===========================
// view_invoice_pei.php (FINAL, header teks tengah + logo kiri, TTD renggang, Direktur Utama -> Direktur)
// ===========================
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function invoice_logo_data_uri(): string {
    $path = realpath(__DIR__ . '/../image/manu.png');
    if (!$path || !is_readable($path)) return '';
    $data = base64_encode(@file_get_contents($path));
    return 'data:image/png;base64,' . $data;
}
$LOGO_SRC = invoice_logo_data_uri();

if (!function_exists('e')) {
    function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

// --- Akses hanya ADMIN ---
if (!isset($_SESSION['id_karyawan']) || (($_SESSION['role'] ?? '') !== 'ADMIN')) {
    header('Location: ../../index.php');
    exit;
}

// Ambil invoice
$id_invoice = (int)($_GET['id'] ?? 0);
if ($id_invoice <= 0) exit('ID Invoice tidak valid');

$stmt = $conn->prepare("SELECT * FROM invoices WHERE id_invoice = ?");
$stmt->bind_param("i", $id_invoice);
$stmt->execute();
$invoice = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$invoice) exit('Invoice tidak ditemukan');

// Nilai tampil
$invoice_date_formated = date('d F Y', strtotime($invoice['invoice_date'] ?? 'now'));
$invoice_number        = $invoice['invoice_number'] ?? 'XXX/MANU-PEI/KEU-AR/VIII/2025';
$billing_period_start  = $invoice['billing_period_start'] ?? 'Agustus 2025';
$billing_period_end    = $invoice['billing_period_end'] ?? 'Bulan Agustus 2025';

// Items
$stmt = $conn->prepare("SELECT * FROM invoice_items WHERE id_invoice = ? ORDER BY id_item ASC");
$stmt->bind_param("i", $id_invoice);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Hitungan (boleh kosong kalau kolom tidak ada; gunakan fallback agar selalu tampil)
$jumlah_setelah_fee = $invoice['jumlah_setelah_fee'] ?? 47960095;
$ppn_11_fee         = $invoice['ppn_11_fee'] ?? 396159;
$total_tagihan      = $invoice['total_tagihan'] ?? 48356254;
$pph_2_fee          = $invoice['pph_2_fee'] ?? 239382;
$total_payment_pei  = $invoice['total_payment_pei'] ?? 48285862;
$terbilang          = $invoice['terbilang'] ?? 'Empat Puluh Delapan Juta Dua Ratus Delapan Puluh Lima Ribu Delapan Ratus Enam Puluh Dua Rupiah';

// Sidebar data
$id_karyawan_admin = $_SESSION['id_karyawan'];
$nama_user_admin   = $_SESSION['nama'] ?? '';
$role_user_admin   = $_SESSION['role'] ?? '';

$sql_pending_requests = "SELECT COUNT(*) AS total_pending FROM pengajuan WHERE status_pengajuan = 'Menunggu'";
$result_pending_requests = $conn->query($sql_pending_requests);
$total_pending = $result_pending_requests ? ($result_pending_requests->fetch_assoc()['total_pending'] ?? 0) : 0;

$stmt = $conn->prepare("SELECT nik_ktp, jabatan FROM karyawan WHERE id_karyawan = ?");
$stmt->bind_param("i", $id_karyawan_admin);
$stmt->execute();
$admin_info = $stmt->get_result()->fetch_assoc();
$stmt->close();
$nik_user_admin = $admin_info['nik_ktp'] ?? 'Tidak Ditemukan';
$jabatan_user_admin = $admin_info['jabatan'] ?? 'Tidak Ditemukan';

function rupiah_int($n) { return number_format((float)$n, 0, ',', '.'); }
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Kwitansi</title>
  <link rel="stylesheet" href="../style.css">
  <link rel="stylesheet" href="style.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

  <!-- Script PDF -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.3/html2pdf.bundle.min.js"></script>
  <script>
  function downloadSuratAsPDF(fileNamePrefix) {
      const element = document.getElementById('surat-tugas-dokumen');
      const opt = {
          margin: [6, 6, 6, 6],
          filename: `Invoice-${fileNamePrefix}.pdf`,
          image: { type: 'jpeg', quality: 0.98 },
          html2canvas: { scale: 2, useCORS: true },
          jsPDF: { unit: 'mm', format: [230, 297], orientation: 'portrait' }
      };
      html2pdf().from(element).set(opt).save();
  }
  </script>

  <style>
    /* Hanya styling area invoice */
    .invoice-canvas { max-width: 900px; margin: 0 auto; }
    .paper {
      background:#fff; padding:30px 40px; border-radius:0; box-shadow:none;
      font-size:11pt; color:#000;
    }

    /* Header: logo kiri, teks tengah */
    .brand {
      display:grid; grid-template-columns:auto 1fr; align-items:center; column-gap:14px;
      border-bottom:none; padding-bottom:0; margin-bottom:0;
    }
    .brand img { height:50px; margin-top:10px; grid-column:1; }
    .brand .brand-text { grid-column:2; text-align:center; line-height:1.25; padding-top:8px; }
    .brand h1 { margin:0; color:#000; font-size:14pt; font-weight:700; text-transform:uppercase; }
    .brand p { margin:0; font-size:9pt; color:#000; }
    .brand .muted { font-style:normal; }

    .kwitansi-title {
      text-align:center; font-size:20pt; font-weight:900; margin:40px 0 20px 0; clear:both;
    }

    .head-section { display:flex; justify-content:space-between; gap:20px; margin-bottom:20px; }
    .billto { width:55%; font-size:10pt; line-height:1.4; }
    .billto .kepada-yth { font-weight:700; margin-bottom:8px; }

    .info-box { width:40%; border:1px solid #000; padding:8px; font-size:10pt; }
    .info-row { display:flex; line-height:1.4; }
    .info-row strong { width:auto; margin-right:6px; }

    .items-table { width:100%; border-collapse:collapse; margin-top:15px; font-size:10pt; }
    .items-table th, .items-table td { border:1px solid #000; padding:4px 8px; background:#fff; vertical-align:top; }
    .items-table th { background:#fff; font-weight:700; text-align:center; }
    .items-table .amount-col { text-align:right; }
    .items-table .row-total td { font-weight:700; }
    .items-table .row-grand-total td { background:#f0f0f0; }

    .terbilang-box { border:1px solid #000; padding:8px; margin-top:15px; font-weight:700; font-size:10pt; }
    .terbilang-box i { font-weight:400; }

    /* TTD direnggangkan supaya muat materai */
    .signature-section { display:flex; justify-content:flex-end; margin-top:42px; font-size:10pt; }
    .signature-box { width:300px; text-align:center; position:relative; min-height:185px; }
    .signature-box p { margin:0; line-height:1.4; position:relative; z-index:2; }
    .signature-box .date { text-align:right; font-size:10pt; margin-bottom:8px; }
    .signature-stamp { position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); width:150px; height:auto; opacity:.8; z-index:1; }
    .signature-name { font-weight:700; border-bottom:1px solid #000; display:inline-block; padding:0 30px; margin-top:105px; line-height:1.2; }
    .signature-title { margin-top:10px; font-style:italic; }

    /* Versi ttd blok yang sudah ada di HTML: betulkan kurung & spacing */
    .ttd { float:right; width:300px; text-align:center; margin-top:42px; }
    .y-membuat { margin-bottom:12px; font-weight:600; }
    .ttd-area img { max-width:150px; height:auto; }
    .nama { font-weight:700; border-bottom:1px solid #000; display:inline-block; padding:0 30px; margin-top:100px; line-height:1.2; }
    .jab { margin-top:10px; font-size:0.95em; }

    /* Cegah style global admin merusak layout invoice */
    .paper, .paper * { word-break:normal !important; overflow-wrap:break-word !important; border-radius:0 !important; box-shadow:none !important; }
  </style>
</head>

<body>
<div class="container">
  <aside class="sidebar">
    <div class="company-brand">
      <img src="<?= $LOGO_SRC ?>" alt="Logo PT Mandiri Andalan Utama" class="company-logo">
      <p class="company-name">PT Mandiri Andalan Utama</p>
    </div>
    <div class="user-info">
      <div class="user-avatar"><?= e(strtoupper(substr($nama_user_admin, 0, 2))) ?></div>
      <div class="user-details">
        <p class="user-name"><?= e($nama_user_admin) ?></p>
        <p class="user-id"><?= e($nik_user_admin ?: '—') ?></p>
        <p class="user-role"><?= e($role_user_admin) ?></p>
      </div>
    </div>
    <nav class="sidebar-nav">
      <ul>
        <li><a href="../dashboard_admin.php"><i class="fas fa-home"></i> Dashboard</a></li>
        <li><a href="../absensi/absensi.php"><i class="fas fa-edit"></i> Absensi </a></li>
        <li><a href="../data_karyawan/all_employees.php"><i class="fas fa-users"></i> Data Karyawan</a></li>
        <li><a href="../pengajuan/pengajuan.php"><i class="fas fa-file-alt"></i> Data Pengajuan <span class="badge"><?= $total_pending ?></span></a></li>
        <li><a href="../monitoring_kontrak/monitoring_kontrak.php"><i class="fas fa-calendar-alt"></i> Monitoring Kontrak</a></li>
        <li><a href="../monitoring_kontrak/surat_tugas_history.php"><i class="fas fa-file-alt"></i> Riwayat Surat Tugas</a></li>
        <li><a href="../payslip/e_payslip_admin.php"><i class="fas fa-money-check-alt"></i> E-Pay Slip</a></li>
        <li class="active"><a href="../invoice/invoice.php"><i class="fas fa-money-check-alt"></i> Invoice</a></li>
      </ul>
    </nav>
    <div class="logout-link"><a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a></div>
  </aside>

  <main class="main-content">
    <header class="main-header">
      <h1>Kwitansi</h1>
      <div style="display:flex;gap:10px;">
        <a href="javascript:void(0)" onclick="downloadSuratAsPDF('<?= e($invoice_number) ?>')" class="btn btn-primary"
           style="background:#2563eb;color:#fff;border-radius:8px;padding:8px 12px;text-decoration:none;display:inline-flex;align-items:center;gap:8px">
           <i class="fas fa-file-pdf"></i> Unduh PDF
        </a>
        <a href="invoice.php" class="btn btn-outline"
           style="background:#fff;border:1px solid #d1d5db;color:#374151;border-radius:8px;padding:8px 12px;text-decoration:none;display:inline-flex;align-items:center;gap:8px">
           <i class="fas fa-arrow-left"></i> Kembali
        </a>
      </div>
    </header>

    <!-- Area yang diexport PDF -->
    <div class="invoice-canvas" id="surat-tugas-dokumen">
      <div class="paper">
        <!-- Header: logo kiri, teks tengah -->
        <div class="brand">
          <img src="<?= $LOGO_SRC ?>" alt="Logo">
          <div class="brand-text">
            <h1>PT. MANDIRI ANDALAN UTAMA</h1>
            <p>Jl. Sultan Iskandar Muda No 30 A-B Lt.3 Kebayoran Lama Selatan, Jakarta Selatan 12240</p>
            <p>Telp: (021) 27081513</p>
            <p>Web: <span style="color:#0000ff;text-decoration:underline;">http://www.manu.co.id/</span></p>
          </div>
        </div>

        <p class="kwitansi-title">KWITANSI</p>

        <div class="head-section">
          <div class="billto">
            <p class="kepada-yth">Kepada Yth :</p>
            <p style="margin-left:20px;">
              <span class="label">Finance</span><br>
              <strong>PT. Pendanaan Efek Indonesia</strong><br>
              Gedung Bursa Efek Indonesia Tower 1<br>
              Lt. 2, Suite 212, Jl. Jend. Sudirman No. 53<br>
              Senayan, Kebayoran Baru, Jakarta Selatan
            </p>
          </div>
          <div class="info-box">
            <div class="info-row"><strong>Tanggal Tagihan</strong> : <?= e($invoice_date_formated) ?></div>
            <div class="info-row"><strong>Nomor Tagihan</strong> : <?= e($invoice_number) ?></div>
            <div class="info-row"><strong>Periode Tagihan</strong> : <?= e($billing_period_start) ?></div>
            <div class="info-row"><strong>Periode Tagihan</strong> : <?= e($billing_period_end) ?></div>
          </div>
        </div>

        <table class="items-table">
          <thead>
            <tr><th>No.</th><th>Keterangan Tagihan</th><th>Jumlah Tagihan</th></tr>
          </thead>
          <tbody>
            <tr>
              <td style="text-align:center">1</td>
              <td><?= e($items[0]['description'] ?? 'Biaya Gaji Karyawan Outsource') ?></td>
              <td></td>
            </tr>
            <tr class="row-total">
              <td colspan="2" style="text-align:right;">TOTAL</td>
              <td class="amount-col">Rp <?= rupiah_int($items[0]['amount'] ?? 44340465) ?></td>
            </tr>
            <tr>
              <td style="text-align:center">2</td>
              <td><?= e($items[1]['description'] ?? 'Fee Management') ?></td>
              <td></td>
            </tr>
            <tr class="row-total">
              <td colspan="2" style="text-align:right;">JUMLAH</td>
              <td class="amount-col">Rp <?= rupiah_int($jumlah_setelah_fee) ?></td>
            </tr>
            <tr>
              <td style="text-align:center">3</td>
              <td>PPN 11 % Fee</td>
              <td class="amount-col">Rp <?= rupiah_int($ppn_11_fee) ?></td>
            </tr>
            <tr class="row-total">
              <td colspan="2" style="text-align:right;">TOTAL TAGIHAN</td>
              <td class="amount-col">Rp <?= rupiah_int($total_tagihan) ?></td>
            </tr>
            <tr>
              <td style="text-align:center">4</td>
              <td>PPH 23 (2 % dari Fee)</td>
              <td class="amount-col">Rp <?= rupiah_int($pph_2_fee) ?></td>
            </tr>
            <tr class="row-total row-grand-total">
              <td colspan="2" style="text-align:right;">TOTAL PAYMENT PEI</td>
              <td class="amount-col">Rp <?= rupiah_int($total_payment_pei) ?></td>
            </tr>
          </tbody>
        </table>

        <div class="terbilang-box">
          <p>Terbilang :</p>
          <p><i><?= e(ucwords(strtolower($terbilang))) ?></i></p>
        </div>

        <!-- TTD versi blok eksisting: lebih renggang & gelar otomatis Direktur -->
        <div class="ttd">
          <div class="y-membuat">Hormat Kami</div>
          <div class="ttd-area"><img src="../image/ttd.png" alt="Tanda Tangan"></div>
          <div class="nama"><?= e($invoice['manu_signatory_name'] ?? 'Oktafian Farhan') ?></div>
          <div class="jab">
            <?php
              $t = trim((string)($invoice['manu_signatory_title'] ?? 'Direktur'));
              if (strcasecmp($t, 'Direktur Utama') === 0) { $t = 'Direktur'; }
              echo e($t);
            ?>
          </div>
        </div>

      </div>
    </div>
  </main>
</div>
</body>
</html>
