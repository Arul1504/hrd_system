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
function kirimEmailNotifikasi($penerima, $subjek, $pesanHTML) {
    $mail = new PHPMailer(true);

    try {
        // Pengaturan Server SMTP (contoh: Gmail)
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        
        // --- GANTI DENGAN KREDENSIAL AKUN ANDA ---
        $mail->Username   = 'ptmanu216@gmail.com';     // Email pengirim
        $mail->Password   = 'aiil pxsl ddfy jsnv';     // Sandi aplikasi (app password)
        
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        // Pengaturan Pengirim dan Penerima
        $mail->setFrom('ptmanu216@gmail.com', 'PT MANDIRI ANDALAN UTAMA'); 
        $mail->addAddress($penerima);

        // Konten Email
        $mail->isHTML(true);
        $mail->Subject = $subjek;
        $mail->Body    = $pesanHTML;
        $mail->AltBody = strip_tags($pesanHTML);

        $mail->send();
    } catch (Exception $e) {
        // Tidak perlu menampilkan error ke user, bisa dicatat di log server
    }
}

// --- Logika Pemrosesan Pengajuan ---

// Periksa hak akses HRD
if (!isset($_SESSION['id_karyawan']) || $_SESSION['role'] !== 'HRD') {
    header("Location: ../../index.php");
    exit();
}

// Periksa apakah ada permintaan aksi
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id_pengajuan = (int)$_GET['id'];
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

            if ($action === 'approve') {
                $status = 'Disetujui';
                $subjek_email = "Notifikasi Pengajuan Cuti/Izin Disetujui";
                $pesan_html = "
                    <html>
                    <body>
                        <h3>Pengajuan Anda Telah Disetujui</h3>
                        <p>Halo " . $nama_pengaju . ",</p>
                        <p>Pengajuan Anda telah disetujui. Berikut rincian pengajuan Anda:</p>
                        <ul>
                            <li><strong>Jenis Pengajuan:</strong> " . $jenis_pengajuan . "</li>
                            <li><strong>Periode:</strong> " . $tgl_mulai . " hingga " . $tgl_berakhir . "</li>
                            <li><strong>Status:</strong> Disetujui</li>
                        </ul>
                        <p>Terima kasih.</p>
                        <p>Hormat kami,<br>PT Mandiri Andalan Utama</p>
                    </body>
                    </html>
                ";
            } elseif ($action === 'reject') {
                $status = 'Ditolak';
                $subjek_email = "Notifikasi Pengajuan Cuti/Izin Ditolak";
                $pesan_html = "
                    <html>
                    <body>
                        <h3>Pengajuan Anda Ditolak</h3>
                        <p>Halo " . $nama_pengaju . ",</p>
                        <p>Mohon maaf, pengajuan Anda telah ditolak. Berikut rincian pengajuan Anda:</p>
                        <ul>
                            <li><strong>Jenis Pengajuan:</strong> " . $jenis_pengajuan . "</li>
                            <li><strong>Periode:</strong> " . $tgl_mulai . " hingga " . $tgl_berakhir . "</li>
                            <li><strong>Status:</strong> Ditolak</li>
                        </ul>
                        <p>Untuk informasi lebih lanjut, silakan hubungi tim HRD.</p>
                        <p>Hormat kami,<br>PT Mandiri Andalan Utama</p>
                    </body>
                    </html>
                ";
            }

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