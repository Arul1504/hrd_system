<?php
// ============================
// process_add_employee.php
// ============================

session_start();
mysqli_report(MYSQLI_REPORT_OFF);

// Koneksi
require_once __DIR__ . '/../config.php';
if (!isset($conn) || !($conn instanceof mysqli)) {
    $conn = new mysqli("localhost", "root", "", "db_hrd2");
    if ($conn->connect_error) {
        die("Koneksi gagal: " . $conn->connect_error);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Simpan input sementara (biar prefill kalau error)
    $_SESSION['add_old'] = $_POST;

    $data_post    = $_POST;
    $proyek       = trim($data_post['proyek'] ?? '');
    $nik_ktp      = trim($data_post['nik_ktp'] ?? '');
    $nik_karyawan = trim($data_post['nik_karyawan'] ?? '');

    // === CEK DUPLIKAT ===
    if ($nik_ktp !== '') {
        $cek = $conn->prepare("SELECT 1 FROM karyawan WHERE nik_ktp = ?");
        $cek->bind_param("s", $nik_ktp);
        $cek->execute();
        if ($cek->get_result()->fetch_row()) {
            $cek->close();
            $_SESSION['add_error'] = "NIK KTP sudah digunakan. Tidak bisa ditambahkan lagi.";
            header("Location: ./all_employees.php?open_add=1");
            exit();
        }
        $cek->close();
    }

    if ($nik_karyawan !== '') {
        $cek = $conn->prepare("SELECT 1 FROM karyawan WHERE nik_karyawan = ?");
        $cek->bind_param("s", $nik_karyawan);
        $cek->execute();
        if ($cek->get_result()->fetch_row()) {
            $cek->close();
            $_SESSION['add_error'] = "NIK Karyawan sudah digunakan. Tidak bisa ditambahkan lagi.";
            header("Location: ./all_employees.php?open_add=1");
            exit();
        }
        $cek->close();
    }

    // === MAPPING INPUT KE KOLOM DATABASE ===
    $input_to_db_map = [
        'nama_karyawan' => 'nama_karyawan',
        'jabatan'       => 'jabatan',
        'jenis_kelamin' => 'jenis_kelamin',
        'tempat_lahir'  => 'tempat_lahir',
        'tanggal_lahir' => 'tanggal_lahir',
        'alamat'        => 'alamat',
        'alamat_tinggal'=> 'alamat_tinggal',
        'rt_rw'         => 'rt_rw',
        'kelurahan'     => 'kelurahan',
        'kecamatan'     => 'kecamatan',
        'kota_kabupaten'=> 'kota_kabupaten',
        'nik_ktp'       => 'nik_ktp',
        'nik_karyawan'  => 'nik_karyawan',
        'nip'           => 'nip',
        'pendidikan_terakhir' => 'pendidikan_terakhir',
        'no_hp'         => 'no_hp',
        'alamat_email'  => 'alamat_email',
        'no_kk'         => 'no_kk',
        'nama_ayah'     => 'nama_ayah',
        'nama_ibu'      => 'nama_ibu',
        'penempatan'    => 'penempatan',
        'cabang'        => 'cabang',
        'kota'          => 'kota',
        'area'          => 'area',
        'nomor_kontrak' => 'nomor_kontrak',
        'tanggal_pembuatan_pks' => 'tanggal_pembuatan_pks',
        'tgl_aktif_masuk' => 'tgl_aktif_masuk',
        'join_date'     => 'join_date',
        'end_date'      => 'end_date',
        'end_date_pks'  => 'end_date_pks',
        'end_of_contract' => 'end_of_contract',
        'status_karyawan' => 'status_karyawan',
        'status'        => 'status',
        'tgl_resign'    => 'tgl_resign',
        'job'           => 'job',
        'channel'       => 'channel',
        'tgl_rmu'       => 'tgl_rmu',
        'nama_sm'       => 'nama_sm',
        'nama_sh'       => 'nama_sh',
        'nama_user'     => 'nama_user',
        'tanggal_pernyataan' => 'tanggal_pernyataan',
        'nomor_surat_tugas' => 'nomor_surat_tugas',
        'masa_penugasan' => 'masa_penugasan',
        'nomor_rekening'=> 'nomor_rekening',
        'nama_bank'     => 'nama_bank',
        'gapok'         => 'gapok',
        'umk_ump'       => 'umk_ump',
        'npwp'          => 'npwp',
        'status_pajak'  => 'status_pajak',
        'recruitment_officer' => 'recruitment_officer',
        'team_leader'   => 'team_leader',
        'recruiter'     => 'recruiter',
        'tl'            => 'tl',
        'manager'       => 'manager',
        'sales_code'    => 'sales_code',
        'nomor_reff'    => 'nomor_reff',
        'no_bpjamsostek'=> 'no_bpjamsostek',
        'no_bpjs_kes'   => 'no_bpjs_kes',
        'role'          => 'role',
    ];

    $columns = [];
    $values  = [];
    $types   = '';

    foreach ($data_post as $input_name => $input_value) {
        if (in_array($input_name, ['proyek','__labels','proyek_otomatis'])) continue;
        $db_col = $input_to_db_map[$input_name] ?? $input_name;
        $columns[] = $db_col;
        $values[]  = ($input_value === '') ? null : $input_value;
        $types    .= 's';
    }

    // Proyek selalu ikut
    $columns[] = 'proyek';
    $values[]  = $proyek;
    $types    .= 's';

    if (empty($columns)) {
        $_SESSION['add_error'] = "Form kosong, tidak ada data yang disimpan.";
        header("Location: ./all_employees.php?open_add=1");
        exit();
    }

    // Query insert
    $placeholders = implode(',', array_fill(0, count($columns), '?'));
    $sql = "INSERT INTO karyawan (".implode(',', $columns).") VALUES ($placeholders)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $_SESSION['add_error'] = "Error SQL: " . $conn->error;
        header("Location: ./all_employees.php?open_add=1");
        exit();
    }

    $bind_params = [];
    $bind_params[] = $types;
    foreach ($values as $key => $val) {
        $bind_params[] = &$values[$key];
    }
    call_user_func_array([$stmt,'bind_param'], $bind_params);

    if ($stmt->execute()) {
        unset($_SESSION['add_old'], $_SESSION['add_error']);
        header("Location: ./all_employees.php?status=success");
        exit();
    } else {
        if ($stmt->errno == 1062) {
            $_SESSION['add_error'] = "NIK sudah digunakan. Tidak bisa ditambahkan lagi.";
            header("Location: ./all_employees.php?open_add=1");
            exit();
        }
        $_SESSION['add_error'] = "Terjadi kesalahan saat menyimpan data.";
        header("Location: ./all_employees.php?open_add=1");
        exit();
    }
}
