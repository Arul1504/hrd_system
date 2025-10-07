<?php
// ===========================
// view_invoice.php (FINAL)
// ===========================
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function invoice_logo_data_uri(): string {
    $path = realpath(__DIR__ . '/../image/manu.png');
    if (!$path || !is_readable($path)) return '';
    $data = base64_encode(file_get_contents($path));
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

// Data dummy/fallback
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

// Hitungan dummy
$jumlah_setelah_fee = $invoice['jumlah_setelah_fee'] ?? 47960095;
$ppn_11_fee         = $invoice['ppn_11_fee'] ?? 396159;
$total_tagihan      = $invoice['total_tagihan'] ?? 48356254;
$pph_2_fee          = $invoice['pph_2_fee'] ?? 239382;
$total_payment_pei  = $invoice['total_payment_pei'] ?? 48285862;
$terbilang          = $invoice['terbilang'] ?? 'Empat Puluh Delapan Juta Dua Ratus Delapan Puluh Lima Ribu Delapan Ratus Enam Puluh Dua Rupiah';

// Sidebar data
$id_karyawan_admin = $_SESSION['id_karyawan'];
$nama_user_admin   = $_SESSION['nama'];
$role_user_admin   = $_SESSION['role'];

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
        /* jangan sentuh sidebar; fokus di dalam .invoice-canvas saja */
        .invoice-canvas {
            max-width: 900px;
            /* Ukuran mendekati A4 landscape/lebar */
            margin: 0 auto;
        }

        .paper {
            background: #fff;
            padding: 30px 40px;
            /* Padding lebih besar untuk area kertas */
            border-radius: 0;
            /* Hilangkan border radius */
            box-shadow: none;
            /* Hilangkan shadow */

            font-size: 11pt;
            color: #000;
        }

        /* Hilangkan elemen header/logo yang ada di file HTML awal, dan ganti dengan layout gambar */
        .brand {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            /* Logo di atas, teks di bawah */
            border-bottom: none;
            padding-bottom: 0;
            margin-bottom: 0;
            width: 50%;
            float: left;
        }

        .brand img {
            height: 50px;
            margin-top: 10px;
        }

        .brand-text {
            line-height: 1.2;
            padding-top: 10px;
        }

        .brand h1 {
            margin: 0;
            color: #000;
            /* Warna hitam */
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .brand p {
            margin: 0;
            font-size: 9pt;
            /* Ukuran font lebih kecil */
            color: #000;
        }

        .brand .muted {
            font-style: normal;
        }

        /* Hapus italic */

        /* Judul Kwitansi */
        .kwitansi-title {
            text-align: center;
            font-size: 20pt;
            font-weight: 900;
            margin: 40px 0 20px 0;
            clear: both;
            /* Pastikan di bawah header PT */
        }

        /* Bagian Kepada Yth. */
        .head-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .billto {
            width: 55%;
            font-size: 10pt;
            line-height: 1.4;
        }

        .billto strong {
            font-weight: bold;
        }

        .billto .label {
            font-weight: bold;
        }

        .billto address-line {
            display: block;
        }

        .kepada-yth {
            font-weight: bold;
            margin-bottom: 8px;
        }

        /* Bagian Tanggal, Nomor, Periode */
        .info-box {
            width: 40%;
            border: 1px solid #000;
            padding: 8px;
            font-size: 10pt;
        }

        .info-row {
            display: flex;
            line-height: 1.4;
        }

        .info-label {
            width: 120px;
            font-weight: bold;
        }

        .info-value {
            flex-grow: 1;
        }

        /* Tabel Tagihan */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 10pt;
        }

        .items-table th,
        .items-table td {
            border: 1px solid #000;
            padding: 4px 8px;
            /* Padding lebih kecil */
            background: #fff;
            vertical-align: top;
        }

        .items-table th {
            background: #fff;
            font-weight: bold;
            text-align: center;
        }

        .items-table .amount-col {
            text-align: right;
        }

        .items-table .row-total td {
            font-weight: bold;
        }

        .items-table .row-grand-total td {
            background: #f0f0f0;
        }

        /* Untuk membedakan baris grand total */

        .kolom-keterangan {
            text-align: left;
            width: 60%;
        }

        .kolom-jumlah {
            text-align: left;
            width: 40%;
        }

        /* Kolom jumlah tagihan disatukan */

        /* Terbilang */
        .terbilang-box {
            border: 1px solid #000;
            padding: 8px;
            margin-top: 15px;
            font-weight: bold;
            font-size: 10pt;
        }

        .terbilang-label {
            margin-bottom: 5px;
        }

        .terbilang-value {
            font-style: italic;
        }

        /* Tanda Tangan */
        .signature-section {
            display: flex;
            justify-content: flex-end;
            margin-top: 40px;
            font-size: 10pt;
        }

        .signature-box {
            width: 300px;
            text-align: center;
            position: relative;
            /* Penting untuk menampung stempel absolut */
        }

        .signature-box p {
            margin: 0;
            line-height: 1.4;
            position: relative;
            /* Agar teks berada di atas stempel */
            z-index: 2;
            /* Agar teks berada di atas stempel */
        }

        .signature-box .date {
            text-align: right;
            font-size: 10pt;
            margin-bottom: 5px;
            /* Kurangi jarak vertikal di sini */
        }

        .signature-stamp {
            position: absolute;
            top: 50%;
            /* Posisikan stempel di tengah vertikal box */
            left: 50%;
            transform: translate(-50%, -50%);
            /* Geser agar benar-benar di tengah */
            width: 150px;
            /* Sesuaikan ukuran stamp */
            height: auto;
            opacity: 0.8;
            z-index: 1;
            /* Penting! Agar stempel berada di BElAKANG teks */
        }

        .signature-name {
            font-weight: bold;
            border-bottom: 1px solid #000;
            display: inline-block;
            padding: 0 30px;
            margin-top: 100px;
            /* Jarak KOSONG untuk tanda tangan & stempel */
            line-height: 1.2;
        }

        .signature-title {
            font-style: italic;
            margin-top: 90px;
            /* Jarak sangat dekat di bawah garis */
        }

        /* Cegah style global admin merusak layout invoice */
        .paper,
        .paper * {
            word-break: normal !important;
            overflow-wrap: break-word !important;
            border-radius: 0 !important;
            box-shadow: none !important;
        }

       .ttd {
    float: right; /* Pindahkan seluruh div ke kanan */
    width: 250px; /* Beri lebar agar konten di dalamnya mudah diatur */
    text-align: center; /* Pusatkan teks (Hormat Kami, nama, jabatan) */
    margin-top: 20px; /* Beri sedikit jarak dari konten di atasnya */
}

/* 2. Style untuk 'Hormat Kami' */
.y-membuat {
    margin-bottom: 20px; /* Beri jarak yang besar ke area tanda tangan */
}

/* 3. Style untuk area gambar TTD (opsional, jika ingin mengontrol ukuran gambar) */
.ttd-area img {
    max-width: 150px; /* Sesuaikan lebar gambar tanda tangan */
    height: auto;
   

/* 4. Style untuk Nama (memberi garis bawah dan jarak) */
.nama {
    font-weight: bold;
    border-bottom: 1px solid #000; /* Garis horizontal */
    display: inline-block; /* Agar border-bottom hanya selebar teks */
    padding: 0 10px; /* Padding samping agar garis lebih panjang dari teks */
    margin-top: 5px; /* Sesuaikan jika Anda menggunakan 'position: absolute' untuk gambar */
}

/* 5. Style untuk Jabatan */
.jab {
    margin-top: 5px; /* Jarak kecil di bawah nama */
    font-size: 0.9em;
}
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
        <div class="brand">
          <img src="<?= $LOGO_SRC ?>" alt="Logo">
          <div class="brand-text">
            <h1>PT. MANDIRI ANDALAN UTAMA</h1>
            <p>Jl. Sultan Iskandar Muda No 30 A-B Lt.3 Kebayoran Lama Selatan, Jakarta Selatan 12240</p>
            <p>Telp: (021) 27081513</p>
            <p>Web: <span style="color:#0000ff;text-decoration:underline;">http://www.manu.co.id/</span></p>
          </div>
        </div>
        <br><br><br><br>
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
            <tr><td style="text-align:center">1</td><td><?= e($items[0]['description'] ?? 'Biaya Gaji Karyawan Outsource') ?></td><td></td></tr>
            <tr class="row-total"><td colspan="2" style="text-align:right;">TOTAL</td><td class="amount-col">Rp <?= rupiah_int($items[0]['amount'] ?? 44340465) ?></td></tr>
            <tr><td style="text-align:center">2</td><td><?= e($items[1]['description'] ?? 'Fee Management') ?></td><td></td></tr>
            <tr class="row-total"><td colspan="2" style="text-align:right;">JUMLAH</td><td class="amount-col">Rp <?= rupiah_int($jumlah_setelah_fee) ?></td></tr>
            <tr><td style="text-align:center">3</td><td>PPN 11 % Fee</td><td class="amount-col">Rp <?= rupiah_int($ppn_11_fee) ?></td></tr>
            <tr class="row-total"><td colspan="2" style="text-align:right;">TOTAL TAGIHAN</td><td class="amount-col">Rp <?= rupiah_int($total_tagihan) ?></td></tr>
            <tr><td style="text-align:center">4</td><td>PPH 23 (2 % dari Fee)</td><td class="amount-col">Rp <?= rupiah_int($pph_2_fee) ?></td></tr>
            <tr class="row-total" style="background:#f3f4f6;"><td colspan="2" style="text-align:right;">TOTAL PAYMENT PEI</td><td class="amount-col">Rp <?= rupiah_int($total_payment_pei) ?></td></tr>
          </tbody>
        </table>

        <div class="terbilang-box">
          <p>Terbilang :</p>
          <p><i><?= e(ucwords(strtolower($terbilang))) ?></i></p>
        </div>

        <div class="ttd">
          <div class="y-membuat">Hormat Kami</div>
          <div class="ttd-area"><img src="../image/ttd.png" alt="Tanda Tangan"></div>
          <div class="nama"><?= e($invoice['manu_signatory_name'] ?? 'Oktafian Farhan') ?></div>
          <div class="jab">Direktur Utama</div>
        </div>

      </div>
    </div>
  </main>
</div>
</body>
</html>
