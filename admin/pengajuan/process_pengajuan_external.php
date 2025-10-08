<?php
// process_pengajuan_external.php
// Script untuk memproses (Approve/Reject) pengajuan dari karyawan non-login
// dan mengirimkan notifikasi email status.

// Memuat file-file inti dari PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Jalur relatif ke folder PHPMailer
require '../../PHPMailer/src/Exception.php';
require '../../PHPMailer/src/PHPMailer.php';
require '../../PHPMailer/src/SMTP.php';

// Memuat konfigurasi database dan sesi
// diasumsikan file ini menyediakan koneksi $conn dan memulai session
require '../config.php'; 

// Fungsi untuk escape output (PENTING untuk mencegah XSS)



// Fungsi untuk mengirim email notifikasi
function kirimEmailNotifikasi($penerima, $subjek, $pesanHTML)
{
    $mail = new PHPMailer(true);

    try {
        // Pengaturan Server SMTP (contoh: Gmail)
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;

        // --- KREDENSIAL AKUN PENGIRIM ---
        // PENTING: Gunakan App Password jika memakai Gmail
        $mail->Username = 'ptmanu216@gmail.com';     // Email pengirim
        $mail->Password = 'aiil pxsl ddfy jsnv';     // Sandi aplikasi (app password)

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Gunakan SSL/SMTPS
        $mail->Port = 465;

        // Pengaturan Pengirim dan Penerima
        $mail->setFrom('ptmanu216@gmail.com', 'PT MANDIRI ANDALAN UTAMA');
        $mail->addAddress($penerima);

        // Konten Email
        $mail->isHTML(true);
        $mail->Subject = $subjek;
        $mail->Body = $pesanHTML;
        $mail->AltBody = strip_tags($pesanHTML);

        $mail->send();
    } catch (Exception $e) {
        // Catat error ke log server untuk debugging. 
        // Jangan tampilkan ke pengguna akhir.
        error_log("PHPMailer Error: Gagal mengirim email ke " . $penerima . ". Error: " . $mail->ErrorInfo);
    }
}

// --- Logika Pemrosesan Pengajuan ---

// Periksa hak akses ADMIN (Asumsi: $_SESSION sudah tersedia dari config.php)
if (session_status() == PHP_SESSION_NONE) { session_start(); } // Pastikan session dimulai
if (!isset($_SESSION['id_karyawan']) || $_SESSION['role'] !== 'ADMIN') {
    // Arahkan ke halaman login jika tidak berhak
    header("Location: ../../index.php");
    exit();
}

// Periksa apakah ada permintaan aksi
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id_pengajuan = (int) $_GET['id'];
    $valid_actions = ['approve', 'reject'];

    if (in_array($action, $valid_actions)) {
        // 1. Ambil detail pengajuan untuk notifikasi
        $stmt_select = $conn->prepare("SELECT email_pengaju, nama_pengaju, jenis_pengajuan, tanggal_mulai, tanggal_berakhir FROM pengajuan WHERE id_pengajuan = ?");
        $stmt_select->bind_param("i", $id_pengajuan);
        $stmt_select->execute();
        $result_select = $stmt_select->get_result();
        $pengajuan_data = $result_select->fetch_assoc();
        $stmt_select->close();

        // Tentukan status yang akan di-update
        $status = ($action === 'approve') ? 'Disetujui' : 'Ditolak';
        
        // 2. Perbarui status di database
        $admin_id = $_SESSION['id_karyawan'] ?? NULL; // Asumsi ID admin tersimpan di session
        
        $sql_update = "UPDATE pengajuan SET status_pengajuan = ?, tanggal_update = NOW(), approved_by = ?, tanggal_persetujuan = NOW() WHERE id_pengajuan = ?";
        $stmt_update = $conn->prepare($sql_update);
        
        if ($stmt_update) {
            $stmt_update->bind_param("sii", $status, $admin_id, $id_pengajuan);
            $stmt_update->execute();
            $stmt_update->close();
        } else {
             // Tangani error prepare statement
             error_log("Error updating status: " . $conn->error);
        }


        // 3. Kirim Email Notifikasi (hanya jika data pengajuan dan email ditemukan)
        if ($pengajuan_data && !empty($pengajuan_data['email_pengaju'])) {
            $email_penerima = $pengajuan_data['email_pengaju'];
            $nama_pengaju = e($pengajuan_data['nama_pengaju']);
            $jenis_pengajuan = e($pengajuan_data['jenis_pengajuan']);
            $tgl_mulai = e(date('d M Y', strtotime($pengajuan_data['tanggal_mulai'])));
            $tgl_berakhir = e(date('d M Y', strtotime($pengajuan_data['tanggal_berakhir'])));

            if ($action === 'approve') {
                $subjek_email = "Notifikasi Pengajuan Cuti/Izin Disetujui";
                $judul = "Pengajuan Anda Telah Disetujui";
            } else {
                $subjek_email = "Notifikasi Pengajuan Cuti/Izin Ditolak";
                $judul = "Pengajuan Anda Ditolak";
            }

            // --- Tampilan Email HTML ---
            $pesan_html = "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                    <style>
                        body {
                            font-family: 'Inter', 'Poppins', sans-serif;
                            background-color: #f4f4f4;
                            margin: 0;
                            padding: 0;
                        }
                        .email-container {
                            background-color: #ffffff;
                            margin: 20px auto;
                            padding: 20px 30px;
                            max-width: 600px;
                            border-radius: 10px;
                            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
                        }
                        .header {
                            text-align: center;
                            padding-bottom: 20px;
                            border-bottom: 1px solid #eeeeee;
                        }
                        .header h1 {
                            color: #16a34a; /* Warna Hijau Primer */
                            margin: 0;
                        }
                        .content {
                            padding: 20px 0;
                            line-height: 1.6;
                            color: #333333;
                        }
                        .details-table {
                            width: 100%;
                            border-collapse: collapse;
                            margin: 20px 0;
                        }
                        .details-table td {
                            padding: 10px;
                            border: 1px solid #eeeeee;
                        }
                        .details-table strong {
                            color: #555555;
                        }
                        .footer {
                            text-align: center;
                            padding-top: 20px;
                            border-top: 1px solid #eeeeee;
                            color: #999999;
                            font-size: 0.9em;
                        }
                        .status-disetujui { color: #28a745; font-weight: bold; background-color: #e6ffe6; padding: 5px; border-radius: 4px; }
                        .status-ditolak { color: #dc3545; font-weight: bold; background-color: #ffe6e6; padding: 5px; border-radius: 4px; }
                    </style>
                </head>
                <body>
                    <div class='email-container'>
                        <div class='header'>
                             <h1>{$judul}</h1>
                        </div>
                        <div class='content'>
                            <p>Halo " . $nama_pengaju . ",</p>
                            <p>Dengan hormat, kami informasikan bahwa pengajuan **{$jenis_pengajuan}** Anda telah diproses dengan status sebagai berikut:</p>
                            <table class='details-table'>
                                <tr>
                                    <td><strong>Jenis Pengajuan</strong></td>
                                    <td>" . $jenis_pengajuan . "</td>
                                </tr>
                                <tr>
                                    <td><strong>Periode</strong></td>
                                    <td>" . $tgl_mulai . " s/d " . $tgl_berakhir . "</td>
                                </tr>
                                <tr>
                                    <td><strong>Status Keputusan</strong></td>
                                    <td><span class='status-" . strtolower(str_replace(' ', '-', $status)) . "'>" . $status . "</span></td>
                                </tr>
                            </table>
                            <p>Terima kasih atas perhatiannya.</p>
                        </div>
                        <div class='footer'>
                            <p>Hormat kami,<br>PT Mandiri Andalan Utama</p>
                            <p>Ini adalah email otomatis, mohon tidak membalas.</p>
                        </div>
                    </div>
                </body>
                </html>
            ";
            // --- Akhir Tampilan Email ---

            kirimEmailNotifikasi($email_penerima, $subjek_email, $pesan_html);
        }

        // 4. Redirect kembali ke halaman kelola pengajuan
        header("Location: kelola_pengajuan.php?status=" . ($action === 'approve' ? 'approved' : 'rejected'));
        exit();
    }
} else {
    // Jika tidak ada parameter yang valid
    header("Location: kelola_pengajuan.php");
    exit();
}

$conn->close();
