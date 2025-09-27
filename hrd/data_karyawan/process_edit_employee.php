<?php
// ============================================================
// process_edit_employee.php — Versi panjang + per-project + full compare
// ============================================================

declare(strict_types=1);
mysqli_report(MYSQLI_REPORT_OFF);

// ------------------------------------------------------------
// Koneksi (pakai config utama; fallback jika perlu)
// ------------------------------------------------------------
require_once __DIR__ . '/../config.php';
if (!isset($conn) || !($conn instanceof mysqli)) {
    // SESUAIKAN jika DB kamu bukan 'db_hrd2'
    $conn = new mysqli('localhost', 'root', '', 'db_hrd2');
    if ($conn->connect_error) {
        die('Koneksi gagal: ' . $conn->connect_error);
    }
}

// ------------------------------------------------------------
// Util
// ------------------------------------------------------------
function back_to_edit(int $id, string $status): void {
    header('Location: edit_employee.php?id=' . $id . '&status=' . urlencode($status));
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
$stmt_old = $conn->prepare("SELECT * FROM karyawan WHERE id_karyawan = ?");
$stmt_old->bind_param("i", $id_karyawan);
$stmt_old->execute();
$res_old = $stmt_old->get_result();
$old = $res_old->fetch_assoc() ?: [];
$stmt_old->close();

if (!$old) back_to_edit($id_karyawan, 'error');

// ------------------------------------------------------------
// Mapping field per-project (sesuai logika awal)
// -> digunakan untuk memfilter field yang diizinkan di-update
// ------------------------------------------------------------
$PROJECT_FIELDS = [
    "CIMB" => [
        "nama_karyawan","cabang","nomor_kontrak","tanggal_pembuatan_pks","tempat_lahir","tanggal_lahir","alamat","rt_rw",
        "kelurahan","kecamatan","kota_kabupaten","nik_ktp","pendidikan_terakhir","no_hp","jabatan","alamat_email",
        "nama_sm","nama_sh","job","channel","tgl_rmu","jenis_kelamin","no_kk","nama_ayah","nama_ibu","team_leader",
        "recruiter","sales_code","join_date","status_karyawan","nomor_rekening","nama_bank","status","tgl_resign"
    ],
    "NOBU" => [
        "nama_karyawan","cabang","nomor_kontrak","tanggal_pembuatan_pks","tempat_lahir","tanggal_lahir","alamat","rt_rw",
        "kelurahan","kecamatan","kota_kabupaten","nik_ktp","pendidikan_terakhir","no_hp","jabatan","tgl_aktif_masuk",
        "alamat_email","jenis_kelamin","no_kk","nama_ayah","nama_ibu","team_leader","recruiter","sales_code","join_date",
        "status_karyawan","nomor_rekening","nama_bank","status","tgl_resign"
    ],
    "MOLADIN" => [
        "nama_karyawan","jabatan","cabang","nomor_kontrak","tanggal_pembuatan_pks","tempat_lahir","tanggal_lahir","alamat","rt_rw",
        "kelurahan","kecamatan","kota_kabupaten","nik_ktp","pendidikan_terakhir","no_hp","alamat_email","jenis_kelamin",
        "no_kk","nama_ayah","nama_ibu","team_leader","recruiter","sales_code","join_date","status_karyawan",
        "nomor_rekening","nama_bank","status","tgl_resign"
    ],
    "ALLO" => [
        "nama_karyawan","jabatan","penempatan","kota","area","nomor_kontrak","tanggal_pembuatan_pks","tempat_lahir","tanggal_lahir",
        "alamat","rt_rw","kelurahan","kecamatan","kota_kabupaten","nik_ktp","pendidikan_terakhir","no_hp","alamat_email",
        "jenis_kelamin","no_kk","nama_ayah","nama_ibu","recruitment_officer","team_leader","join_date","status_karyawan",
        "nomor_rekening","nama_bank","status","tgl_resign"
    ],
    "CNAF" => [
        "nama_karyawan","cabang","nomor_kontrak","tanggal_pembuatan_pks","tempat_lahir","tanggal_lahir","alamat","rt_rw",
        "kelurahan","kecamatan","kota_kabupaten","nik_ktp","pendidikan_terakhir","no_hp","jabatan","join_date","end_date",
        "end_date_pks","umk_ump","jenis_kelamin","alamat_email","alamat_tinggal","npwp","status_pajak","no_kk","nama_ayah",
        "nama_ibu","recruiter","team_leader","nik_karyawan","status","nomor_rekening","nama_bank","no_bpjamsostek",
        "no_bpjs_kes","end_of_contract"
    ],
    "BNIF" => [
        "nama_karyawan","cabang","nomor_kontrak","tanggal_pembuatan_pks","tempat_lahir","tanggal_lahir","alamat","rt_rw",
        "kelurahan","kecamatan","kota_kabupaten","nik_ktp","pendidikan_terakhir","no_hp","jabatan","join_date","end_date",
        "end_date_pks","umk_ump","jenis_kelamin","alamat_email","alamat_tinggal","nip","npwp","status_pajak","no_kk",
        "nama_ayah","nama_ibu","recruiter","team_leader","nik_karyawan","status","nomor_rekening","nama_bank",
        "no_bpjamsostek","no_bpjs_kes","end_of_contract"
    ],
    "SMBCI" => [
        "nomor_kontrak","tanggal_pembuatan_pks","tanggal_lahir","nama_karyawan","tempat_lahir","jabatan","nik_ktp","alamat","rt_rw",
        "kelurahan","kecamatan","kota","no_hp","pendidikan_terakhir","nama_user","penempatan","join_date","end_date","umk_ump",
        "tanggal_pernyataan","nik_karyawan","nomor_surat_tugas","masa_penugasan","alamat_email","nomor_reff","jenis_kelamin",
        "npwp","status_pajak","no_kk","nama_ayah","nama_ibu","recruitment_officer","team_leader","status","nomor_rekening",
        "nama_bank","no_bpjamsostek","no_bpjs_kes","end_of_contract"
    ],
    "INTERNAL" => [
        "nama_karyawan","cabang","nomor_kontrak","tanggal_pembuatan_pks","tempat_lahir","tanggal_lahir","alamat","rt_rw",
        "kelurahan","kecamatan","kota_kabupaten","nik_ktp","pendidikan_terakhir","no_hp","jabatan","join_date","end_date",
        "gapok","jenis_kelamin","alamat_email","alamat_tinggal","tl","manager","npwp","status_pajak","no_kk","nama_ayah",
        "nama_ibu","status","nomor_rekening","nama_bank","end_of_contract","role"
    ]
];

// ------------------------------------------------------------
// Tentukan proyek saat ini (dari POST kalau ada; jika tidak, pakai data lama)
// ------------------------------------------------------------
$proyek_now = trim((string)($_POST['proyek'] ?? $old['proyek'] ?? ''));
$proyek_now_upper = strtoupper($proyek_now);

// Daftar kolom global (supaya struktur tetap panjang & eksplisit)
$columns = [
    'nik_ktp','nik_karyawan',
    'nama_karyawan','jenis_kelamin','tempat_lahir','tanggal_lahir','alamat','alamat_tinggal','rt_rw',
    'kelurahan','kecamatan','kota_kabupaten','no_hp','pendidikan_terakhir','status_pernikahan','agama',
    'kenalan_serumah_nama','kenalan_serumah_no_hp','kenalan_serumah_hubungan','kenalan_serumah_alamat',
    'tidak_serumah_nama','tidak_serumah_no_hp','tidak_serumah_hubungan','tidak_serumah_alamat',
    'tanggal_pembuatan_pks','tgl_aktif_masuk','join_date','end_date','end_date_pks','end_of_contract',
    'departemen','jabatan','jenis_skema','status_kerja','status_pegawai','lokasi_kerja',
    'status_karyawan','status','cabang','job','channel','area','penempatan','kota',
    'npwp','no_bpjs_kes','no_bpjamsostek','nomor_rekening','nama_bank','alamat_email',
    'recruitment_officer','team_leader','recruiter','tl','manager','sales_code','nomor_reff',
    'gapok','umk_ump','role','nama_sm','nama_sh','tgl_rmu','tanggal_pernyataan',
    'nomor_surat_tugas','masa_penugasan','nama_user','nip','nomor_kontrak'
];

// ------------------------------------------------------------
// Validasi & cek duplikat NIK (hanya bila user mengubah)
// ------------------------------------------------------------
$nik_ktp_post      = isset($_POST['nik_ktp'])      ? trim((string)$_POST['nik_ktp'])      : null;
$nik_karyawan_post = isset($_POST['nik_karyawan']) ? trim((string)$_POST['nik_karyawan']) : null;

// Format NIK KTP jika diisi
if ($nik_ktp_post !== null && $nik_ktp_post !== '') {
    if (!preg_match('/^\d{16}$/', $nik_ktp_post)) {
        back_to_edit($id_karyawan, 'error'); // format salah
    }
}

// Cek duplikat jika berubah
if ($nik_ktp_post !== null && $nik_ktp_post !== '' && $nik_ktp_post !== (string)($old['nik_ktp'] ?? '')) {
    $cek = $conn->prepare("SELECT 1 FROM karyawan WHERE nik_ktp = ? AND id_karyawan <> ?");
    $cek->bind_param("si", $nik_ktp_post, $id_karyawan);
    $cek->execute();
    if ($cek->get_result()->fetch_row()) { $cek->close(); back_to_edit($id_karyawan, 'nik_duplicate'); }
    $cek->close();
}
if ($nik_karyawan_post !== null && $nik_karyawan_post !== '' && $nik_karyawan_post !== (string)($old['nik_karyawan'] ?? '')) {
    $cek = $conn->prepare("SELECT 1 FROM karyawan WHERE nik_karyawan = ? AND id_karyawan <> ?");
    $cek->bind_param("si", $nik_karyawan_post, $id_karyawan);
    $cek->execute();
    if ($cek->get_result()->fetch_row()) { $cek->close(); back_to_edit($id_karyawan, 'nik_duplicate'); }
    $cek->close();
}

// ------------------------------------------------------------
// Bangun SET clause berdasarkan project (whitelist field by project)
// ------------------------------------------------------------
$allowed = $columns; // default: semua (kalau project tidak terdaftar)
if ($proyek_now_upper && isset($PROJECT_FIELDS[$proyek_now_upper])) {
    // tambahkan kolom identitas yang mungkin tidak disebut di project fields
    $allowed = array_values(array_unique(array_merge(
        $PROJECT_FIELDS[$proyek_now_upper],
        ['nik_ktp','nik_karyawan','status','role','nomor_kontrak','join_date','end_date','end_of_contract','end_date_pks']
    )));
}

$set_clauses = [];
$params = [];
$types  = '';
$newData = [];

// Normalisasi tanggal untuk field tanggal agar konsisten compare
$dateFields = ['tanggal_lahir','tgl_aktif_masuk','join_date','end_date','end_date_pks','end_of_contract','tanggal_pembuatan_pks','tgl_rmu','tanggal_pernyataan'];

foreach ($columns as $col) {
    if (!in_array($col, $allowed, true)) continue;          // tidak diizinkan oleh project
    if (!array_key_exists($col, $_POST)) continue;          // tidak ada di POST
    $raw = $_POST[$col];
    $val = ($raw === '') ? null : $raw;

    if (in_array($col, $dateFields, true)) {
        $val = norm_date($val); // YYYY-mm-dd atau null
    }

    // Simpan untuk compare
    $newData[$col] = $val;

    // Tambahkan ke SQL
    $set_clauses[] = "$col = ?";
    $params[] = $val;
    $types   .= 's';
}

// ------------------------------------------------------------
// Upload foto (opsional, tetap ada seperti versi panjang)
// ------------------------------------------------------------
if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
    $target_dir = __DIR__ . "/../uploads/photos/";
    if (!is_dir($target_dir)) { @mkdir($target_dir, 0777, true); }
    $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
    $newname = 'photo_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    $target_file_fs  = $target_dir . $newname;
    $target_file_rel = "../uploads/photos/" . $newname; // path relatif untuk web

    if (move_uploaded_file($_FILES['foto']['tmp_name'], $target_file_fs)) {
        // Hapus foto lama jika ada
        $stmt_p = $conn->prepare("SELECT foto_path FROM karyawan WHERE id_karyawan = ?");
        $stmt_p->bind_param("i", $id_karyawan);
        $stmt_p->execute();
        if ($r = $stmt_p->get_result()->fetch_assoc()) {
            $oldPath = $r['foto_path'];
            $oldFs   = (strpos((string)$oldPath, '../') === 0) ? __DIR__ . '/' . substr((string)$oldPath, 3) : (string)$oldPath;
            if (!empty($oldPath) && @file_exists($oldFs)) { @unlink($oldFs); }
        }
        $stmt_p->close();

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

// Jika tidak ada perubahan input sama sekali
if (empty($set_clauses)) {
    back_to_edit($id_karyawan, 'nochange');
}

// ------------------------------------------------------------
// Auto non-aktif bila kontrak sudah lewat (sesuai logika awal)
// & Redirect bila hasil akhir = TIDAK AKTIF
// ------------------------------------------------------------
$today = (new DateTime('today'))->format('Y-m-d');

$end_candidates = [
    $_POST['end_date']        ?? null,
    $_POST['end_date_pks']    ?? null,
    $_POST['end_of_contract'] ?? null,
];

$expired = false;
foreach ($end_candidates as $cand) {
    $n = norm_date($cand);
    if ($n !== null && $n < $today) { $expired = true; break; }
}

$will_redirect_to_nonaktif = false;

if ($expired) {
    $set_clauses[] = "status = ?";
    $params[]      = 'TIDAK AKTIF';
    $types        .= 's';
    $newData['status'] = 'TIDAK AKTIF';
    $will_redirect_to_nonaktif = true;
} else {
    // Jika user memang mengubah status jadi TIDAK AKTIF
    if (array_key_exists('status', $_POST)) {
        $st = strtoupper(trim((string)$_POST['status']));
        if ($st === 'TIDAK AKTIF') {
            $will_redirect_to_nonaktif = true;
            // Pastikan ikut terset di SQL bila belum ditimpa sebelumnya
            // (kalau user pilih TIDAK AKTIF tapi tidak ada di $set_clauses karena filtering project)
            $already = false;
            foreach ($set_clauses as $c) { if (strpos($c, 'status = ?') !== false) { $already = true; break; } }
            if (!$already) {
                $set_clauses[] = "status = ?";
                $params[]      = 'TIDAK AKTIF';
                $types        .= 's';
                $newData['status'] = 'TIDAK AKTIF';
            }
        }
    }
}

// ------------------------------------------------------------
// Eksekusi UPDATE
// ------------------------------------------------------------
$set_clause_string = implode(', ', $set_clauses);
$sql = "UPDATE karyawan SET $set_clause_string WHERE id_karyawan = ?";

$params[] = $id_karyawan;
$types   .= 'i';

$stmt = $conn->prepare($sql);
if (!$stmt) {
    back_to_edit($id_karyawan, 'error');
}
call_user_func_array([$stmt, 'bind_param'], ref_values(array_merge([$types], $params)));
$stmt->execute();

if ($stmt->errno === 1062) {
    $stmt->close(); $conn->close();
    back_to_edit($id_karyawan, 'nik_duplicate');
} elseif ($stmt->errno) {
    $stmt->close(); $conn->close();
    back_to_edit($id_karyawan, 'error');
}

// ------------------------------------------------------------
// FULL COMPARE (old vs new) — bukan cuma cek affected_rows
// ------------------------------------------------------------
$changed = false;
foreach ($newData as $k => $v) {
    $ov = $old[$k] ?? null;

    // Normalisasi tanggal untuk membandingkan
    if (in_array($k, $dateFields, true)) {
        $ov = norm_date($ov);
        $v  = norm_date($v);
    }

    if ($v != $ov) { $changed = true; break; }
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
