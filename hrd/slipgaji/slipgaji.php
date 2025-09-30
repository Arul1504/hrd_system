<?php
// ===========================
// hrd/slipgaji/slipgaji.php
// ===========================


// ===========================
// hrd/slipgaji/slipgaji.php
// ===========================

require __DIR__ . '/../config.php'; // di sini session_start() sudah otomatis jalan

// --- CEK SESSION (hanya HRD atau ADMIN) ---
$role = strtoupper($_SESSION['role'] ?? '');
if (!isset($_SESSION['id_karyawan']) || !in_array($role, ['HRD', 'ADMIN'])) {
  header('Location: ../../index.php');
  exit();
}

$id_hrd = (int) $_SESSION['id_karyawan'];
$nama_hrd = $_SESSION['nama'] ?? '';
$role_hrd = $_SESSION['role'] ?? '';
$sql_pending_requests = "SELECT COUNT(*) AS total_pending FROM pengajuan WHERE status_pengajuan = 'Menunggu'";
$result_pending_requests = $conn->query($sql_pending_requests);
$total_pending = $result_pending_requests->fetch_assoc()['total_pending'] ?? 0;


// Ambil info dasar HRD/ADMIN (untuk header/sidebar)
$stmt = $conn->prepare("SELECT nik_ktp, jabatan FROM karyawan WHERE id_karyawan=? LIMIT 1");
$stmt->bind_param("i", $id_hrd);
$stmt->execute();
$info = $stmt->get_result()->fetch_assoc() ?: ['nik_ktp' => '', 'jabatan' => ''];
$stmt->close();

// Filter karyawan
$filter_karyawan = isset($_GET['id_karyawan']) ? (int) $_GET['id_karyawan'] : 0;

// Ambil daftar karyawan aktif (untuk dropdown pilih karyawan)
$employees = [];
$res_emp = $conn->query("SELECT id_karyawan, nama_karyawan 
                         FROM karyawan 
                         WHERE (status IS NULL OR status='' OR UPPER(status) <> 'TIDAK AKTIF')
                         ORDER BY nama_karyawan ASC");
if ($res_emp) {
  $employees = $res_emp->fetch_all(MYSQLI_ASSOC);
}

// Filter tahun (opsional)
$filter_tahun = isset($_GET['year']) && ctype_digit($_GET['year']) ? (int) $_GET['year'] : 0;

// Ambil daftar tahun yang tersedia (jika karyawan dipilih)
$years = [];
if ($filter_karyawan > 0) {
  $yrq = $conn->prepare("SELECT DISTINCT periode_tahun 
                         FROM payroll 
                         WHERE id_karyawan=? 
                         ORDER BY periode_tahun DESC");
  $yrq->bind_param("i", $filter_karyawan);
  $yrq->execute();
  $yrres = $yrq->get_result();
  while ($row = $yrres->fetch_assoc()) {
    $years[] = (int) $row['periode_tahun'];
  }
  $yrq->close();
}

// Ambil slip gaji (jika ada karyawan dipilih)
$slips = [];
if ($filter_karyawan > 0) {
  if ($filter_tahun > 0) {
    $q = $conn->prepare("SELECT * FROM payroll 
                         WHERE id_karyawan=? AND periode_tahun=? 
                         ORDER BY periode_tahun DESC, periode_bulan DESC");
    $q->bind_param("ii", $filter_karyawan, $filter_tahun);
  } else {
    $q = $conn->prepare("SELECT * FROM payroll 
                         WHERE id_karyawan=? 
                         ORDER BY periode_tahun DESC, periode_bulan DESC");
    $q->bind_param("i", $filter_karyawan);
  }
  $q->execute();
  $res = $q->get_result();
  $slips = $res->fetch_all(MYSQLI_ASSOC);
  $q->close();
}

$conn->close();

// Helper nama bulan
function bulanNama($b)
{
  $bulan = [
    1 => 'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember'
  ];
  $b = (int) $b;
  return $bulan[$b] ?? $b;
}
?>

<!DOCTYPE html>
<html lang="id">
<style>
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
  .badge {
    background: #ef4444;
    color: #fff;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 12px;
  }
</style>

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Slip Gaji - HRD</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
  <link rel="stylesheet" href="../style.css" />
</head>

<body>
  <div class="container">
    <!-- Sidebar -->
    <aside class="sidebar">
      <div>
        <div class="company-brand">
          <img src="../image/manu.png" alt="Logo" class="company-logo">
          <p class="company-name">PT Mandiri Andalan Utama</p>
        </div>
        <div class="user-info">
          <div class="user-avatar"><?= strtoupper(substr($nama_hrd, 0, 2)) ?></div>
          <div>
            <p class="user-name"><?= htmlspecialchars($nama_hrd) ?></p>
            <p class="user-id"><?= htmlspecialchars($info['nik_ktp'] ?? '') ?></p>
            <p class="user-role"><?= htmlspecialchars($info['jabatan'] ?? '') ?></p>
          </div>
        </div>
        <nav class="sidebar-nav">
          <ul>
             <li><a href="../dashboard_hrd.php"><i class="fas fa-home"></i> Dashboard</a></li>
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
                        <a href="#" class="dropdown-link"><i class="fas fa-users"></i> Data Pengajuan<span
                                class="badge"><?= $total_pending ?></span> <i class="fas fa-caret-down"></i></a>
                        <ul class="dropdown-menu">
                            <li><a href="../pengajuan/pengajuan.php">Pengajuan</a></li>
                            <li><a href="../pengajuan/kelola_pengajuan.php">Kelola Pengajuan<span
                                        class="badge"><?= $total_pending ?></span></a></li>
                        </ul>
                </li>
                    <li><a href="../monitoring_kontrak/monitoring_kontrak.php"><i class="fas fa-calendar-alt"></i>
                            Monitoring Kontrak</a></li>
            <li class="active"><a href="../slipgaji.php"><i class="fas fa-file-invoice"></i> Slip Gaji</a></li>
          </ul>
        </nav>
      </div>
      <div class="logout-link">
        <a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a>
      </div>
    </aside>

    <!-- Content -->
    <main class="main-content">
      <header class="main-header">
        <h1>Slip Gaji (HRD)</h1>
        <p class="current-date"><?= date('l, d F Y') ?></p>
      </header>

      <section class="card">
        <h2>Data Slip Gaji</h2>
        <p class="muted">Halo, <b><?= htmlspecialchars($nama_hrd) ?></b>. Pilih karyawan untuk melihat slip gaji:</p>

        <div class="toolbar">
          <form method="get" style="display:flex;gap:10px;align-items:center;">
            <label for="id_karyawan">Karyawan:&nbsp;</label>
            <select id="id_karyawan" name="id_karyawan" class="select" onchange="this.form.submit()">
              <option value="">-- Pilih Karyawan --</option>
              <?php foreach ($employees as $e): ?>
                <option value="<?= $e['id_karyawan'] ?>" <?= $filter_karyawan === $e['id_karyawan'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($e['nama_karyawan']) ?>
                </option>
              <?php endforeach; ?>
            </select>

            <?php if ($filter_karyawan > 0): ?>
              <label for="year">Tahun:&nbsp;</label>
              <select id="year" name="year" class="select" onchange="this.form.submit()">
                <option value="">-- Semua Tahun --</option>
                <?php foreach ($years as $y): ?>
                  <option value="<?= $y ?>" <?= $filter_tahun === $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endforeach; ?>
              </select>
            <?php endif; ?>
          </form>
        </div>

        <?php if ($filter_karyawan > 0): ?>
          <div style="overflow:auto">
            <table>
              <thead>
                <tr>
                  <th>Periode</th>
                  <th>Total Pendapatan</th>
                  <th>Total Potongan</th>
                  <th>Take Home Pay</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($slips)): ?>
                  <tr>
                    <td colspan="5" style="text-align:center">Belum ada slip gaji</td>
                  </tr>
                <?php else:
                  foreach ($slips as $s): ?>
                    <tr>
                      <td><?= bulanNama($s['periode_bulan']) . ' ' . $s['periode_tahun'] ?></td>
                      <td><?= number_format((int) $s['total_pendapatan'], 0, ',', '.') ?></td>
                      <td><?= number_format((int) $s['total_potongan'], 0, ',', '.') ?></td>
                      <td><b><?= number_format((int) $s['total_payroll'], 0, ',', '.') ?></b></td>
                      <td>
                        <a class="btn" href="../../admin/payslip/export_payroll_pdf.php?id=<?= (int) $s['id'] ?>"
                          target="_blank">Unduh PDF</a>
                      </td>
                    </tr>
                  <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p style="text-align:center;margin-top:20px">Silakan pilih karyawan untuk menampilkan slip gaji.</p>
        <?php endif; ?>
      </section>
    </main>
  </div>
</body>

</html>