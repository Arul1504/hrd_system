<?php
// ============= invoice.php (FINAL VERSION LENGKAP + AUTO NUMBER) =============

require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CSRF token untuk aksi hapus
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];


// --- Helper untuk menghindari XSS (Wajib Didefinisikan) ---
if (!function_exists('e')) {
    function e($string)
    {
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
$nama_user_admin   = $_SESSION['nama'];
$role_user_admin   = $_SESSION['role'];

// Ambil jumlah pending requests untuk sidebar
$sql_pending_requests    = "SELECT COUNT(*) AS total_pending FROM pengajuan WHERE status_pengajuan = 'Menunggu'";
$result_pending_requests = isset($conn) ? $conn->query($sql_pending_requests) : false;
$total_pending           = $result_pending_requests ? ($result_pending_requests->fetch_assoc()['total_pending'] ?? 0) : 0;

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
$nik_user_admin     = $admin_info['nik_ktp'] ?? 'Tidak Ditemukan';
$jabatan_user_admin = $admin_info['jabatan'] ?? 'Tidak Ditemukan';

// ============= FUNGSI: Generate Nomor Invoice Otomatis (reset per bulan) =============
function generateInvoiceNumber(mysqli $conn): string {
    $romanMonths = ["I","II","III","IV","V","VI","VII","VIII","IX","X","XI","XII"];
    $now   = new DateTime();
    $month = (int)$now->format('n');  // 1-12
    $year  = $now->format('Y');
    $roman = $romanMonths[$month-1];

    // Cari invoice terakhir di bulan & tahun ini berdasarkan pola di kolom invoice_number
    $like = "%/$roman/$year";
    $sql  = "SELECT invoice_number
             FROM invoices
             WHERE invoice_number LIKE ?
             ORDER BY id_invoice DESC
             LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $res  = $stmt->get_result();
    $last = $res->fetch_assoc()['invoice_number'] ?? null;
    $stmt->close();

    $running = 0;
    if ($last) {
        $parts = explode("/", $last); // [NNN, Inv, ManU, ROMAN, YYYY]
        if (!empty($parts[0]) && ctype_digit($parts[0])) {
            $running = (int)$parts[0];
        }
    }

    $newRunning = str_pad((string)($running + 1), 3, "0", STR_PAD_LEFT);
    return "{$newRunning}/Inv/ManU/{$roman}/{$year}";
}

// Nomor untuk form (dibuat sekali saat halaman dimuat)
$next_invoice_number = generateInvoiceNumber($conn);

// ============= MENGAMBIL DATA PROJECT DARI DATABASE =============
$projects = [];
$project_descriptions = [];

$sql_projects   = "SELECT id, project_code, bank_name, address1, address2, address3, person_up_name, person_up_title, is_management_fee, is_ppn, is_pph FROM projects";
$result_projects = $conn->query($sql_projects);

if ($result_projects && $result_projects->num_rows > 0) {
    while ($row = $result_projects->fetch_assoc()) {
        $projects[$row['project_code']] = [
            'id'                => $row['id'],
            'bank'              => $row['bank_name'],
            'address1'          => $row['address1'],
            'address2'          => $row['address2'],
            'address3'          => $row['address3'],
            'person_up_name'    => $row['person_up_name'],
            'person_up_title'   => $row['person_up_title'],
            'is_management_fee' => $row['is_management_fee'],
            'is_ppn'            => $row['is_ppn'],
            'is_pph'            => $row['is_pph'],
            'default_descriptions' => []
        ];
    }
}

// Mengambil deskripsi default
$sql_descriptions   = "SELECT project_id, description FROM project_descriptions ORDER BY project_id, order_number";
$result_descriptions = $conn->query($sql_descriptions);

if ($result_descriptions && $result_descriptions->num_rows > 0) {
    while ($row = $result_descriptions->fetch_assoc()) {
        foreach ($projects as $project_key => $project_data) {
            if ($project_data['id'] === $row['project_id']) {
                $projects[$project_key]['default_descriptions'][] = $row['description'];
                break;
            }
        }
    }
}

// Encode data projects ke JSON untuk digunakan oleh JavaScript
$projects_json = json_encode($projects);

// Tentukan project default untuk mengisi form saat modal dimuat
$default_project_key = array_key_first($projects);
$default_project     = $projects[$default_project_key] ?? null;

// --- Logika untuk halaman Invoice (Daftar) ---
$search_query = $_GET['search'] ?? '';

// SELECT + JOIN + ORDER BY (tetap seperti versi kamu)
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
        LEFT JOIN projects p
        ON p.project_code = i.project_key
        ORDER BY i.id_invoice DESC";
$result = $conn->query($sql_all_invoices);

$all_invoices = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $all_invoices[] = $row;
    }
}

// Terapkan filter pencarian pada data
$invoices = $all_invoices;
if (!empty($search_query)) {
    $search_term = strtolower($search_query);
    $invoices = array_filter($all_invoices, function ($invoice) use ($search_term) {
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
        <div class="logout-link">
            <a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a>
        </div>
    </aside>

    <main class="main-content">
        <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
            <div style="padding: 12px; background:#d4edda; color:#155724; border:1px solid #c3e6cb; border-radius:5px; margin-bottom:15px;">
                ✅ Invoice berhasil disimpan.
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['deleted']) && $_GET['deleted'] == 1): ?>
            <div style="padding:12px;background:#fdecea;color:#611a15;border:1px solid #f5c6cb;border-radius:5px;margin-bottom:15px;">
                🗑️ Invoice berhasil dihapus.
            </div>
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
                <button type="button" class="download-btn create-btn" style="background-color:#007bff; color:#fff;" onclick="openModal('invoiceModal')">
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
                        <tr>
                            <td id="no-data-invoice" colspan="6" style="text-align:center;">Tidak ada data invoice yang ditemukan.</td>
                        </tr>
                    <?php else: foreach ($invoices as $invoice): ?>
                        <tr>
                            <td><?= e($invoice['invoice_number']) ?></td>
                            <td><?= e(date('d F Y', strtotime($invoice['invoice_date']))) ?></td>
                            <td><?= e($invoice['project_code'] ?? '') ?></td>
                            <td>Rp <?= number_format($invoice['grand_total'], 0, ',', '.') ?></td>
                            <td>
                                <?php $status_class = strtolower(str_replace(' ', '', $invoice['status_pembayaran'])); ?>
                                <span class="status-label <?= e($status_class) ?>"><?= e($invoice['status_pembayaran']) ?></span>
                            </td>
                            <td class="action-buttons" style="display:flex; gap:6px; align-items:center;">
                                <a class="action-btn view-btn" href="view_invoice.php?id=<?= $invoice['id_invoice'] ?>" title="Lihat">
                                    <i class="fas fa-eye"></i>
                                </a>

                                <!-- Form hapus: gunakan POST + CSRF -->
                                <form action="delete_invoice.php" method="POST" onsubmit="return confirm('Hapus invoice ini? Tindakan tidak bisa dibatalkan.');" style="display:inline;">
                                    <input type="hidden" name="id" value="<?= (int)$invoice['id_invoice'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                    <button type="submit" class="action-btn" title="Hapus" style="background:#e74c3c;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<!-- ================= Modal Buat Invoice ================= -->
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
                            <select id="project-select" name="project_code" required>
                                <option value="" disabled>-- Pilih Project --</option>
                                <?php foreach ($projects as $key => $project): ?>
                                    <option value="<?= e($key) ?>"
                                        data-is-ppn="<?= e($project['is_ppn']) ?>"
                                        data-is-pph="<?= e($project['is_pph']) ?>"
                                        data-is-management-fee="<?= e($project['is_management_fee']) ?>"
                                        <?= ($key === $default_project_key) ? 'selected' : '' ?>>
                                        <?= e($key) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <br><br>
                            <strong>BILL TO:</strong>
                            <p><input type="text" name="bill_to_bank" id="bill_to_bank" placeholder="Nama Bank" value="<?= e($default_project['bank'] ?? '') ?>" required></p>
                            <p><input type="text" name="bill_to_address1" id="bill_to_address1" placeholder="Alamat 1" value="<?= e($default_project['address1'] ?? '') ?>" required></p>
                            <p><input type="text" name="bill_to_address2" id="bill_to_address2" placeholder="Alamat 2" value="<?= e($default_project['address2'] ?? '') ?>"></p>
                            <p><input type="text" name="bill_to_address3" id="bill_to_address3" placeholder="Alamat 3" value="<?= e($default_project['address3'] ?? '') ?>"></p>
                        </div>

                        <div class="invoice-details">
                            <p><span>No</span> :
                                <input type="text" id="invoice_no" name="invoice_no"
                                       value="<?= e($next_invoice_number) ?>" placeholder="Nomor Invoice" readonly>
                            </p>
                            <p><span>Tanggal</span> :
                                <input type="date" name="invoice_date" value="<?= date('Y-m-d'); ?>" required>
                            </p>
                        </div>

                        <div class="person-up">
                            <p><span>Up</span> :
                                <input type="text" name="person_up_name" id="person_up_name"
                                       value="<?= e($default_project['person_up_name'] ?? '') ?>" required>
                            </p>
                            <p>
                                <input type="text" name="person_up_title" id="person_up_title"
                                       value="<?= e($default_project['person_up_title'] ?? '') ?>" required>
                            </p>
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
                            <tbody id="invoice-items"></tbody>
                            <tfoot>
                                <tr class="add-item-row">
                                    <td colspan="4"><button type="button" class="add-item-btn" onclick="addItem()">Tambah Item Manual</button></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="summary-section">
                        <table>
                            <tbody>
                                <tr>
                                    <td class="text-right"><strong>SUB TOTAL</strong></td>
                                    <td class="text-right"><span id="sub-total-display">Rp 0</span><input type="hidden" name="sub_total" id="sub-total-input"></td>
                                </tr>
                                <tr id="row-management-fee">
                                    <td class="text-right"><strong>MANAGEMENT FEE (<input type="number" name="management_fee_percentage" id="management-fee-percentage-input" value="0" step="0.1" style="width: 50px; text-align: right;" oninput="calculateTotals()">%)</strong></td>
                                    <td><span id="management-fee-amount-display">Rp 0</span><input type="hidden" name="management_fee_amount" id="management-fee-amount-input"></td>
                                </tr>
                                <tr id="row-ppn">
                                    <td class="text-right"><strong>PPN (<input type="number" name="ppn_percentage" id="ppn-percentage-input" value="11" step="0.1" style="width: 50px; text-align: right;" oninput="calculateTotals()">%)</strong></td>
                                    <td><span id="ppn-amount-display">Rp 0</span><input type="hidden" name="ppn_amount" id="ppn-amount-input"></td>
                                </tr>
                                <tr id="row-pph">
                                    <td class="text-right"><strong>PPH (<input type="number" name="pph_percentage" id="pph-percentage-input" value="23" step="0.1" style="width: 50px; text-align: right;" oninput="calculateTotals()">%)</strong></td>
                                    <td><span id="pph-amount-display">Rp 0</span><input type="hidden" name="pph_amount" id="pph-amount-input"></td>
                                </tr>
                                <tr class="grand-total">
                                    <td class="text-right"><strong>GRAND TOTAL</strong></td>
                                    <td><span id="grand-total-display">Rp 0</span><input type="hidden" name="grand_total" id="grand-total-input"></td>
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
                        <div style="display: flex; justify-content: flex-end; width: 100%;">
                            <div style="width: 45%; text-align: left; margin-top: 30px;">
                                <p style="margin: 0 0 10px 0;">
                                    Jakarta, <input type="date" name="footer_date" value="<?= date('Y-m-d'); ?>" required style="width: auto; display: inline-block; border: none; padding: 0;">
                                </p>
                                <br><br><br>
                                <p class="signatory-name" style="margin: 0; border-bottom: 1px solid #000; display: inline-block; padding: 0 0px;">
                                    <input type="text" name="manu_signatory_name" value="Oktafian Farhan" style="border: none; text-align: left; width: auto; padding: 0;">
                                </p>
                                <p class="signatory-title" style="margin: 5px 0 0 0; font-size: 12px; color: #777;">
                                    <input type="text" name="manu_signatory_title" value="Direktur Utama" style="border: none; text-align: left; width: auto; padding: 0;">
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
// FUNGSI MODAL (tanpa auto number JS; nomor sudah dari PHP)
// =========================================================
function openModal(modalId) {
    document.getElementById(modalId).style.display = 'block';
}
function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}
window.onclick = function(event) {
    const modal = document.getElementById('invoiceModal');
    if (event.target === modal) modal.style.display = "none";
}

// =========================================================
// DATA DAN FUNGSI LOGIKA INVOICE
// =========================================================
const projectsData = <?= $projects_json ?>;
let itemCounter = 0;

function createItemRow(description = '') {
    const tableBody = document.getElementById('invoice-items');
    const newRow = document.createElement('tr');
    newRow.classList.add('item-row');
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

function updateProjectDetails() {
    const selectedProject = document.getElementById('project-select').value;
    const project = projectsData[selectedProject];
    const tableBody = document.getElementById('invoice-items');
    if (project) {
        document.getElementById('bill_to_bank').value = project.bank;
        document.getElementById('bill_to_address1').value = project.address1;
        document.getElementById('bill_to_address2').value = project.address2;
        document.getElementById('bill_to_address3').value = project.address3;
        document.getElementById('person_up_name').value = project.person_up_name;
        document.getElementById('person_up_title').value = project.person_up_title;
        tableBody.innerHTML = '';
        if (project.default_descriptions && project.default_descriptions.length > 0) {
            project.default_descriptions.forEach(desc => { createItemRow(desc); });
        } else {
            createItemRow('');
        }
    } else {
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
    itemNos.forEach((element, index) => { element.textContent = index + 1; });
    itemCounter = itemNos.length;
}

function formatRupiah(number) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency', currency: 'IDR',
        minimumFractionDigits: 0, maximumFractionDigits: 0
    }).format(number);
}

function calculateTotals() {
    let subTotal = 0;
    const itemAmounts = document.querySelectorAll('.item-amount');
    itemAmounts.forEach(input => { subTotal += parseFloat(input.value) || 0; });
    document.getElementById('sub-total-display').textContent = formatRupiah(subTotal);
    document.getElementById('sub-total-input').value = subTotal;

    const mgmtFeePercentage = parseFloat(document.getElementById('management-fee-percentage-input').value) || 0;
    const mgmtFeeAmount = subTotal * (mgmtFeePercentage / 100);
    document.getElementById('management-fee-amount-display').textContent = formatRupiah(mgmtFeeAmount);
    document.getElementById('management-fee-amount-input').value = mgmtFeeAmount;

    const ppnPercentage = parseFloat(document.getElementById('ppn-percentage-input').value) || 0;
    const ppnBase = subTotal + mgmtFeeAmount;
    const ppnAmount = ppnBase * (ppnPercentage / 100);
    document.getElementById('ppn-amount-display').textContent = formatRupiah(ppnAmount);
    document.getElementById('ppn-amount-input').value = ppnAmount;

    const pphPercentage = parseFloat(document.getElementById('pph-percentage-input').value) || 0;
    const pphAmount = subTotal * (pphPercentage / 100);
    document.getElementById('pph-amount-display').textContent = formatRupiah(pphAmount);
    document.getElementById('pph-amount-input').value = pphAmount;

    const grandTotal = subTotal + mgmtFeeAmount + ppnAmount - pphAmount;
    document.getElementById('grand-total-display').textContent = formatRupiah(grandTotal);
    document.getElementById('grand-total-input').value = grandTotal;
}

// INISIALISASI
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('project-select').addEventListener('change', updateProjectDetails);
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
