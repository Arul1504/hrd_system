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


// ============= DATA PROJECT MAPPING DARI GAMBAR INVOICE =============

// --- Data Project (Diperluas untuk mencakup semua gambar) ---
$projects = [
    'CIMB' => [
        'bank' => 'PT Bank CIMB Niaga',
        'address1' => 'Griya CIMB Niaga II Lt. 7',
        'address2' => 'Jl. Wahid Hasyim Blok B4, No.3',
        'address3' => 'Bintaro Jaya Sektor 7',
        'person_up_name' => 'Ibu Desi Suprianti',
        'person_up_title' => 'Administration & Payment Monitoring Officer',
        'default_descriptions' => [
            "BASIC ALLOWANCE PERSONAL LOAN CSE & TL PERFORMANCE",
            "INCENTIVE PERSONAL LOAN CSE & TL PERFORMANCE",
        ],
        'is_management_fee' => 'Y',
        'is_ppn' => 'Y',
        'is_pph' => 'N'
    ],
    'ALLO' => [
        'bank' => 'PT Allo Bank Indonesia Tbk',
        'address1' => 'Jl. Sudirman No. 123',
        'address2' => 'Jakarta Pusat',
        'address3' => '',
        'person_up_name' => 'Bapak Joko Susilo',
        'person_up_title' => 'Project Manager',
        'default_descriptions' => [
            "PEMBAYARAN INSENTIF OFFLINE SALES PERFORMANCE",
            "PEMBAYARAN INSENTIF BUSINESS LEADER PERFORMANCE",
        ],
        'is_management_fee' => 'N',
        'is_ppn' => 'Y',
        'is_pph' => 'Y'
    ],
    'CNAF' => [
        'bank' => 'PT CIMB Niaga Auto Finance',
        'address1' => 'Menara BCA Lt. 25',
        'address2' => 'Jl. MH Thamrin No. 1',
        'address3' => 'Jakarta Pusat',
        'person_up_name' => 'Ibu Fitriani',
        'person_up_title' => 'Finance & Accounting Head',
        'default_descriptions' => [
            "REWARD REFERRAL GET COLLECTION",
        ],
        'is_management_fee' => 'N',
        'is_ppn' => 'N',
        'is_pph' => 'N'
    ],
    'BNI' => [
        'bank' => 'PT BNI Finance',
        'address1' => 'BNI Tower Lt. 17',
        'address2' => 'Jl. Jenderal Sudirman Kav. 1',
        'address3' => 'Jakarta Pusat',
        'person_up_name' => 'Bapak Rian Hidayat',
        'person_up_title' => 'Division Head',
        'default_descriptions' => [
            "INCENTIVE FIELD COLLECTOR PERFORMANCE",
        ],
        'is_management_fee' => 'Y',
        'is_ppn' => 'Y',
        'is_pph' => 'Y'
    ],
    'NOBU' => [
        'bank' => 'PT Bank Nationalnobu Tbk',
        'address1' => 'Jl. Jenderal Sudirman No. 25',
        'address2' => 'Jakarta Selatan',
        'address3' => '',
        'person_up_name' => 'Bapak Wibowo',
        'person_up_title' => 'Finance Head',
        'default_descriptions' => [
            "INSENTIF SALES KPR PERFORMANCE",
        ],
        'is_management_fee' => 'Y',
        'is_ppn' => 'Y',
        'is_pph' => 'Y'
    ],
    'SYARIAH' => [
        'bank' => 'PT Bank Mega Syariah',
        'address1' => 'Menara Mega Syariah Lt. 2',
        'address2' => 'Jl. HR Rasuna Said Kav. 11',
        'address3' => 'Jakarta Selatan',
        'person_up_name' => 'Ibu Siska',
        'person_up_title' => 'HR Operations',
        'default_descriptions' => [
            "INVOICE PKWT BANK MEGA SYARIAH PERIODE", // Periode akan diisi manual
        ],
        'is_management_fee' => 'Y',
        'is_ppn' => 'Y',
        'is_pph' => 'N'
    ],
    'PEI' => [
        'bank' => 'PT Pendanaan Efek Indonesia',
        'address1' => 'Gedung Bursa Efek Indonesia Tower 2 Lt. 5',
        'address2' => 'Jl. Jenderal Sudirman Kav. 52-53',
        'address3' => 'Jakarta Selatan',
        'person_up_name' => 'Bapak Angga',
        'person_up_title' => 'Finance Manager',
        'default_descriptions' => [
            "Biaya Gaji Karyawan Outsource PT Mandiri Andalan Utama",
            "Fee Management",
        ],
        'is_management_fee' => 'Y',
        'is_ppn' => 'Y',
        'is_pph' => 'Y'
    ],
    'SMBC' => [
        'bank' => 'PT SMBC Indonesia',
        'address1' => 'SMBC Tower Lt. 10',
        'address2' => 'Jl. MH Thamrin No. 10',
        'address3' => 'Jakarta Pusat',
        'person_up_name' => 'Ibu Siti',
        'person_up_title' => 'HR & GA Officer',
        'default_descriptions' => [
            "BIAYA GAJI PKWT RELATIONSHIP OFFICER PERIODE",
        ],
        'is_management_fee' => 'Y',
        'is_ppn' => 'Y',
        'is_pph' => 'Y'
    ],
];

// Encode data projects ke JSON untuk digunakan oleh JavaScript
$projects_json = json_encode($projects);

// Tentukan project default untuk mengisi form saat modal dimuat
$default_project_key = array_key_first($projects);
$default_project = $projects[$default_project_key] ?? null;

// --- Logika untuk halaman Invoice (Daftar) ---
$search_query = $_GET['search'] ?? '';

// Contoh data statis untuk demonstrasi. Ganti dengan QUERY DATABASE Anda.
// $all_invoices = [
//     [
//         'id' => 1,
//         'invoice_number' => 'INV-202509-001',
//         'invoice_date' => '2025-09-23',
//         'employee_name' => 'Arul Rahmadan',
//         'total' => '5500000',
//         'status' => 'Sudah Dibayar'
//     ],
//     [
//         'id' => 2,
//         'invoice_number' => 'INV-202509-002',
//         'invoice_date' => '2025-09-24',
//         'employee_name' => 'Budi Santoso',
//         'total' => '6000000',
//         'status' => 'Belum Dibayar'
//     ],
//     [
//         'id' => 3,
//         'invoice_number' => 'INV-202509-003',
//         'invoice_date' => '2025-09-24',
//         'employee_name' => 'Citra Dewi',
//         'total' => '5850000',
//         'status' => 'Belum Dibayar'
//     ],
// ];
$sql_all_invoices = "SELECT 
            id_invoice,
            invoice_number,
            invoice_date,
            project_key,
            bill_to_bank,
            bill_to_address1,
            bill_to_address2,
            bill_to_address3,
            person_up_name,
            person_up_title,
            sub_total,
            mgmt_fee_percent,
            mgmt_fee_amount,
            ppn_percent,
            ppn_amount,
            grand_total,
            transfer_bank,
            transfer_account_no,
            transfer_account_name,
            footer_date,
            manu_signatory_name,
            manu_signatory_title,
            status_pembayaran,
            created_by_id,
            created_at,
            updated_at
        FROM invoices
        ORDER BY created_at DESC";

$result = $conn->query($sql_all_invoices);

$all_invoices = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $all_invoices[] = $row;
    }
}

// Terapkan filter pencarian pada data statis
$invoices = $all_invoices;
if (!empty($search_query)) {
    $search_term = strtolower($search_query);
    $invoices = array_filter($all_invoices, function ($invoice) use ($search_term) {
        return str_contains(strtolower($invoice['invoice_number']), $search_term) ||
            str_contains(strtolower($invoice['employee_name']), $search_term);
    });
}
// ==========================================================
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Halaman Invoice</title>
    <link rel="stylesheet" href="../style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* ======================== Gaya Invoice List & Sidebar ======================== */
        .main-content .card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-top: 20px;
        }

        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .invoice-table th,
        .invoice-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .invoice-table th {
            background-color: #f2f2f2;
        }

        .status-label {
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: bold;
            font-size: 1em;
        }

        .status-label.sudahdibayar {
            background-color: #28a745;
        }

        .status-label.belumdibayar {
            background-color: #ffc107;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            align-items: center
        }

        .download-btn {
            padding: 8px 14px;
            border-radius: 6px;
            background: #3498db;
            color: #fff;
            text-decoration: none;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: background .2s;
            cursor: pointer;
        }

        .download-btn:hover {
            background: #2980b9
        }

        .download-btn i {
            font-size: 16px
        }

        .download-btn:nth-child(1) {
            background: #e74c3c
        }

        .download-btn:nth-child(1):hover {
            background: #c0392b
        }

        .download-btn:nth-child(2) {
            background: #27ae60
        }

        .download-btn:nth-child(2):hover {
            background: #1e8449
        }

        .sidebar-nav .dropdown-trigger {
            position: relative
        }

        .sidebar-nav .dropdown-link {
            display: flex;
            justify-content: space-between;
            align-items: center
        }

        .sidebar-nav .dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            min-width: 200px;
            background: #2c3e50;
            box-shadow: 0 4px 8px rgba(0, 0, 0, .2);
            padding: 0;
            margin: 0;
            list-style: none;
            z-index: 11;
            border-radius: 0 0 8px 8px;
            overflow: hidden
        }

        .sidebar-nav .dropdown-menu li a {
            padding: 12px 20px;
            display: block;
            color: #ecf0f1;
            text-decoration: none;
            transition: background-color .3s
        }

        .sidebar-nav .dropdown-menu li a:hover {
            background: #34495e
        }

        .sidebar-nav .dropdown-trigger:hover .dropdown-menu {
            display: block
        }

        .badge {
            background: #ef4444;
            color: #fff;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 12px;
        }

        /* ======================== Gaya Modal (Pop-up) ======================== */
        .modal {
            display: none;
            position: fixed;
            z-index: 100;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
            padding-top: 20px;
        }

        .modal-content {
            background-color: #fefefe;
            margin: auto;
            padding: 0;
            border: 1px solid #888;
            width: 95%;
            max-width: 1200px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            animation-name: animatetop;
            animation-duration: 0.4s;
        }

        .modal-header {
            padding: 15px 25px;
            background-color: #007bff;
            color: white;
            border-radius: 10px 10px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body {
            padding: 25px;
        }

        .close-btn {
            color: #fff;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s;
        }

        .close-btn:hover,
        .close-btn:focus {
            color: #ccc;
            text-decoration: none;
            cursor: pointer;
        }

        @keyframes animatetop {
            from {
                top: -300px;
                opacity: 0
            }

            to {
                top: 0;
                opacity: 1
            }
        }

        /* ======================== Gaya Form Invoice (Tambahan & Override) ======================== */
        .invoice-container {
            padding: 0;
        }

        /* Memperkecil logo PT Mandiri Andalan Utama */
        .header-section img {
            max-width: 80px;
            /* Diperkecil dari 150px menjadi 80px */
            margin-bottom: 10px;
        }

        .header-section {
            text-align: center;
            margin-bottom: 40px;
        }

        .bill-to-section {
            border-top: 5px solid #F00000;
            padding-top: 10px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .bill-to-info,
        .invoice-details,
        .person-up {
            width: 32%;
            box-sizing: border-box;
        }

        .items-section table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .items-section th,
        .items-section td {
            border: 1px solid #eee;
            padding: 8px;
            text-align: left;
        }

        .items-section th {
            background-color: #f2f2f2;
            font-weight: bold;
        }

        .items-section .remove-item-btn {
            background-color: #f44336;
            color: white;
            padding: 5px 8px;
            border: none;
            cursor: pointer;
            font-size: 12px;
            margin-left: 5px;
        }

        .items-section .add-item-btn {
            background-color: #4CAF50;
            color: white;
            padding: 8px 12px;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }

        .summary-section table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .summary-section td {
            border: 1px solid #eee;
            padding: 8px;
            text-align: right;
        }

        .summary-section td:first-child {
            text-align: left;
            width: 70%;
        }

        .summary-section tr.grand-total td {
            font-weight: bold;
            background-color: #f2f2f2;
        }

        .transfer-section {
            margin-top: 30px;
            border: 1px dashed #ccc;
            padding: 15px;
            background-color: #f9f9f9;
        }

        input[type="text"],
        input[type="number"],
        input[type="date"],
        select,
        textarea {
            width: 100%;
            padding: 8px;
            margin: 5px 0;
            display: inline-block;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        input[type="date"] {
            width: auto;
        }

        button[type="submit"] {
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 20px;
        }

        /* Perbaikan Input Summary */
        .summary-section input[type="number"] {
            width: 100%;
            padding: 5px;
            box-sizing: border-box;
            border: 1px solid #ddd;
            text-align: right;
        }

        .summary-section input[type="number"][style*="width: 50px"] {
            width: 50px !important;
            text-align: right;
            padding: 5px 0;
        }

        /* Gaya Tanda Tangan */
        .footer-section {
            margin-top: 50px;
            font-size: 13px;
        }

        .footer-section .signature-placeholder {
            width: 150px;
            height: 70px;
            border: 1px dashed #ccc;
            margin: 5px auto;
        }

        .footer-section .signatory-name input,
        .footer-section .signatory-title input {
            width: 90%;
            border: none !important;
            text-align: center;
            padding: 5px 0;
        }

        .footer-section .signatory-name {
            margin: 0;
            border-bottom: 1px solid #000;
            display: inline-block;
            padding: 0 20px;
        }
    </style>
</head>

<body>
    <div class="container">
        <aside class="sidebar">
            <div class="company-brand">
                <img src="../image/manu.png" alt="Logo PT Mandiri Andalan Utama" class="company-logo">
                <p class="company-name">PT Mandiri Andalan Utama</p>
            </div>
            <div class="user-info">
                <div class="user-avatar"><?= e(strtoupper(substr($nama_user_admin, 0, 2))) ?></div>
                <div class="user-details">
                    <p class="user-name"><?= e($nama_user_admin) ?></p>
                    <p class="user-id"><?= e($nik_user_admin ?: '—') ?></p>
                    <p class="user-role"><?= e($role_user_admin) ?></p>
                </div>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="../dashboard_admin.php"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="../absensi/absensi.php"><i class="fas fa-edit"></i> Absensi </span></a></li>
                    <li class="dropdown-trigger">
                        <a href="#" class="dropdown-link"><i class="fas fa-users"></i> Data Karyawan <i
                                class="fas fa-caret-down"></i></a>
                        <ul class="dropdown-menu">
                            <li><a href="../data_karyawan/all_employees.php">Semua Karyawan</a></li>
                            <li><a href="../data_karyawan/karyawan_nonaktif.php">Non-Aktif</a></li>
                        </ul>
                    </li>
                    <li class="dropdown-trigger">
                        <a href="#" class="dropdown-link"><i class="fas fa-users"></i> Data Pengajuan<span
                                class="badge"><?= $total_pending ?></span> <i class="fas fa-caret-down"></i></a>
                        <ul class="dropdown-menu">
                            <li><a href="../pengajuan/pengajuan.php">Pengajuan</a></li>
                            <li><a href="../pengajuan/kelola_pengajuan.php">Kelola Pengajuan<span
                                        class="badge"><?= $total_pending ?></span></a></li>
                        </ul>
                    </li>
                    <li><a href="../monitoring_kontrak/monitoring_kontrak.php"><i class="fas fa-calendar-alt"></i>
                            Monitoring Kontrak</a></li>
                    <li><a href="../payslip/e_payslip_admin.php"><i class="fas fa-money-check-alt"></i> E-Pay Slip</a>
                    </li>
                    <li class="active"><a href="../invoice/invoice.php"><i class="fas fa-money-check-alt"></i>
                            Invoice</a>
                    </li>
                </ul>
            </nav>
            <div class="logout-link">
                <a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a>
            </div>
        </aside>

        <main class="main-content">
            <header class="main-header">
                <h1>Kelola Invoice</h1>
                <p class="current-date"><?= date('l, d F Y'); ?></p>
            </header>

            <div class="toolbar">
                <div class="search-filter-container">
                    <form action="invoice.php" method="GET" class="search-form">
                        <div class="search-box">
                            <input type="text" name="search" placeholder="Cari No. Invoice atau Nama..."
                                value="<?= e($search_query) ?>">
                            <button type="submit"><i class="fas fa-search"></i></button>
                        </div>
                    </form>
                </div>
                <div class="action-buttons">
                    <a class="download-btn pdf" href="#">
                        <i class="fas fa-file-pdf"></i> Unduh PDF
                    </a>
                    <a class="download-btn excel" href="#">
                        <i class="fas fa-file-excel"></i> Unduh Excel
                    </a>
                    <button type="button" class="download-btn create-btn" style="background-color:#007bff; color:#fff;"
                        onclick="openModal('invoiceModal')">
                        <i class="fas fa-plus"></i> Buat Invoice Baru
                    </button>
                </div>
            </div>

            <div class="data-table-container card">
                <h2>Daftar Invoice</h2>
                <table class="invoice-table">
                    <thead>
                        <tr>
                            <th>Nomor Invoice</th>
                            <th>Tanggal</th>
                            <th>Untuk Karyawan</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($invoices)): ?>
                            <tr>
                                <td colspan="6" style="text-align:center;">Tidak ada data invoice yang ditemukan.</td>
                            </tr>
                        <?php else:
                            foreach ($invoices as $invoice): ?>
                                <tr>
                                    <td><?= e($invoice['invoice_number']) ?></td>
                                    <td><?= e(date('d F Y', strtotime($invoice['invoice_date']))) ?></td>
                                    <td><?= e($invoice['person_up_name']) ?></td>
                                    <td>Rp <?= number_format($invoice['grand_total'], 0, ',', '.') ?></td>
                                    <td>
                                        <?php
                                        $status_class = strtolower(str_replace(' ', '', $invoice['status_pembayaran']));
                                        ?>
                                        <span class="status-label <?= e($status_class) ?>">
                                            <?= e($invoice['status_pembayaran']) ?>
                                        </span>
                                    </td>
                                    <td class="action-buttons">
                                        <button class="action-btn view-btn" title="Lihat"><i class="fas fa-eye"></i></button>
                                        <button class="action-btn" style="background:#3498db" title="Cetak"><i
                                                class="fas fa-print"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <div id="invoiceModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Buat Invoice Baru</h2>
                <span class="close-btn" onclick="closeModal('invoiceModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div class="invoice-container">
                    <div class="header-section">
                        <img src="../image/manu.png" alt="PT. MANDIRI ANDALAN UTAMA Logo">
                        <h1>PT. MANDIRI ANDALAN UTAMA</h1>
                        <p class="tagline">Committed to delivered the best result</p>
                        <p>Jl Sultan Iskandar Muda No. 30 A-B</p>
                        <p>Kebayoran Lama Selatan - Kebayoran Lama Jakarta Selatan 12240</p>
                        <p>021-27518306</p>
                        <p><a href="http://www.manu.co.id">www.manu.co.id</a></p>
                    </div>

                    <form id="invoiceForm" action="save_invoice.php" method="POST">
                        <div class="bill-to-section">
                            <div class="bill-to-info">
                                <strong>Project :</strong>
                                <select id="project-select" name="project" required>
                                    <option value="" disabled>-- Pilih Project --</option>
                                    <?php foreach ($projects as $key => $project): ?>
                                    <option 
                                        value="<?= e($key) ?>"
                                        data-is-ppn="<?= e($project['is_ppn']) ?>"
                                        data-is-pph="<?= e($project['is_pph']) ?>"
                                        data-is-management-fee="<?= e($project['is_management_fee']) ?>"
                                        <?= ($key === $default_project_key) ? 'selected' : '' ?>
                                    >
                                        <?= e($key) ?>
                                    </option>
                                <?php endforeach; ?>
                                </select>
                                <br><br>
                                <strong>BILL TO:</strong>
                                <p><input type="text" name="bill_to_bank" id="bill_to_bank" placeholder="Nama Bank"
                                        value="<?= e($default_project['bank'] ?? '') ?>" required></p>
                                <p><input type="text" name="bill_to_address1" id="bill_to_address1"
                                        placeholder="Alamat 1" value="<?= e($default_project['address1'] ?? '') ?>"
                                        required></p>
                                <p><input type="text" name="bill_to_address2" id="bill_to_address2"
                                        placeholder="Alamat 2" value="<?= e($default_project['address2'] ?? '') ?>"></p>
                                <p><input type="text" name="bill_to_address3" id="bill_to_address3"
                                        placeholder="Alamat 3" value="<?= e($default_project['address3'] ?? '') ?>"></p>
                            </div>
                            <div class="invoice-details">
                                <p><span>No</span> : <input type="text" id="invoice_no" name="invoice_no" value=""
                                        placeholder="Nomor Invoice" readonly></p>
                                <p><span>Tanggal</span> : <input type="date" name="invoice_date"
                                        value="<?php echo date('Y-m-d'); ?>" required></p>
                            </div>
                            <div class="person-up">
                                <p><span>Up</span> : <input type="text" name="person_up_name" id="person_up_name"
                                        value="<?= e($default_project['person_up_name'] ?? '') ?>" required></p>
                                <p><input type="text" name="person_up_title" id="person_up_title"
                                        value="<?= e($default_project['person_up_title'] ?? '') ?>" required></p>
                            </div>
                        </div>

                        <div class="items-section">
                            <table>
                                <thead>
                                    <tr>
                                        <th style="width: 5%">No</th>
                                        <th style="width: 65%">Description</th>
                                        <th style="width: 20%">Amount</th>
                                        <th style="width: 10%"></th>
                                    </tr>
                                </thead>
                                <tbody id="invoice-items">
                                </tbody>
                                <tfoot>
                                    <tr class="add-item-row">
                                        <td colspan="4"><button type="button" class="add-item-btn"
                                                onclick="addItem()">Tambah Item Manual</button></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div class="summary-section">
                            <table>
                                <tbody>
                                    <tr>
                                        <td class="text-right"><strong>SUB TOTAL</strong></td>
                                        <td class="text-right"><span id="sub-total-display">Rp 0</span><input
                                                type="hidden" name="sub_total" id="sub-total-input"></td>
                                    </tr>
                                    <tr id="row-management-fee">
                                        <td class="text-right"><strong>MANAGEMENT FEE (<input type="number"
                                                    name="management_fee_percentage"
                                                    id="management-fee-percentage-input" value="0" step="0.1"
                                                    style="width: 50px; text-align: right;"
                                                    oninput="calculateTotals()">%)</strong></td>
                                        <td><span id="management-fee-amount-display">Rp 0</span><input type="hidden"
                                                name="management_fee_amount" id="management-fee-amount-input"></td>
                                    </tr>
                                    <tr id="row-ppn">
                                        <td class="text-right"><strong>PPN (<input type="number" name="ppn_percentage"
                                                    id="ppn-percentage-input" value="11" step="0.1"
                                                    style="width: 50px; text-align: right;"
                                                    oninput="calculateTotals()">%)</strong></td>
                                        <td><span id="ppn-amount-display">Rp 0</span><input type="hidden"
                                                name="ppn_amount" id="ppn-amount-input"></td>
                                    </tr>
                                    <tr id="row-pph">
                                        <td class="text-right"><strong>PPH (<input type="number" name="pph_percentage"
                                                    id="pph-percentage-input" value="23" step="0.1"
                                                    style="width: 50px; text-align: right;"
                                                    oninput="calculateTotals()">%)</strong></td>
                                        <td><span id="pph-amount-display">Rp 0</span><input type="hidden"
                                                name="pph_amount" id="pph-amount-input"></td>
                                    </tr>
                                    <tr class="grand-total">
                                        <td class="text-right"><strong>GRAND TOTAL</strong></td>
                                        <td><span id="grand-total-display">Rp 0</span><input type="hidden"
                                                name="grand_total" id="grand-total-input"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="transfer-section">
                            <strong>Please Transfer To Account:</strong>
                            <p><span>Bank</span> : <input type="text" name="transfer_bank" value="CIMB Niaga" required>
                            </p>
                            <p><span>Rekening Number</span>: <input type="text" name="transfer_account_no"
                                    value="800140878000" required></p>
                            <p><span>A/C</span> : <input type="text" name="transfer_account_name"
                                    value="PT Mandiri Andalan Utama" required></p>
                        </div>

                        <div class="footer-section">
                            <div style="display: flex; justify-content: flex-end; width: 100%;">
                                <div style="width: 45%; text-align: left; margin-top: 30px;">
                                    <p style="margin: 0 0 10px 0;">
                                        Jakarta, <input type="date" name="footer_date"
                                            value="<?php echo date('Y-m-d'); ?>" required
                                            style="width: auto; display: inline-block; border: none; padding: 0;">
                                    </p>

                                    <br><br><br>

                                    <p class="signatory-name"
                                        style="margin: 0; border-bottom: 1px solid #000; display: inline-block; padding: 0 0px;">
                                        <input type="text" name="manu_signatory_name" value="Oktafian Farhan"
                                            style="border: none; text-align: left; width: auto; padding: 0;">
                                    </p>
                                    <p class="signatory-title" style="margin: 5px 0 0 0; font-size: 12px; color: #777;">
                                        <input type="text" name="manu_signatory_title" value="Direktur Utama"
                                            style="border: none; text-align: left; width: auto; padding: 0;">
                                    </p>
                                </div>
                            </div>
                        </div>

                        <button type="submit">Simpan Invoice</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script>
        // =========================================================
        // FUNGSI MODAL
        // =========================================================
        function openModal(modalId) {
            const invoiceInput = document.getElementById("invoice_no");
            invoiceInput.value = getNextInvoiceNumber();
            document.getElementById(modalId).style.display = 'block';
        }

        function getNextInvoiceNumber() {
            const rows = document.querySelectorAll("tbody tr td:first-child");
            const prefix = "INV";
            const currentPeriod = new Date().toISOString().slice(0,7).replace("-", "");
            let maxRunning = 0;

            rows.forEach(td => {
                const text = td.textContent.trim();
                const parts = text.split("-");
                if (parts.length === 3 && parts[1] === currentPeriod) {
                    const num = parseInt(parts[2], 10);
                    if (!isNaN(num) && num > maxRunning) {
                        maxRunning = num;
                    }
                }
            });

            const newRunning = String(maxRunning + 1).padStart(3, "0");
            return `${prefix}-${currentPeriod}-${newRunning}`;
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Tutup modal jika user mengklik di luar area modal
        window.onclick = function (event) {
            const modal = document.getElementById('invoiceModal');
            if (event.target === modal) {
                modal.style.display = "none";
            }
        }

        // =========================================================
        // DATA DAN FUNGSI LOGIKA INVOICE
        // =========================================================
        const projectsData = <?= $projects_json ?>;
        let itemCounter = 0;

        // Fungsi untuk membuat baris item baru dengan deskripsi yang sudah terisi
        function createItemRow(description = '') {
            const tableBody = document.getElementById('invoice-items');
            const newRow = document.createElement('tr');
            newRow.classList.add('item-row');

            // Input teks (untuk deskripsi yang diisi otomatis atau manual)
            const descriptionInput = description
                ? `<input type="text" name="description[]" value="${description.replace(/"/g, '&quot;')}" required>`
                : `<input type="text" name="description[]" value="" placeholder="Masukkan deskripsi item..." required>`;

            newRow.innerHTML = `
            <td class="item-no">${itemCounter}</td>
            <td>${descriptionInput}</td>
            <td><input type="number" name="amount[]" class="item-amount text-right" value="0" step="1" oninput="calculateTotals()" required></td>
            <td><button type="button" class="remove-item-btn" onclick="removeItem(this)">Hapus</button></td>
        `;
            tableBody.appendChild(newRow);
        }

        // Fungsi utama: mengisi detail project dan item secara otomatis
        function updateProjectDetails() {
            const selectedProject = document.getElementById('project-select').value;
            const project = projectsData[selectedProject];
            const tableBody = document.getElementById('invoice-items');

            // 1. Update Detail Bill To
            if (project) {
                document.getElementById('bill_to_bank').value = project.bank;
                document.getElementById('bill_to_address1').value = project.address1;
                document.getElementById('bill_to_address2').value = project.address2;
                document.getElementById('bill_to_address3').value = project.address3;
                document.getElementById('person_up_name').value = project.person_up_name;
                document.getElementById('person_up_title').value = project.person_up_title;

                // 2. Kosongkan dan Isi Ulang Item Invoice
                tableBody.innerHTML = ''; // Hapus semua baris item yang ada

                if (project.default_descriptions && project.default_descriptions.length > 0) {
                    project.default_descriptions.forEach(desc => {
                        createItemRow(desc);
                    });
                } else {
                    // Jika tidak ada deskripsi default, buat satu baris kosong
                    createItemRow('');
                }

            } else {
                // Kosongkan semua input jika tidak ada project yang dipilih
                document.getElementById('bill_to_bank').value = '';
                document.getElementById('bill_to_address1').value = '';
                document.getElementById('bill_to_address2').value = '';
                document.getElementById('bill_to_address3').value = '';
                document.getElementById('person_up_name').value = '';
                document.getElementById('person_up_title').value = '';
                tableBody.innerHTML = '';
                createItemRow('');
            }

            reindexItems();
            calculateTotals();
        }

        // Fungsi untuk menambah item kosong secara manual
        function addItem() {
            createItemRow('');
            reindexItems();
            calculateTotals();
        }

        function removeItem(button) {
            const row = button.closest('.item-row');
            row.remove();
            reindexItems();
            calculateTotals();
        }

        function reindexItems() {
            const itemNos = document.querySelectorAll('#invoice-items .item-no');
            itemNos.forEach((element, index) => {
                element.textContent = index + 1;
            });
            itemCounter = itemNos.length;
        }

        function formatRupiah(number) {
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(number);
        }

        function calculateTotals() {
            let subTotal = 0;
            const itemAmounts = document.querySelectorAll('.item-amount');
            itemAmounts.forEach(input => {
                subTotal += parseFloat(input.value) || 0;
            });

            // 1. Hitung Sub Total
            document.getElementById('sub-total-display').textContent = formatRupiah(subTotal);
            document.getElementById('sub-total-input').value = subTotal;

            // 2. Hitung Management Fee (Nominal) dari Persentase
            const mgmtFeePercentage = parseFloat(document.getElementById('management-fee-percentage-input').value) || 0;
            const mgmtFeeAmount = subTotal * (mgmtFeePercentage / 100);

            document.getElementById('management-fee-amount-display').textContent = formatRupiah(mgmtFeeAmount);
            document.getElementById('management-fee-amount-input').value = mgmtFeeAmount;

            // 3. Hitung PPN
            const ppnPercentage = parseFloat(document.getElementById('ppn-percentage-input').value) || 0;

            // Dasar pengenaan PPN: Sub Total + Management Fee Nominal
            const ppnBase = subTotal + mgmtFeeAmount;

            const ppnAmount = ppnBase * (ppnPercentage / 100);

            document.getElementById('ppn-amount-display').textContent = formatRupiah(ppnAmount);
            document.getElementById('ppn-amount-input').value = ppnAmount;

            // 4. Hitung Grand Total
            const grandTotal = ppnBase + ppnAmount;

            document.getElementById('grand-total-display').textContent = formatRupiah(grandTotal);
            document.getElementById('grand-total-input').value = grandTotal;
        }

        // =========================================================
        // INISIALISASI
        // =========================================================
        document.addEventListener('DOMContentLoaded', function () {
            // 1. Event listener untuk update project details
            document.getElementById('project-select').addEventListener('change', updateProjectDetails);

            // 2. Panggil fungsi untuk mengisi form dan item awal
            updateProjectDetails();

            const projectSelect = document.getElementById("project-select");
            
            const rowPPN = document.getElementById("row-ppn");
            const rowPPH = document.getElementById("row-pph");
            const rowManagementFee = document.getElementById("row-management-fee");

            function toggleRows() {
                const selectedOption = projectSelect.options[projectSelect.selectedIndex];
                const isPPN = selectedOption.getAttribute("data-is-ppn");
                const isPPH = selectedOption.getAttribute("data-is-pph");
                const isManagementFee = selectedOption.getAttribute("data-is-management-fee");

                rowPPN.style.display = (isPPN === "Y") ? "table-row" : "none";
                rowPPH.style.display = (isPPH === "Y") ? "table-row" : "none";
                rowManagementFee.style.display = (isManagementFee === "Y") ? "table-row" : "none";

                if (isPPN !== "Y") {
                    document.getElementById("ppn-percentage-input").value = 0;
                    document.getElementById("ppn-amount-input").value = 0;
                    document.getElementById("ppn-amount-display").textContent = "Rp 0";
                }
                if (isPPH !== "Y") {
                    document.getElementById("pph-percentage-input").value = 0;
                    document.getElementById("pph-amount-input").value = 0;
                    document.getElementById("pph-amount-display").textContent = "Rp 0";
                }
                if (isManagementFee !== "Y") {
                    document.getElementById("management-fee-percentage-input").value = 0;
                    document.getElementById("management-fee-amount-input").value = 0;
                    document.getElementById("management-fee-amount-display").textContent = "Rp 0";
                }

                calculateTotals();
            }

            projectSelect.addEventListener("change", toggleRows);

            toggleRows();
        });
    </script>
</body>

</html>