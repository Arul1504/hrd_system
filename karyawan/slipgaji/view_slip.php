<?php
// FILE: karyawan/slipgaji/view_payslip.php

session_start();

// Wajib login sebagai KARYAWAN
if (!isset($_SESSION['id_karyawan']) || strtoupper($_SESSION['role'] ?? '') !== 'KARYAWAN') {
    http_response_code(401);
    exit('Unauthorized');
}

require __DIR__ . '/../config.php'; // Sesuaikan path config Anda

$user_id = $_SESSION['id_karyawan'];
$id_slip = (int)($_GET['id'] ?? 0);

if ($id_slip === 0) {
    exit('ID Slip Gaji tidak valid.');
}

// Query untuk mengambil data slip, dan memastikan ID pemiliknya cocok
$q = $conn->prepare("SELECT p.*, k.nama_karyawan, k.nik_ktp, k.jabatan, k.proyek, k.status_karyawan, k.cabang,
                     k.nomor_rekening, k.nama_bank, k.npwp, k.alamat_email, k.join_date, k.status_karyawan, p.id_karyawan
                     FROM payroll p 
                     JOIN karyawan k ON k.id_karyawan=p.id_karyawan
                     WHERE p.id=? LIMIT 1");
$q->bind_param("i", $id_slip);
$q->execute();
$r = $q->get_result()->fetch_assoc();
$q->close();

if (!$r) {
    exit('Slip tidak ditemukan');
}

// --- OTORISASI ---
// Pastikan Karyawan yang login adalah pemilik slip gaji ini
if ($user_id !== (int)$r['id_karyawan']) {
    http_response_code(403);
    exit('Forbidden: Anda tidak diizinkan melihat slip gaji karyawan lain.');
}
// --- END OTORISASI ---

// Dekode komponen
$comp = json_decode($r['components_json'], true) ?: [];

// Pisahkan pendapatan & potongan
$POTONGAN = [
    "Biaya Admin", "Total tax (PPh21)", "BPJS Kesehatan", "BPJS Ketenagakerjaan", 
    "Dana Pensiun", "Keterlambatan Kehadiran", "Potongan Lainnya", 
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

$conn->close();

$periode = date('F Y', strtotime($r['periode_tahun'] . '-' . $r['periode_bulan'] . '-01'));

// Fungsi untuk HTML escaping
if (!function_exists('e')) {
    function e($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Slip Gaji <?= $periode ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../style.css"> 
    <style>
        body { font-family: 'Poppins', sans-serif; margin: 0; background: #fff; }
        .slip { max-width: 800px; margin: 20px auto; background: #fff; border-radius: 10px; padding: 25px; box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1); }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 20px; }
        .header .left { display: flex; align-items: center; gap: 10px; }
        .header .logo { width: 50px; height: auto; }
        .company-info { text-align: right; font-size: 13px; color: #555; line-height: 1.4; }
        .info-table { width: 100%; font-size: 14px; margin-bottom: 20px; border-collapse: collapse; }
        .info-table td { padding: 4px 8px; vertical-align: top; }
        .box-container { display: flex; gap: 20px; }
        .box { flex: 1; background: #fafafa; padding: 15px; border-radius: 10px; }
        .box h3 { margin: 0 0 10px; font-size: 16px; color: #333; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        .row { display: flex; justify-content: space-between; margin: 5px 0; font-size: 14px; }
        .total { font-weight: bold; border-top: 1px solid #ccc; padding-top: 8px; margin-top: 8px; }
        .thp { text-align: center; margin-top: 25px; }
        .thp .amount { font-size: 26px; font-weight: bold; color: #0a0; }
        .footer { margin-top: 25px; font-size: 13px; color: #555; border-top: 1px dashed #ddd; padding-top: 10px; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>

<body>
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
        
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.3/html2pdf.bundle.min.js"></script>
    <script>
    function downloadSlipAsPDF() {
        const element = document.querySelector('.slip');
        const opt = {
            margin: [5, 5, 5, 5],
            filename: 'Slip-Gaji-<?= $periode ?>.pdf',
            image: { type: 'jpeg', quality: 0.9 },
            html2canvas: { scale: 2, useCORS: true },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };
        html2pdf().from(element).set(opt).save();
    }

    // Trigger download otomatis saat halaman dimuat
    document.addEventListener('DOMContentLoaded', function() {
        downloadSlipAsPDF();
        
        // Opsional: Tutup jendela setelah beberapa detik setelah download dimulai
        setTimeout(() => {
             window.close();
        }, 1500); 
    });
    </script>
</body>
</html>