<?php


require '../config.php';

// Fungsi bantuan untuk HTML escaping (jika belum ada di config.php)
if (!function_exists('e')) {
    function e($text)
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

// Periksa hak akses ADMIN
if (!isset($_SESSION['id_karyawan']) || $_SESSION['role'] !== 'ADMIN') {
    header("Location: ../../index.php");
    exit();
}

// Ambil data admin dari sesi
$nama_admin = $_SESSION['nama'] ?? 'ADMIN_SYSTEM';

if (isset($_GET['action']) && isset($_GET['id']) && isset($_GET['notes'])) {
    $action = $_GET['action'];
    $id_pengajuan = (int)$_GET['id'];
    // Notes sudah di-URL encode di frontend, kita gunakan di sini
    $notes = $_GET['notes']; 
    
    $new_status = '';

    if ($action === 'approve') {
        $new_status = 'Disetujui';
    } elseif ($action === 'reject') {
        $new_status = 'Ditolak';
    } else {
        header("Location: kelola_reimburse.php");
        exit();
    }

    // --- 1. UPDATE status final di tabel pengajuan ---
    // Mencatat status, approved_by, dan tanggal persetujuan/penolakan terakhir
    $sql_update = "UPDATE pengajuan SET 
                    status_pengajuan = ?, 
                    approved_by = ?, 
                    tanggal_persetujuan = NOW() 
                   WHERE id_pengajuan = ?";
    
    $stmt_update = $conn->prepare($sql_update);
    // Menggunakan 'ssi' (string, string, integer)
    $stmt_update->bind_param("ssi", $new_status, $nama_admin, $id_pengajuan);

    // --- 2. INSERT log ke tabel pengajuan_log ---
    // Log status mencakup nama admin untuk riwayat multi-approval
    $log_status = ($action === 'approve') ? "Approved by " . $nama_admin : "Rejected by " . $nama_admin;
    
    // ASUMSI: Tabel pengajuan_log ada (seperti instruksi sebelumnya)
    $sql_log = "INSERT INTO pengajuan_log (id_pengajuan, status, notes, tanggal) 
                VALUES (?, ?, ?, NOW())";

    $stmt_log = $conn->prepare($sql_log);
    // Menggunakan 'iss' (integer, string, string)
    $stmt_log->bind_param("iss", $id_pengajuan, $log_status, $notes);
    
    $success = true;
    
    $conn->begin_transaction(); // Mulai transaksi untuk memastikan kedua query berhasil
    
    try {
        if (!$stmt_update->execute()) {
            throw new Exception("Error updating pengajuan: " . $stmt_update->error);
        }
        
        if (!$stmt_log->execute()) {
            throw new Exception("Error inserting log: " . $stmt_log->error);
        }
        
        $conn->commit(); // Commit jika kedua query berhasil
        
    } catch (Exception $e) {
        $conn->rollback(); // Rollback jika ada error
        die("Error pemrosesan: " . $e->getMessage());
        $success = false;
    }

    // Redirect kembali ke halaman kelola reimburse
    if ($success) {
        header("Location: kelola_reimburse.php?status=" . ($action === 'approve' ? 'approved' : 'rejected'));
        exit();
    } else {
        // Jika gagal, redirect ke halaman error atau kembali ke kelola_reimburse
        header("Location: kelola_reimburse.php?status=error_processing");
        exit();
    }
    
    // Tutup statement
    $stmt_update->close();
    $stmt_log->close();
    $conn->close();

} else {
    header("Location: kelola_reimburse.php");
    exit();
}
?>