<?php
// ===========================
// payslip/send_slip_email.php
// ===========================

// Pastikan PHPMailer sudah diinstal via Composer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Jalur relatif ke folder PHPMailer
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

require '../config.php';
if (session_status() === PHP_SESSION_NONE)
    session_start();

// Periksa hak akses dan metode permintaan
if (!isset($_SESSION['id_karyawan']) || !in_array($_SESSION['role'] ?? '', ['HRD', 'ADMIN', 'Admin', 'admin'])) {
    http_response_code(401);
    exit('Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id_slip']) || !isset($_FILES['slip_pdf'])) {
    http_response_code(400);
    exit('Permintaan tidak valid.');
}

$id_slip = (int) $_POST['id_slip'];

// Ambil data slip gaji dan email karyawan dari database
$q = $conn->prepare("SELECT p.periode_tahun, p.periode_bulan, k.nama_karyawan, k.alamat_email, k.jabatan
                     FROM payroll p
                     JOIN karyawan k ON k.id_karyawan = p.id_karyawan
                     WHERE p.id = ? LIMIT 1");
$q->bind_param("i", $id_slip);
$q->execute();
$r = $q->get_result()->fetch_assoc();
$q->close();

if (!$r || empty($r['alamat_email'])) {
    // Tutup koneksi sebelum keluar
    $conn->close();
    http_response_code(404);
    exit('Data slip gaji atau alamat email karyawan tidak ditemukan.');
}

$periode = date('F Y', strtotime($r['periode_tahun'] . '-' . $r['periode_bulan'] . '-01'));

// Konfigurasi PHPMailer
$mail = new PHPMailer(true);
try {
    // Pengaturan Server
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com'; 
    $mail->SMTPAuth = true;
    $mail->Username = 'ptmanu216@gmail.com'; 
    $mail->Password = 'aiil pxsl ddfy jsnv'; 
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;
    $mail->setFrom('ptmanu216@gmail.com', 'PT Mandiri Andalan Utama');
    $mail->addAddress($r['alamat_email'], $r['nama_karyawan']);
    $mail->addReplyTo('hrd@manu.co.id', 'HRD'); 

    // Lampirkan file PDF yang dikirim dari frontend
    $slip_path = $_FILES['slip_pdf']['tmp_name'];
    $slip_name = 'Slip Gaji - ' . $r['nama_karyawan'] . ' - ' . $periode . '.pdf';
    $mail->addAttachment($slip_path, $slip_name);

    // Konten Email
    $mail->isHTML(true);
    $mail->Subject = 'Slip Gaji Bulan ' . $periode;
    $mail->Body = "
        <p>Yth. Bpk/Ibu <b>" . htmlspecialchars($r['nama_karyawan']) . "</b>,</p>
        <p>Berikut terlampir slip gaji Anda untuk periode bulan <b>" . $periode . "</b>.</p>
        <p>Slip ini adalah dokumen rahasia. Jangan bagikan kepada siapa pun.</p>
        <p>Hormat kami,<br>
        <b>HRD PT Mandiri Andalan Utama</b></p>";
    $mail->AltBody = "Yth. " . $r['nama_karyawan'] . ", berikut terlampir slip gaji Anda untuk periode bulan " . $periode . ".";

    $mail->send();

    // Perbarui status di database setelah email berhasil dikirim
    $update_status_q = $conn->prepare("UPDATE payroll SET is_email_sent = 1 WHERE id = ?");
    $update_status_q->bind_param("i", $id_slip);
    $update_status_q->execute();
    $update_status_q->close();
    
    echo 'Slip gaji berhasil dikirim ke ' . $r['alamat_email'];

} catch (Exception $e) {
    http_response_code(500);
    echo "Pengiriman email gagal. Kesalahan: {$mail->ErrorInfo}";
}

// Tutup koneksi database di akhir skrip
$conn->close();
?>