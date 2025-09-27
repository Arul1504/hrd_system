<?php
session_start();
require '../config.php';

// Memuat file-file inti dari PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// --- Periksa Hak Akses ADMIN ---
if (!isset($_SESSION['id_karyawan']) || ($_SESSION['role'] ?? '') !== 'ADMIN') {
    header("Location: ../../index.php");
    exit();
}

// Fungsi untuk mengirim email notifikasi (disalin dari file lain)
function kirimEmailNotifikasi($penerima, $subjek, $pesanHTML) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host      = 'smtp.gmail.com';
        $mail->SMTPAuth  = true;
        
        $mail->Username  = 'ptmanu216@gmail.com';     
        $mail->Password  = 'aiil pxsl ddfy jsnv';     
        
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port      = 465;

        $mail->setFrom('ptmanu216@gmail.com', 'PT MANDIRI ANDALAN UTAMA'); 
        $mail->addAddress($penerima);

        $mail->isHTML(true);
        $mail->Subject = $subjek;
        $mail->Body    = $pesanHTML;
        $mail->AltBody = strip_tags($pesanHTML);

        $mail->send();
    } catch (Exception $e) {
        // Log error, jangan tampilkan ke pengguna
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_karyawan = (int)($_POST['id_karyawan'] ?? 0);
    
    if ($id_karyawan <= 0 || !isset($_FILES['surat_tugas']) || $_FILES['surat_tugas']['error'] !== UPLOAD_ERR_OK) {
        header("Location: all_employees.php?status=upload_error");
        exit();
    }

    $file = $_FILES['surat_tugas'];
    $upload_dir = '../../uploads/surat_tugas/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $file_info = pathinfo($file['name']);
    $file_extension = strtolower($file_info['extension']);
    
    if ($file_extension !== 'pdf') {
        header("Location: all_employees.php?status=upload_error");
        exit();
    }

    $new_filename = 'surat_tugas_' . $id_karyawan . '_' . uniqid() . '.' . $file_extension;
    $target_path = $upload_dir . $new_filename;

    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        // --- Langkah 1: Perbarui database ---
        $stmt = $conn->prepare("UPDATE karyawan SET surat_tugas = ? WHERE id_karyawan = ?");
        if (!$stmt) {
             error_log("Error preparing statement: " . $conn->error);
             header("Location: all_employees.php?status=upload_error");
             exit();
        }
        $stmt->bind_param("si", $new_filename, $id_karyawan);
        
        if ($stmt->execute()) {
            // --- Langkah 2: Kirim email notifikasi ---
            // Ambil data karyawan untuk email
            $stmt_karyawan = $conn->prepare("SELECT nama_karyawan, alamat_email FROM karyawan WHERE id_karyawan = ?");
            $stmt_karyawan->bind_param("i", $id_karyawan);
            $stmt_karyawan->execute();
            $result_karyawan = $stmt_karyawan->get_result();
            $karyawan_data = $result_karyawan->fetch_assoc();
            $stmt_karyawan->close();
            
            if ($karyawan_data && !empty($karyawan_data['alamat_email'])) {
                $nama_karyawan = htmlspecialchars($karyawan_data['nama_karyawan']);
                $email_penerima = htmlspecialchars($karyawan_data['alamat_email']);
                $subjek = "Surat Tugas Anda Telah Tersedia";

                $pesan_html = "
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset='UTF-8'>
                        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                        <style>
                            body { font-family: 'Poppins', sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
                            .email-container { background-color: #ffffff; margin: 20px auto; padding: 20px 30px; max-width: 600px; border-radius: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
                            .header { text-align: center; padding-bottom: 20px; border-bottom: 1px solid #eeeeee; }
                            .header img { width: 150px; margin-bottom: 10px; }
                            .content { padding: 20px 0; line-height: 1.6; color: #333333; }
                            .download-btn { display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; }
                            .footer { text-align: center; padding-top: 20px; border-top: 1px solid #eeeeee; color: #999999; font-size: 0.9em; }
                        </style>
                    </head>
                    <body>
                        <div class='email-container'>
                            <div class='header'>
                                <img src='https://i.imgur.com/your-image-url.png' alt='Logo PT Mandiri Andalan Utama'>
                                <h1>Surat Tugas Anda Tersedia</h1>
                            </div>
                            <div class='content'>
                                <p>Halo " . $nama_karyawan . ",</p>
                                <p>Surat tugas terbaru Anda telah diunggah dan tersedia untuk diunduh.</p>
                                <p>Anda dapat mengunduhnya dengan masuk ke dashboard Anda.</p>
                                <p style='text-align: center; margin: 30px 0;'>
                                    <a href='http://localhost/hrd_system2/dashboard_karyawan.php' class='download-btn'>Masuk ke Dashboard</a>
                                </p>
                            </div>
                            <div class='footer'>
                                <p>Hormat kami,<br>PT Mandiri Andalan Utama</p>
                            </div>
                        </div>
                    </body>
                    </html>
                ";

                kirimEmailNotifikasi($email_penerima, $subjek, $pesan_html);
            }

            // Redirect dengan pesan sukses
            header("Location: all_employees.php?status=upload_success");
            exit();
        } else {
            error_log("Error updating DB: " . $stmt->error);
            header("Location: all_employees.php?status=upload_error");
            exit();
        }
    } else {
        header("Location: all_employees.php?status=upload_error");
        exit();
    }
} else {
    header("Location: all_employees.php");
    exit();
}