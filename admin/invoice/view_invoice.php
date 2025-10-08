<?php 
// ===========================
// view_invoice.php (Tema merah brand lembut + ringkasan gabung tabel + sembunyikan Project/Nota Debet)
// ===========================
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!function_exists('e')) { function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
function rupiah($n){ return 'Rp ' . number_format((float)$n, 0, ',', '.'); }

// logo inline
function invoice_logo_data_uri(): string {
  $path = realpath(__DIR__ . '/../image/manu.png');
  if (!$path || !is_readable($path)) return '';
  return 'data:image/png;base64,' . base64_encode(file_get_contents($path));
}
$LOGO_SRC = invoice_logo_data_uri();

// akses
if (!isset($_SESSION['id_karyawan']) || (($_SESSION['role'] ?? '') !== 'ADMIN')) { header('Location: ../../index.php'); exit; }

// ambil invoice
$id_invoice = (int)($_GET['id'] ?? 0);
if ($id_invoice <= 0) exit('ID Invoice tidak valid');

$stmt = $conn->prepare("SELECT * FROM invoices WHERE id_invoice = ?");
$stmt->bind_param("i", $id_invoice);
$stmt->execute();
$invoice = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$invoice) exit('Invoice tidak ditemukan');

// items
$stmt = $conn->prepare("SELECT * FROM invoice_items WHERE id_invoice = ? ORDER BY item_number ASC, id_item ASC");
$stmt->bind_param("i", $id_invoice);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// adjustments (jika ada)
$adjustments = [];
if ($conn && $stmt = $conn->prepare("SELECT label, percent, amount FROM invoice_adjustments WHERE id_invoice = ? ORDER BY id ASC")) {
  $stmt->bind_param("i", $id_invoice);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($r = $res->fetch_assoc()) $adjustments[] = $r;
  $stmt->close();
}

// sidebar
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
$nik_user_admin     = $admin_info['nik_ktp'] ?? 'Tidak Ditemukan';
$jabatan_user_admin = $admin_info['jabatan'] ?? 'Tidak Ditemukan';

// flags
$employeeStatus =
    ($invoice['employee_status'] ?? '') ?:
    ($invoice['surat_tipe'] ?? '') ?:
    ($invoice['status_karyawan'] ?? '');
$employeeStatus = strtoupper(trim((string)$employeeStatus));
$isMitra = ($employeeStatus === 'MITRA');
$isPkwt  = ($employeeStatus === 'PKWT');

$hasMgmtFee = ((float)($invoice['mgmt_fee_amount'] ?? 0) > 0) || ((float)($invoice['mgmt_fee_percent'] ?? 0) > 0);
$hasPPN     = ((float)($invoice['ppn_amount'] ?? 0) > 0);
$hasPPH     = ((float)($invoice['pph_amount'] ?? 0) > 0);
$hasSubTotal= ((float)($invoice['sub_total'] ?? 0) > 0);
$hasGrand   = ((float)($invoice['grand_total'] ?? 0) > 0);

$hasTransferBank = !empty($invoice['transfer_bank']);
$hasTransferNo   = !empty($invoice['transfer_account_no']);
$hasTransferName = !empty($invoice['transfer_account_name']);

$hasUpName  = !empty($invoice['person_up_name']);
$hasUpTitle = !empty($invoice['person_up_title']);

$hasBillToBank = !empty($invoice['bill_to_bank']);
$hasAddr1      = !empty($invoice['bill_to_address1']);
$hasAddr2      = !empty($invoice['bill_to_address2']);
$hasAddr3      = !empty($invoice['bill_to_address3']);

$themeClass = $isMitra ? 'theme-mitra' : ($isPkwt ? 'theme-pkwt' : '');
$badgeText  = $isMitra ? '' : ($isPkwt ? '' : '');
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Detail Invoice</title>
  <link rel="stylesheet" href="../style.css">
  <link rel="stylesheet" href="style.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

  <style>
    /* palet brand merah lembut */
    :root{ --brand-red:#DC143C; --brand-red-soft: #DC143C; }
    
.items tr.summary-row td:first-child,
.items tr.summary-row td[colspan="2"] {
  text-align: left !important;
}

    .invoice-canvas { max-width: 960px; margin: 0 auto; }
    .paper { background:#fff; padding:24px 28px; border-radius:12px; box-shadow:0 6px 18px rgba(0,0,0,.08); }
    .paper, .paper *{ word-break: normal !important; overflow-wrap: break-word !important; border-radius:0; box-shadow:none; }

    .brand { display:flex; gap:16px; align-items:center; border-bottom:3px solid var(--brand-red); padding-bottom:8px; margin-bottom:12px; }
    .brand img { height:56px; }
    .brand h1 { margin:0; color:var(--brand-red); font-size:20px; font-weight:700; }
    .brand p { margin:0; font-size:12px; color:#374151; }
    .brand .muted { font-style: italic; }

    .badge-theme { display:inline-block; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:700; margin-left:10px; background:#444; color:#fff; }

    .head-row { display:flex; justify-content:space-between; gap:24px; }
    .billto p { margin:0 0 4px 0; }

    .info table { width:100%; border-collapse:collapse; font-size:13px; }
    .info td { padding:2px 0; }
    .info .label { width:80px; color:#374151; }

    /* label bertema */
    .tag-themed{ display:inline-block; background:var(--brand-red); color:#fff; font-weight:700; padding:6px 10px; border-radius:6px; margin:8px 0 10px; }

    /* tabel items */
    .items { width:100%; border-collapse:collapse; margin-top:8px; font-size:13px; }
    .items th, .items td { border:1px solid #111; padding:8px; background:#fff; }
    .items thead th { 
  background:var(--brand-red-soft); 
  color: #fff;       /* hitam */
  font-weight:700; 
  text-align: center; 
}

    .tr { text-align:right; }

    /* summary-row: SUBTOTAL, PPN, PPH tetap putih */
.items tr.summary-row td { 
  background:#fff; 
  font-weight:700;
  
}

/* hanya GRAND TOTAL yang berwarna */
.items tr.grand-row td { 
  background:var(--brand-red-soft);
  color: #fff; 
  font-weight:800;
  text-align: center; 
}

    .transfer p { margin:4px 0; font-size:13px; }

    .sign { display:flex; justify-content:flex-end; margin-top:34px; }
    .sign .box { width:280px; text-align:center; }
    .sign .date { text-align:right; font-size:13px; color:#374151; }
    .sign .line { border-bottom:1px solid #000; display:inline-block; padding:0 68px; font-weight:700; margin-bottom:8px; }
    .sign .title { font-size:12px; color:#374151; }
  </style>
</head>
<body>
<div class="container">
  <!-- SIDEBAR -->
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
        <li class="dropdown-trigger">
          <a href="#" class="dropdown-link"><i class="fas fa-users"></i> Data Karyawan <i class="fas fa-caret-down"></i></a>
          <ul class="dropdown-menu">
            <li><a href="../data_karyawan/all_employees.php">Semua Karyawan</a></li>
            <li><a href="../data_karyawan/karyawan_nonaktif.php">Non-Aktif</a></li>
          </ul>
        </li>
        <li class="dropdown-trigger">
          <a href="#" class="dropdown-link"><i class="fas fa-users"></i> Data Pengajuan<span class="badge"><?= $total_pending ?></span> <i class="fas fa-caret-down"></i></a>
          <ul class="dropdown-menu">
            <li><a href="../pengajuan/pengajuan.php">Pengajuan</a></li>
            <li><a href="../pengajuan/kelola_pengajuan.php">Kelola Pengajuan<span class="badge"><?= $total_pending ?></span></a></li>
          </ul>
        </li>
        <li><a href="../monitoring_kontrak/monitoring_kontrak.php"><i class="fas fa-calendar-alt"></i> Monitoring Kontrak</a></li>
        <li><a href="../monitoring_kontrak/surat_tugas_history.php"><i class="fas fa-file-alt"></i> Riwayat Surat Tugas</a></li>
        <li><a href="../payslip/e_payslip_admin.php"><i class="fas fa-money-check-alt"></i> E-Pay Slip</a></li>
        <li class="active"><a href="../invoice/invoice.php"><i class="fas fa-money-check-alt"></i> Invoice</a></li>
      </ul>
    </nav>
    <div class="logout-link"><a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a></div>
  </aside>

  <!-- MAIN -->
  <main class="main-content">
    <header class="main-header">
      <h1>Detail Invoice</h1>
      <div style="display:flex;gap:10px;">
        <a href="download_invoice.php?id=<?= $id_invoice ?>" class="btn btn-primary" style="background:#2563eb;color:#fff;border-radius:8px;padding:8px 12px;text-decoration:none;display:inline-flex;align-items:center;gap:8px">
          <i class="fas fa-file-pdf"></i> Unduh PDF
        </a>
        <a href="invoice.php" class="btn btn-outline" style="background:#fff;border:1px solid #d1d5db;color:#374151;border-radius:8px;padding:8px 12px;text-decoration:none;display:inline-flex;align-items:center;gap:8px">
          <i class="fas fa-arrow-left"></i> Kembali
        </a>
      </div>
    </header>

    <div class="invoice-canvas <?= e($themeClass) ?>">
      <div class="paper">
        <!-- header -->
        <div class="brand">
          <img src="<?= $LOGO_SRC ?>" alt="Logo">
          <div>
            <h1>PT. MANDIRI ANDALAN UTAMA
              <?php if($badgeText): ?><span class="badge-theme"><?= e($badgeText) ?></span><?php endif; ?>
            </h1>
            <p class="muted">Committed to delivered the best result</p>
            <p>Jl Sultan Iskandar Muda No. 50 A-B</p>
            <p>Kebayoran Lama Selatan - Kebayoran Lama Jakarta Selatan 12240</p>
            <p>021-27518306 • www.manu.co.id</p>
          </div>
        </div>

        <?php if($hasBillToBank || $hasAddr1 || $hasAddr2 || $hasAddr3): ?>
          <span class="tag-themed">BILL TO:</span>
        <?php endif; ?>

        <!-- bill & info -->
        <div class="head-row">
          <div class="billto">
            <?php if($hasBillToBank): ?><p><strong><?= e($invoice['bill_to_bank']) ?></strong></p><?php endif; ?>
            <?php if($hasAddr1): ?><p><?= e($invoice['bill_to_address1']) ?></p><?php endif; ?>
            <?php if($hasAddr2): ?><p><?= e($invoice['bill_to_address2']) ?></p><?php endif; ?>
            <?php if($hasAddr3): ?><p><?= e($invoice['bill_to_address3']) ?></p><?php endif; ?>
            <?php /* Project & Nota Debet disembunyikan sesuai permintaan */ ?>
          </div>
          <div class="info">
            <table>
              <tr><td class="label">No</td><td>:</td><td><?= e($invoice['invoice_number'] ?? '') ?></td></tr>
              <tr><td class="label">Tanggal</td><td>:</td><td><?= e(date('d/m/Y', strtotime($invoice['invoice_date'] ?? 'now'))) ?></td></tr>
              <?php if($hasUpName): ?><tr><td class="label">Up</td><td>:</td><td><?= e($invoice['person_up_name']) ?></td></tr><?php endif; ?>
              <?php if($hasUpTitle): ?><tr><td class="label"></td><td></td><td class="muted"><?= e($invoice['person_up_title']) ?></td></tr><?php endif; ?>
            </table>
          </div>
        </div>

        <!-- items + ringkasan gabung -->
        <table class="items">
          <thead>
            <tr>
              <th style="width:6%;">No</th>
              <th style="width:64%;">Description</th>
              <th style="width:30%;" class="tr">Amount</th>
            </tr>
          </thead>
          <tbody>
          <?php if(!empty($items)): $i=1; foreach($items as $it): ?>
            <tr>
              <td><?= $i++ ?></td>
              <td><?= e($it['description'] ?? '') ?></td>
              <td class="tr"><?= rupiah($it['amount'] ?? 0) ?></td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="3" style="text-align:center">Tidak ada item.</td></tr>
          <?php endif; ?>
          </tbody>

          <tfoot>
            <?php
              $ppnLabel = ((float)($invoice['ppn_percent'] ?? 0) > 0) ? 'PPN ('.rtrim(rtrim(number_format((float)$invoice['ppn_percent'],2,'.',''), '0'),'.').'%)' : 'PPN';
              $pphLabel = ((float)($invoice['pph_percent'] ?? 0) > 0) ? 'PPH ('.rtrim(rtrim(number_format((float)$invoice['pph_percent'],2,'.',''), '0'),'.').'%)' : 'PPH';
              $mfLabel  = ((float)($invoice['mgmt_fee_percent'] ?? 0) > 0) ? 'MANAGEMENT FEE ('.rtrim(rtrim(number_format((float)$invoice['mgmt_fee_percent'],2,'.',''), '0'),'.').'%)' : 'MANAGEMENT FEE';
            ?>

            <?php if($hasMgmtFee): ?>
              <tr class="summary-row">
                <td colspan="2" class="tr"><?= e($mfLabel) ?></td>
                <td class="tr"><?= rupiah($invoice['mgmt_fee_amount'] ?? 0) ?></td>
              </tr>
            <?php endif; ?>

            <?php if($hasSubTotal): ?>
              <tr class="summary-row">
                <td colspan="2" class="tr">SUB TOTAL</td>
                <td class="tr"><?= rupiah($invoice['sub_total'] ?? 0) ?></td>
              </tr>
            <?php endif; ?>

            <?php if($hasPPN): ?>
              <tr class="summary-row">
                <td colspan="2" class="tr"><?= e($ppnLabel) ?></td>
                <td class="tr"><?= rupiah($invoice['ppn_amount'] ?? 0) ?></td>
              </tr>
            <?php endif; ?>

            <?php if($hasPPH): ?>
              <tr class="summary-row">
                <td colspan="2" class="tr"><?= e($pphLabel) ?></td>
                <td class="tr"><?= rupiah($invoice['pph_amount'] ?? 0) ?></td>
              </tr>
            <?php endif; ?>

            <?php if(!empty($adjustments)): foreach($adjustments as $adj): ?>
              <tr class="summary-row">
                <td colspan="2" class="tr"><?= e($adj['label']) ?> (<?= rtrim(rtrim(number_format((float)$adj['percent'],2,'.',''), '0'),'.') ?>%)</td>
                <td class="tr"><?= rupiah($adj['amount'] ?? 0) ?></td>
              </tr>
            <?php endforeach; endif; ?>

            <?php if($hasGrand): ?>
              <tr class="grand-row">
                <td colspan="2" class="tr">GRAND TOTAL</td>
                <td class="tr"><?= rupiah($invoice['grand_total'] ?? 0) ?></td>
              </tr>
            <?php endif; ?>
          </tfoot>
        </table>

        <!-- transfer -->
        <?php if($hasTransferBank || $hasTransferNo || $hasTransferName): ?>
        <div class="transfer" style="margin-top:10px;">
          <span class="tag-themed">Please Transfer to Account:</span>
          <?php if($hasTransferBank): ?><p><strong>Bank :</strong> <?= e($invoice['transfer_bank']) ?></p><?php endif; ?>
          <?php if($hasTransferNo): ?><p><strong>Rekening Number :</strong> <?= e($invoice['transfer_account_no']) ?></p><?php endif; ?>
          <?php if($hasTransferName): ?><p><strong>A/C :</strong> <?= e($invoice['transfer_account_name']) ?></p><?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- signature (ruang diperlebar untuk materai) -->
        <div class="sign">
          <div class="box">
            <p class="date">Jakarta, <?= e(date('d F Y', strtotime($invoice['footer_date'] ?? 'now'))) ?></p>
            <br><br><br><br><br><br>
            <?php if(!empty($invoice['manu_signatory_name'])): ?>
              <p class="line"><?= e($invoice['manu_signatory_name']) ?></p>
            <?php else: ?>
              <p class="line">&nbsp;</p>
            <?php endif; ?>
            <?php if(!empty($invoice['manu_signatory_title'])): ?>
              <p class="title"><?= e($invoice['manu_signatory_title']) ?></p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>
</body>
</html>
