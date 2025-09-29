<?php
// ===========================
// delete_invoice.php
// ===========================
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hanya ADMIN
if (!isset($_SESSION['id_karyawan']) || (($_SESSION['role'] ?? '') !== 'ADMIN')) {
    header('Location: ../login.php');
    exit;
}

// Validasi method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: invoice.php');
    exit;
}

// CSRF
$csrf_post = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_post)) {
    http_response_code(403);
    exit('CSRF token tidak valid.');
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: invoice.php');
    exit;
}

$conn->begin_transaction();
try {
    // Hapus item terlebih dahulu (kalau belum ON DELETE CASCADE)
    $stmt = $conn->prepare("DELETE FROM invoice_items WHERE id_invoice = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    // Hapus invoice
    $stmt = $conn->prepare("DELETE FROM invoices WHERE id_invoice = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    $conn->commit();

    // Kalau tidak ada baris terhapus, mungkin ID tidak valid
    header('Location: invoice.php?deleted=' . ($affected > 0 ? '1' : '0'));
    exit;
} catch (Throwable $e) {
    $conn->rollback();
    // Opsional: log error
    // error_log('Delete invoice error: ' . $e->getMessage());
    header('Location: invoice.php?deleted=0');
    exit;
}
