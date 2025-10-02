<?php
// ===================================
// send_rekening_email.php
// Mengirim Surat Rekomendasi Rekening sebagai PDF lampiran
// ===================================

// Pastikan PHPMailer sudah diinstal via Composer (atau pastikan require path benar)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Sesuaikan path PHPMailer dengan struktur folder Anda
// Asumsi: folder PHPMailer berada di root atau di path yang dapat diakses
require '../PHPMailer/src/Exception.php'; 
require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';

require '../../config.php'; // Koneksi ke database
if (session_status() === PHP_SESSION_NONE)
    session_start();

// Periksa hak akses & request
if (!isset($_SESSION['id_karyawan']) || !in_array($_SESSION['role'] ?? '', ['ADMIN'])) {
    http_response_code(401);
    exit('Unauthorized: Anda tidak memiliki hak akses.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id_karyawan']) || !isset($_FILES['surat_pdf'])) {
    http_response_code(400);
    exit('Permintaan tidak valid: Data karyawan atau file PDF tidak ditemukan.');
}

// Data yang dikirim dari frontend
$id_karyawan = (int) $_POST['id_karyawan'];
$nomor_surat_baru = htmlspecialchars(trim($_POST['nomor_surat'] ?? ''));
$tanggal_surat_raw = htmlspecialchars(trim($_POST['tanggal_surat'] ?? date('Y-m-d')));

// Ambil data karyawan & alamat email
$q = $conn->prepare("SELECT nama_karyawan, nik_ktp, jabatan, alamat_email, proyek
                     FROM karyawan
                     WHERE id_karyawan = ? LIMIT 1");
$q->bind_param("i", $id_karyawan);
$q->execute();
$r = $q->get_result()->fetch_assoc();
$q->close();
$conn->close();

if (!$r || empty($r['alamat_email'])) {
    http_response_code(404);
    exit('Alamat email karyawan tidak ditemukan.');
}

// Helper untuk format tanggal Indonesia
function formatTanggalIndonesia($tgl) {
    $bulan = [
        'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret', 
        'April' => 'April', 'May' => 'Mei', 'June' => 'Juni', 
        'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September', 
        'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember'
    ];
    return strtr(date('d F Y', strtotime($tgl)), $bulan);
}

$tanggal_formatted = formatTanggalIndonesia($tanggal_surat_raw);
$nama_karyawan = htmlspecialchars($r['nama_karyawan']);
$email_tujuan = $r['alamat_email'];

// Konfigurasi PHPMailer
$mail = new PHPMailer(true);
try {
    // Pengaturan Server
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com'; 
    $mail->SMTPAuth = true;
    $mail->Username = 'ptmanu216@gmail.com'; 
    $mail->Password = 'aiil pxsl ddfy jsnv'; // Ganti sesuai App Password Gmail Anda
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;
    $mail->CharSet = 'UTF-8';

    // Pengirim dan Penerima
    $mail->setFrom('ptmanu216@gmail.com', 'PT Mandiri Andalan Utama');
    $mail->addAddress($email_tujuan, $nama_karyawan);
    $mail->addReplyTo('hrd@manu.co.id', 'HRD'); 

    // Lampirkan file PDF surat dari input form
    $surat_path = $_FILES['surat_pdf']['tmp_name'];
    $surat_name = 'Surat Rekomendasi Rekening - ' . $nama_karyawan . ' - ' . $nomor_surat_baru . '.pdf';
    
    // PHPMailer memerlukan nama file yang valid, jadi kita pakai nama yang sudah dibersihkan
    $mail->addAttachment($surat_path, $surat_name);

    // Isi email
    $mail->isHTML(true);
    $mail->Subject = 'Surat Rekomendasi Pembukaan Rekening Bank | ' . $nama_karyawan;
    $mail->Body = "
        <p>Yth. Bpk/Ibu <b>" . $nama_karyawan . "</b>,</p>
        <p>Berikut terlampir Surat Rekomendasi Pembukaan Rekening Bank CIMB Niaga dengan nomor <b>" . $nomor_surat_baru . "</b>, 
        tertanggal <b>" . $tanggal_formatted . "</b>.</p>
        <p>Silakan gunakan surat ini sebagai dokumen persyaratan untuk proses pembukaan rekening bank Anda.</p>
        <p>Hormat kami,<br>
        <b>HRD PT Mandiri Andalan Utama</b></p>";
        
    $mail->AltBody = "Yth. " . $nama_karyawan . ", berikut Surat Rekomendasi Pembukaan Rekening Bank dengan nomor " . $nomor_surat_baru . " tanggal " . $tanggal_formatted;

    $mail->send();

    echo 'Surat Rekomendasi Rekening berhasil dikirim ke ' . $email_tujuan;

} catch (Exception $e) {
    http_response_code(500);
    error_log("PHPMailer Error: " . $e->getMessage()); // Log error untuk debugging server
    echo "Pengiriman email gagal. Kesalahan: Silakan cek email tujuan dan konfigurasi server. (" . $mail->ErrorInfo . ")";
}
?>
