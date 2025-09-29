<?php
// ===========================
// view_invoice.php  (sidebar: sama dengan invoice.php, isi: seperti PDF awal)
// ===========================
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

function invoice_logo_data_uri(): string {
    // Sesuaikan lokasi file logo di server
    $path = realpath(__DIR__ . '/../image/manu.png');
    if (!$path || !is_readable($path)) return ''; // fallback: kosong
    $data = base64_encode(file_get_contents($path));
    // Jika logomu JPEG, ganti image/png jadi image/jpeg
    return 'data:image/png;base64,' . $data;
}

$LOGO_SRC = invoice_logo_data_uri();


// helper XSS
if (!function_exists('e')) { function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }

// akses admin
if (!isset($_SESSION['id_karyawan']) || (($_SESSION['role'] ?? '') !== 'ADMIN')) {
    header('Location: ../../index.php'); exit;
}

// ambil invoice
$id_invoice = (int) ($_GET['id'] ?? 0);
if ($id_invoice <= 0) exit('ID Invoice tidak valid');

$stmt = $conn->prepare("SELECT * FROM invoices WHERE id_invoice = ?");
$stmt->bind_param("i", $id_invoice);
$stmt->execute();
$invoice = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$invoice) exit('Invoice tidak ditemukan');

// ambil items
$stmt = $conn->prepare("SELECT * FROM invoice_items WHERE id_invoice = ? ORDER BY id_item ASC");
$stmt->bind_param("i", $id_invoice);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// data sidebar (SAMAA persis dengan invoice.php)
$id_karyawan_admin = $_SESSION['id_karyawan'];
$nama_user_admin   = $_SESSION['nama'];
$role_user_admin   = $_SESSION['role'];

$sql_pending_requests   = "SELECT COUNT(*) AS total_pending FROM pengajuan WHERE status_pengajuan = 'Menunggu'";
$result_pending_requests = $conn->query($sql_pending_requests);
$total_pending           = $result_pending_requests ? ($result_pending_requests->fetch_assoc()['total_pending'] ?? 0) : 0;

$stmt = $conn->prepare("SELECT nik_ktp, jabatan FROM karyawan WHERE id_karyawan = ?");
$stmt->bind_param("i", $id_karyawan_admin);
$stmt->execute();
$admin_info = $stmt->get_result()->fetch_assoc();
$stmt->close();
$nik_user_admin     = $admin_info['nik_ktp'] ?? 'Tidak Ditemukan';
$jabatan_user_admin = $admin_info['jabatan'] ?? 'Tidak Ditemukan';

function rupiah($n){ return 'Rp ' . number_format((float)$n, 0, ',', '.'); }
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Detail Invoice</title>
  <!-- stylesheet sama seperti invoice.php -->
  <link rel="stylesheet" href="../style.css">
  <link rel="stylesheet" href="style.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

  <!-- ======== SCOPED STYLE: tampilan isi invoice diset seperti PDF awal ======== -->
  <style>
    /* jangan sentuh sidebar; fokus di dalam .invoice-canvas saja */
    .invoice-canvas { max-width: 960px; margin: 0 auto; }

    .paper {
      background:#fff; padding:24px 28px; border-radius:12px;
      box-shadow:0 6px 18px rgba(0,0,0,.08);
    }

    /* header perusahaan: sama seperti PDF (garis merah bawah) */
    .brand {
      display:flex; gap:16px; align-items:center;
      border-bottom: 3px solid #e31837; padding-bottom:8px; margin-bottom:12px;
    }
    .brand img { height:56px; }
    .brand h1 { margin:0; color:#e31837; font-size:20px; font-weight:700; }
    .brand p { margin:0; font-size:12px; color:#374151; }
    .brand .muted { font-style: italic; }

    .tag-red {
      display:inline-block; background:#e31837; color:#fff; font-weight:700;
      padding:6px 10px; border-radius:6px; margin:8px 0 10px;
    }

    .head-row { display:flex; justify-content:space-between; gap:24px; }
    .billto p { margin:0 0 4px 0; }

    .info table { width:100%; border-collapse:collapse; font-size:13px; }
    .info td { padding:2px 0; }
    .info .label { width:68px; color:#374151; }

    /* tabel item seperti PDF (garis hitam biasa) */
    .items { width:100%; border-collapse:collapse; margin-top:8px; font-size:13px; }
    .items th, .items td { border:1px solid #111; padding:8px; background:#fff; }
    .items thead th { background:#f3f4f6; font-weight:700; }
    .tr { text-align:right; }

    /* ringkasan di kanan */
    .summary { width: 40%; margin-left:auto; border-collapse:collapse; font-size:13px; margin-top:8px; }
    .summary td { border:1px solid #111; padding:8px; background:#fff; }
    .summary tr:last-child td { background:#f3f4f6; font-weight:700; }

    .transfer .tag-red { margin-top:14px; }
    .transfer p { margin:4px 0; font-size:13px; }

    .sign { display:flex; justify-content:flex-end; margin-top:26px; }
    .sign .box { width:260px; text-align:center; }
    .sign .date { text-align:right; font-size:13px; color:#374151; }
    .sign .line { border-bottom:1px solid #000; display:inline-block; padding:0 60px; font-weight:700; margin-bottom:6px; }
    .sign .title { font-size:12px; color:#374151; }

    /* cegah style global admin merusak layout invoice */
    .paper, .paper * {
      word-break: normal !important;
      overflow-wrap: break-word !important;
      border-radius: 0; box-shadow: none;
    }
  </style>
</head>
<body>
<div class="container">
  <!-- ===== SIDEBAR (copy dari invoice.php) ===== -->
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
    <div class="logout-link">
      <a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a>
    </div>
  </aside>

  <!-- ===== MAIN ===== -->
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

    <div class="invoice-canvas">
      <div class="paper">
        <!-- header -->
        <div class="brand">
          <img src="../image/manu.png" alt="Logo">
          <div>
            <h1>PT. MANDIRI ANDALAN UTAMA</h1>
            <p class="muted">Committed to delivered the best result</p>
            <p>Jl Sultan Iskandar Muda No. 50 A-B</p>
            <p>Kebayoran Lama Selatan - Kebayoran Lama Jakarta Selatan 12240</p>
            <p>021-27518306 • www.manu.co.id</p>
          </div>
        </div>

        <span class="tag-red">BILL TO:</span>

        <!-- bill & info -->
        <div class="head-row">
          <div class="billto">
            <p><strong><?= e($invoice['bill_to_bank'] ?? '') ?></strong></p>
            <?php if(!empty($invoice['bill_to_address1'])): ?><p><?= e($invoice['bill_to_address1']) ?></p><?php endif; ?>
            <?php if(!empty($invoice['bill_to_address2'])): ?><p><?= e($invoice['bill_to_address2']) ?></p><?php endif; ?>
            <?php if(!empty($invoice['bill_to_address3'])): ?><p><?= e($invoice['bill_to_address3']) ?></p><?php endif; ?>
          </div>
          <div class="info">
            <table>
              <tr><td class="label">No</td><td>:</td><td><?= e($invoice['invoice_number'] ?? '') ?></td></tr>
              <tr><td class="label">Tanggal</td><td>:</td><td><?= e(date('d/m/Y', strtotime($invoice['invoice_date'] ?? 'now'))) ?></td></tr>
              <tr><td class="label">Up</td><td>:</td><td><?= e($invoice['person_up_name'] ?? '') ?></td></tr>
            </table>
          </div>
        </div>

        <!-- items -->
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
        </table>

        <!-- summary -->
        <table class="summary">
          <tr><td>SUB TOTAL</td><td class="tr"><?= rupiah($invoice['sub_total'] ?? 0) ?></td></tr>
          <tr><td>PPN</td><td class="tr"><?= rupiah($invoice['ppn_amount'] ?? 0) ?></td></tr>
          <tr><td>PPH</td><td class="tr"><?= rupiah($invoice['pph_amount'] ?? 0) ?></td></tr>
          <tr><td>GRAND TOTAL</td><td class="tr"><?= rupiah($invoice['grand_total'] ?? 0) ?></td></tr>
        </table>

        <!-- transfer -->
        <div class="transfer">
          <span class="tag-red">Please Transfer to Account:</span>
          <p><strong>Bank :</strong> <?= e($invoice['transfer_bank'] ?? '') ?></p>
          <p><strong>Rekening Number :</strong> <?= e($invoice['transfer_account_no'] ?? '') ?></p>
          <p><strong>A/C :</strong> <?= e($invoice['transfer_account_name'] ?? '') ?></p>
        </div>

        <!-- signature -->
        <div class="sign">
          <div class="box">
            <p class="date">Jakarta, <?= e(date('d F Y', strtotime($invoice['footer_date'] ?? 'now'))) ?></p>
            <br><br><br>
            <p class="line"><?= e($invoice['manu_signatory_name'] ?? '') ?></p>
            <p class="title"><?= e($invoice['manu_signatory_title'] ?? '') ?></p>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>
</body>
</html>
