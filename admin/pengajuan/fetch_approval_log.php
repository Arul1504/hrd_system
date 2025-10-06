<?php
// File: fetch_approval_log.php

require '../config.php'; // Sesuaikan path config Anda

// Cek autentikasi dan otorisasi
if (!isset($_SESSION['id_karyawan']) || $_SESSION['role'] !== 'ADMIN') {
    http_response_code(403); 
    echo "Akses ditolak.";
    exit();
}

// Fungsi bantuan untuk HTML escaping
if (!function_exists('e')) {
    function e($text)
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

$id_pengajuan = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_pengajuan === 0) {
    echo "<p style='color: red;'>ID Pengajuan tidak valid.</p>";
    exit();
}

// ASUMSI: Anda memiliki tabel 'pengajuan_log' dengan kolom status, notes, dan tanggal.
$sql_log = "SELECT status, notes, tanggal FROM pengajuan_log WHERE id_pengajuan = ? ORDER BY tanggal ASC";
$stmt_log = $conn->prepare($sql_log);
$stmt_log->bind_param("i", $id_pengajuan);
$stmt_log->execute();
$result_log = $stmt_log->get_result();

$log_html = '<div class="approval-log-container">';
$log_html .= '<h4>Riwayat Persetujuan (Log)</h4>';
$log_html .= '<table class="log-table" style="width: 100%; font-size: 0.85em; border-collapse: collapse;">';
$log_html .= '<thead><tr><th>Status</th><th>Catatan</th><th>Tanggal</th></tr></thead>';
$log_html .= '<tbody>';

if ($result_log->num_rows > 0) {
    while ($log = $result_log->fetch_assoc()) {
        $status = e($log['status']);
        $notes = e($log['notes']);
        
        // Format tanggal sesuai kebutuhan log
        $tanggal = date('Y-m-d H:i:s', strtotime($log['tanggal']));

        $log_html .= "<tr>";
        // Status sudah harus berisi "Approved by @Nama" dari process_pengajuan.php
        $log_html .= "<td>{$status}</td>"; 
        $log_html .= "<td>{$notes}</td>";
        $log_html .= "<td>{$tanggal}</td>";
        $log_html .= "</tr>";
    }
} else {
    $log_html .= "<tr><td colspan='3' style='text-align: center; color: #888;'>Tidak ada riwayat log persetujuan.</td></tr>";
}

$log_html .= '</tbody></table>';
$log_html .= '</div>';

$stmt_log->close();
$conn->close();

echo $log_html;
?>