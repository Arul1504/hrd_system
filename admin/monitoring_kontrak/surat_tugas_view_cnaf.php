

<?php
// ===========================
// surat_tugas_view_allo.php (KODE FINAL YANG SUDAH DIBERSIHKAN)
// ===========================

// Pastikan file config.php di-require di awal
require '../config.php';

// Cek dan mulai sesi
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper aman HTML
if (!function_exists('e')) {
    function e($s) {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }
}

// Akses hanya HRD / ADMIN
if (!isset($_SESSION['id_karyawan']) || !in_array($_SESSION['role'] ?? '', ['HRD', 'ADMIN'])) {
    // BARIS INI (19) HARUSNYA BEBAS DARI ERROR JIKA SINTAKS DI ATAS BERSIH
    exit('Unauthorized');
}

// Ambil parameter dan data dari database
$id = (int) ($_GET['id'] ?? 0);

// Ambil data surat + karyawan (karena id_karyawan tidak punya 'email', diganti 'alamat_email')
$q = $conn->prepare("SELECT st.*, k.nama_karyawan, k.nik_ktp, k.jabatan, k.proyek, k.alamat_email, k.no_hp, k.sales_code,
                             k.alamat AS alamat_karyawan
                      FROM surat_tugas st
                      JOIN karyawan k ON k.id_karyawan = st.id_karyawan
                      WHERE st.id=? LIMIT 1");
$q->bind_param("i", $id);
$q->execute();
$r = $q->get_result()->fetch_assoc();
$q->close();

if (!$r) {
    exit('Surat tugas tidak ditemukan');
}

// Data sidebar (dibiarkan sesuai kode sebelumnya)
$id_karyawan_admin = $_SESSION['id_karyawan'];
$nama_user_admin = $_SESSION['nama'] ?? 'User';
$role_user_admin = $_SESSION['role'] ?? 'KARYAWAN';

$sql_pending = "SELECT COUNT(*) AS total_pending FROM pengajuan WHERE status_pengajuan = 'Menunggu'";
$total_pending = ($conn->query($sql_pending)->fetch_assoc()['total_pending'] ?? 0);

$stmt_admin_info = $conn->prepare("SELECT nik_ktp, jabatan FROM karyawan WHERE id_karyawan = ?");
$nik_user_admin = 'Tidak Ditemukan';
$jabatan_user_admin = 'Tidak Ditemukan';
if ($stmt_admin_info) {
    $stmt_admin_info->bind_param("i", $id_karyawan_admin);
    $stmt_admin_info->execute();
    if ($info = $stmt_admin_info->get_result()->fetch_assoc()) {
        $nik_user_admin = $info['nik_ktp'] ?? $nik_user_admin;
        $jabatan_user_admin = $info['jabatan'] ?? $jabatan_user_admin;
    }
    $stmt_admin_info->close();
}

// Helper tanggal Indonesia (panjang)
function idDate($date)
{
    if (!$date) return '-';
    $bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $ts = strtotime($date);
    if (!$ts) return $date;
    return date('d ', $ts) . $bulan[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts);
}

// ====== Mapping nilai (default sesuai contoh Allo Bank) ======
$noSurat = $r['no_surat'] ?? '151/ST/HRD-MANU-ALLO/SEPTEMBER/2025';
$klien_vendor = $r['klien'] ?? 'CIMB Niaga';
$nama = $r['nama_karyawan'] ?? 'Hironimus Perius Zai';
$posisi = ($r['posisi'] ?? '') ?: ($r['jabatan'] ?? 'Offline Sales');
$salesCode = $r['sales_code'] ?? 'MANU2017';
$cabang_penempatan = $r['penempatan'] ?? 'Transmart Medan Fair';
$alamat_penempatan = $r['alamat_penempatan'] ?? 'Jl. Gatot Subroto, Petisah Tengah, Kec. Medan Petisah, Kota Medan, Sumatera Utara 20111';
$tglSurat = $r['tgl_pembuatan'] ?? date('Y-m-d', strtotime('2025-09-29'));
$nama_pembuat = 'Kutobburizal';
$jabatan_pembuat = 'HRD Manager';

// Penyesuaian Yth: di gambar Allo Bank menggunakan "Kepala Cabang 1156"
$yth_kepala = $r['yth'] ?? 'Kepala Cabang 1156';

// Nama file unduh
$file_no_surat = preg_replace('/[^A-Za-z0-9-]+/', '-', $noSurat ?: 'tanpa-nomor');
$email_tujuan = $r['alamat_email'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Surat Tugas - <?= e($noSurat) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../style.css">
    <style>
        /* CSS umum (tetap) */
        :root { --hitam: #111; --abu: #666; --merah: #e53935; }
        * { box-sizing: border-box; }
  
        .container { display: flex; min-height: 100vh; }
        .main { flex: 1; padding: 28px; }

        /* KARTU SURAT A4 */
        .surat {
            width: 794px; /* A4 */
            min-height: 800px; /* A4 */
            margin: 0 auto;
            background: #fff;
            color: #111;
            border-radius: 10px;
            padding: 36px 40px 28px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, .08);
            line-height: 1.5;
        }

        /* === KOP SURAT BARU === */
.header-pt {
    border-bottom: 3px solid #f00;
    padding-bottom: 10px;
    margin-bottom: 25px;
    text-align: center;
}

.kop-wrapper {
    display: flex;
    align-items: center;
    justify-content: center; /* Supaya posisi keseluruhan di tengah */
    gap: 15px;
}

.logo-area img {
    width: 95px;   /* Bisa kamu kecilkan/besarkan sesuai kebutuhan */
    height: auto;
}

.text-area {
    text-align: center; /* Isi teks tetap rata tengah */
    line-height: 1.3;
}

.text-area h1 {
    margin: ;
    font-size: 25pt;
    font-weight: 700;
}

.text-area p {
    margin: 16px 0 0;
    font-size: 10pt;
    line-height: 1.3;
}

        /* Judul & Nomor */
        .judul { text-align: center; margin: 14px 0 4px; font-weight: 700; font-size: 11pt; }
        .nomor { text-align: center; font-size: 11pt; margin-bottom: 14px; }
        
        /* Header Surat (Perihal / Yth) */
        .header-surat { font-size: 11pt; margin-bottom: 20px; }
        .header-surat table { width: auto; margin-bottom: 10px; }
        .header-surat td { padding: 0 5px 0 0; }
        .header-surat .lbl { width: 80px; }
        .header-surat .yth-block { margin-top: 10px; }

        /* Paragraf */
        .p { font-size: 11pt; margin: 12px 0 16px; text-align: justify; line-height: 1.5; text-indent: 40px;} /* Tambah indent */
        .p-no-indent { text-indent: 0; }
        
        /* Tabel Karyawan */
        .tbl-karyawan { width: 100%; border-collapse: collapse; font-size: 11pt; margin: 4px 0 10px; }
        .tbl-karyawan th, .tbl-karyawan td { border: 1px solid #000; padding: 6px 8px; vertical-align: top; }
        .tbl-karyawan th { background: #f0f0f0; font-weight: bold; text-align: center; }
        .tbl-karyawan td { text-align: left; }
        .tbl-karyawan .no { width: 36px; text-align: center; }
        .tbl-karyawan .posisi { width: 250px; }
        .tbl-karyawan .sales { width: 150px; }
  .ttd {
            margin-top: 26px;
            font-size: 13.5px
        }

        .ttd .tgl {
            margin-bottom: 14px
        }

        .ttd .nama {
            font-weight: 700;
            margin-top: 15px;
        }

        .ttd .jab {
            color: #555;
            margin-top: 2px
        }

        .tanda-tangan {
            width: 155px;
            height: auto;
            margin-top: 0px
        }
        /* TTD */
        .ttd-container { display: flex; justify-content: space-between; margin-top: 30px; font-size: 11pt; }
        .ttd-block { width: 45%; }
     
        .ttd-right { text-align: left; } /* Dibuat kiri agar sejajar dengan "Mengetahui," */
        
        .ttd-right .ttd-area { height: 100px; position: relative; } /* Ruang untuk ttd */
        .ttd-area img { 
            width: 155px; 
            height: auto; 
            position: absolute; 
            top: 00.1mm;
            bottom: 0px; 
            left: 0; 
            opacity: 0.9;
        }

        .ttd-right .nama { font-weight: 700; margin-top: 50px; text-decoration: underline; display: block;}
        .ttd-right .jab { color: #111; margin-top: 0px; display: block; }
        
        /* Penyesuaian agar TTD kiri (Yang Membuat) dan kanan (Mengetahui) sejajar */
        /* TTD KIRI */
        
        .ttd-area {
            position: relative;
            height: 100px;
            margin-top: 5px;
        }

        .ttd-area .nama {
            font-weight: 700;
            margin-top: 5px;
            text-decoration: underline;
            display: block;
        }

        .ttd-area .jab {
            color: #111;
            margin-top: 0;
            display: block;
        }
        
        .ttd-area-wrapper {
            position: relative;
        }
        
        .ttd-area-wrapper img {
            width: 150px;
            height: auto;
            position: absolute;
            bottom: 10px;
            left: 0;
        }
        
        .ttd-area-wrapper .nama {
            position: absolute;
            bottom: -20px;
            left: 0;
        }
          .ttd-area img {
                width: 130px
            }

            .ttd-area {
                height: 80px
            }

            .ttd .nama {
                font-size: 10.5pt
            }

        /* MEDIA PRINT (tetap) */
        @media print {
            body { background: #fff; }
            .sidebar, .download-controls { display: none !important; }
            .surat { box-shadow: none; border: 0; margin: 0; width: auto; min-height: auto; padding: 0; }
            .kop img { width: 60px; }
            .kop .title .pt, .kop .title .brand { font-size: 18pt; }
            .kop .detail { font-size: 8pt; }
            .judul, .nomor, .header-surat, .p, .tbl-karyawan, .ttd-container { font-size: 10.5pt !important; }
            .ttd-area img { width: 130px; }
            .ttd-area { height: 80px; }
        }
         /* Sidebar dropdown */
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
            background: #2c3e50;
            box-shadow: 0 4px 8px rgba(0, 0, 0, .2);
            padding: 0;
            margin: 0;
            list-style: none;
            z-index: 11;
            border-radius: 0 0 8px 8px;
            overflow: hidden;
        }

        .sidebar-nav .dropdown-menu li a {
            padding: 12px 20px;
            display: block;
            color: #ecf0f1;
            text-decoration: none;
            transition: background-color .3s;
        }

        .sidebar-nav .dropdown-menu li a:hover {
            background: #34495e;
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
        .download-controls button {
            padding: 8px 14px;
            background: #3498db;
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            margin-left: 6px;
        }
    </style>
</head>
<body>
    <div class="container">
        <aside class="sidebar">
            <div class="company-brand">
                <img src="../image/manu.png" class="company-logo">
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
                    <li><a href="../absensi/absensi.php"><i class="fas fa-edit"></i> Absensi</a></li>
                    <li class="dropdown-trigger">
                        <a href="#" class="dropdown-link"><i class="fas fa-users"></i> Data Karyawan <i
                                class="fas fa-caret-down"></i></a>
                        <ul class="dropdown-menu">
                            <li><a href="../data_karyawan/all_employees.php">Semua Karyawan</a></li>
                            <li><a href="../data_karyawan/karyawan_nonaktif.php">Non-Aktif</a></li>
                        </ul>
                    </li>
                   <li class="dropdown-trigger">
                            <a href="#" class="dropdown-link"><i class="fas fa-users"></i> Data Pengajuan <i class="fas fa-caret-down"><span class="badge"><?= $total_pending ?></span></i></a>
                            <ul class="dropdown-menu">
                                <li><a href="../pengajuan/pengajuan.php">Pengajuan</a></li>
                                <li><a href="../pengajuan/kelola_pengajuan.php">Kelola Pengajuan<span class="badge"><?= $total_pending ?></span></a></li>
                                <li><a href="../pengajuan/kelola_reimburse.php">Kelola Reimburse<span class="badge"><?= $total_pending ?></span></a></li>
                            </ul>
                        </li>
                    <li><a href="../monitoring_kontrak/monitoring_kontrak.php"><i class="fas fa-calendar-alt"></i>
                            Monitoring Kontrak</a></li>
                    <li class="active"><a href="surat_tugas_history.php"><i class="fas fa-file-alt"></i> Riwayat Surat
                            Tugas</a></li>
                    <li><a href="../payslip/e_payslip_admin.php"><i class="fas fa-money-check-alt"></i> E-Pay Slip</a>
                    </li>
                    <li><a href="../invoice/invoice.php"><i class="fas fa-money-check-dollar"></i> Invoice</a></li>
                </ul>
            </nav>
            <div class="logout-link"><a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a></div>
        </aside>

        <main class="main">
            <div class="download-controls" style="text-align:right; margin-bottom:16px;">
                <button onclick="downloadSuratAsPDF('<?= e($file_no_surat) ?>')"><i class="fas fa-download"></i> Unduh PDF</button>
                <button onclick="sendSuratAsEmail()" id="btnSendEmail"><i class="fas fa-envelope"></i> Kirim Surat ke Email</button>
            </div>
            <div id="emailStatus" style="margin:6px 0 12px; text-align:right; font-weight:600;"></div>

            <section class="surat" id="surat-tugas-dokumen">
                <div class="header-pt">
    <div class="kop-wrapper">
        <div class="logo-area">
            <img src="../image/manu.png" alt="Logo PT Mandiri Andalan Utama">
        </div>
        <div class="text-area">
            <h1>
                PT.
                <span style="color: red;">M</span>ANDIRI
                <span style="color: red;">A</span>NDALA<span style="color: red;">N</span>
                <span style="color: red;">U</span>TAMA
            </h1>
            <p>
                Jl. Sultan Iskandar Muda No. 30 A – B Lt. 3, Arteri Pondok Indah<br>
                Kebayoran Lama Selatan - Kebayoran Lama – Jakarta Selatan 12240<br>
                Telp: (021) 27518306 Web: http://www.manu.co.id/
            </p>
        </div>
    </div>
</div>

                <div class="judul">SURAT TUGAS</div>
                <div class="nomor">NO : <?= e($noSurat) ?></div>

                <div class="header-surat">
                    <table>
                        <tr>
                            <td class="lbl">Perihal</td>
                            <td>:</td>
                            <td>Surat Pengantar</td>
                        </tr>
                    </table>
                    <div class="yth-block">Yth :</div>
                    <div><strong><?= e($yth_kepala) ?></strong></div>
                </div>

                <p class="p">
                    Dengan ini kami PT. Mandiri Andalan Utama vendor dari <strong><?= e($klien_vendor) ?></strong>, ingin memberikan informasi bahwa nama di bawah ini adalah benar mitra kerja kami yang ditempatkan di cabang <strong><?= e($cabang_penempatan) ?></strong> : <?= e($alamat_penempatan) ?>.
                </p>

                <table class="tbl-karyawan">
                    <thead>
                        <tr>
                            <th class="no">No</th>
                            <th class="nama">Nama Karyawan</th>
                            <th class="posisi">Posisi</th>
                            <th class="sales">Sales Code</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="no">1</td>
                            <td class="nama"><?= e($nama) ?></td>
                            <td class="posisi"><?= e($posisi) ?></td>
                            <td class="sales"><?= e($salesCode) ?></td>
                        </tr>
                    </tbody>
                </table>

                <p class="p">
                    Dalam rangka menjalankan pekerjaannya sebagai <?= e($posisi) ?> di cabang yang Bapak/Ibu pimpin, besar harapan kami dapat diterima dan bekerja sama dengan baik.
                </p>
                <p class="p">
                    Demikian surat pengantar ini kami buat, atas perhatian dan kerja samanya kami ucapkan terima kasih.
                </p>

                <div class="ttd-container">
                    <div class="ttd">
                    <div class="tgl">Jakarta, <?= e(idDate($tglSurat)) ?></div>
                    <div class="y-membuat">Yang membuat,</div>
                    <div><strong>PT. Mandiri Andalan Utama</strong></div>
                    <div class="ttd-area">
                        <img src="../image/ttd.png" alt="Tanda Tangan">
                    </div>
                    <div class="nama"><?= e($nama_pembuat) ?></div>
                    <div class="jab"><?= e($jabatan_pembuat) ?></div>
                </div>
                    
                    <div class="ttd-right">
                        <div class="tgl" style="visibility: hidden;">Jakarta, <?= e(idDate($tglSurat)) ?></div>
                        <div class="y-membuat">Mengetahui,</div>
                        <div style="margin-bottom: 5px; height: 15px;"></div>
                        
                        <div class="ttd-area-wrapper" style="height: 100px;">
                            <div class="nama" style="margin-top: 10px;">.........................................</div>
                            <div class="jab" style="margin-top: 25px;">Sales Manager</div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.3/html2pdf.bundle.min.js"></script>

    <script>
        function sendSuratAsEmail() {
            const element = document.getElementById('surat-tugas-dokumen');
            const emailStatus = document.getElementById('emailStatus');
            const btn = document.getElementById('btnSendEmail');
            const original = btn.innerHTML;
            
            // Ambil data PHP
            const idSurat = "<?= e($r['id'] ?? 0) ?>";
            const emailTujuan = "<?= e($r['alamat_email'] ?? '') ?>"; // Menggunakan alamat_email dari PHP
            const fileNamePrefix = "<?= e($file_no_surat) ?>";

            if (emailTujuan === '') {
                 alert('Email karyawan tidak ditemukan di database.');
                 return;
            }

            if (!confirm("Kirim surat tugas ini ke email karyawan?")) return;

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengirim...';
            emailStatus.innerHTML = '';
            
            const fileFinalName = `Surat-Tugas-${fileNamePrefix}.pdf`;

            const opt = {
                margin: [5, 5, 5, 5],
                filename: fileFinalName, // Menggunakan nama file yang sama
                image: { type: 'jpeg', quality: 0.9 },
                html2canvas: { scale: 2, useCORS: true },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            const worker = html2pdf().from(element).set(opt);

            worker.output('blob').then(function (pdfBlob) {
                const formData = new FormData();
                formData.append('surat_pdf', pdfBlob, fileFinalName);
                formData.append('id_surat', idSurat);
                formData.append('email_tujuan', emailTujuan);

                return fetch('send_surat_email.php', {
                    method: 'POST',
                    body: formData
                });
            }).then(res => res.text()).then(result => {
                btn.disabled = false;
                btn.innerHTML = original;

                if (result && result.toLowerCase().includes('berhasil')) {
                    emailStatus.innerHTML = '<span style="color:#27ae60"><i class="fas fa-check-circle"></i> ' + result + '</span>';
                    btn.style.backgroundColor = '#95a5a6';
                } else {
                    emailStatus.innerHTML = '<span style="color:#c0392b"><i class="fas fa-exclamation-triangle"></i> ' + (result || 'Gagal mengirim. Cek console error.') + '</span>';
                }
            }).catch(err => {
                console.error("Gagal saat memproses PDF atau Jaringan:", err);
                emailStatus.innerHTML = '<span style="color:#c0392b"><i class="fas fa-times-circle"></i> Terjadi kesalahan fatal.</span>';
                btn.disabled = false;
                btn.innerHTML = original;
            });
        }

        function downloadSuratAsPDF(fileNamePrefix) {
            const element = document.getElementById('surat-tugas-dokumen');
            const opt = {
                margin: [6, 6, 6, 6],
                // SINTAKS DIPERBAIKI: Menggunakan backticks (`)
                filename: `Surat-Tugas-${fileNamePrefix}.pdf`, 
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true },
                jsPDF: { unit: 'mm', format: [230, 297], orientation: 'portrait' }
            };
            html2pdf().from(element).set(opt).save();
        }
    </script>
</body>
</html>