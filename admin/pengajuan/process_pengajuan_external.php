<?php
// process_pengajuan_external.php

// Memuat file-file inti dari PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Jalur relatif ke folder PHPMailer
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// Memuat konfigurasi database dan sesi
require '../config.php';

// Fungsi untuk escape output


// Fungsi untuk mengirim email notifikasi
function kirimEmailNotifikasi($penerima, $subjek, $pesanHTML)
{
    $mail = new PHPMailer(true);

    try {
        // Pengaturan Server SMTP (contoh: Gmail)
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;

        // --- GANTI DENGAN KREDENSIAL AKUN ANDA ---
        $mail->Username = 'ptmanu216@gmail.com';     // Email pengirim
        $mail->Password = 'aiil pxsl ddfy jsnv';     // Sandi aplikasi (app password)

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
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
        // Tidak perlu menampilkan error ke user, bisa dicatat di log server
    }
}

// --- Logika Pemrosesan Pengajuan ---

// Periksa hak akses ADMIN
if (!isset($_SESSION['id_karyawan']) || $_SESSION['role'] !== 'ADMIN') {
    header("Location: ../../index.php");
    exit();
}

// Periksa apakah ada permintaan aksi
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id_pengajuan = (int) $_GET['id'];
    $valid_actions = ['approve', 'reject'];

    if (in_array($action, $valid_actions)) {
        // Ambil detail pengajuan untuk notifikasi
        $stmt_select = $conn->prepare("SELECT email_pengaju, nama_pengaju, jenis_pengajuan, tanggal_mulai, tanggal_berakhir FROM pengajuan WHERE id_pengajuan = ?");
        $stmt_select->bind_param("i", $id_pengajuan);
        $stmt_select->execute();
        $result_select = $stmt_select->get_result();
        $pengajuan_data = $result_select->fetch_assoc();
        $stmt_select->close();

        // Pastikan data pengajuan ditemukan dan ada email
        if ($pengajuan_data && !empty($pengajuan_data['email_pengaju'])) {
            $email_penerima = $pengajuan_data['email_pengaju'];
            $nama_pengaju = e($pengajuan_data['nama_pengaju']);
            $jenis_pengajuan = e($pengajuan_data['jenis_pengajuan']);
            $tgl_mulai = e(date('d M Y', strtotime($pengajuan_data['tanggal_mulai'])));
            $tgl_berakhir = e(date('d M Y', strtotime($pengajuan_data['tanggal_berakhir'])));

            // Tentukan status dan pesan
            if ($action === 'approve') {
                $status = 'Disetujui';
                $subjek_email = "Notifikasi Pengajuan Cuti/Izin Disetujui";
                $judul = "Pengajuan Anda Telah Disetujui";
            } else {
                $status = 'Ditolak';
                $subjek_email = "Notifikasi Pengajuan Cuti/Izin Ditolak";
                $judul = "Pengajuan Anda Ditolak";
            }

            // --- Tampilan Email Baru yang Rapi ---
            $pesan_html = "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                    <style>
                        body {
                            font-family: 'Poppins', sans-serif;
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
                        .header img {
                            width: 150px;
                            margin-bottom: 10px;
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
                        .status-approved { color: #28a745; font-weight: bold; }
                        .status-rejected { color: #dc3545; font-weight: bold; }
                    </style>
                </head>
                <body>
                    <div class='email-container'>
                        <div class='header'>
                                                        <h1>$judul</h1>
                        </div>
                        <div class='content'>
                            <p>Halo " . $nama_pengaju . ",</p>
                            <p>Dengan hormat, kami informasikan bahwa pengajuan Anda telah diproses dengan status sebagai berikut:</p>
                            <table class='details-table'>
                                <tr>
                                    <td><strong>Jenis Pengajuan</strong></td>
                                    <td>" . $jenis_pengajuan . "</td>
                                </tr>
                                <tr>
                                    <td><strong>Periode</strong></td>
                                    <td>" . $tgl_mulai . " - " . $tgl_berakhir . "</td>
                                </tr>
                                <tr>
                                    <td><strong>Status</strong></td>
                                    <td><span class='status-" . strtolower($status) . "'>" . $status . "</span></td>
                                </tr>
                            </table>
                            <p>Terima kasih atas perhatiannya.</p>
                        </div>
                        <div class='footer'>
                            <p>Hormat kami,<br>PT Mandiri Andalan Utama</p>
                        </div>
                    </div>
                </body>
                </html>
            ";
            // --- Akhir Tampilan Email ---

            // Perbarui status di database
            $stmt_update = $conn->prepare("UPDATE pengajuan SET status_pengajuan = ?, tanggal_update = NOW() WHERE id_pengajuan = ?");
            $stmt_update->bind_param("si", $status, $id_pengajuan);
            $stmt_update->execute();
            $stmt_update->close();

            // Kirim email notifikasi setelah berhasil diperbarui
            kirimEmailNotifikasi($email_penerima, $subjek_email, $pesan_html);

            // Redirect kembali dengan status
            header("Location: kelola_pengajuan.php?status=" . ($action === 'approve' ? 'approved' : 'rejected'));
            exit();

        } else {
            // Jika data pengajuan tidak ditemukan atau tidak ada email,
            // tetap perbarui status dan redirect tanpa kirim email
            $status = ($action === 'approve' ? 'Disetujui' : 'Ditolak');
            $stmt_update = $conn->prepare("UPDATE pengajuan SET status_pengajuan = ?, tanggal_update = NOW() WHERE id_pengajuan = ?");
            $stmt_update->bind_param("si", $status, $id_pengajuan);
            $stmt_update->execute();
            $stmt_update->close();

            header("Location: kelola_pengajuan.php?status=" . ($action === 'approve' ? 'approved' : 'rejected'));
            exit();
        }

    }
} else {
    // Jika tidak ada parameter yang valid
    header("Location: kelola_pengajuan.php");
    exit();
}

$conn->close();