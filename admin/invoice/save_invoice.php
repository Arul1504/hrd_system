<?php
// ============= save_invoice.php (FIXED) =============

require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Wajib admin
if (!isset($_SESSION['id_karyawan']) || (($_SESSION['role'] ?? '') !== 'ADMIN')) {
    header('Location: ../login.php');
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Helper
function val($arr, $key, $default = null) { return isset($arr[$key]) ? $arr[$key] : $default; }

// ---- Util: ROMAWI bulan
function romanMonth(int $m): string {
    $r = ["I","II","III","IV","V","VI","VII","VIII","IX","X","XI","XII"];
    return $r[max(1, min(12, $m)) - 1];
}

// ---- Generate nomor invoice (retry bila bentrok UNIQUE)
function generateInvoiceNumberDb(mysqli $conn): string {
    $now   = new DateTime('now');
    $year  = $now->format('Y');
    $roman = romanMonth((int)$now->format('n'));

    // Ambil running max di bulan/tahun ini
    $like = "%/$roman/$year";
    $sql  = "SELECT MAX(CAST(SUBSTRING_INDEX(invoice_number, '/', 1) AS UNSIGNED)) AS max_run
             FROM invoices
             WHERE invoice_number LIKE ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $running = (int)($row['max_run'] ?? 0);
    $running++;
    return str_pad((string)$running, 3, "0", STR_PAD_LEFT) . "/Inv/ManU/{$roman}/{$year}";
}

// ---- Lookup project_id dari project_code (boleh NULL kalau tidak ada)
function getProjectIdByCode(mysqli $conn, ?string $code): ?int {
    if (!$code) return null;
    $stmt = $conn->prepare("SELECT id FROM projects WHERE project_code = ? LIMIT 1");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['id'] : null;
}

// Pastikan POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: invoice.php');
    exit;
}

// Ambil data POST
$invoice_date  = val($_POST, 'invoice_date');
$project_code  = val($_POST, 'project_code');
$bill_to_bank  = val($_POST, 'bill_to_bank');
$addr1         = val($_POST, 'bill_to_address1');
$addr2         = val($_POST, 'bill_to_address2');
$addr3         = val($_POST, 'bill_to_address3');
$up_name       = val($_POST, 'person_up_name');
$up_title      = val($_POST, 'person_up_title');

$sub_total     = (float)val($_POST, 'sub_total', 0);
$mgmt_pct      = (float)val($_POST, 'management_fee_percentage', 0);
$mgmt_amt      = (float)val($_POST, 'management_fee_amount', 0);
$ppn_pct       = (float)val($_POST, 'ppn_percentage', 0);
$ppn_amt       = (float)val($_POST, 'ppn_amount', 0);

// pph tidak disimpan di DB, hanya mempengaruhi grand_total yang sudah dikirim
$grand_total   = (float)val($_POST, 'grand_total', 0);

$tf_bank       = val($_POST, 'transfer_bank');
$tf_no         = val($_POST, 'transfer_account_no');
$tf_name       = val($_POST, 'transfer_account_name');
$footer_date   = val($_POST, 'footer_date');
$sig_name      = val($_POST, 'manu_signatory_name');
$sig_title     = val($_POST, 'manu_signatory_title');

$descs         = isset($_POST['description']) && is_array($_POST['description']) ? $_POST['description'] : [];
$amounts       = isset($_POST['amount']) && is_array($_POST['amount']) ? $_POST['amount'] : [];

$created_by_id = (int)$_SESSION['id_karyawan'];

// Mulai transaksi
$conn->begin_transaction();

try {
    // Cari project_id
    $project_id = getProjectIdByCode($conn, $project_code);

    // Siapkan statement insert invoice
    $sqlInv = "INSERT INTO invoices
        (invoice_number, invoice_date, project_id, project_key, bill_to_bank, bill_to_address1, bill_to_address2, bill_to_address3,
         person_up_name, person_up_title, sub_total, mgmt_fee_percent, mgmt_fee_amount, ppn_percent, ppn_amount, grand_total,
         transfer_bank, transfer_account_no, transfer_account_name, footer_date, manu_signatory_name, manu_signatory_title, created_by_id)
        VALUES (?,?,?,?,?,?,?,?,?, ?,?,?,?,?,?, ?,?,?,?,?,?, ?,?)";
    $stmtInv = $conn->prepare($sqlInv);

    // Tipe bind:
    // 1-10 : ssisssssss
    // 11-16: dddddd
    // 17-22: ssssss
    // 23   : i
    $types = "ssisssssssddddddssssssi";

    // Kita akan retry kalau bentrok UNIQUE (1062)
    $maxRetry = 5;
    $invoice_no = null;
    for ($attempt = 1; $attempt <= $maxRetry; $attempt++) {
        $invoice_no = generateInvoiceNumberDb($conn);
        $stmtInv->bind_param(
            $types,
            $invoice_no,            // 1 s
            $invoice_date,          // 2 s
            $project_id,            // 3 i
            $project_code,          // 4 s
            $bill_to_bank,          // 5 s
            $addr1,                 // 6 s
            $addr2,                 // 7 s
            $addr3,                 // 8 s
            $up_name,               // 9 s
            $up_title,              //10 s
            $sub_total,             //11 d
            $mgmt_pct,              //12 d
            $mgmt_amt,              //13 d
            $ppn_pct,               //14 d
            $ppn_amt,               //15 d
            $grand_total,           //16 d
            $tf_bank,               //17 s
            $tf_no,                 //18 s
            $tf_name,               //19 s
            $footer_date,           //20 s
            $sig_name,              //21 s
            $sig_title,             //22 s
            $created_by_id          //23 i
        );

        try {
            $stmtInv->execute();
            break; // sukses
        } catch (mysqli_sql_exception $e) {
            // 1062 = duplicate key (kemungkinan invoice_number sama)
            if ((int)$e->getCode() === 1062 && $attempt < $maxRetry) {
                // retry dengan nomor baru
                continue;
            }
            throw $e; // lempar lagi kalau bukan 1062 atau sudah mentok
        }
    }

    $invoice_id = (int)$stmtInv->insert_id;
    $stmtInv->close();

    // ==== INSERT ITEMS ====
    // Penting: JANGAN isi id_item (PK AUTO_INCREMENT). Isi ke kolom item_number!
    // Struktur tabel & PK AUTO_INCREMENT terlihat di dump: PK di id_item, item_number hanyalah nomor urut. 
    // (lihat file dump: definisi tabel + index + auto increment)
    //  - CREATE TABLE `invoice_items` (...) id_item PK, item_number untuk urutan. :contentReference[oaicite:4]{index=4}
    //  - PK & index: id_item primary. :contentReference[oaicite:5]{index=5}
    //  - AUTO_INCREMENT untuk id_item. :contentReference[oaicite:6]{index=6}

    if (!empty($descs)) {
        $sqlItem = "INSERT INTO invoice_items (id_invoice, item_number, description, amount) VALUES (?, ?, ?, ?)";
        $stmtItem = $conn->prepare($sqlItem);

        foreach ($descs as $i => $desc) {
            $num = $i + 1; // 1-based
            $amt = (float)($amounts[$i] ?? 0);
            $stmtItem->bind_param("iisd", $invoice_id, $num, $desc, $amt);
            $stmtItem->execute();
        }
        $stmtItem->close();
    }

    $conn->commit();

    // redirect sukses
    header("Location: invoice.php?success=1");
    exit;

} catch (Throwable $e) {
    $conn->rollback();
    // untuk debug dev: echo pesan. Di produksi sebaiknya log saja.
    http_response_code(500);
    echo "Gagal menyimpan invoice: " . $e->getMessage();
    exit;
}
