<?php
// =========================================================
// process_pengajuan_cuti.php
// Menangani proses Approve/Reject untuk Cuti, Izin, & Sakit
// =========================================================
session_start();
require '../config.php';

// Fungsi bantuan untuk HTML escaping
if (!function_exists('e')) {
    function e($text)
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

// Cek hak akses ADMIN/HRD
if (!isset($_SESSION['id_karyawan']) || !in_array($_SESSION['role'] ?? '', ['ADMIN', 'HRD'])) {
    header("Location: ../../index.php");
    exit();
}

// Ambil data admin dari sesi
$nama_admin = $_SESSION['nama'] ?? 'ADMIN_SYSTEM';
$id_admin = $_SESSION['id_karyawan'];

// Ambil parameter yang dibutuhkan
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id_pengajuan = (int)$_GET['id'];
    
    // Notes default karena tidak ada form input notes di kelola_pengajuan.php
    $notes = $_GET['notes'] ?? 'Disetujui/Ditolak melalui halaman Kelola Pengajuan.'; 
    
    $new_status = '';

    if ($action === 'approve') {
        $new_status = 'Disetujui';
    } elseif ($action === 'reject') {
        $new_status = 'Ditolak';
    } else {
        // Aksi tidak valid, kembali ke halaman kelola pengajuan
        header("Location: kelola_pengajuan.php");
        exit();
    }

    // Pastikan ID valid
    if ($id_pengajuan <= 0) {
        header("Location: kelola_pengajuan.php?status=error_invalid_id");
        exit();
    }

    // --- 1. UPDATE status final di tabel pengajuan ---
    // Hanya proses pengajuan Cuti/Izin/Sakit (yang bukan Reimburse)
    $sql_update = "UPDATE pengajuan SET 
                    status_pengajuan = ?, 
                    approved_by = ?, 
                    tanggal_persetujuan = NOW() 
                    WHERE id_pengajuan = ? AND jenis_pengajuan != 'Reimburse' AND status_pengajuan = 'Menunggu'";
    
    $stmt_update = $conn->prepare($sql_update);
    
    // Menggunakan 'sii' (string status, integer approved_by ID, integer id_pengajuan)
    $stmt_update->bind_param("sii", $new_status, $id_admin, $id_pengajuan);

    // --- 2. INSERT log ke tabel pengajuan_log ---
    $log_status = ($action === 'approve') ? "Disetujui oleh " . $nama_admin : "Ditolak oleh " . $nama_admin;
    
    // ASUMSI: Tabel pengajuan_log ada
    $sql_log = "INSERT INTO pengajuan_log (id_pengajuan, status, notes, tanggal) 
                 VALUES (?, ?, ?, NOW())";

    $stmt_log = $conn->prepare($sql_log);
    $stmt_log->bind_param("iss", $id_pengajuan, $log_status, $notes);
    
    $success = true;
    $conn->begin_transaction(); // Mulai transaksi
    
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
        error_log("Error pemrosesan pengajuan ID $id_pengajuan: " . $e->getMessage());
        $success = false;
    }

    // Redirect secara HARDCODE kembali ke kelola_pengajuan.php
    if ($success) {
        header("Location: kelola_pengajuan.php?status=" . ($action === 'approve' ? 'approved' : 'rejected'));
    } else {
        header("Location: kelola_pengajuan.php?status=error_processing");
    }
    
    // Tutup koneksi
    if (isset($stmt_update)) $stmt_update->close();
    if (isset($stmt_log)) $stmt_log->close();
    $conn->close();
    exit();

} else {
    // Parameter tidak lengkap atau tidak valid
    header("Location: kelola_pengajuan.php"); 
    exit();
}
?>