<?php
// ===========================
// payslip/e_payslip_hrd.php
// ===========================
require '../config.php';

// Fungsi helper untuk mencegah XSS
if (!function_exists('e')) {
    function e($value)
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['id_karyawan']) || !in_array($_SESSION['role'] ?? '', ['HRD', 'ADMIN', 'Admin', 'admin'])) {
    header("Location: ../../index.php");
    exit();
}

// Ambil data karyawan aktif
$q = "SELECT id_karyawan, nik_karyawan, nama_karyawan, jabatan, proyek
      FROM karyawan
      WHERE (status IS NULL OR status='' OR UPPER(status) <> 'TIDAK AKTIF')
      ORDER BY nama_karyawan ASC";
$employees = [];
$res = $conn->query($q);
if ($res) {
    $employees = $res->fetch_all(MYSQLI_ASSOC);
}

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

// Tutup koneksi setelah semua operasi database selesai
$conn->close();

// Komponen payslip (gabungan unik)
$KOMPONEN = [
    "Gaji Pokok", "Rapel Gaji Bulan Sebelumnya", "Tunjangan Kesehatan", "Tunjangan Kehadiran",
    "Tunjangan Komunikasi", "Tunjangan Transportasi", "Tunjangan Jabatan", "Incentive",
    "Bonus Performance", "Konsistensi", "Booster", "Reimbursment", "Pengembalian Incentive",
    "Tunjangan Hari Raya", "Lembur", "Biaya Admin",
    "Total tax (PPh21)", "BPJS Kesehatan", "BPJS Ketenagakerjaan", "Dana Pensiun",
    "Keterlambatan Kehadiran", "Potongan Lainnya", "Potongan Loan (Mobil/Motor/Lainnya/SPPI)"
];
$POTONGAN = [
    "Total tax (PPh21)", "BPJS Kesehatan", "BPJS Ketenagakerjaan", "Dana Pensiun",
    "Keterlambatan Kehadiran", "Potongan Lainnya", "Potongan Loan (Mobil/Motor/Lainnya/SPPI)"
];
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>E-Pay Slip (HRD)</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../style.css">
    <style>
        .wrap {
            max-width: 1200px;
            margin: 20px auto;
            padding: 16px
        }

        .card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 14px
        }

        h1 {
            margin: 0 0 10px
        }

        .row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap
        }

        select,
        input {
            padding: 10px;
            border: 1px solid #e5e7eb;
            border-radius: 10px
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px
        }

        th,
        td {
            border: 1px solid #eef0f3;
            padding: 10px
        }

        .btn {
            padding: 8px 12px;
            border-radius: 8px;
            border: none;
            cursor: pointer
        }

        .btn.primary {
            background: #2563eb;
            color: #fff
        }

        .btn.info {
            background: #0ea5e9;
            color: #fff
        }

        .btn.success {
            background: #16a34a;
            color: #fff
        }

        .btn[disabled] {
            opacity: .5;
            cursor: not-allowed
        }

        .pill {
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            padding: 6px 10px;
            border-radius: 999px
        }

        /* modal */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .35);
            z-index: 9999;
            align-items: center;
            justify-content: center
        }

        .modal .box {
            width: min(1000px, 96vw);
            max-height: 90vh;
            overflow: auto;
            background: #fff;
            border-radius: 14px
        }

        .modal header,
        .modal footer {
            padding: 12px 16px;
            border-bottom: 1px solid #eef0f3
        }

        .modal footer {
            border-top: 1px solid #eef0f3;
            border-bottom: none;
            display: flex;
            gap: 8px;
            justify-content: space-between;
            align-items: center
        }

        .modal .body {
            padding: 14px
        }

        .grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px
        }

        @media(max-width:1000px) {
            .grid-3 {
                grid-template-columns: 1fr
            }
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

        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .filter-header .left,
        .filter-header .right {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .button-view, .button-download {
            margin-left: 10px;
        }
    </style>
</head>
<body>
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
                            <a href="#" class="dropdown-link"><i class="fas fa-users"></i> Data Karyawan <i class="fas fa-caret-down"></i></a>
                            <ul class="dropdown-menu">
                                <li><a href="../data_karyawan/all_employees.php">Semua Karyawan</a></li>
                                <li><a href="../data_karyawan/karyawan_nonaktif.php">Non-Aktif</a></li>
                            </ul>
                        </li>
                        <li class="dropdown-trigger">
                            <a href="#" class="dropdown-link"><i class="fas fa-file-alt"></i> Data Pengajuan<span class="badge"><?= $total_pending ?? 0 ?></span> <i class="fas fa-caret-down"></i></a>
                            <ul class="dropdown-menu">
                                <li><a href="../pengajuan/pengajuan.php">Pengajuan</a></li>
                                <li><a href="../pengajuan/kelola_pengajuan.php">Kelola Pengajuan<span class="badge"><?= $total_pending ?? 0 ?></span></a></li>
                            </ul>
                        </li>
                        <li><a href="../monitoring_kontrak/monitoring_kontrak.php"><i class="fas fa-calendar-alt"></i> Monitoring Kontrak</a></li>
                        <li class="active"><a href="#"><i class="fas fa-money-check-alt"></i> E-Pay Slip</a></li>
                        <li><a href="../invoice/invoice.php"><i class="fas fa-file-invoice"></i> Invoice</a></li>
                    </ul>
                </nav>
                <div class="logout-link">
                    <a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a>
                </div>
            </div>
        </aside>

        <main class="main-content">
            <header class="main-header">
                <h1>E-Pay Slip (HRD)</h1>
                <p class="current-date"><?= date('l, d F Y'); ?></p>
            </header>

            <div class="dashboard-content">
                <div class="card">
                    <div class="filter-header">
                        <div class="left">
                            <div class="pill">Pilih periode untuk aksi “Lihat/Unduh”</div>
                            <select id="bulan">
                                <?php
                                $nama_bulan = [
                                    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                                    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                                    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
                                ];
                                for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?= $m ?>" <?= $m == (int)date('n') ? 'selected' : ''; ?>>
                                        <?= $nama_bulan[$m] ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <input type="number" id="tahun" value="<?= date('Y') ?>" min="2000" style="width:120px">
                        </div>
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <th style="width:110px">NIK</th>
                                <th>Nama</th>
                                <th>Jabatan</th>
                                <th>Proyek</th>
                                <th style="width:310px">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($employees)): ?>
                                <tr>
                                    <td colspan="5" style="text-align:center">Tidak ada karyawan</td>
                                </tr>
                            <?php else: foreach ($employees as $e): ?>
                                <tr data-id="<?= (int)$e['id_karyawan'] ?>">
                                    <td><?= e($e['nik_karyawan'] ?? '-') ?></td>
                                    <td><?= e($e['nama_karyawan']) ?></td>
                                    <td><?= e($e['jabatan'] ?? '-') ?></td>
                                    <td><?= e($e['proyek'] ?? '-') ?></td>
                                    <td>
                                        <button class="btn primary btn-input">Input / Update Slip</button>
                                        <button class="btn info btn-view">Lihat</button>
                                        <button class="btn success btn-download">Unduh PDF</button>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <div class="modal" id="modalInput">
        <div class="box">
            <header>
                <h3 id="m_title">Input Slip</h3>
                <span class="close-btn" onclick="hideModal()">&times;</span>
            </header>
            <form action="save_payroll.php" method="POST">
                <div class="body grid-3">
                    <?php foreach ($KOMPONEN as $komponen): ?>
                        <div class="input-group">
                            <label><?= e($komponen) ?></label>
                            <input type="number" name="komponen[<?= e($komponen) ?>]" class="angka" data-nama="<?= e($komponen) ?>" value="0" step="any" min="0">
                        </div>
                    <?php endforeach; ?>
                </div>
                <footer>
                    <div>
                        Total Pendapatan: <span id="t_pendapatan">0</span><br>
                        Total Potongan: <span id="t_potongan">0</span><br>
                        Total THP: <span id="t_total">0</span>
                    </div>
                    <div style="text-align:right">
                        <input type="hidden" id="f_id_karyawan" name="id_karyawan">
                        <input type="hidden" id="f_bulan" name="bulan">
                        <input type="hidden" id="f_tahun" name="tahun">
                        <input type="hidden" id="__tot_pendapatan" name="total_pendapatan">
                        <input type="hidden" id="__tot_potongan" name="total_potongan">
                        <input type="hidden" id="__tot_total" name="total_payroll">
                        <button class="btn info" type="submit">Simpan Slip</button>
                        <button class="btn" type="button" onclick="hideModal()">Batal</button>
                    </div>
                </footer>
            </form>
        </div>
    </div>
    
    <script>
    const POTONGAN = new Set(<?= json_encode($POTONGAN, JSON_UNESCAPED_UNICODE) ?>);
    
    function fmt(n) {
        return (n || 0).toLocaleString('id-ID');
    }

    function hitung() {
        let pend = 0,
            pot = 0;
        document.querySelectorAll('#modalInput .angka').forEach(i => {
            const v = parseInt(i.value || '0', 10) || 0;
            if (POTONGAN.has(i.dataset.nama)) pot += v;
            else pend += v;
        });
        const thp = pend - pot;
        document.getElementById('t_pendapatan').textContent = fmt(pend);
        document.getElementById('t_potongan').textContent = fmt(pot);
        document.getElementById('t_total').textContent = fmt(thp);
        document.getElementById('__tot_pendapatan').value = pend;
        document.getElementById('__tot_potongan').value = pot;
        document.getElementById('__tot_total').value = thp;
    }

    function resetAngka() {
        document.querySelectorAll('#modalInput .angka').forEach(i => i.value = 0);
        hitung();
    }

    function showModal() {
        document.getElementById('modalInput').style.display = 'flex';
    }

    function hideModal() {
        document.getElementById('modalInput').style.display = 'none';
    }
    
    document.querySelectorAll('#modalInput .angka').forEach(i => i.addEventListener('input', hitung));
    
    function getPeriode() {
        return {
            bulan: parseInt(document.getElementById('bulan').value, 10),
            tahun: parseInt(document.getElementById('tahun').value, 10)
        };
    }
    
    function setFormPeriode() {
        const p = getPeriode();
        document.getElementById('f_bulan').value = p.bulan;
        document.getElementById('f_tahun').value = p.tahun;
    }
    
    document.addEventListener('click', async (e) => {
        const tr = e.target.closest('tr[data-id]');
        if (!tr) return;
        const id = tr.getAttribute('data-id');
        const { bulan, tahun } = getPeriode();
    
        // OPEN INPUT
        if (e.target.classList.contains('btn-input')) {
            document.getElementById('f_id_karyawan').value = id;
            setFormPeriode();
            document.getElementById('m_title').textContent = `Input Slip (${nama_karyawan}) - Periode: ${bulan}/${tahun}`;
            
            // Fetch existing data if any
            const response = await fetch(`get_payroll_data.php?id_karyawan=${id}&bulan=${bulan}&tahun=${tahun}`);
            const data = await response.json();
            
            resetAngka();
            if (data.status === 'success' && data.payload.components_json) {
                const components = JSON.parse(data.payload.components_json);
                for (const type in components) {
                    for (const name in components[type]) {
                        const input = document.querySelector(`.angka[data-nama="${name}"]`);
                        if (input) {
                            input.value = components[type][name];
                        }
                    }
                }
                hitung();
            }
            
            showModal();
        }
    
        // VIEW
        if (e.target.classList.contains('btn-view')) {
            const pid = await fetch(`get_payroll_id.php?id_karyawan=${id}&bulan=${bulan}&tahun=${tahun}`).then(r => r.json()).catch(() => null);
            if (!pid || !pid.id) {
                return alert('Slip untuk periode ini belum ada. Silakan buat terlebih dahulu.');
            }
            window.open(`view_payroll.php?id=${pid.id}&bulan=${bulan}&tahun=${tahun}`, '_blank');
        }
    
        // DOWNLOAD PDF
        if (e.target.classList.contains('btn-download')) {
            const pid = await fetch(`get_payroll_id.php?id_karyawan=${id}&bulan=${bulan}&tahun=${tahun}`).then(r => r.json()).catch(() => null);
            if (!pid || !pid.id) {
                return alert('Slip untuk periode ini belum ada. Silakan buat terlebih dahulu.');
            }
            window.location.href = `export_payroll_pdf.php?id=${pid.id}`;
        }
    });
    </script>
</body>
</html>