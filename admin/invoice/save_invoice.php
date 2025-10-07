<?php
// ============= save_invoice.php (FINAL: simpan semua input) =============
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['id_karyawan']) || (($_SESSION['role'] ?? '') !== 'ADMIN')) {
    header('Location: ../login.php');
    exit;
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function val($a,$k,$d=null){ return isset($a[$k]) ? $a[$k] : $d; }
function romanMonth(int $m): string { $r=["I","II","III","IV","V","VI","VII","VIII","IX","X","XI","XII"]; return $r[max(1,min(12,$m))-1]; }

function generateInvoiceNumberDb(mysqli $conn): string {
    $now = new DateTime('now'); $year=$now->format('Y'); $roman=romanMonth((int)$now->format('n'));
    $like = "%/$roman/$year";
    $stmt = $conn->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(invoice_number, '/', 1) AS UNSIGNED)) AS max_run
                            FROM invoices WHERE invoice_number LIKE ?");
    $stmt->bind_param("s",$like); $stmt->execute();
    $running = (int)($stmt->get_result()->fetch_assoc()['max_run'] ?? 0);
    $stmt->close();
    $running++;
    return str_pad((string)$running,3,"0",STR_PAD_LEFT)."/Inv/ManU/{$roman}/{$year}";
}
function getProjectIdByCode(mysqli $conn, ?string $code): ?int {
    if(!$code) return null;
    $stmt=$conn->prepare("SELECT id FROM projects WHERE project_code=? LIMIT 1");
    $stmt->bind_param("s",$code); $stmt->execute();
    $row=$stmt->get_result()->fetch_assoc(); $stmt->close();
    return $row ? (int)$row['id'] : null;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: invoice.php'); exit; }

// ====== Ambil semua input dari form ======
$status_karyawan = strtoupper(trim(val($_POST,'surat_tipe',''))); // MITRA/PKWT
$invoice_date    = val($_POST,'invoice_date');
$project_code    = val($_POST,'project_code');
$nota_debet      = val($_POST,'nota_debet','TIDAK');

$bill_to_bank    = val($_POST,'bill_to_bank');
$addr1           = val($_POST,'bill_to_address1');
$addr2           = val($_POST,'bill_to_address2');
$addr3           = val($_POST,'bill_to_address3');
$up_name         = val($_POST,'person_up_name');
$up_title        = val($_POST,'person_up_title');

$sub_total       = (float)val($_POST,'sub_total',0);
$mgmt_pct        = (float)val($_POST,'management_fee_percentage',0);
$mgmt_amt        = (float)val($_POST,'management_fee_amount',0);
$ppn_pct         = (float)val($_POST,'ppn_percentage',0);
$ppn_amt         = (float)val($_POST,'ppn_amount',0);
$pph_pct         = (float)val($_POST,'pph_percentage',0);
$pph_amt         = (float)val($_POST,'pph_amount',0);
$grand_total     = (float)val($_POST,'grand_total',0);

$tf_bank         = val($_POST,'transfer_bank');
$tf_no           = val($_POST,'transfer_account_no');
$tf_name         = val($_POST,'transfer_account_name');
$footer_date     = val($_POST,'footer_date');
$sig_name        = val($_POST,'manu_signatory_name');
$sig_title       = val($_POST,'manu_signatory_title');

$descs   = (array)val($_POST,'description',[]);
$amounts = (array)val($_POST,'amount',[]);
$adj_lbl = (array)val($_POST,'percent_label',[]);
$adj_pct = (array)val($_POST,'percent_value',[]);
$adj_amt = (array)val($_POST,'percent_amount',[]);

$created_by_id = (int)($_SESSION['id_karyawan'] ?? 0);

// ====== Simpan ke DB ======
$conn->begin_transaction();
try {
    $project_id = getProjectIdByCode($conn,$project_code);

    $sqlInv = "INSERT INTO invoices
        (invoice_number, invoice_date, project_id, project_key, employee_status,
        bill_to_bank, bill_to_address1, bill_to_address2, bill_to_address3,
        person_up_name, person_up_title, nota_debet,
        sub_total, mgmt_fee_percent, mgmt_fee_amount, ppn_percent, ppn_amount, pph_percent, pph_amount, grand_total,
        transfer_bank, transfer_account_no, transfer_account_name, footer_date,
        manu_signatory_name, manu_signatory_title, created_by_id)
        VALUES (?,?,?,?,?,
                ?,?,?,?,?,?,?,
                ?,?,?,?,?,?,?,?,
                ?,?,?,?,
                ?,?,?)";   // ← baris terakhir sekarang 3 tanda tanya (bukan 4)


    $types = "ssiss"      // 5 kolom pertama: s s i s s
            . "sssssss"   // 7 kolom berikutnya (bill_to.. s/d nota_debet): 7 s
            . "dddddddd"  // 8 angka (sub_total..grand_total): 8 d
            . "ssss"      // 4 string (transfer_bank..footer_date): 4 s
            . "ssi";      // 3 terakhir (sign_name, sign_title, created_by_id): s s i
    // Hasil gabungannya: "ssisssssssssddddddddssssssi"

    $stmtInv = $conn->prepare($sqlInv);

    $maxRetry = 5; $invoice_no=null;
    for($i=1;$i<=$maxRetry;$i++){
        $invoice_no = generateInvoiceNumberDb($conn);
        $stmtInv->bind_param($types,
            $invoice_no, $invoice_date, $project_id, $project_code, $status_karyawan,
            $bill_to_bank, $addr1, $addr2, $addr3,
            $up_name, $up_title, $nota_debet,
            $sub_total, $mgmt_pct, $mgmt_amt, $ppn_pct, $ppn_amt, $pph_pct, $pph_amt, $grand_total,
            $tf_bank, $tf_no, $tf_name, $footer_date,
            $sig_name, $sig_title, $created_by_id
        );
        try { $stmtInv->execute(); break; }
        catch (mysqli_sql_exception $e) { if((int)$e->getCode()===1062 && $i<$maxRetry) continue; throw $e; }
    }
    $invoice_id = (int)$stmtInv->insert_id; $stmtInv->close();

    // Items
    if (!empty($descs)) {
        $stmtItem=$conn->prepare("INSERT INTO invoice_items (id_invoice,item_number,description,amount) VALUES (?,?,?,?)");
        $urut=0;
        foreach($descs as $k=>$desc){
            $desc = trim((string)$desc);
            $amt  = (float)($amounts[$k] ?? 0);
            if($desc==='' && $amt==0) continue;   // lewati item kosong
            $urut++; $stmtItem->bind_param("iisd",$invoice_id,$urut,$desc,$amt); $stmtItem->execute();
        }
        $stmtItem->close();
    }

    // Penyesuaian (%)
    if (!empty($adj_lbl)) {
        $stmtAdj=$conn->prepare("INSERT INTO invoice_adjustments (id_invoice,label,percent,amount) VALUES (?,?,?,?)");
        foreach($adj_lbl as $i=>$lbl){
            $label = trim((string)$lbl);
            $pct   = (float)($adj_pct[$i] ?? 0);
            $amt   = (float)($adj_amt[$i] ?? 0);
            if($label==='' || ($pct==0 && $amt==0)) continue; // simpan yang terisi saja
            $stmtAdj->bind_param("isdd",$invoice_id,$label,$pct,$amt);
            $stmtAdj->execute();
        }
        $stmtAdj->close();
    }

    $conn->commit();
    header("Location: invoice.php?success=1");
    exit;

} catch(Throwable $e){
    $conn->rollback();
    http_response_code(500);
    echo "Gagal menyimpan invoice: ".$e->getMessage();
    exit;
}
