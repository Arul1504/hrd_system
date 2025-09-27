<?php
// ============= invoice.php (FINAL VERSION LENGKAP) =============
// Menampilkan daftar invoice dan menyediakan form pembuatan invoice dalam modal

require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Helper untuk menghindari XSS (Wajib Didefinisikan) ---
if (!function_exists('e')) {
    function e($string)
    {
        // Menggunakan ENT_QUOTES untuk mengonversi single dan double quotes
        return htmlspecialchars((string) $string, ENT_QUOTES, 'UTF-8');
    }
}

// --- Akses hanya ADMIN ---
if (!isset($_SESSION['id_karyawan']) || (($_SESSION['role'] ?? '') !== 'ADMIN')) {
    header('Location: ../login.php'); // sesuaikan jika perlu
    exit;
}

// Ambil data user dari sesi untuk sidebar
$id_karyawan_admin = $_SESSION['id_karyawan'];
$nama_user_admin = $_SESSION['nama'];
$role_user_admin = $_SESSION['role'];

// Ambil jumlah pending requests untuk sidebar (Diasumsikan query ini berfungsi)
$sql_pending_requests = "SELECT COUNT(*) AS total_pending FROM pengajuan WHERE status_pengajuan = 'Menunggu'";
$result_pending_requests = isset($conn) ? $conn->query($sql_pending_requests) : false;
$total_pending = $result_pending_requests ? ($result_pending_requests->fetch_assoc()['total_pending'] ?? 0) : 0;

// Ambil info admin untuk sidebar
$stmt_admin_info = isset($conn) ? $conn->prepare("SELECT nik_ktp, jabatan FROM karyawan WHERE id_karyawan = ?") : false;

if ($stmt_admin_info) {
    $stmt_admin_info->bind_param("i", $id_karyawan_admin);
    $stmt_admin_info->execute();
    $result_admin_info = $stmt_admin_info->get_result();
    $admin_info = $result_admin_info->fetch_assoc();
    $stmt_admin_info->close();
} else {
    $admin_info = null;
}

$nik_user_admin = $admin_info['nik_ktp'] ?? 'Tidak Ditemukan';
$jabatan_user_admin = $admin_info['jabatan'] ?? 'Tidak Ditemukan';

$sub_total = isset($_POST['sub_total']) ? (float) $_POST['sub_total'] : 0.0;
$mgmt_fee_percent = isset($_POST['management_fee_percentage']) ? (float) $_POST['management_fee_percentage'] : 0.0;
$mgmt_fee_amount = isset($_POST['management_fee_amount']) ? (float) $_POST['management_fee_amount'] : 0.0;
$ppn_percent = isset($_POST['ppn_percentage']) ? (float) $_POST['ppn_percentage'] : 11.0;
$ppn_amount = isset($_POST['ppn_amount']) ? (float) $_POST['ppn_amount'] : 0.0;
$grand_total = isset($_POST['grand_total']) ? (float) $_POST['grand_total'] : 0.0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare("
            INSERT INTO invoices 
            (invoice_number, invoice_date, project_key, bill_to_bank, bill_to_address1, bill_to_address2, bill_to_address3,
            person_up_name, person_up_title, sub_total, mgmt_fee_percent, mgmt_fee_amount, ppn_percent, ppn_amount, grand_total, 
            transfer_bank, transfer_account_no, transfer_account_name, footer_date, manu_signatory_name, manu_signatory_title, created_by_id)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");


        $stmt->bind_param(
            "sssssssssddddddssssssi",
            $_POST['invoice_no'],
            $_POST['invoice_date'],
            $_POST['project'],
            $_POST['bill_to_bank'],
            $_POST['bill_to_address1'],
            $_POST['bill_to_address2'],
            $_POST['bill_to_address3'],
            $_POST['person_up_name'],
            $_POST['person_up_title'],
            $sub_total,
            $mgmt_fee_percent,
            $mgmt_fee_amount,
            $ppn_percent,
            $ppn_amount,
            $grand_total,
            $_POST['transfer_bank'],
            $_POST['transfer_account_no'],
            $_POST['transfer_account_name'],
            $_POST['footer_date'],
            $_POST['manu_signatory_name'],
            $_POST['manu_signatory_title'],
            $id_karyawan_admin
        );


        $stmt->execute();
        $invoice_id = $stmt->insert_id;
        $stmt->close();

        // --- 2. Insert ke tabel invoice_description ---
        if (!empty($_POST['items']) && is_array($_POST['items'])) {
            $stmtItem = $conn->prepare("
                INSERT INTO invoice_description (invoice_id, item_no, description, amount)
                VALUES (?,?,?,?)
            ");
            foreach ($_POST['items'] as $index => $item) {
                $item_no = $index + 1;
                $desc = $item['description'];
                $amount = $item['amount'];
                $stmtItem->bind_param("iisd", $invoice_id, $item_no, $desc, $amount);
                $stmtItem->execute();
            }
            $stmtItem->close();
        }

        // --- 3. Commit transaction ---
        $conn->commit();

        // --- 3. Commit transaction ---


        // Redirect balik dengan notifikasi sukses
        header("Location: invoice.php?success=1");
        exit();


    } catch (Exception $e) {
        $conn->rollback();
        echo "Error: " . $e->getMessage();
    }
}
?>