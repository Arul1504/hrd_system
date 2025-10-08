<?php
// ===========================
// surat_tugas/send_surat_email.php
// ===========================

// Pastikan PHPMailer sudah diinstal via Composer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require '../../PHPMailer/src/Exception.php';
require '../../PHPMailer/src/PHPMailer.php';
require '../../PHPMailer/src/SMTP.php';

require '../config.php';
if (session_status() === PHP_SESSION_NONE)
    session_start();

// Periksa hak akses & request
if (!isset($_SESSION['id_karyawan']) || !in_array($_SESSION['role'] ?? '', ['HRD','ADMIN','Admin','admin'])) {
    http_response_code(401);
    exit('Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id_surat']) || !isset($_FILES['surat_pdf'])) {
    http_response_code(400);
    exit('Permintaan tidak valid.');
}

$id_surat = (int) $_POST['id_surat'];

// Ambil data surat tugas & email karyawan
$q = $conn->prepare("SELECT st.no_surat, st.tgl_pembuatan, k.nama_karyawan, k.alamat_email, k.jabatan
                     FROM surat_tugas st
                     JOIN karyawan k ON k.id_karyawan = st.id_karyawan
                     WHERE st.id = ? LIMIT 1");
$q->bind_param("i", $id_surat);
$q->execute();
$r = $q->get_result()->fetch_assoc();
$q->close();

if (!$r || empty($r['alamat_email'])) {
    $conn->close();
    http_response_code(404);
    exit('Data surat tugas atau alamat email karyawan tidak ditemukan.');
}

// Tentukan nomor surat & tanggal
$no_surat = $r['no_surat'] ?? 'Tanpa Nomor';
$tanggal  = date('d M Y', strtotime($r['tgl_pembuatan'] ?? date('Y-m-d')));

// Konfigurasi PHPMailer
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com'; 
    $mail->SMTPAuth = true;
    $mail->Username = 'ptmanu216@gmail.com'; 
    $mail->Password = 'aiil pxsl ddfy jsnv'; // <- ganti sesuai App Password Gmail
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;

    $mail->setFrom('ptmanu216@gmail.com', 'PT Mandiri Andalan Utama');
    $mail->addAddress($r['alamat_email'], $r['nama_karyawan']);
    $mail->addReplyTo('hrd@manu.co.id', 'HRD'); 

    // Lampirkan file PDF surat tugas
    $surat_path = $_FILES['surat_pdf']['tmp_name'];
    $surat_name = 'Surat Tugas - ' . $r['nama_karyawan'] . ' - ' . $no_surat . '.pdf';
    $mail->addAttachment($surat_path, $surat_name);

    // Isi email
    $mail->isHTML(true);
    $mail->Subject = 'Surat Tugas No. ' . $no_surat;
    $mail->Body = "
        <p>Yth. Bpk/Ibu <b>" . htmlspecialchars($r['nama_karyawan']) . "</b>,</p>
        <p>Berikut terlampir surat tugas dengan nomor <b>" . htmlspecialchars($no_surat) . "</b>, 
        tertanggal <b>" . $tanggal . "</b>.</p>
        <p>Harap surat ini dijaga kerahasiaannya dan digunakan sebagaimana mestinya.</p>
        <p>Hormat kami,<br>
        <b>HRD PT Mandiri Andalan Utama</b></p>";
    $mail->AltBody = "Yth. " . $r['nama_karyawan'] . ", berikut surat tugas nomor " . $no_surat . " tanggal " . $tanggal;

    $mail->send();

    echo 'Surat tugas berhasil dikirim ke ' . $r['alamat_email'];

} catch (Exception $e) {
    http_response_code(500);
    echo "Pengiriman email gagal. Kesalahan: {$mail->ErrorInfo}";
}

$conn->close();
?>
