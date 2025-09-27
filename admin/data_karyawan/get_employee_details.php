<?php
// ===================================
// get_employee_details.php (JSON API)
// ===================================
require '../config.php';

// Opsional: hanya ADMIN yang boleh lihat detail
if (!isset($_SESSION['id_karyawan']) || ($_SESSION['role'] ?? '') !== 'ADMIN') {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Akses ditolak']);
    exit();
}

// Ambil id dari query string
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Parameter id tidak valid']);
    exit();
}

// Ambil data karyawan
$stmt = $conn->prepare("SELECT * FROM karyawan WHERE id_karyawan = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

header('Content-Type: application/json; charset=utf-8');

if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'Data tidak ditemukan']);
    exit();
}

/*
 * Opsional: kalau ada kolom sensitif di tabel, kamu bisa unset di sini.
 * Contoh:
 * unset($row['password_hash'], $row['token_reset']);
 */

// Normalisasi beberapa nilai agar aman dikonsumsi JS (null jadi string kosong jika perlu)
foreach ($row as $k => $v) {
    // Biarkan null tetap null—JS di halaman kamu sudah handle (menampilkan '-' saat null)
    // Jika ingin paksa string: $row[$k] = ($v === null) ? '' : $v;
}

echo json_encode($row, JSON_UNESCAPED_UNICODE);
exit();