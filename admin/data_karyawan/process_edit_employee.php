<?php
// ============================================================
// process_edit_employee.php — FINAL: MENDUKUNG SEMUA FIELD (OPSIONAL)
// ============================================================

declare(strict_types=1);
mysqli_report(MYSQLI_REPORT_OFF);

// ------------------------------------------------------------
// Koneksi (pakai config utama; fallback jika perlu)
// ------------------------------------------------------------
require_once __DIR__ . '/../config.php';
if (!isset($conn) || !($conn instanceof mysqli)) {
    // SESUAIKAN jika DB kamu bukan 'hrd2'
    $conn = new mysqli('localhost', 'root', '', 'db_hrd2');
    if ($conn->connect_error) {
        die('Koneksi gagal: ' . $conn->connect_error);
    }
}

// ------------------------------------------------------------
// Util
// ------------------------------------------------------------
function back_to_edit(int $id, string $status): void {
    // Mengarahkan kembali ke all_employees.php karena form edit sekarang dibuka di modal
    header('Location: all_employees.php?status=' . urlencode($status)); 
    exit;
}

function ref_values(array $arr) {
    $refs = [];
    foreach ($arr as $k => $v) { $refs[$k] = &$arr[$k]; }
    return $refs;
}

function norm_date(?string $v): ?string {
    if ($v === null || $v === '') return null;
    $d = date_create($v);
    return $d ? $d->format('Y-m-d') : null;
}

// ------------------------------------------------------------
// Guard
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: all_employees.php');
    exit;
}

$id_karyawan = (int)($_POST['id_karyawan'] ?? 0);
if ($id_karyawan <= 0) {
    back_to_edit(0, 'error');
}

// ------------------------------------------------------------
// Ambil data lama lengkap (untuk full compare & fallback proyek)
// ------------------------------------------------------------
$old = [];
// Ambil semua kolom untuk compare
$stmt_old = $conn->prepare("SELECT * FROM karyawan WHERE id_karyawan = ?");
$stmt_old->bind_param("i", $id_karyawan);
$stmt_old->execute();
$res_old = $stmt_old->get_result();
$old = $res_old->fetch_assoc() ?: [];
$stmt_old->close();

if (!$old) back_to_edit($id_karyawan, 'error');

// ------------------------------------------------------------
// DAFTAR LENGKAP SEMUA KOLOM (Termasuk kolom baru Anda)
// ------------------------------------------------------------
$columns = [
    'nik_ktp','nik_karyawan',
    'nama_karyawan','jenis_kelamin','tempat_lahir','tanggal_lahir','alamat','alamat_tinggal','rt_rw',
    'kelurahan','kecamatan','kota_kabupaten','no_hp','pendidikan_terakhir','status_pernikahan','agama',
    'no_kk','nama_ayah','nama_ibu','nip','penempatan','nama_user','kota','area',
    
    // Kontrak & Status
    'nomor_kontrak','tanggal_pembuatan_pks','nomor_surat_tugas','masa_penugasan','tgl_aktif_masuk','join_date','end_date',
    'end_date_pks','end_of_contract','status_karyawan','status','tgl_resign','cabang','job','channel','tgl_rmu',
    
    // Keuangan & Admin
    'nomor_rekening','nama_bank','gapok','umk_ump','tanggal_pernyataan','npwp','status_pajak',
    'no_bpjamsostek','no_bpjs_kes','allowance','tunjangan_kesehatan',

    // Struktur & Atasan
    'recruitment_officer','team_leader','recruiter','tl','manager','nama_sm','nama_sh','sales_code','nomor_reff',
    'role','proyek','sub_project_cnaf','no','tanggal_pembuatan','spr_bro','nama_bm_sm','nama_tl','level',
    'tanggal_pkm','nama_sm_cm','sbi','tanggal_sign_kontrak','nama_oh','jabatan_sebelumnya','nama_bm','om','nama_cm'
];

// Field tanggal untuk normalisasi/compare
$dateFields = [
    'tanggal_lahir','tgl_aktif_masuk','join_date','end_date','end_date_pks','end_of_contract',
    'tanggal_pembuatan_pks','tgl_rmu','tanggal_pernyataan','tanggal_pembuatan','tanggal_pkm','tanggal_sign_kontrak','tgl_resign'
];

// ------------------------------------------------------------
// Validasi & cek duplikat NIK (hanya bila user mengubah)
// ------------------------------------------------------------
$nik_ktp_post    = trim((string)($_POST['nik_ktp'] ?? ''));
$nik_karyawan_post = trim((string)($_POST['nik_karyawan'] ?? ''));

// Cek duplikat NIK KTP jika berubah
if ($nik_ktp_post !== '' && $nik_ktp_post !== (string)($old['nik_ktp'] ?? '')) {
    if (!preg_match('/^\d{16}$/', $nik_ktp_post)) { back_to_edit($id_karyawan, 'error'); } // Format salah
    $cek = $conn->prepare("SELECT 1 FROM karyawan WHERE nik_ktp = ? AND id_karyawan <> ?");
    $cek->bind_param("si", $nik_ktp_post, $id_karyawan);
    $cek->execute();
    if ($cek->get_result()->fetch_row()) { $cek->close(); back_to_edit($id_karyawan, 'nik_duplicate'); }
    $cek->close();
}
// Cek duplikat NIK Karyawan jika berubah
if ($nik_karyawan_post !== '' && $nik_karyawan_post !== (string)($old['nik_karyawan'] ?? '')) {
    $cek = $conn->prepare("SELECT 1 FROM karyawan WHERE nik_karyawan = ? AND id_karyawan <> ?");
    $cek->bind_param("si", $nik_karyawan_post, $id_karyawan);
    $cek->execute();
    if ($cek->get_result()->fetch_row()) { $cek->close(); back_to_edit($id_karyawan, 'nik_duplicate'); }
    $cek->close();
}

// ------------------------------------------------------------
// Bangun SET clause (Menerima SEMUA field dari POST)
// ------------------------------------------------------------
$set_clauses = [];
$params = [];
$types  = '';
$newData = []; // Untuk menyimpan nilai POST yang sudah dinormalisasi

foreach ($columns as $col) {
    if (!array_key_exists($col, $_POST)) {
        // Jika kolom tidak ada di POST, biarkan nilai lama (skip)
        continue;
    }
    
    // Ambil nilai, ganti string kosong menjadi NULL untuk DB
    $raw = $_POST[$col];
    $val = ($raw === '') ? null : $raw;

    // Normalisasi tanggal
    if (in_array($col, $dateFields, true)) {
        $val = norm_date($val); // YYYY-mm-dd atau null
    }
    
    // Simpan untuk compare dan tambahkan ke SQL
    $newData[$col] = $val;
    $set_clauses[] = "$col = ?";
    $params[] = $val;
    $types   .= 's';
}


// ------------------------------------------------------------
// Upload foto (opsional)
// ------------------------------------------------------------
if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
    $target_dir = __DIR__ . "/../uploads/photos/";
    if (!is_dir($target_dir)) { @mkdir($target_dir, 0777, true); }
    $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
    $newname = 'photo_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    $target_file_fs  = $target_dir . $newname;
    $target_file_rel = "../uploads/photos/" . $newname; 

    if (move_uploaded_file($_FILES['foto']['tmp_name'], $target_file_fs)) {
        // Hapus foto lama jika ada
        $oldPath = $old['foto_path'] ?? null;
        if (!empty($oldPath)) {
             $oldFs = (strpos((string)$oldPath, '../') === 0) ? __DIR__ . '/' . substr((string)$oldPath, 3) : (string)$oldPath;
             if (@file_exists($oldFs)) { @unlink($oldFs); }
        }

        $set_clauses[] = "foto_path = ?";
        $params[]      = $target_file_rel;
        $types        .= 's';
        $newData['foto_path'] = $target_file_rel;
    }
}

// ------------------------------------------------------------
// Password (opsional)
// ------------------------------------------------------------
if (!empty($_POST['password'])) {
    $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $set_clauses[] = "password_hash = ?";
    $params[]      = $password_hash;
    $types        .= 's';
    $newData['password_hash'] = $password_hash;
}

// Jika tidak ada perubahan input yang valid sama sekali
if (empty($set_clauses)) {
    back_to_edit($id_karyawan, 'nochange');
}

// ------------------------------------------------------------
// Auto non-aktif bila kontrak sudah lewat
// ------------------------------------------------------------
$today = (new DateTime('today'))->format('Y-m-d');
$end_date_check = $newData['end_date'] ?? $old['end_date'] ?? null;
$status_check = $newData['status'] ?? $old['status'] ?? null;
$status_karyawan_check = $newData['status_karyawan'] ?? $old['status_karyawan'] ?? null;

$will_redirect_to_nonaktif = false;

// Logika: Jika PKWT DAN end_date sudah lewat HARI INI
if (strtoupper((string)$status_karyawan_check) === 'PKWT' && $end_date_check !== null && $end_date_check < $today) {
    // Force status menjadi TIDAK AKTIF
    $status_to_set = 'TIDAK AKTIF';
    
    // Tambahkan/Update status di clause SQL
    $found = false;
    foreach ($set_clauses as $i => $c) {
        if (strpos($c, 'status = ?') !== false) {
            // Update parameter yang sudah ada
            $params[array_search($old['status'], $params, true)] = $status_to_set;
            $found = true;
            break;
        }
    }
    if (!$found) {
        $set_clauses[] = "status = ?";
        $params[]      = $status_to_set;
        $types        .= 's';
    }
    $newData['status'] = $status_to_set;
    $will_redirect_to_nonaktif = true;

} elseif (strtoupper((string)$status_check) === 'TIDAK AKTIF') {
    // Jika user secara manual set TIDAK AKTIF
    $will_redirect_to_nonaktif = true;
}


// ------------------------------------------------------------
// Eksekusi UPDATE
// ------------------------------------------------------------
$set_clause_string = implode(', ', $set_clauses);
$sql = "UPDATE karyawan SET $set_clause_string WHERE id_karyawan = ?";

$params[] = $id_karyawan;
$types  .= 'i';

$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("SQL Prepare Error: " . $conn->error . " for query: " . $sql);
    back_to_edit($id_karyawan, 'error');
}

// Bind parameters
call_user_func_array([$stmt, 'bind_param'], ref_values(array_merge([$types], $params)));

$stmt->execute();

if ($stmt->errno === 1062) {
    $stmt->close(); $conn->close();
    back_to_edit($id_karyawan, 'nik_duplicate');
} elseif ($stmt->errno) {
    error_log("SQL Execute Error: " . $stmt->error);
    $stmt->close(); $conn->close();
    back_to_edit($id_karyawan, 'error');
}

// ------------------------------------------------------------
// FULL COMPARE (old vs new)
// ------------------------------------------------------------
$changed = false;
foreach ($newData as $k => $v) {
    $ov = $old[$k] ?? null;

    // Normalisasi tanggal untuk membandingkan
    if (in_array($k, $dateFields, true)) {
        $ov = norm_date($ov);
        $v  = norm_date($v);
    }
    
    // Periksa perubahan
    if ($v !== $ov) { 
        $changed = true; 
        break; 
    }
}

$stmt->close();
$conn->close();

// ------------------------------------------------------------
// Redirect sesuai status akhir
// ------------------------------------------------------------
if ($will_redirect_to_nonaktif) {
    header('Location: karyawan_nonaktif.php?status=' . ($changed ? 'updated' : 'nochange'));
    exit;
}
header('Location: all_employees.php?status=' . ($changed ? 'updated' : 'nochange'));
exit;