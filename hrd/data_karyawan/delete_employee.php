<?php
// ==============================
// delete_employee.php (fixed)
// ==============================
require '../config.php';

// Hanya HRD yang boleh hapus
if (!isset($_SESSION['id_karyawan']) || ($_SESSION['role'] ?? '') !== 'HRD') {
    header("Location: ../../index.php");
    exit();
}

// Ambil ID karyawan dari URL (?id=123)
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: all_employees.php?status=error");
    exit();
}

// Helper: cek kolom ada di tabel via information_schema (bukan SHOW COLUMNS)
function column_exists(mysqli $conn, string $table, string $column): bool {
    // Ambil nama DB aktif
    $dbName = null;
    if (defined('DB_NAME')) {
        $dbName = DB_NAME;
    } else {
        $q = $conn->query("SELECT DATABASE() AS db");
        $dbName = $q ? ($q->fetch_assoc()['db'] ?? null) : null;
    }
    if (!$dbName) return false;

    $sql = "SELECT 1 
            FROM information_schema.COLUMNS 
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? 
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param("sss", $dbName, $table, $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res && $res->num_rows > 0;
    $stmt->close();
    return $exists;
}

// Tentukan kolom foto yang tersedia (opsional)
$fotoColumn = null;
if (column_exists($conn, 'karyawan', 'foto_path')) {
    $fotoColumn = 'foto_path';
} elseif (column_exists($conn, 'karyawan', 'foto')) {
    $fotoColumn = 'foto';
}

// Ambil path foto bila kolomnya ada
$foto_path = null;
if ($fotoColumn !== null) {
    $sql = "SELECT `$fotoColumn` AS fp FROM karyawan WHERE id_karyawan = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $foto_path = $row['fp'] ?: null;
        }
        $stmt->close();
    }
}

// Hapus data karyawan
$stmtDel = $conn->prepare("DELETE FROM karyawan WHERE id_karyawan = ?");
if (!$stmtDel) {
    header("Location: all_employees.php?status=error");
    exit();
}
$stmtDel->bind_param("i", $id);
$ok = $stmtDel->execute();
$stmtDel->close();

if ($ok) {
    // Hapus file foto jika ada
    if ($foto_path) {
        // Normalisasi path dan coba hapus
        $candidate1 = realpath(__DIR__ . '/' . ltrim($foto_path, '/'));
        $candidate2 = __DIR__ . '/' . ltrim($foto_path, '/');

        if ($candidate1 && is_file($candidate1)) {
            @unlink($candidate1);
        } elseif (is_file($candidate2)) {
            @unlink($candidate2);
        } elseif (is_file($foto_path)) {
            @unlink($foto_path);
        }
    }
    header("Location: all_employees.php?status=deleted");
    exit();
} else {
    header("Location: all_employees.php?status=error");
    exit();
}
