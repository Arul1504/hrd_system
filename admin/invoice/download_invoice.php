<?php
// ===========================
// admin/invoice/download_invoice.php
// ===========================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../vendor/autoload.php'; // pastikan vendor di hrd_system/vendor

use Dompdf\Dompdf;
use Dompdf\Options;

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['id_karyawan']) || ($_SESSION['role'] ?? '') !== 'ADMIN') {
    header('Location: ../../index.php'); exit;
}

/**
 * Cari sumber logo: 1) data-uri (base64) 2) file:///path 3) URL absolut
 * Return: array [$LOGO_SRC, $LOGO_FILE, $LOGO_URL]
 */
function get_invoice_logo_sources(): array {
    $candidates = [
        __DIR__ . '/../image/manu.png',
        __DIR__ . '/../image/manu.jpg',
        dirname(__DIR__) . '/image/manu.png',
        dirname(__DIR__) . '/image/manu.jpg',
        ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/hrd_system/admin/image/manu.png',
        ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/hrd_system/admin/image/manu.jpg',
    ];

    $path = null;
    foreach ($candidates as $p) {
        if ($p && is_readable($p)) { $path = realpath($p); break; }
    }

    $src_data = '';
    $src_file = '';
    $mime = 'image/png';

    if ($path) {
        // deteksi mime
        if (function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            $det = finfo_file($fi, $path);
            finfo_close($fi);
            if ($det) $mime = $det;
        } else {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if ($ext === 'jpg' || $ext === 'jpeg') $mime = 'image/jpeg';
            if ($ext === 'png') $mime = 'image/png';
        }

        // 1) data-uri
        $bin = @file_get_contents($path);
        if ($bin !== false) {
            $src_data = 'data:' . $mime . ';base64,' . base64_encode($bin);
        }

        // 2) file:///
        $src_file = 'file:///' . str_replace('\\','/',$path);
    }

    // 3) URL absolut (butuh isRemoteEnabled = true)
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $url_abs = $scheme . '://' . $host . '/hrd_system/admin/image/manu.png';

    return [$src_data, $src_file, $url_abs];
}

list($LOGO_SRC, $LOGO_FILE, $LOGO_URL) = get_invoice_logo_sources();

$id_invoice = (int)($_GET['id'] ?? 0);
if ($id_invoice <= 0) { die('ID Invoice tidak valid'); }

// Ambil data invoice
$stmt = $conn->prepare("SELECT * FROM invoices WHERE id_invoice = ?");
$stmt->bind_param("i", $id_invoice);
$stmt->execute();
$invoice = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$invoice) { die('Invoice tidak ditemukan'); }

// Ambil item
$stmt_items = $conn->prepare("SELECT * FROM invoice_items WHERE id_invoice = ? ORDER BY id_item ASC");
$stmt_items->bind_param("i", $id_invoice);
$stmt_items->execute();
$items = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_items->close();

// Siapkan HTML via template (template memanfaatkan $invoice, $items, $LOGO_* )
ob_start();
include __DIR__ . '/invoice_pdf_template.php';
$html = ob_get_clean();

// Dompdf options
$options = new Options();
$options->set('isRemoteEnabled', true);     // agar URL https bekerja (fallback ke-3)
$options->set('defaultFont', 'DejaVu Sans'); // aman untuk UTF-8

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait'); // A4 portrait
$dompdf->render();

// Nama file
$filename = 'invoice-' . preg_replace('/[^A-Za-z0-9\-_.]/', '_', $invoice['invoice_number'] ?? ('ID-'.$id_invoice)) . '.pdf';

// Stream ke browser
$dompdf->stream($filename, ['Attachment' => true]);
