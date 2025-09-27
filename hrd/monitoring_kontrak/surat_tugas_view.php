<?php
// ===========================
// surat_tugas_view.php (HRD)
// ===========================
require '../config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Hanya HRD yang diizinkan
if (!isset($_SESSION['id_karyawan']) || ($_SESSION['role'] ?? '') !== 'HRD') {
    exit('Unauthorized');
}

$id = (int) ($_GET['id'] ?? 0);

// Ambil data surat tugas dari tabel `surat_tugas` dan info karyawan
$q = $conn->prepare("SELECT st.*, k.nama_karyawan, k.nik_ktp, k.jabatan, k.proyek, k.join_date
                     FROM surat_tugas st
                     JOIN karyawan k ON k.id_karyawan = st.id_karyawan
                     WHERE st.id = ? LIMIT 1");
$q->bind_param("i", $id);
$q->execute();
$r = $q->get_result()->fetch_assoc();
$q->close();
if (!$r) exit('Surat tugas tidak ditemukan');

// Ambil data user dari sesi untuk sidebar
$id_karyawan_hrd = $_SESSION['id_karyawan'];
$nama_user_hrd   = $_SESSION['nama'];
$role_user_hrd   = $_SESSION['role'];
$sql_pending_requests = "SELECT COUNT(*) AS total_pending FROM pengajuan WHERE status_pengajuan = 'Menunggu'";
$result_pending_requests = $conn->query($sql_pending_requests);
$total_pending = $result_pending_requests->fetch_assoc()['total_pending'] ?? 0;

$stmt_hrd_info = $conn->prepare("SELECT nik_ktp, jabatan FROM karyawan WHERE id_karyawan = ?");
$nik_user_hrd = 'Tidak Ditemukan';
$jabatan_user_hrd = 'Tidak Ditemukan';
if ($stmt_hrd_info) {
    $stmt_hrd_info->bind_param("i", $id_karyawan_hrd);
    $stmt_hrd_info->execute();
    $result_hrd_info = $stmt_hrd_info->get_result();
    $hrd_info = $result_hrd_info->fetch_assoc();
    if ($hrd_info) {
        $nik_user_hrd = $hrd_info['nik_ktp'];
        $jabatan_user_hrd = $hrd_info['jabatan'];
    }
    $stmt_hrd_info->close();
}
$conn->close();

// Helper untuk format tanggal
function formatDate($date) {
    return date('d M Y', strtotime($date));
}

// Data untuk nama file PDF
$file_no_surat = preg_replace('/[^A-Za-z0-9-]+/', '-', $r['no_surat'] ?? 'tanpa-nomor');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Surat Tugas - <?= htmlspecialchars($r['no_surat'] ?? 'Detail') ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../style.css">
    <style>
        body { font-family:'Poppins',sans-serif; margin:0; background:#f5f6fa;}
        .container { display:flex; min-height:100vh;}
        .main { flex:1; padding:30px;}
        .surat { max-width:800px; margin:auto; background:#fff; border-radius:10px; padding:30px; box-shadow:0 2px 6px rgba(0,0,0,0.1);}
        .header { display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid #2c3e50; padding-bottom:10px; margin-bottom:20px;}
        .header .left { display:flex; align-items:center; gap:15px;}
        .header .logo { width:70px; height:auto;}
        .header h2 { margin:0; font-size:24px; color:#333;}
        .company-info { text-align:right; font-size:13px; color:#555; line-height:1.4;}
        .surat-title { text-align:center; font-size:18px; font-weight:bold; text-decoration:underline; margin:25px 0 10px;}
        .surat-content p,.surat-content table { font-size:15px; line-height:1.6; margin-bottom:15px;}
        .surat-content table { width:100%; border-collapse:collapse;}
        .surat-content table td { padding:5px 0; vertical-align:top;}
        .surat-content table td.label { width:150px; font-weight:bold;}
        .ttd { margin-top:40px; text-align:right; font-size:14px;}
        .ttd .nama-pemberi-tugas { font-weight:bold; margin-top:60px; border-bottom:1px solid #000; padding-bottom:3px; display:inline-block;}
        .ttd .jabatan { font-size:13px; color:#555;}
        .sidebar-nav .dropdown-trigger { position:relative;}
        .sidebar-nav .dropdown-link { display:flex; justify-content:space-between; align-items:center;}
        .sidebar-nav .dropdown-menu { display:none; position:absolute; top:100%; left:0; min-width:200px; background:#2c3e50;
            box-shadow:0 4px 8px rgba(0,0,0,0.2); padding:0; margin:0; list-style:none; z-index:1000; border-radius:0 0 8px 8px; overflow:hidden;}
        .sidebar-nav .dropdown-menu li a { padding:12px 20px; display:block; color:#ecf0f1; text-decoration:none; transition:background-color .3s;}
        .sidebar-nav .dropdown-menu li a:hover { background:#34495e;}
        .sidebar-nav .dropdown-trigger:hover .dropdown-menu { display:block;}
        .badge { background:#ef4444; color:#fff; padding:2px 8px; border-radius:999px; font-size:12px;}
    </style>
</head>
<body>
<div class="container">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="company-brand">
            <img src="../image/manu.png" class="company-logo">
            <p class="company-name">PT Mandiri Andalan Utama</p>
        </div>
        <div class="user-info">
            <div class="user-avatar"><?= e(strtoupper(substr($nama_user_hrd, 0, 2))) ?></div>
            <div class="user-details">
                <p class="user-name"><?= e($nama_user_hrd) ?></p>
                <p class="user-id"><?= e($nik_user_hrd) ?></p>
                <p class="user-role"><?= e($role_user_hrd) ?></p>
            </div>
        </div>
        <nav class="sidebar-nav">
            <ul>
                <li><a href="../dashboard_hrd.php"><i class="fas fa-home"></i> Dashboard</a></li>
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
                <li class="active"><a href="surat_tugas_history.php"><i class="fas fa-file-alt"></i> Riwayat Surat Tugas</a></li>
                <li><a href="../payslip/e_payslip_hrd.php"><i class="fas fa-money-check-alt"></i> E-Pay Slip</a></li>
                <li><a href="../invoice/invoice.php"><i class="fas fa-money-check-alt"></i> Invoice</a></li>
            </ul>
        </nav>
        <div class="logout-link">
            <a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a>
        </div>
    </aside>

    <!-- Main content -->
    <main class="main">
        <div class="download-controls" style="text-align:right; margin-bottom:20px;">
            <button onclick="downloadSuratAsPDF('<?= $file_no_surat ?>')"
                style="padding:8px 15px; background:#3498db; color:#fff; border:none; border-radius:5px; cursor:pointer;">
                <i class="fas fa-download"></i> Unduh PDF
            </button>
        </div>

        <div class="surat" id="surat-tugas-dokumen">
            <div class="header">
                <div class="left">
                    <img src="../image/manu.png" alt="Logo" class="logo">
                    <h2>PT Mandiri Andalan Utama</h2>
                </div>
                <div class="company-info">
                    Jl. Sultan Iskandar Muda No.30 A-B <br>
                    Kebayoran Lama, Jakarta Selatan <br>
                    Telp : (021) 275 18 306<br>
                    www.manu.co.id
                </div>
            </div>

            <h3 class="surat-title">SURAT TUGAS</h3>
            <div style="text-align:center; font-size:14px; margin-bottom:20px;">
                Nomor: <?= htmlspecialchars($r['no_surat'] ?? '-') ?>
            </div>

            <div class="surat-content">
                <p>Yang bertanda tangan di bawah ini, manajemen PT Mandiri Andalan Utama menugaskan:</p>
                <table>
                    <tr><td class="label">Nama</td><td>: <?= htmlspecialchars($r['nama_karyawan'] ?? '-') ?></td></tr>
                    <tr><td class="label">NIK</td><td>: <?= htmlspecialchars($r['nik_ktp'] ?? '-') ?></td></tr>
                    <tr><td class="label">Posisi</td><td>: <?= htmlspecialchars($r['posisi'] ?? $r['jabatan'] ?? '-') ?></td></tr>
                    <tr><td class="label">Penempatan</td><td>: <?= htmlspecialchars($r['penempatan'] ?? '-') ?></td></tr>
                    <tr><td class="label">Sales Code</td><td>: <?= htmlspecialchars($r['sales_code'] ?? '-') ?></td></tr>
                    <tr><td class="label">Alamat Penempatan</td><td>: <?= nl2br(htmlspecialchars($r['alamat_penempatan'] ?? '-')) ?></td></tr>
                    <tr><td class="label">Tanggal Bergabung</td><td>: <?= htmlspecialchars($r['join_date'] ?? '-') ?></td></tr>
                </table>
                <p>Demikian surat tugas ini dibuat untuk dipergunakan sebagaimana mestinya.</p>
            </div>

            <div class="ttd">
                <p>Jakarta, <?= htmlspecialchars(formatDate($r['tgl_pembuatan'] ?? date('Y-m-d'))) ?></p>
                <p><strong>PT Mandiri Andalan Utama</strong></p>
                <div style="margin-top:80px;"></div>
                <p class="nama-pemberi-tugas">Oktafian Farhan</p>
                <p class="jabatan">Direktur Utama</p>
            </div>
        </div>
    </main>
</div>

<!-- Script Libraries -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.3/html2pdf.bundle.min.js"></script>
<script>
function downloadSuratAsPDF(fileNamePrefix) {
    const element = document.getElementById('surat-tugas-dokumen');
    const opt = {
        margin: 10,
        filename: `Surat-Tugas-${fileNamePrefix}.pdf`,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };
    html2pdf().from(element).set(opt).save();
}

function downloadSuratAsPDF2(fileNamePrefix) {
    const { jsPDF } = window.jspdf;
    const surat = document.getElementById('surat-tugas-dokumen');
    const sidebar = document.querySelector('.sidebar');
    const controls = document.querySelector('.download-controls');

    sidebar.style.display = 'none';
    controls.style.display = 'none';
    document.body.style.backgroundColor = '#fff';

    html2canvas(surat, { scale: 1, useCORS: true }).then(canvas => {
        sidebar.style.display = 'block';
        controls.style.display = 'block';
        document.body.style.backgroundColor = '#f5f6fa';

        let imgData = canvas.toDataURL('image/jpeg', 0.6);
        let pdf = new jsPDF('p', 'mm', 'a4');
        let pdfWidth = pdf.internal.pageSize.getWidth();
        let pdfHeight = (canvas.height * pdfWidth) / canvas.width;
        pdf.addImage(imgData, 'PNG', 0, 0, pdfWidth, pdfHeight);
        pdf.save(`Surat-Tugas-${fileNamePrefix}.pdf`);
    }).catch(error => {
        sidebar.style.display = 'block';
        controls.style.display = 'block';
        document.body.style.backgroundColor = '#f5f6fa';
        console.error("Gagal membuat PDF:", error);
    });
}
</script>
</body>
</html>
