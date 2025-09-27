<?php
// ===========================
// payslip/view_payroll.php
// ===========================
require '../config.php';
if (session_status() === PHP_SESSION_NONE)
  session_start();

if (!isset($_SESSION['id_karyawan']) || !in_array($_SESSION['role'] ?? '', ['HRD', 'ADMIN', 'Admin', 'admin'])) {
  exit('Unauthorized');
}

$id = (int) ($_GET['id'] ?? 0);
$q = $conn->prepare("SELECT p.*, k.nama_karyawan, k.nik_ktp, k.jabatan, k.proyek, k.status_karyawan, k.cabang, k.nomor_rekening, k.nama_bank, k.npwp, k.join_date
                     FROM payroll p JOIN karyawan k ON k.id_karyawan=p.id_karyawan
                     WHERE p.id=? LIMIT 1");
$q->bind_param("i", $id);
$q->execute();
$r = $q->get_result()->fetch_assoc();
$q->close();
if (!$r)
  exit('Slip tidak ditemukan');
$comp = json_decode($r['components_json'], true) ?: [];
// Ambil data user dari sesi untuk sidebar
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

// Format bulan dan tahun
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
      background: #f5f6fa;
    }

    .container {
      display: flex;
      min-height: 100vh;
    }

    .main {
      flex: 1;
      padding: 30px;
    }

    .slip {
      max-width: 800px;
      margin: auto;
      background: #fff;
      border-radius: 10px;
      padding: 25px;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
    }

    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 2px solid #eee;
      padding-bottom: 10px;
      margin-bottom: 20px
    }

    .header .left {
      display: flex;
      align-items: center;
      gap: 10px
    }

    .header .logo {
      width: 50px;
      height: auto
    }

    .header h2 {
      margin: 0;
      font-size: 22px;
      color: #333
    }

    .company-info {
      text-align: right;
      font-size: 13px;
      color: #555;
      line-height: 1.4
    }

    .info-table {
      width: 100%;
      font-size: 14px;
      margin-bottom: 20px;
      border-collapse: collapse;
    }

    .info-table td {
      padding: 4px 8px;
      vertical-align: top;
    }

    .info-table b {
      color: #333;
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

    .sidebar-nav .dropdown-trigger {
      position: relative;
    }

    .sidebar-nav .dropdown-link {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .sidebar-nav .dropdown-menu {
      display: none;
      position: absolute;
      top: 100%;
      left: 0;
      min-width: 200px;
      background-color: #2c3e50;
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
      padding: 0;
      margin: 0;
      list-style: none;
      z-index: 1000;
      border-radius: 0 0 8px 8px;
      overflow: hidden;
    }

    .sidebar-nav .dropdown-menu li a {
      padding: 12px 20px;
      display: block;
      color: #ecf0f1;
      text-decoration: none;
      transition: background-color 0.3s;
    }

    .sidebar-nav .dropdown-menu li a:hover {
      background-color: #34495e;
    }

    .sidebar-nav .dropdown-trigger:hover .dropdown-menu {
      display: block;
    }

    .badge {
      background: #ef4444;
      color: #fff;
      padding: 2px 8px;
      border-radius: 999px;
      font-size: 12px;
    }
    .filter-container {
  background: #fff;
  padding: 15px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
  margin-bottom: 20px;
  display: flex;
  justify-content: flex-end; /* Mengatur posisi filter ke kanan */
  align-items: center;
  gap: 10px;
}

.filter-container form {
  display: flex;
  align-items: center;
  gap: 10px;
}

.filter-container label {
  font-weight: bold;
  color: #555;
  font-size: 14px;
}

.filter-container select,
.filter-container button {
  padding: 8px 12px;
  border: 1px solid #ddd;
  border-radius: 5px;
  font-size: 14px;
  transition: all 0.3s ease;
}

.filter-container select:focus,
.filter-container button:focus {
  outline: none;
  border-color: #3498db;
  box-shadow: 0 0 5px rgba(52, 152, 219, 0.3);
}

.filter-container button {
  background: #3498db;
  color: #fff;
  cursor: pointer;
}

.filter-container button:hover {
  background: #2980b9;
}

  </style>
</head>

<body>
  <div class="container">
    <!-- Sidebar -->
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
              <a href="#" class="dropdown-link"><i class="fas fa-file-alt"></i> Data Pengajuan<span
                  class="badge"><?= $total_pending ?? 0 ?></span> <i class="fas fa-caret-down"></i></a>
              <ul class="dropdown-menu">
                <li><a href="../pengajuan/pengajuan.php">Pengajuan</a></li>
                <li><a href="../pengajuan/kelola_pengajuan.php">Kelola Pengajuan<span
                      class="badge"><?= $total_pending ?? 0 ?></span></a></li>
              </ul>
            </li>
            <li><a href="../monitoring_kontrak/monitoring_kontrak.php"><i class="fas fa-calendar-alt"></i> Monitoring
                Kontrak</a></li>
            <li class="active"><a href="#"><i class="fas fa-money-check-alt"></i> E-Pay Slip</a></li>
            <li><a href="../invoice/invoice.php"><i class="fas fa-file-invoice"></i> Invoice</a></li>
          </ul>
        </nav>
        <div class="logout-link">
          <a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a>
        </div>
      </div>
    </aside>

    <!-- Main content -->
    <main class="main">
    <div class="filter-container" style="text-align: right; margin-bottom: 20px;">
        <form action="" method="GET" style="display: inline-block; margin-right: 10px;">
            <label for="month">Bulan:</label>
            <select name="bulan" id="month">
                <?php
                $nama_bulan = [
                    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
                ];
                $selected_month = (int) ($_GET['bulan'] ?? date('m'));
                $selected_year = (int) ($_GET['tahun'] ?? date('Y'));
                
                foreach ($nama_bulan as $num => $name) {
                    $selected = ($num == $selected_month) ? 'selected' : '';
                    echo "<option value=\"$num\" $selected>$name</option>";
                }
                ?>
            </select>

            <label for="year">Tahun:</label>
            <select name="tahun" id="year">
                <?php
                $current_year = date('Y');
                for ($year = $current_year; $year >= 2020; $year--) {
                    $selected = ($year == $selected_year) ? 'selected' : '';
                    echo "<option value=\"$year\" $selected>$year</option>";
                }
                ?>
            </select>
            <input type="hidden" name="id" value="<?= htmlspecialchars($_GET['id'] ?? '') ?>">
            <button type="submit" style="padding:8px 15px; background:#2ecc71; color:#fff; border:none; border-radius:5px; cursor:pointer;">
                <i class="fas fa-filter"></i> Filter
            </button>
        </form>
        <button onclick="downloadSlipAsPDF()"
            style="padding:8px 15px; background:#3498db; color:#fff; border:none; border-radius:5px; cursor:pointer;">
            <i class="fas fa-file-pdf"></i> Download PDF
        </button>
    </div>

    <?php if (!$r['nama_karyawan']): ?>
        <div style="text-align:center; padding: 50px; background: #fff; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1);">
            <i class="fas fa-folder-open" style="font-size: 50px; color: #95a5a6;"></i>
            <p style="margin-top: 20px; font-size: 18px; color: #555;">Belum ada slip gaji untuk bulan ini.</p>
        </div>
    <?php else: ?>
        <div class="slip">
            <div class="header">
                <div class="left">
                    <img src="../image/manu.png" alt="Logo" class="logo">
                    <h2>Slip Gaji</h2>
                </div>
                <div class="company-info">
                    <b>PT Mandiri Andalan Utama</b><br>
                    Jl. Sultan Iskandar Muda No.30 A-B <br>
                    Kebayoran Lama, Jakarta Selatan <br>
                    Telp : (021) 275 18 306<br>
                    www.manu.co.id
                </div>
            </div>

            <table class="info-table">
                <tr>
                    <td><b>NIK</b></td>
                    <td>: <?= htmlspecialchars($r['nik_ktp'] ?? '-') ?></td>
                    <td><b>Status Pegawai</b></td>
                    <td>: <?= htmlspecialchars($r['status_karyawan'] ?? '-') ?></td>
                </tr>
                <tr>
                    <td><b>Nama</b></td>
                    <td>: <?= htmlspecialchars($r['nama_karyawan'] ?? '-') ?></td>
                    <td><b>No. Rekening</b></td>
                    <td>: <?= htmlspecialchars($r['nomor_rekening'] ?? '-') ?></td>
                </tr>
                <tr>
                    <td><b>Jabatan</b></td>
                    <td>: <?= htmlspecialchars($r['jabatan'] ?? '-') ?></td>
                    <td><b>Bank</b></td>
                    <td>: <?= htmlspecialchars($r['nama_bank'] ?? '-') ?></td>
                </tr>
                <tr>
                    <td><b>Penempatan</b></td>
                    <td>: <?= htmlspecialchars($r['proyek'] ?? '-') ?> - <?= htmlspecialchars($r['cabang'] ?? '-') ?></td>
                    <td><b>NPWP</b></td>
                    <td>: <?= htmlspecialchars($r['npwp'] ?? '-') ?></td>
                </tr>
                <tr>
                    <td><b>Join Date</b></td>
                    <td>: <?= htmlspecialchars($r['join_date'] ?? '-') ?></td>
                    <td><b>Bulan</b></td>
                    <td>: <?= $periode ?></td>
                </tr>
            </table>

            <div class="box-container">
                <div class="box">
                    <h3>Pendapatan</h3>
                    <?php foreach (($comp['pendapatan'] ?? []) as $k => $v): ?>
                        <div class="row"><span><?= htmlspecialchars($k) ?></span><span>Rp. <?= number_format($v, 0, ',', '.') ?></span></div>
                    <?php endforeach; ?>
                    <div class="row total"><span>Total Pendapatan</span><span>Rp. <?= number_format($r['total_pendapatan'] ?? 0, 0, ',', '.') ?></span></div>
                </div>
                <div class="box">
                    <h3>Potongan</h3>
                    <?php foreach (($comp['potongan'] ?? []) as $k => $v): ?>
                        <div class="row"><span><?= htmlspecialchars($k) ?></span><span>Rp. <?= number_format($v, 0, ',', '.') ?></span></div>
                    <?php endforeach; ?>
                    <div class="row total"><span>Total Potongan</span><span>Rp. <?= number_format($r['total_potongan'] ?? 0, 0, ',', '.') ?></span></div>
                </div>
            </div>

            <div class="thp">
                <h2>Total Penerimaan (Take-Home Pay)</h2>
                <div class="amount">Rp. <?= number_format($r['total_payroll'] ?? 0, 0, ',', '.') ?></div>
            </div>

            <div class="footer">
                Pembayaran gaji telah ditransfer ke rekening:<br>
                <b><?= htmlspecialchars($r['nama_bank'] ?? '-') ?> - <?= htmlspecialchars($r['nomor_rekening'] ?? '-') ?></b>
            </div>
        </div>
    <?php endif; ?>
</main>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script>
      function downloadSlipAsPDF() {
        const { jsPDF } = window.jspdf;
        let slip = document.querySelector('.slip'); // ambil div slip gaji

        html2canvas(slip, { scale: 2 }).then(canvas => {
          let imgData = canvas.toDataURL('image/png');
          let pdf = new jsPDF('p', 'mm', 'a4');

          // hitung skala agar muat di halaman A4
          let pdfWidth = pdf.internal.pageSize.getWidth();
          let pdfHeight = (canvas.height * pdfWidth) / canvas.width;

          pdf.addImage(imgData, 'PNG', 0, 0, pdfWidth, pdfHeight);
          pdf.save("Slip-Gaji.pdf");
        });
      }
    </script>
  </div>
</body>

</html>