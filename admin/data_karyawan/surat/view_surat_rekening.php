<?php
// ===================================
// view_surat_rekening.php (Surat Rekomendasi Pembukaan Rekening CIMB)
// ===================================
require '../../config.php';
if (session_status() === PHP_SESSION_NONE)
    session_start();

// Data user dari session untuk sidebar
$id_karyawan_admin = $_SESSION['id_karyawan'] ?? 0;
$nama_user_admin = $_SESSION['nama'] ?? 'User';
$role_user_admin = $_SESSION['role'] ?? 'KARYAWAN';

// Ambil NIK dan Jabatan user admin untuk sidebar
$nik_user_admin = 'Tidak Ditemukan';
$jabatan_user_admin = 'Tidak Ditemukan';
if ($stmt_admin_info = $conn->prepare("SELECT nik_ktp, jabatan FROM karyawan WHERE id_karyawan = ?")) {
    $stmt_admin_info->bind_param("i", $id_karyawan_admin);
    $stmt_admin_info->execute();
    $admin_info = $stmt_admin_info->get_result()->fetch_assoc();
    if ($admin_info) {
        $nik_user_admin = $admin_info['nik_ktp'];
        $jabatan_user_admin = $admin_info['jabatan'];
    }
    $stmt_admin_info->close();
}

// Hitung pending requests untuk sidebar
$sql_pending_requests = "SELECT COUNT(*) AS total_pending FROM pengajuan WHERE status_pengajuan = 'Menunggu'";
$result_pending_requests = $conn->query($sql_pending_requests);
$total_pending = $result_pending_requests->fetch_assoc()['total_pending'] ?? 0;


// Hanya ADMIN yang diizinkan (atau HRD jika perlu)
if ($role_user_admin !== 'ADMIN') {
    exit('Unauthorized');
}

$id_karyawan = (int) ($_GET['id'] ?? 0);

// Ambil data karyawan
$q = $conn->prepare("SELECT 
    k.*, 
    k.nama_karyawan, 
    k.nik_ktp, 
    k.jabatan, 
    k.proyek,
    k.alamat,
    k.rt_rw,
    k.kelurahan,
    k.kecamatan,
    k.kota_kabupaten,
    k.alamat_email
    FROM karyawan k
    WHERE k.id_karyawan = ? LIMIT 1");
$q->bind_param("i", $id_karyawan);
$q->execute();
$r = $q->get_result()->fetch_assoc();
$q->close();
$conn->close();

if (!$r)
    exit('Data Karyawan tidak ditemukan.');

// Data surat (Sesuai template Anda)
// Data surat (Sesuai template Anda)
$nama_karyawan_clean = preg_replace('/[^A-Za-z0-9-]+/', '-', $r['nama_karyawan'] ?? 'karyawan');
$file_surat_name = "Surat-Rekomendasi-Rekening-" . $nama_karyawan_clean;

$nama_penandatangan = "Kutobburizal";
$jabatan_penandatangan = "HRD Manager";
$alamat_perusahaan = "Jl. Sultan Iskandar Muda No. 30 A-B Lt. 3 Kebayoran Lama Selatan – Kebayoran Lama Jakarta Selatan 12240";
$telepon_perusahaan = "(021) 275 18 306";
$website_perusahaan = "http://www.manu.co.id/";

// Ganti nilai statis menjadi default:
$default_nomor_surat = "2474/REK/HRD/MANU-CNAF/X/2025";
$default_tanggal_surat_db = date('Y-m-d'); // Tanggal hari ini

// Memisahkan dan menyiapkan data karyawan
$alamat_karyawan = htmlspecialchars($r['alamat'] ?? '-');
$rt_rw_karyawan = htmlspecialchars($r['rt_rw'] ?? '000/000');
$kelurahan_karyawan = htmlspecialchars($r['kelurahan'] ?? '-');
$kecamatan_karyawan = htmlspecialchars($r['kecamatan'] ?? '-');
$kota_karyawan = htmlspecialchars($r['kota_kabupaten'] ?? '-');

$data_karyawan_surat = [
    'Nama' => htmlspecialchars($r['nama_karyawan'] ?? '-'),
    'Nik' => htmlspecialchars($r['nik_ktp'] ?? '-'), // Menggunakan NIK KTP
];

// Helper untuk format tanggal Indonesia
function formatTanggalIndonesia($tgl)
{
    $bulan = [
        'January' => 'Januari',
        'February' => 'Februari',
        'March' => 'Maret',
        'April' => 'April',
        'May' => 'Mei',
        'June' => 'Juni',
        'July' => 'Juli',
        'August' => 'Agustus',
        'September' => 'September',
        'October' => 'Oktober',
        'November' => 'November',
        'December' => 'Desember'
    ];
    return strtr(date('d F Y', strtotime($tgl)), $bulan);
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Surat Rekomendasi Rekening - <?= htmlspecialchars($r['nama_karyawan']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="../../style.css">
    <style>
        .surat-input-controls {
            display: flex;
            gap: 15px;
            align-items: center;
            justify-content: flex-end;
            /* Dorong ke kanan */
            margin-bottom: 20px;
            font-size: 14px;
        }

        .surat-input-controls label {
            font-weight: 600;
            margin-right: 5px;
        }

        .surat-input-controls input {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-family: inherit;
            font-size: 14px;
        }

        .surat-container {
            width: 100%;
            margin: 0 auto;
            padding: 30px;
        }

        .header-pt {
            text-align: center;
            border-bottom: 3px solid #f00;
            padding-bottom: 5px;
            margin-bottom: 25px;
        }

        .header-pt img {
            width: 50px;
            height: auto;
            margin-bottom: 5px;
        }

        .header-pt h1 {
            margin: 0;
            font-size: 16pt;
            font-weight: 700;
            color: #f00;
            /* Merah PT */
        }

        .header-pt p {
            margin: 0;
            font-size: 8pt;
            line-height: 1.2;
        }

        .surat-title {
            text-align: center;
            font-size: 12pt;
            font-weight: 600;
            margin: 20px 0 10px;
        }

        .nomor-surat {
            text-align: center;
            font-size: 10pt;
            margin-bottom: 30px;
        }

        /* CSS Khusus untuk Layout Sidebar & Konten */
        .container {
            display: flex;
            min-height: 100vh;
            background-color: #f5f6fa;
        }

        .main-content {
            flex-grow: 1;
            padding: 20px;
        }


        .surat-rekening-wrapper {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            padding: 30px;
        }

        .top-action-bar {
            text-align: right;
            margin-bottom: 20px;
        }

        .download-btn,
        .email-btn {
            padding: 8px 15px;
            margin-left: 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
        }

        .download-btn {
            background-color: #3498db;
            color: white;
        }

        .email-btn {
            background-color: #2ecc71;
            color: white;
        }

        /* CSS Untuk Dokumen Surat Rekening (Sama seperti sebelumnya) */
        .header-pt {
            text-align: center;
            border-bottom: 3px solid #f00;
            padding-bottom: 5px;
            margin-bottom: 25px;
        }

        .header-pt img {
            width: 50px;
            height: auto;
            margin-bottom: 5px;
        }

        /* ... (CSS styling lainnya di sini) */
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

        /* CSS KHUSUS PRINT */
        @media print {
            body {
                background: none;
                font-size: 10.5pt;
            }


            .sidebar,
            .top-action-bar,
            .surat-input-controls {
                /* <-- PERBARUI INI */
                display: none;

                .container {
                    display: block;
                    /* Matikan flex untuk print */
                    padding: 0;
                }

                .main-content {
                    padding: 0;
                    margin: 0;
                }

                .surat-rekening-wrapper {
                    box-shadow: none;
                    margin: 0;
                    border-radius: 0;
                    padding: 10mm;
                }
            }
    </style>
</head>

<body>
    <div class="container">
        <aside class="sidebar">
            <div class="company-brand">
                <img src="../../image/manu.png" alt="Logo PT Mandiri Andalan Utama" class="company-logo">
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
                    <li><a href="../../dashboard_admin.php"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="../../absensi/absensi.php"><i class="fas fa-edit"></i> Absensi </a></li>
                    <li class="dropdown-trigger">
                        <a href="#" class="dropdown-link"><i class="fas fa-users"></i> Data Karyawan <i
                                class="fas fa-caret-down"></i></a>
                        <ul class="dropdown-menu">
                            <li><a href="../all_employees.php">Semua Karyawan</a></li>
                            <li><a href="../karyawan_nonaktif.php">Non-Aktif</a></li>
                        </ul>
                    </li>
                    <li class="dropdown-trigger">
                        <a href="#" class="dropdown-link"><i class="fas fa-users"></i> Data Pengajuan
                            <span class="badge red"><?= $total_pending ?></span><i class="fas fa-caret-down"></i></a>
                        <ul class="dropdown-menu">
                            <li><a href="../../pengajuan/pengajuan.php">Pengajuan</a></li>
                            <li><a href="../../pengajuan/kelola_pengajuan.php">Kelola Pengajuan
                                    <span class="badge red"><?= $total_pending ?></span></a></li>
                        </ul>
                    </li>
                    <li><a href="../../monitoring_kontrak/monitoring_kontrak.php"><i class="fas fa-calendar-alt"></i>
                            Monitoring Kontrak</a></li>
                    <li><a href="../../monitoring_kontrak/surat_tugas_history.php"><i class="fas fa-file-alt"></i>
                            Riwayat
                            Surat Tugas</a></li>
                    <li><a href="../../payslip/e_payslip_admin.php"><i class="fas fa-money-check-alt"></i> E-Pay
                            Slip</a>
                    </li>
                    <li><a href="../../invoice/invoice.php"><i class="fas fa-money-check-alt"></i> Invoice</a></li>
                </ul>
            </nav>
            <div class="logout-link">
                <a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a>
            </div>
        </aside>
        <main class="main-content">

            <div class="surat-input-controls">
                <label for="input_nomor_surat">Nomor Surat:</label>
                <input type="text" id="input_nomor_surat" value="<?= htmlspecialchars($default_nomor_surat) ?>"
                    style="width: 250px;">

                <label for="input_tanggal_surat">Tanggal:</label>
                <input type="date" id="input_tanggal_surat" value="<?= $default_tanggal_surat_db ?>"
                    style="width: 150px;">
            </div>
            <div class="top-action-bar">
                <button class="download-btn" onclick="downloadSuratAsPDF('<?= $file_surat_name ?>')">
                    <i class="fas fa-download"></i> Unduh PDF
                </button>
                <button class="email-btn" onclick="sendSuratAsEmail(<?= $id_karyawan ?>)">
                    <i class="fas fa-envelope"></i> Kirim Surat ke Email
                </button>
                <div id="emailStatus" style="margin-top: 10px; font-weight: 600;"></div>
            </div>

            <div class="surat-rekening-wrapper">
                <div class="header-pt">
                    <img src="../../image/manu.png" alt="Logo PT Mandiri Andalan Utama">
                    <h1>PT. MANDIRI ANDALAN UTAMA</h1>
                    <p>
                        <?= $alamat_perusahaan ?><br>
                        Telp: <?= $telepon_perusahaan ?> <br>
                        Web: <a href="<?= $website_perusahaan ?>"><?= $website_perusahaan ?></a>
                    </p>
                </div>

                <div class="surat-title">Surat Rekomendasi Pembukaan Rekening Bank</div>
                <div class="nomor-surat" id="nomor_surat_tampilan"><?= $default_nomor_surat ?></div>

                <div class="body-content">
                    <div class="kepada">
                        Kepada <br>
                        Yth. Kepala Cabang Bank CIMB Niaga
                        <br>
                    </div>
                    <br>
                    <p>Dengan hormat,</p>

                    <div class="data-penandatangan">
                        Yang bertanda tangan di bawah ini:<br>
                        <table style="width: 40%; margin-top: 5px;">
                            <tr>
                                <td>Nama</td>
                                <td>:</td>
                                <td><?= $nama_penandatangan ?></td>
                            </tr>
                            <tr>
                                <td>Jabatan</td>
                                <td>:</td>
                                <td><?= $jabatan_penandatangan ?></td>
                            </tr>
                        </table>
                    </div>

                    <p style="margin-top: 15px;">Menerangkan bahwa:</p>

                    <div class="data-karyawan">
                        <table style="width: 100%;">
                            <tr>
                                <td>Nama</td>
                                <td>:</td>
                                <td><?= $data_karyawan_surat['Nama'] ?></td>
                            </tr>
                            <tr>
                                <td>Nik</td>
                                <td>:</td>
                                <td><?= $data_karyawan_surat['Nik'] ?></td>
                            </tr>
                            <tr>
                                <td style="vertical-align: top;">Alamat</td>
                                <td style="vertical-align: top;">:</td>
                                <td>
                                    <?= htmlspecialchars($r['alamat'] ?? '-') ?><br>
                                    Panggisari RT/RW : <?= $rt_rw_karyawan ?><br>
                                    Kel. <?= $kelurahan_karyawan ?> Kec. <?= $kecamatan_karyawan ?>
                                    <?= $kota_karyawan ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <p style="margin-top: 15px;">Benar bekerja di perusahaan kami :</p>
                    <br>
                    <div class="status-kerja" >
                        
                        <table style="width: 100%; margin-top: -10px;">
                            <tr>
                                <td>Nama Perusahaan</td>
                                <td>:</td>
                                <td>PT. Mandiri Andalan Utama</td>
                            </tr>
                            <tr>
                                <td style="vertical-align: top;">Alamat</td>
                                <td style="vertical-align: top;">:</td>
                                <td>
                                    <?= $alamat_perusahaan ?>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <p style="margin-top: 20px;">Demikian Surat Keterangan Kerja ini dibuat dan ditandatangani sebagai
                        syarat untuk pembukaan rekening Bank CIMB Niaga</p>
                </div>
                <br>

                <div class="ttd-area">
                    <p id="tanggal_surat_tampilan">Jakarta, <?= formatTanggalIndonesia($default_tanggal_surat_db) ?></p>
                    <p>Dibuat oleh,</p>

                    <div class="tandatangan">
                        <img src="../../image/ttd.png" alt="Tanda Tangan" class="logo-ttd">
                        <div class="ttd-info">
                            <p
                                style="font-weight: 700; border-bottom: 1px solid #000; display: inline-block; padding-bottom: 2px; margin-bottom: 2px;">
                                <?= $nama_penandatangan ?>
                            </p>
                            <p style="margin: 0;"><?= $jabatan_penandatangan ?></p>
                            <p style="margin: 0;">PT Mandiri Andalan Utama</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.3/html2pdf.bundle.min.js"></script>
    <script>
        // --- Variabel PHP yang Diteruskan ---
        const ID_KARYAWAN = <?= $id_karyawan ?>;
        const KARYAWAN_EMAIL = '<?= htmlspecialchars($r['alamat_email'] ?? '') ?>';

        // --- Variabel Global DOM Elements ---
        const inputNomorSurat = document.getElementById('input_nomor_surat');
        const inputTanggalSurat = document.getElementById('input_tanggal_surat');
        const tampilanNomorSurat = document.getElementById('nomor_surat_tampilan');
        const tampilanTanggalSurat = document.getElementById('tanggal_surat_tampilan');

        // Helper untuk format tanggal (sama dengan PHP, untuk tampilan real-time)
        function formatTanggalSurat(dateString) {
            if (!dateString) return '—';
            const d = new Date(dateString + 'T00:00:00'); // Tambahkan T00:00:00 untuk menghindari masalah timezone
            if (isNaN(d)) return dateString;

            const bulan = [
                'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
            ];
            const day = d.getDate();
            const month = bulan[d.getMonth()];
            const year = d.getFullYear();

            return `Jakarta, ${day} ${month} ${year}`;
        }

        /**
         * Memperbarui Nomor dan Tanggal Surat di tampilan dokumen secara real-time.
         */
        function updateSuratContent() {
            if (tampilanNomorSurat) {
                tampilanNomorSurat.textContent = inputNomorSurat.value;
            }
            if (tampilanTanggalSurat) {
                tampilanTanggalSurat.textContent = formatTanggalSurat(inputTanggalSurat.value);
            }
        }

        // --- Event Listeners untuk Update Real-time ---
        if (inputNomorSurat) inputNomorSurat.addEventListener('input', updateSuratContent);
        if (inputTanggalSurat) inputTanggalSurat.addEventListener('change', updateSuratContent);

        // Panggil sekali saat DOM dimuat untuk menginisialisasi tampilan
        document.addEventListener('DOMContentLoaded', updateSuratContent);

        // --- FUNGSI DOWNLOAD PDF ---
        function downloadSuratAsPDF(fileNamePrefix) {
            // Ambil nomor surat terbaru dari input untuk nama file
            const currentNomor = inputNomorSurat.value.replace(/[^A-Za-z0-9-]+/g, '-');
            const finalFileName = `${fileNamePrefix}-${currentNomor}`;

            const element = document.querySelector('.surat-rekening-wrapper');

            const opt = {
                margin: [5, 5, 5, 5],
                filename: `${finalFileName}.pdf`,
                image: { type: 'jpeg', quality: 0.9 },
                html2canvas: { scale: 3, useCORS: true },
                jsPDF: { unit: 'mm', format: [210, 390], orientation: 'portrait' }
            };


            html2pdf().from(element).set(opt).save();
        }

        // --- FUNGSI KIRIM EMAIL ---
        function sendSuratAsEmail() {
            // Ambil nilai terbaru untuk digunakan dalam payload/nama file
            const currentNomor = inputNomorSurat.value.replace(/[^A-Za-z0-9-]+/g, '-');
            const currentTanggal = inputTanggalSurat.value;

            const element = document.querySelector('.surat-rekening-wrapper');
            const emailStatus = document.getElementById('emailStatus');
            const btn = document.querySelector('.email-btn');
            const originalText = btn.innerHTML;

            if (KARYAWAN_EMAIL === '') {
                emailStatus.innerHTML = '<span style="color: #c0392b;"><i class="fas fa-exclamation-triangle"></i> Email karyawan tidak ditemukan.</span>';
                return;
            }

            if (!confirm(`Kirim Surat Rekomendasi Rekening ke email ${KARYAWAN_EMAIL} dengan Nomor ${inputNomorSurat.value} dan Tanggal ${currentTanggal}?`)) return;

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengirim...';
            emailStatus.innerHTML = '';

            const opt = {
                margin: 10,
                filename: `Surat-Rekomendasi-Rekening-${currentNomor}.pdf`, // Gunakan nomor surat baru
                image: { type: 'jpeg', quality: 0.9 },
                html2canvas: { scale: 3, useCORS: true },
                jsPDF: { unit: 'mm', format: [210, 390], orientation: 'portrait' }
            };

            html2pdf().from(element).set(opt).output('blob').then(function (pdfBlob) {
                const formData = new FormData();
                // Gunakan Nomor Surat Terbaru untuk nama file lampiran
                formData.append('surat_pdf', pdfBlob, `Surat-Rekomendasi-Rekening-${ID_KARYAWAN}-${currentNomor}.pdf`);

                // Kirim ID Karyawan dan data surat yang baru ke backend
                formData.append('id_karyawan', ID_KARYAWAN);
                formData.append('tanggal_surat', currentTanggal);
                formData.append('nomor_surat', inputNomorSurat.value);

                // Ganti dengan path script pengiriman email Anda yang sebenarnya
                fetch('./send_rekening_email.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(res => res.text())
                    .then(result => {
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                        if (result.includes("berhasil dikirim")) {
                            emailStatus.innerHTML = '<span style="color: #27ae60;"><i class="fas fa-check-circle"></i> Berhasil dikirim!</span>';
                        } else {
                            emailStatus.innerHTML = '<span style="color: #c0392b;"><i class="fas fa-exclamation-triangle"></i> Gagal mengirim: ' + result + '</span>';
                        }
                    })
                    .catch(err => {
                        console.error("Fetch Error:", err);
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                        emailStatus.innerHTML = '<span style="color: #c0392b;"><i class="fas fa-times-circle"></i> Terjadi kesalahan saat koneksi.</span>';
                    });
            });
        }
    </script>
</body>

</html>