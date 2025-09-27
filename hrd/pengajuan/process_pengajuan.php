<?php

require '../config.php';

// Periksa hak akses HRD
if (!isset($_SESSION['id_karyawan']) || $_SESSION['role'] !== 'HRD') {
    header("Location: ../../index.php");
    exit();
}

if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id_pengajuan = $_GET['id'];
    $new_status = '';

    if ($action === 'approve') {
        $new_status = 'Disetujui';
    } elseif ($action === 'reject') {
        $new_status = 'Ditolak';
    } else {
        header("Location: kelola_pengajuan.php");
        exit();
    }

    // Update status pengajuan di database
    $sql_update = "UPDATE pengajuan SET status_pengajuan = ? WHERE id_pengajuan = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("si", $new_status, $id_pengajuan);

    if ($stmt_update->execute()) {
        header("Location: kelola_pengajuan.php?status=" . ($action === 'approve' ? 'approved' : 'rejected'));
        exit();
    } else {
        die("Error: " . $stmt_update->error);
    }

    $stmt_update->close();
    $conn->close();
} else {
    header("Location: kelola_pengajuan.php");
    exit();
}
?>