<?php 
// ============= invoice.php (Show-on-select + Tema + CNAF Nota Debet + Management Fee + Status Button) =============

require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

if (!function_exists('e')) {
    function e($string) { return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8'); }
}

// --- Akses ---
if (!isset($_SESSION['id_karyawan']) || (($_SESSION['role'] ?? '') !== 'ADMIN')) {
    header('Location: ../login.php');
    exit;
}

$id_karyawan_admin = $_SESSION['id_karyawan'];
$nama_user_admin   = $_SESSION['nama'] ?? '';
$role_user_admin   = $_SESSION['role'] ?? '';

// Badge pending
$sql_pending_requests = "SELECT COUNT(*) AS total_pending FROM pengajuan WHERE status_pengajuan = 'Menunggu' AND jenis_pengajuan != 'Reimburse'";
$result_pending_requests = $conn->query($sql_pending_requests);
$total_pending = $result_pending_requests->fetch_assoc()['total_pending'] ?? 0;

$sql_pending_reimburse = "SELECT COUNT(*) AS total_pending FROM pengajuan WHERE jenis_pengajuan = 'Reimburse' AND status_pengajuan = 'Menunggu'";
$result_pending_reimburse = $conn->query($sql_pending_reimburse);
$total_pending_reimburse = $result_pending_reimburse->fetch_assoc()['total_pending'] ?? 0;

// Info admin
$stmt_admin_info = isset($conn) ? $conn->prepare("SELECT nik_ktp, jabatan FROM karyawan WHERE id_karyawan = ?") : false;
if ($stmt_admin_info) {
    $stmt_admin_info->bind_param("i", $id_karyawan_admin);
    $stmt_admin_info->execute();
    $result_admin_info = $stmt_admin_info->get_result();
    $admin_info = $result_admin_info->fetch_assoc();
    $stmt_admin_info->close();
} else { $admin_info = null; }
$nik_user_admin    = $admin_info['nik_ktp'] ?? 'Tidak Ditemukan';
$jabatan_user_admin= $admin_info['jabatan'] ?? 'Tidak Ditemukan';

function generateInvoiceNumber(mysqli $conn): string {
    $romanMonths = ["I","II","III","IV","V","VI","VII","VIII","IX","X","XI","XII"];
    $now   = new DateTime();
    $month = (int)$now->format('n');
    $year  = $now->format('Y');
    $roman = $romanMonths[$month - 1];

    $like = "%/$roman/$year";
    $sql  = "SELECT invoice_number FROM invoices WHERE invoice_number LIKE ? ORDER BY id_invoice DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $res  = $stmt->get_result();
    $last = $res->fetch_assoc()['invoice_number'] ?? null;
    $stmt->close();

    $running = 0;
    if ($last) {
        $parts = explode("/", $last);
        if (!empty($parts[0]) && ctype_digit($parts[0])) $running = (int)$parts[0];
    }

    $newRunning = str_pad((string)($running + 1), 3, "0", STR_PAD_LEFT);
    return "{$newRunning}/Inv/ManU/{$roman}/{$year}";
}
$next_invoice_number = generateInvoiceNumber($conn);

// Projects
$projects = [];
$sql_projects = "SELECT id, project_code, bank_name, address1, address2, address3, person_up_name, person_up_title, is_management_fee, is_ppn, is_pph FROM projects";
$result_projects = $conn->query($sql_projects);
if ($result_projects && $result_projects->num_rows > 0) {
    while ($row = $result_projects->fetch_assoc()) {
        $projects[$row['project_code']] = [
            'id'               => (int)$row['id'],
            'bank'             => $row['bank_name'],
            'address1'         => $row['address1'],
            'address2'         => $row['address2'],
            'address3'         => $row['address3'],
            'person_up_name'   => $row['person_up_name'],
            'person_up_title'  => $row['person_up_title'],
            'is_management_fee'=> $row['is_management_fee'],
            'is_ppn'           => $row['is_ppn'],
            'is_pph'           => $row['is_pph'],
            'default_descriptions'=> []
        ];
    }
}
// Descriptions
$sql_descriptions = "SELECT project_id, description FROM project_descriptions ORDER BY project_id, order_number";
$result_descriptions = $conn->query($sql_descriptions);
if ($result_descriptions && $result_descriptions->num_rows > 0) {
    while ($row = $result_descriptions->fetch_assoc()) {
        foreach ($projects as $pcode => $pdata) {
            if ($pdata['id'] === (int)$row['project_id']) {
                $projects[$pcode]['default_descriptions'][] = $row['description'];
                break;
            }
        }
    }
}
$projects_json = json_encode($projects);

// List table
$search_query = $_GET['search'] ?? '';
$sql_all_invoices = "SELECT 
            i.id_invoice AS id_invoice,
            i.invoice_number,
            i.invoice_date,
            i.bill_to_bank,
            i.grand_total,
            i.status_pembayaran,
            i.person_up_name,
            COALESCE(p.project_code, i.project_key) AS project_code
        FROM invoices i
        LEFT JOIN projects p ON p.project_code = i.project_key
        ORDER BY i.id_invoice DESC";
$result = $conn->query($sql_all_invoices);
$all_invoices = [];
if ($result && $result->num_rows > 0) { while ($row = $result->fetch_assoc()) $all_invoices[] = $row; }
$invoices = $all_invoices;
if (!empty($search_query)) {
    $search_term = strtolower($search_query);
    $invoices = array_filter($all_invoices, function($invoice) use ($search_term) {
        return str_contains(strtolower($invoice['invoice_number']), $search_term) ||
               str_contains(strtolower($invoice['person_up_name']), $search_term);
    });
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Halaman Invoice</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .theme-badge { display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:600;margin-left:10px; }
        .theme-mitra .header-section { background:#ffe2ef; }
        .theme-mitra .theme-badge   { background:#ff6fa3;color:#fff; }
        .theme-pkwt  .header-section { background:#e2efff; }
        .theme-pkwt  .theme-badge   { background:#3b82f6;color:#fff; }

        .notadebet-row { display:none; margin-top:6px; }
        select:disabled { background:#f2f2f2; cursor:not-allowed; }

        .hidden { display:none !important; }
        .percent-items table { width:100%; border-collapse:collapse; margin:8px 0; }
        .percent-items th, .percent-items td { padding:8px; border-top:1px solid #eee; }
        .percent-items .remove-btn { background:#e74c3c;color:#fff;border:none;padding:6px 10px;border-radius:6px;cursor:pointer;}
        .add-percent-btn { background:#0d6efd;color:#fff;border:none;padding:6px 10px;border-radius:6px;cursor:pointer;}
        .text-right { text-align:right; }
        .muted { color:#777; font-size:12px; }

        /* Badge status pembayaran */
        .status-label { padding:4px 8px; border-radius:999px; font-weight:600; font-size:12px; }
        .belumbayar { background:#fee2e2; color:#b91c1c; }  /* merah muda */
        .proses     { background:#fff7ed; color:#c2410c; }  /* oranye muda */
        .selesai    { background:#dcfce7; color:#166534; }  /* hijau muda */

        /* --- Aksi tombol di tabel invoice --- */
        .action-buttons{
        display:flex;
        gap:8px;
        align-items:center;
        }
        .action-buttons .action-btn{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        width:38px;          /* ukuran konsisten */
        height:38px;
        border-radius:8px;
        border:0;
        text-decoration:none;
        color:#fff;
        line-height:1;
        font-size:14px;      /* ukuran ikon */
        cursor:pointer;
        }

        /* Warna tombol */
        .action-buttons .view-btn{    background:#0ea5e9; }  /* biru */
        .action-buttons .approve-btn{ background:#10b981; }  /* hijau */
        .action-buttons .delete-btn{  background:#e74c3c; }  /* merah */

        /* Hilangkan margin default form agar sejajar */
        .action-buttons form{ margin:0; }

        /* Tabel rapi, garis lurus */
                .invoice-table{
                width:100%;
                border-collapse:collapse;
                table-layout:fixed;
                }
                .invoice-table th,
                .invoice-table td{
                padding:12px 14px;
                border-bottom:1px solid #e5e7eb;  /* garis antar baris */
                vertical-align:middle;
                background:#fff;
                }
                .invoice-table thead th{
                border-bottom:2px solid #e5e7eb;
                }

                /* Kolom aksi (opsional) */
                .invoice-table th:nth-child(6),
                .invoice-table td:nth-child(6){
                width:170px; /* sesuaikan */
                }

                /* Container tombol di dalam TD */
                .action-buttons{
                display:flex;
                gap:8px;
                align-items:center;
                }

                /* Tombol ikon seragam */
                .action-btn{
                display:inline-flex;
                align-items:center;
                justify-content:center;
                width:38px;
                height:38px;
                border-radius:8px;
                border:0;
                text-decoration:none;
                color:#fff;
                line-height:1;
                font-size:14px;
                cursor:pointer;
                }
                .view-btn{    background:#0ea5e9; }
                .approve-btn{ background:#10b981; }
                .delete-btn{  background:#e74c3c; }

                /* Hilangkan margin default form */
                .action-buttons form{ margin:0; }


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
                    <li><a href="../absensi/absensi.php"><i class="fas fa-edit"></i> Absensi </a></li>
                    <li class="dropdown-trigger">
                        <a href="#" class="dropdown-link"><i class="fas fa-users"></i> Data Karyawan <i class="fas fa-caret-down"></i></a>
                        <ul class="dropdown-menu">
                            <li><a href="../data_karyawan/all_employees.php">Semua Karyawan</a></li>
                            <li><a href="../data_karyawan/karyawan_nonaktif.php">Non-Aktif</a></li>
                        </ul>
                    </li>
                    <li class="dropdown-trigger">
                        <a href="#" class="dropdown-link"><i class="fas fa-users"></i> Data Pengajuan<span class="badge"><?= $total_pending ?></span> <i class="fas fa-caret-down"></i></a>
                        <ul class="dropdown-menu">
                            <li><a href="../pengajuan/pengajuan.php">Pengajuan</a></li>
                            <li><a href="../pengajuan/kelola_pengajuan.php">Kelola Pengajuan<span class="badge"><?= $total_pending ?></span></a></li>
                        </ul>
                    </li>
                    <li><a href="../monitoring_kontrak/monitoring_kontrak.php"><i class="fas fa-calendar-alt"></i> Monitoring Kontrak</a></li>
                    <li><a href="../monitoring_kontrak/surat_tugas_history.php"><i class="fas fa-file-alt"></i> Riwayat Surat Tugas</a></li>
                    <li><a href="../payslip/e_payslip_admin.php"><i class="fas fa-money-check-alt"></i> E-Pay Slip</a></li>
                    <li class="active"><a href="../invoice/invoice.php"><i class="fas fa-money-check-alt"></i> Invoice</a></li>
                </ul>
            </nav>
            <div class="logout-link"><a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a></div>
        </aside>

        <main class="main-content">
            <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
                <div style="padding:12px;background:#d4edda;color:#155724;border:1px solid #c3e6cb;border-radius:5px;margin-bottom:15px;">✅ Invoice berhasil disimpan.</div>
            <?php endif; ?>
            <?php if (isset($_GET['deleted']) && $_GET['deleted'] == 1): ?>
                <div style="padding:12px;background:#fdecea;color:#611a15;border:1px solid #f5c6cb;border-radius:5px;margin-bottom:15px;">🗑️ Invoice berhasil dihapus.</div>
            <?php endif; ?>
            <?php if (isset($_GET['status_updated'])): ?>
              <?php if ($_GET['status_updated']=='1'): ?>
                <div style="padding:12px;background:#d4edda;color:#155724;border:1px solid #c3e6cb;border-radius:5px;margin-bottom:15px;">
                  ✅ Status pembayaran berhasil diperbarui.
                </div>
              <?php else: ?>
                <div style="padding:12px;background:#fdecea;color:#611a15;border:1px solid #f5c6cb;border-radius:5px;margin-bottom:15px;">
                  ⚠️ Gagal memperbarui status pembayaran.
                </div>
              <?php endif; ?>
            <?php endif; ?>

            <header class="main-header">
                <h1>Kelola Invoice</h1>
                <p class="current-date"><?= date('l, d F Y'); ?></p>
            </header>

            <div class="toolbar">
                <div class="search-filter-container">
                    <form action="invoice.php" method="GET" class="search-form">
                        <div class="search-box">
                            <input type="text" name="search" placeholder="Cari No. Invoice atau Nama..." value="<?= e($search_query) ?>">
                            <button type="submit"><i class="fas fa-search"></i></button>
                        </div>
                    </form>
                </div>
                <div class="action-buttons">
                    <button type="button" class="download-btn create-btn" style="background-color:#007bff;color:#fff;" onclick="openModal('invoiceModal')">
                        <i class="fas fa-plus"></i> Buat Invoice Baru
                    </button>
                </div>
            </div>

            <div class="data-table-container card">
                <h2>Daftar Invoice</h2>
                <table id="invoice-table" class="invoice-table">
                    <thead>
                        <tr>
                            <th>Nomor Invoice</th>
                            <th>Tanggal</th>
                            <th>Untuk Project</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($invoices)): ?>
                            <tr><td id="no-data-invoice" colspan="6" style="text-align:center;">Tidak ada data invoice yang ditemukan.</td></tr>
                        <?php else: foreach ($invoices as $invoice): ?>
                            <tr>
                                <td><?= e($invoice['invoice_number']) ?></td>
                                <td><?= e(date('d F Y', strtotime($invoice['invoice_date']))) ?></td>
                                <td><?= e($invoice['project_code'] ?? '') ?></td>
                                <td>Rp <?= number_format((float)$invoice['grand_total'], 0, ',', '.') ?></td>
                                <td>
                                    <?php $status_class = strtolower(str_replace(' ', '', $invoice['status_pembayaran'])); ?>
                                    <span class="status-label <?= e($status_class) ?>"><?= e($invoice['status_pembayaran']) ?></span>
                                </td>
                                <td>
                                <div class="action-buttons">
                                    <?php
// Tentukan URL berdasarkan nilai project
$view_file = ($invoice['project_code'] === 'PEI') ? 'view_invoice_pei.php' : 'view_invoice.php';
$invoice_id = (int)$invoice['id_invoice'];
?>

<a class="action-btn view-btn" href="<?= $view_file ?>?id=<?= $invoice_id ?>" title="Lihat">
    <i class="fas fa-eye"></i>
</a>

                                    <?php if (strcasecmp($invoice['status_pembayaran'], 'Sudah Dibayar') !== 0): ?>
                                    <form action="update_invoice_status.php" method="POST">
                                        <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                        <input type="hidden" name="id" value="<?= (int)$invoice['id_invoice'] ?>">
                                        <input type="hidden" name="status_pembayaran" value="Sudah Dibayar">
                                        <button type="submit" class="action-btn approve-btn" title="Tandai Sudah Dibayar">
                                        <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>

                                    <form action="delete_invoice.php" method="POST" onsubmit="return confirm('Hapus invoice ini? Tindakan tidak bisa dibatalkan.');">
                                    <input type="hidden" name="id" value="<?= (int)$invoice['id_invoice'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                    <button type="submit" class="action-btn delete-btn" title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    </form>
                                </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- ================= Modal Buat Invoice (tetap versi kamu) ================= -->
    <div id="invoiceModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Buat Invoice Baru <span id="badge-surat" class="theme-badge" style="display:none;"></span></h2>
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
                        <input type="hidden" name="surat_tipe" id="surat_tipe" value="">

                        <!-- BLOK ATAS -->
                        <div class="bill-to-section">
                            <div class="bill-to-info">
                                <p style="margin-bottom:10px;">
                                    <strong>Status Karyawan :</strong>
                                    <select id="status-select" name="status_karyawan" required>
                                        <option value="" selected disabled>-- Pilih Status --</option>
                                        <option value="MITRA">MITRA</option>
                                        <option value="PKWT">PKWT</option>
                                    </select>
                                </p>

                                <strong>Project :</strong>
                                <select id="project-select" name="project_code" required disabled>
                                    <option value="" disabled selected>-- Pilih Project --</option>
                                    <?php foreach ($projects as $key => $project): ?>
                                        <option value="<?= e($key) ?>"
                                                data-is-ppn="<?= e($project['is_ppn']) ?>"
                                                data-is-pph="<?= e($project['is_pph']) ?>"
                                                data-is-management-fee="<?= e($project['is_management_fee']) ?>">
                                            <?= e($key) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <div id="notadebet-wrapper" class="notadebet-row">
                                    <label for="notadebet_select"><strong>Nota Debet?</strong></label>
                                    <select id="notadebet_select" name="nota_debet">
                                        <option value="" selected disabled>-- Pilih --</option>
                                        <option value="YA">YA</option>
                                        <option value="TIDAK">TIDAK</option>
                                    </select>
                                </div>
                            </div>

                            <div class="invoice-details">
                                <p><span>No</span> :
                                    <input type="text" id="invoice_no" name="invoice_no" value="<?= e($next_invoice_number) ?>" placeholder="Nomor Invoice" readonly>
                                </p>
                                <p><span>Tanggal</span> :
                                    <input type="date" name="invoice_date" value="<?= date('Y-m-d'); ?>" required>
                                </p>
                            </div>

                            <div class="person-up">
                                <p><span>Up</span> :
                                    <input type="text" name="person_up_name" id="person_up_name" value="" required>
                                </p>
                                <p>
                                    <input type="text" name="person_up_title" id="person_up_title" value="" required>
                                </p>
                            </div>
                        </div>

                        <!-- BLOK BAWAH: Hidden sampai project dipilih -->
                        <div id="section-after-project" class="hidden">
                            <div class="bill-to-fields">
                                <strong>BILL TO:</strong>
                                <p><input type="text" name="bill_to_bank" id="bill_to_bank" placeholder="Nama Bank" value="" required></p>
                                <p><input type="text" name="bill_to_address1" id="bill_to_address1" placeholder="Alamat 1" value="" required></p>
                                <p><input type="text" name="bill_to_address2" id="bill_to_address2" placeholder="Alamat 2" value=""></p>
                                <p><input type="text" name="bill_to_address3" id="bill_to_address3" placeholder="Alamat 3" value=""></p>
                            </div>

                            <div class="items-section">
                                <table>
                                    <thead>
                                        <tr>
                                            <th style="width:5%">No</th>
                                            <th style="width:65%">Description</th>
                                            <th style="width:20%">Amount</th>
                                            <th style="width:10%"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="invoice-items"></tbody>
                                    <tfoot>
                                        <tr class="add-item-row">
                                            <td colspan="4"><button type="button" class="add-item-btn" onclick="addItem()">Tambah Item Manual</button></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <!-- RINGKASAN: MF -> SUBTOTAL -> PPN -> PPH -> Penyesuaian -> GRAND -->
                            <div class="summary-section">
                                <table>
                                    <tbody>
                                        <!-- Management Fee DI ATAS SUB TOTAL -->
                                        <tr id="row-management-fee">
                                            <td class="text-right"><strong>MANAGEMENT FEE (<input type="number" name="management_fee_percentage" id="management-fee-percentage-input" value="0" step="0.1" style="width:50px;text-align:right;" oninput="calculateTotals()">%)</strong></td>
                                            <td class="text-right"><span id="management-fee-amount-display">Rp 0</span><input type="hidden" name="management_fee_amount" id="management-fee-amount-input"></td>
                                        </tr>

                                        <!-- SUB TOTAL = jumlah Description + Management Fee -->
                                        <tr id="row-subtotal">
                                            <td class="text-right"><strong>SUB TOTAL</strong></td>
                                            <td class="text-right"><span id="sub-total-display">Rp 0</span><input type="hidden" name="sub_total" id="sub-total-input"></td>
                                        </tr>

                                        <tr id="row-ppn">
                                            <td class="text-right"><strong>PPN (<input type="number" name="ppn_percentage" id="ppn-percentage-input" value="11" step="0.1" style="width:50px;text-align:right;" oninput="calculateTotals()">%)</strong></td>
                                            <td class="text-right"><span id="ppn-amount-display">Rp 0</span><input type="hidden" name="ppn_amount" id="ppn-amount-input"></td>
                                        </tr>
                                        <tr id="row-pph">
                                            <td class="text-right"><strong>PPH 23(<input type="number" name="pph_percentage" id="pph-percentage-input" value="2" step="0.1" style="width:50px;text-align:right;" oninput="calculateTotals()">%)</strong></td>
                                            <td class="text-right"><span id="pph-amount-display">Rp 0</span><input type="hidden" name="pph_amount" id="pph-amount-input"></td>
                                        </tr>

                                        <!-- Nama Penyesuaian (%) di bawah PPH -->
                                        <tr>
                                            <td colspan="2">
                                                <div class="percent-items">
                                                    <div style="display:flex;justify-content:space-between;align-items:center;">
                                                        <div><strong>Nama Penyesuaian (%)</strong></div>
                                                        <div><button type="button" class="add-percent-btn" onclick="addPercentItem()">+ Tambah Item Manual (%)</button></div>
                                                    </div>
                                                    <table id="percent-items-table">
                                                        <thead>
                                                            <tr>
                                                                <th style="width:55%">Nama Penyesuaian (%)</th>
                                                                <th style="width:20%">Persen</th>
                                                                <th style="width:20%" class="text-right">Nilai</th>
                                                                <th style="width:5%"></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="percent-items-body"></tbody>
                                                    </table>
                                                </div>
                                            </td>
                                        </tr>

                                        <tr class="grand-total">
                                            <td class="text-right"><strong>GRAND TOTAL</strong></td>
                                            <td class="text-right"><span id="grand-total-display">Rp 0</span><input type="hidden" name="grand_total" id="grand-total-input"></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="transfer-section">
                                <strong>Please Transfer To Account:</strong>
                                <p><span>Bank</span> : <input type="text" name="transfer_bank" value="CIMB Niaga" required></p>
                                <p><span>Rekening Number</span>: <input type="text" name="transfer_account_no" value="800140878000" required></p>
                                <p><span>A/C</span> : <input type="text" name="transfer_account_name" value="PT Mandiri Andalan Utama" required></p>
                            </div>

                            <div class="footer-section">
                                <div style="display:flex;justify-content:flex-end;width:100%;">
                                    <div style="width:45%;text-align:left;margin-top:30px;">
                                        <p style="margin:0 0 10px 0;">
                                            Jakarta, <input type="date" name="footer_date" value="<?= date('Y-m-d'); ?>" required style="width:auto;display:inline-block;border:none;padding:0;">
                                        </p>
                                        <br><br><br>
                                        <p class="signatory-name" style="margin:0;border-bottom:1px solid #000;display:inline-block;padding:0 0px;">
                                            <input type="text" name="manu_signatory_name" value="Oktafian Farhan" style="border:none;text-align:left;width:auto;padding:0;">
                                        </p>
                                        <p class="signatory-title" style="margin:5px 0 0 0;font-size:12px;color:#777;">
                                            <input type="text" name="manu_signatory_title" value="Direktur Utama" style="border:none;text-align:left;width:auto;padding:0;">
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <button id="submit-btn" type="submit">Simpan Invoice</button>
                        </div> <!-- end section-after-project -->
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openModal(id){ document.getElementById(id).style.display='block'; }
        function closeModal(id){ document.getElementById(id).style.display='none'; }
        window.onclick=function(e){ const m=document.getElementById('invoiceModal'); if(e.target===m) m.style.display='none'; }

        const projectsData = <?= $projects_json ?>;
        let itemCounter = 0;

        function createItemRow(description=''){
            const tbody=document.getElementById('invoice-items');
            const tr=document.createElement('tr'); tr.classList.add('item-row');
            const desc = description ? `<input type="text" name="description[]" value="${description.replace(/"/g,'&quot;')}" required>`
                                     : `<input type="text" name="description[]" value="" placeholder="Masukkan deskripsi item..." required>`;
            tr.innerHTML = `
                <td class="item-no">${itemCounter}</td>
                <td>${desc}</td>
                <td><input type="number" name="amount[]" class="item-amount text-right" value="0" step="1" oninput="calculateTotals()" required></td>
                <td><button type="button" class="remove-item-btn" onclick="removeItem(this)">Hapus</button></td>`;
            tbody.appendChild(tr);
        }
        function removeItem(btn){ btn.closest('.item-row').remove(); reindexItems(); calculateTotals(); }
        function reindexItems(){ const nos=document.querySelectorAll('#invoice-items .item-no'); nos.forEach((el,i)=>el.textContent=i+1); itemCounter=nos.length; }

        // Percent items (display only)
        function addPercentItem(name='', percentValue=''){
            const tbody = document.getElementById('percent-items-body');
            const tr = document.createElement('tr');
            tr.classList.add('percent-item');
            tr.innerHTML = `
                <td><input type="text" name="percent_label[]" value="${name}" placeholder="mis. Admin Fee / Diskon" required></td>
                <td><input type="number" name="percent_value[]" class="percent-value" value="${percentValue!==''?percentValue:0}" step="0.1" oninput="calculateTotals()" required> %</td>
                <td class="text-right"><span class="percent-amount-display">Rp 0</span><input type="hidden" name="percent_amount[]" class="percent-amount-input" value="0"></td>
                <td><button type="button" class="remove-btn" onclick="removePercentItem(this)">Hapus</button></td>
            `;
            tbody.appendChild(tr);
            calculateTotals();
        }
        function removePercentItem(btn){
            btn.closest('tr.percent-item').remove();
            calculateTotals();
        }

        function formatRupiah(x){ 
            const n = isFinite(x) ? x : 0;
            return new Intl.NumberFormat('id-ID',{style:'currency',currency:'IDR',minimumFractionDigits:0,maximumFractionDigits:0}).format(n); 
        }

        // === RUMUS: SUBTOTAL = Descriptions + MF; Pajak dari MF jika ada, else dari SUBTOTAL; GRAND = SUBTOTAL + PPN - PPH
        function calculateTotals(){
            // 1) Total item utama
            let itemsTotal = 0;
            document.querySelectorAll('.item-amount').forEach(i=>{ itemsTotal += parseFloat(i.value)||0; });

            // 2) Management Fee
            const mfPct = parseFloat(document.getElementById('management-fee-percentage-input').value)||0;
            const mfAmt = itemsTotal * (mfPct/100);
            document.getElementById('management-fee-amount-display').textContent = formatRupiah(mfAmt);
            document.getElementById('management-fee-amount-input').value = mfAmt;

            // 3) Subtotal
            const subTotal = itemsTotal + mfAmt;
            document.getElementById('sub-total-display').textContent = formatRupiah(subTotal);
            document.getElementById('sub-total-input').value = subTotal;

            // 4) Basis pajak
            const taxBase = (mfAmt > 0) ? mfAmt : subTotal;

            // 5) Pajak (perhatikan bisa hidden)
            const ppnPct = parseFloat(document.getElementById('ppn-percentage-input').value)||0;
            const pphPct = parseFloat(document.getElementById('pph-percentage-input').value)||0;
            const rowPPNVisible = document.getElementById('row-ppn').style.display !== 'none';
            const rowPPHVisible = document.getElementById('row-pph').style.display !== 'none';

            const ppnAmount = rowPPNVisible ? taxBase * (ppnPct/100) : 0;
            const pphAmount = rowPPHVisible ? taxBase * (pphPct/100) : 0;

            document.getElementById('ppn-amount-display').textContent = formatRupiah(ppnAmount);
            document.getElementById('ppn-amount-input').value = ppnAmount;
            document.getElementById('pph-amount-display').textContent = formatRupiah(pphAmount);
            document.getElementById('pph-amount-input').value = pphAmount;

            // 6) Penyesuaian (%) hanya tampilan
            document.querySelectorAll('#percent-items-body tr.percent-item').forEach(row=>{
                const pct = parseFloat(row.querySelector('.percent-value').value)||0;
                const val = subTotal * (pct/100);
                row.querySelector('.percent-amount-display').textContent = formatRupiah(val);
                row.querySelector('.percent-amount-input').value = val;
            });

            // 7) Grand Total
            const grand = subTotal + ppnAmount - pphAmount;
            document.getElementById('grand-total-display').textContent = formatRupiah(grand);
            document.getElementById('grand-total-input').value = grand;
        }

        document.addEventListener('DOMContentLoaded', function(){
            const modal         = document.getElementById('invoiceModal');
            const statusSelect  = document.getElementById('status-select');
            const projectSelect = document.getElementById('project-select');
            const badgeSurat    = document.getElementById('badge-surat');
            const suratTipe     = document.getElementById('surat_tipe');
            const afterProject  = document.getElementById('section-after-project');
            const ndWrap        = document.getElementById('notadebet-wrapper');
            const ndSelect      = document.getElementById('notadebet_select');

            // awal
            projectSelect.disabled = true;
            afterProject.classList.add('hidden');
            ndWrap.style.display = 'none';

            statusSelect.addEventListener('change', function(){
                const val=this.value;
                modal.classList.remove('theme-mitra','theme-pkwt');
                if(val==='MITRA'){ modal.classList.add('theme-mitra'); badgeSurat.textContent='SURAT MITRA'; }
                else if(val==='PKWT'){ modal.classList.add('theme-pkwt'); badgeSurat.textContent='SURAT PKWT'; }
                else { badgeSurat.textContent=''; }
                badgeSurat.style.display = badgeSurat.textContent ? 'inline-block' : 'none';
                suratTipe.value = val || '';

                projectSelect.disabled = !(val==='MITRA'||val==='PKWT');
                projectSelect.value = "";
                afterProject.classList.add('hidden');
                ndWrap.style.display='none'; if(ndSelect) ndSelect.value='';

                // reset field
                document.getElementById('bill_to_bank').value='';
                document.getElementById('bill_to_address1').value='';
                document.getElementById('bill_to_address2').value='';
                document.getElementById('bill_to_address3').value='';
                document.getElementById('person_up_name').value='';
                document.getElementById('person_up_title').value='';
                document.getElementById('invoice-items').innerHTML='';
                document.getElementById('percent-items-body').innerHTML='';
                createItemRow('');
                reindexItems(); calculateTotals();
            });

            projectSelect.addEventListener('change', function(){
                const selected = this.value;
                afterProject.classList.toggle('hidden', !selected);
                updateProjectDetails();

                // CNAF nota debet
                if (selected && selected.toUpperCase()==='CNAF'){
                    ndWrap.style.display='block';
                } else {
                    ndWrap.style.display='none';
                    if(ndSelect) ndSelect.value='';
                    enableTaxes(true); // non-CNAF -> pajak aktif
                }
                calculateTotals();
            });

            ndSelect && ndSelect.addEventListener('change', function(){
                const shouldUseTax = (this.value !== 'YA'); // Nota Debet YA => tanpa pajak
                enableTaxes(shouldUseTax);
            });

            function enableTaxes(enabled){
                const rowPPN = document.getElementById("row-ppn");
                const rowPPH = document.getElementById("row-pph");
                if(enabled){
                    rowPPN.style.display='table-row';
                    rowPPH.style.display='table-row';
                    if(!document.getElementById('ppn-percentage-input').value) document.getElementById('ppn-percentage-input').value = 11;
                    if(!document.getElementById('pph-percentage-input').value) document.getElementById('pph-percentage-input').value = 2;
                } else {
                    rowPPN.style.display='none';
                    rowPPH.style.display='none';
                    document.getElementById('ppn-percentage-input').value = 0;
                    document.getElementById('pph-percentage-input').value = 0;
                }
                calculateTotals();
            }

            document.getElementById('invoiceForm').addEventListener('submit', function(e){
                if(!statusSelect.value){ e.preventDefault(); alert('Pilih Status Karyawan terlebih dahulu.'); statusSelect.focus(); return; }
                if(!projectSelect.value){ e.preventDefault(); alert('Pilih Project setelah memilih Status.'); projectSelect.focus(); return; }
                if(projectSelect.value.toUpperCase()==='CNAF' && !ndSelect.value){
                    e.preventDefault(); alert('Untuk project CNAF, pilih “Nota Debet?”'); ndSelect.focus(); return;
                }
            });
        });

        function updateProjectDetails(){
            const selected = document.getElementById('project-select').value;
            const project  = projectsData[selected];
            const tableBody= document.getElementById('invoice-items');
            if(project){
                document.getElementById('bill_to_bank').value     = project.bank || '';
                document.getElementById('bill_to_address1').value = project.address1 || '';
                document.getElementById('bill_to_address2').value = project.address2 || '';
                document.getElementById('bill_to_address3').value = project.address3 || '';
                document.getElementById('person_up_name').value   = project.person_up_name || '';
                document.getElementById('person_up_title').value  = project.person_up_title || '';
                tableBody.innerHTML='';
                if(project.default_descriptions && project.default_descriptions.length>0){
                    project.default_descriptions.forEach(d=>createItemRow(d));
                } else { createItemRow(''); }
            } else {
                document.getElementById('bill_to_bank').value='';
                document.getElementById('bill_to_address1').value='';
                document.getElementById('bill_to_address2').value='';
                document.getElementById('bill_to_address3').value='';
                document.getElementById('person_up_name').value='';
                document.getElementById('person_up_title').value='';
                tableBody.innerHTML=''; createItemRow('');
            }
            // reset percent items when project changes
            document.getElementById('percent-items-body').innerHTML='';
            reindexItems(); calculateTotals();
        }

        // expose functions
        window.addPercentItem = addPercentItem;
        window.addItem = function(){ createItemRow(''); reindexItems(); calculateTotals(); }
        window.removeItem = function(btn){ const row = btn.closest('tr.item-row'); if(row) row.remove(); reindexItems(); calculateTotals(); }
        window.removePercentItem = function(btn){ const row = btn.closest('tr.percent-item'); if(row) row.remove(); calculateTotals(); }
    </script>
</body>
</html>
