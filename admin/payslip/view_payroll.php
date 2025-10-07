<?php
// ===========================
// payslip/view_payroll.php
// ===========================
require '../config.php';
if (session_status() === PHP_SESSION_NONE)
  session_start();

// Ambil ID slip yang diminta dan data user yang login
// --- INISIALISASI VARIABEL KONTROL AKSES (PERBAIKAN) ---
$user_id = $_SESSION['id_karyawan'] ?? 0;
$user_role = strtoupper($_SESSION['role'] ?? '');
$is_admin = in_array($user_role, ['HRD', 'ADMIN']);
$is_owner = false; // Definisikan default

// Pastikan user login
if ($user_id === 0) {
  exit('Unauthorized: Sesi Kedaluwarsa');
}
// Cek apakah mode tampilan minimal (untuk Karyawan yang mengunduh)
$is_download_mode = isset($_GET['download']) && $_GET['download'] === 'true' && $is_owner;

// Jika dalam mode download, kita tidak perlu data sidebar Admin
if ($is_download_mode) {
  // Hapus variabel yang tidak relevan jika dalam mode download
  $nik_user_admin = '';
  $jabatan_user_admin = '';
  $total_pending = 0;
  // Lanjutkan langsung ke konten HTML
} else {
  // ... (data sidebar Admin dipertahankan)
}

$id = (int) ($_GET['id'] ?? 0);
$q = $conn->prepare("SELECT p.*, p.is_email_sent, k.nama_karyawan, k.nik_ktp, k.jabatan, k.proyek, k.status_karyawan, k.cabang,
                    k.nomor_rekening, k.nama_bank, k.npwp, k.alamat_email,k.join_date, k.status_karyawan
                    FROM payroll p 
                    JOIN karyawan k ON k.id_karyawan=p.id_karyawan
                    WHERE p.id=? LIMIT 1");
$q->bind_param("i", $id);
$q->execute();
$r = $q->get_result()->fetch_assoc();
$q->close();
if (!$r)
  exit('Slip tidak ditemukan');

// decode komponen (flat array)
$comp = json_decode($r['components_json'], true) ?: [];

// Pisahkan pendapatan & potongan
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
$pendapatan = [];
$potongan = [];
foreach ($comp as $k => $v) {
  if (in_array($k, $POTONGAN, true))
    $potongan[$k] = $v;
  else
    $pendapatan[$k] = $v;
}

// Data sidebar
$id_karyawan_admin = $_SESSION['id_karyawan'];
$nama_user_admin = $_SESSION['nama'];
$role_user_admin = $_SESSION['role'];
$sql_pending_requests = "SELECT COUNT(*) AS total_pending FROM pengajuan WHERE status_pengajuan = 'Menunggu'";
$result_pending_requests = $conn->query($sql_pending_requests);
$total_pending = $result_pending_requests->fetch_assoc()['total_pending'] ?? 0;

$stmt_admin_info = $conn->prepare("SELECT nik_ktp, jabatan FROM karyawan WHERE id_karyawan = ?");
$nik_user_admin = 'Tidak Ditemukan';
$jabatan_user_admin = 'Tidak Ditemukan';
if ($stmt_admin_info) {
  $stmt_admin_info->bind_param("i", $id_karyawan_admin);
  $stmt_admin_info->execute();
  $result_admin_info = $stmt_admin_info->get_result();
  $admin_info = $result_admin_info->fetch_assoc();
  if ($admin_info) {
    $nik_user_admin = $admin_info['nik_ktp'];
    $jabatan_user_admin = $admin_info['jabatan'];
  }
  $stmt_admin_info->close();
}
$conn->close();

$periode = date('F Y', strtotime($r['periode_tahun'] . '-' . $r['periode_bulan'] . '-01'));


?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <title>Slip Gaji</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link rel="stylesheet" href="../style.css">
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      margin: 0;
      background: #f5f6fa
    }

    .container {
      display: flex;
      min-height: 100vh
    }

    .main {
      flex: 1;
      padding: 30px
    }

    .slip {
      max-width: 800px;
      margin: auto;
      background: #fff;
      border-radius: 10px;
      padding: 25px;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1)
    }

    /* Pastikan CSS ini ada di bagian <style> Anda */
    .header {
      display: flex;
      align-items: center;
      /* PENTING: Untuk meratakan vertikal logo dan teks */
      border-bottom: 2px solid #eee;
      padding-bottom: 10px;
      margin-bottom: 20px;
      /* Hilangkan justify-content: space-between */
    }

    .header .logo {
      width: 60px;
      /* Sedikit diperbesar dari 50px */
      height: auto;
      margin-right: 15px;
      /* Tambahkan jarak antara logo dan teks */
    }

    .header .title-block {
      /* Container untuk nama PT dan alamat */
      line-height: 1.2;
    }

    .header h2 {
      margin: 0;
      font-size: 16pt;
      /* Font ukuran besar untuk PT */
      font-weight: bold;
      color: #000;
      display: inline;
      /* Agar tidak ada baris baru */
    }

    .header .address {
      font-size: 10pt;
      color: #333;
      margin-top: 5px;
      line-height: 1.4;
    }

    /* Hapus .company-info yang sebelumnya ada di kanan */
    .company-info {
      display: none;
    }

    .info-table {
      width: 100%;
      font-size: 14px;
      margin-bottom: 20px;
      border-collapse: collapse
    }

    .info-table td {
      padding: 4px 8px;
      vertical-align: top
    }

    .box-container {
      display: flex;
      gap: 20px
    }

    .box {
      flex: 1;
      background: #fafafa;
      padding: 15px;
      border-radius: 10px
    }

    .box h3 {
      margin: 0 0 10px;
      font-size: 16px;
      color: #333;
      border-bottom: 1px solid #eee;
      padding-bottom: 5px
    }

    .row {
      display: flex;
      justify-content: space-between;
      margin: 5px 0;
      font-size: 14px
    }

    .total {
      font-weight: bold;
      border-top: 1px solid #ccc;
      padding-top: 8px;
      margin-top: 8px
    }

    .thp {
      text-align: center;
      margin-top: 25px
    }

    .thp .amount {
      font-size: 26px;
      font-weight: bold;
      color: #0a0
    }

    .footer {
      margin-top: 25px;
      font-size: 13px;
      color: #555;
      border-top: 1px dashed #ddd;
      padding-top: 10px
    }

    .badge {
      background: #ef4444;
      color: #fff;
      padding: 2px 8px;
      border-radius: 999px;
      font-size: 12px
    }

    .sidebar-nav .dropdown-trigger {
      position: relative
    }

    .sidebar-nav .dropdown-link {
      display: flex;
      justify-content: space-between;
      align-items: center
    }

    .sidebar-nav .dropdown-menu {
      display: none;
      position: absolute;
      top: 100%;
      left: 0;
      min-width: 200px;
      background: #2c3e50;
      box-shadow: 0 4px 8px rgba(0, 0, 0, .2);
      padding: 0;
      margin: 0;
      list-style: none;
      z-index: 11;
      border-radius: 0 0 8px 8px;
      overflow: hidden
    }

    .sidebar-nav .dropdown-menu li a {
      padding: 12px 20px;
      display: block;
      color: #ecf0f1;
      text-decoration: none;
      transition: background-color .3s
    }

    .sidebar-nav .dropdown-menu li a:hover {
      background: #34495e
    }

    .sidebar-nav .dropdown-trigger:hover .dropdown-menu {
      display: block
    }
  </style>
</head>

<body>
  <?php if (!$is_download_mode): ?>
    <div class="container">
      <aside class="sidebar">

        <div>
          <div class="company-brand">
            <img src="../image/manu.png" alt="Logo PT Mandiri Andalan Utama" class="company-logo">
            <p class="company-name">PT Mandiri Andalan Utama</p>
          </div>
          <div class="user-info">
            <div class="user-avatar"><?= e(strtoupper(substr($nama_user_admin, 0, 2))) ?></div>
            <div class="user-details">
              <p class="user-name"><?= e($nama_user_admin) ?></p>
              <p class="user-id"><?= e($nik_user_admin) ?></p>
              <p class="user-role"><?= e($role_user_admin) ?></p>
            </div>
          </div>
          <nav class="sidebar-nav">
            <ul>
              <li><a href="../dashboard_admin.php"><i class="fas fa-home"></i> Dashboard</a></li>
              <li><a href="../absensi/absensi.php"><i class="fas fa-edit"></i> Absensi </a></li>
              <li class="dropdown-trigger">
                <a href="#" class="dropdown-link"><i class="fas fa-users"></i> Data Karyawan <i
                    class="fas fa-caret-down"></i></a>
                <ul class="dropdown-menu">
                  <li><a href="../data_karyawan/all_employees.php">Semua Karyawan</a></li>
                  <li><a href="../data_karyawan/karyawan_nonaktif.php">Non-Aktif</a></li>
                </ul>
              </li>
              <li class="dropdown-trigger">
                <a href="#" class="dropdown-link"><i class="fas fa-users"></i> Data Pengajuan <i
                    class="fas fa-caret-down"><span class="badge"><?= $total_pending ?></span></i></a>
                <ul class="dropdown-menu">
                  <li><a href="../pengajuan/pengajuan.php">Pengajuan</a></li>
                  <li><a href="../pengajuan/kelola_pengajuan.php">Kelola Pengajuan<span
                        class="badge"><?= $total_pending ?></span></a></li>
                  <li><a href="../pengajuan/kelola_reimburse.php">Kelola Reimburse<span
                        class="badge"><?= $total_pending ?></span></a></li>
                </ul>
              </li>
              <li><a href="../monitoring_kontrak/monitoring_kontrak.php"><i class="fas fa-calendar-alt"></i> Monitoring
                  Kontrak</a></li>
              <li><a href="../monitoring_kontrak/surat_tugas_history.php"><i class="fas fa-file-alt"></i>
                  Riwayat Surat Tugas</a></li>
              <li class="active"><a href="#"><i class="fas fa-money-check-alt"></i> E-Pay Slip</a></li>
              <li><a href="../invoice/invoice.php"><i class="fas fa-file-invoice"></i> Invoice</a></li>
            </ul>
          </nav>
          <div class="logout-link">
            <a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a>
          </div>
        </div>
      </aside>

      <main class="main">
        <div style="text-align: right; margin-bottom: 10px;" id="statusBadge">
          <?php if ($r['is_email_sent']): ?>
            <span
              style="background-color: #28a745; color: white; padding: 5px 10px; border-radius: 5px; font-weight: bold;">
              <i class="fas fa-envelope"></i> Sudah Dikirim
            </span>
          <?php else: ?>
            <span
              style="background-color: #ffc107; color: black; padding: 5px 10px; border-radius: 5px; font-weight: bold;">
              <i class="fas fa-hourglass-half"></i> Belum Dikirim
            </span>
          <?php endif; ?>
        </div>
        <div style="text-align:right;margin-bottom:15px">
          <button onclick="downloadSlipAsPDF()" style="padding:8px 15px;background:#3498db;color:#fff;
                    border:none;border-radius:5px;cursor:pointer;">
            <i class="fas fa-file-pdf"></i> Download PDF
          </button>
          <button onclick="sendSlipAsEmail()" style="padding:8px 15px;background:#3498db;color:#fff;
            border:none;border-radius:5px;cursor:pointer;">
            <i class="fas fa-envelope"></i> Kirim Slip ke Email
          </button>
        </div>
        <div id="emailStatus" style="margin-top: 15px; text-align: right; font-weight: bold;"></div>
      <?php endif; // END IF !$is_download_mode ?>

      <?php if ($is_download_mode): // Jika dalam mode download, wrap konten di <body> ?>
        <div style="width: 800px; margin: 0 auto;">
        <?php endif; ?>

        <div class="slip">
          <div class="slip">
            <div class="header">
              <img src="../image/manu.png" alt="Logo PT Mandiri Andalan Utama" class="logo">
              <div class="title-block">
                <h2>
                  PT <span style="color:red;">M</span>andiri <span style="color:red;">A</span>ndala<span
                    style="color:red;">N</span> <span style="color:red;">U</span>tama
                </h2>
                <div class="address">
                  Jl. Sultan Iskandar Muda No. 30 A - B<br>
                  Kebayoran Lama Selatan - Kebayoran Lama Jakarta Selatan<br>
                  Telp : (021) 275 18 306<br>
                  www.manu.co.id
                </div>
              </div>
            </div>

            <table class="info-table">
              <tr>
                <td><b>NIK</b></td>
                <td>: <?= e($r['nik_ktp'] ?? '-') ?></td>
                <td><b>Status Pegawai</b></td>
                <td>: <?= e($r['status_karyawan'] ?? '-') ?></td>
              </tr>
              <tr>
                <td><b>Nama</b></td>
                <td>: <?= e($r['nama_karyawan'] ?? '-') ?></td>
                <td><b>No. Rekening</b></td>
                <td>: <?= e($r['nomor_rekening'] ?? '-') ?></td>
              </tr>
              <tr>
                <td><b>Jabatan</b></td>
                <td>: <?= e($r['jabatan'] ?? '-') ?></td>
                <td><b>Bank</b></td>
                <td>: <?= e($r['nama_bank'] ?? '-') ?></td>
              </tr>
              <tr>
                <td><b>Penempatan</b></td>
                <td>: <?= e($r['proyek'] ?? '-') ?> - <?= e($r['cabang'] ?? '-') ?></td>
                <td><b>NPWP</b></td>
                <td>: <?= e($r['npwp'] ?? '-') ?></td>
              </tr>
              <tr>
                <td><b>Join Date</b></td>
                <td>: <?= e($r['join_date'] ?? '-') ?></td>
                <td><b>Bulan</b></td>
                <td>: <?= $periode ?></td>
              </tr>
            </table>

            <div class="box-container">
              <div class="box">
                <h3>Pendapatan</h3>
                <?php foreach ($pendapatan as $k => $v): ?>
                  <div class="row"><span><?= e($k) ?></span><span>Rp. <?= number_format($v, 0, ',', '.') ?></span></div>
                <?php endforeach; ?>
                <div class="row total"><span>Total Pendapatan</span><span>Rp.
                    <?= number_format($r['total_pendapatan'] ?? 0, 0, ',', '.') ?></span></div>
              </div>
              <div class="box">
                <h3>Potongan</h3>
                <?php foreach ($potongan as $k => $v): ?>
                  <div class="row"><span><?= e($k) ?></span><span>Rp. <?= number_format($v, 0, ',', '.') ?></span></div>
                <?php endforeach; ?>
                <div class="row total"><span>Total Potongan</span><span>Rp.
                    <?= number_format($r['total_potongan'] ?? 0, 0, ',', '.') ?></span></div>
              </div>
            </div>

            <div class="thp">
              <h2>Total Penerimaan (Take-Home Pay)</h2>
              <div class="amount">Rp. <?= number_format($r['total_payroll'] ?? 0, 0, ',', '.') ?></div>
            </div>

            <div class="footer">
              Pembayaran gaji telah ditransfer ke rekening:<br>
              <b><?= e($r['nama_bank'] ?? '-') ?> - <?= e($r['nomor_rekening'] ?? '-') ?></b>
            </div>

          </div> <?php if ($is_download_mode): ?>
          </div>
      </main> <?php endif; ?>

    <?php if (!$is_download_mode): ?>
      </main>
    </div> <?php endif; ?>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.3/html2pdf.bundle.min.js"></script>
  <script>
    function downloadSlipAsPDF() {
      const element = document.querySelector('.slip');
      const periode = "<?= $periode ?>"; // Ambil periode dari PHP
      const namaFile = `Slip_Gaji_<?= $r['nik_ktp'] ?? 'unknown' ?>_${periode.replace(' ', '_')}.pdf`;

      const opt = {
        margin: [5, 5, 5, 5],
        filename: namaFile,
        image: { type: 'jpeg', quality: 0.9 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
      };
      html2pdf().from(element).set(opt).save();
    }

    // =================================================================
    // FUNGSI PERBAIKAN: sendSlipAsEmail()
    // =================================================================
    function sendSlipAsEmail() {
      const element = document.querySelector('.slip');
      const emailStatus = document.getElementById('emailStatus');
      const btn = document.querySelector('button[onclick="sendSlipAsEmail()"]');
      const originalText = btn.innerHTML;

      // Data dari PHP
      const emailTujuan = "<?= $r['alamat_email'] ?? '' ?>";
      const slipId = "<?= $r['id'] ?? 0 ?>";
      const periode = "<?= $periode ?>";
      const namaFile = `Slip_Gaji_${"<?= $r['nik_ktp'] ?? 'unknown' ?>"}_${periode.replace(' ', '_')}.pdf`;

      if (emailTujuan === '') {
        emailStatus.innerHTML = '<span style="color:#c0392b; font-weight: bold;">[!] Email Karyawan tidak ditemukan.</span>';
        return;
      }

      if (!confirm(`Kirim slip gaji periode ${periode} ke email ${emailTujuan}?`)) return;

      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
      emailStatus.innerHTML = '';

      const opt = {
        margin: [5, 5, 5, 5],
        filename: namaFile,
        image: { type: 'jpeg', quality: 0.9 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
      };

      const worker = html2pdf().from(element).set(opt);

      worker.output('blob').then(function (pdfBlob) {
        const formData = new FormData();
        formData.append('slip_pdf', pdfBlob, namaFile);
        formData.append('id_slip', slipId);
        formData.append('email_tujuan', emailTujuan);

        // Fetch ke endpoint yang akan menangani pengiriman email (Anda harus membuat file ini!)
        return fetch('send_slip_email.php', {
          method: 'POST',
          body: formData
        });
      }).then(res => res.text()).then(result => {
        btn.disabled = false;
        btn.innerHTML = originalText;

        // Logika respons dari send_slip_email.php
        if (result && result.toLowerCase().includes('berhasil')) {
          emailStatus.innerHTML = '<span style="color:#28a745; font-weight: bold;"><i class="fas fa-check-circle"></i> Berhasil dikirim!</span>';
          document.getElementById('statusBadge').innerHTML = '<span style="background-color: #28a745; color: white; padding: 5px 10px; border-radius: 5px; font-weight: bold;"><i class="fas fa-envelope"></i> Sudah Dikirim</span>';
        } else {
          emailStatus.innerHTML = '<span style="color:#c0392b; font-weight: bold;"><i class="fas fa-exclamation-triangle"></i> Gagal Kirim: ' + (result || 'Kesalahan Server') + '</span>';
        }
      }).catch(err => {
        console.error(err);
        emailStatus.innerHTML = '<span style="color:#c0392b; font-weight: bold;"><i class="fas fa-times-circle"></i> Kesalahan Jaringan/Klien.</span>';
        btn.disabled = false;
        btn.innerHTML = originalText;
      });
    }

    document.addEventListener('DOMContentLoaded', function () {
      const urlParams = new URLSearchParams(window.location.search);
      // HANYA trigger download jika parameter 'download' ada (dan diasumsikan user adalah pemilik slip)
      if (urlParams.get('download') === 'true') {
        // Sembunyikan tombol kirim/download di mode download
        const controlsDiv = document.querySelector('div[style="text-align:right;margin-bottom:15px"]');
        if (controlsDiv) controlsDiv.style.display = 'none';

        const statusBadge = document.querySelector('#statusBadge');
        if (statusBadge) statusBadge.style.display = 'none';

        // Panggil fungsi unduh
        downloadSlipAsPDF();

        // Hapus parameter download dari URL agar tidak terjadi loop jika user refresh
        const newUrl = window.location.pathname + window.location.search.replace(/&?download=true/, '');
        history.replaceState({}, document.title, newUrl);
      }
    });
  </script>
</body>

</html>