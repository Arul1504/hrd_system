<?php
// ===================================
// process_absensi_admin.php
// Menangani absen manual dan edit status absensi oleh admin
// ===================================

session_start();
require '../config.php';
// Fungsi helper untuk set timezone berdasarkan provinsi
function set_timezone_from_province(string $province): string {
    $p = mb_strtolower(trim($province));

    $wib = [
        'aceh','sumatera utara','sumatera barat','riau','kepulauan riau',
        'jambi','bengkulu','sumatera selatan','kepulauan bangka belitung','lampung',
        'banten','dki jakarta','jakarta','jawa barat','jawa tengah','di yogyakarta','jawa timur',
        'kalimantan barat','kalimantan tengah'
    ];

    $wita = [
        'bali','nusa tenggara barat','nusa tenggara timur',
        'kalimantan selatan','kalimantan timur','kalimantan utara',
        'sulawesi utara','sulawesi tengah','sulawesi selatan','sulawesi tenggara','gorontalo'
    ];

    $wit = [
        'maluku','maluku utara',
        'papua','papua barat','papua selatan','papua tengah','papua pegunungan','papua barat daya'
    ];

    if (in_array($p, $wib, true)) {
        date_default_timezone_set('Asia/Jakarta'); // WIB
        return 'Asia/Jakarta';
    }
    if (in_array($p, $wita, true)) {
        date_default_timezone_set('Asia/Makassar'); // WITA
        return 'Asia/Makassar';
    }
    if (in_array($p, $wit, true)) {
        date_default_timezone_set('Asia/Jayapura'); // WIT
        return 'Asia/Jayapura';
    }

    // default fallback WIB
    date_default_timezone_set('Asia/Jakarta');
    return 'Asia/Jakarta';
}

// --- Periksa Hak Akses ADMIN ---
if (!isset($_SESSION['id_karyawan']) || ($_SESSION['role'] ?? '') !== 'ADMIN') {
    header("Location: ../../index.php");
    exit();
}

// Periksa apakah ada data POST yang dikirim
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    header("Location: absensi.php?status=error_invalid_request");
    exit();
}

$action = $_POST['action'];
$id_karyawan = (int)($_POST['id_karyawan'] ?? 0);
$tanggal = $_POST['tanggal'] ?? date('Y-m-d');
$client_time = $_POST['client_time'] ?? date('Y-m-d H:i:s');

if ($id_karyawan <= 0) {
    header("Location: absensi.php?status=error_id_karyawan");
    exit();
}
$province = $_POST['provinsi'] ?? '';  // hasil dari JS hidden input
$tz = set_timezone_from_province($province);

// Gunakan waktu lokal sesuai provinsi
$now = new DateTime('now', new DateTimeZone($tz));
$formatted_now = $now->format('Y-m-d H:i:s');


// === LOGIKA UNTUK ABSENSI MANUAL ===
if ($action === 'absen_manual') {
    $jenis_absen = $_POST['jenis'] ?? '';
    $lokasi = $_POST['lokasi'] ?? '';
    
    $stmt_nik = $conn->prepare("SELECT nik_karyawan FROM karyawan WHERE id_karyawan = ?");
    $stmt_nik->bind_param("i", $id_karyawan);
    $stmt_nik->execute();
    $result_nik = $stmt_nik->get_result();
    $nik_karyawan_target = $result_nik->fetch_assoc()['nik_karyawan'] ?? NULL;
    $stmt_nik->close();

    if ($jenis_absen === 'masuk') {
        $stmt_check = $conn->prepare("SELECT id_absensi FROM absensi WHERE id_karyawan = ? AND tanggal = ?");
        $stmt_check->bind_param("is", $id_karyawan, $tanggal);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            header("Location: absensi.php?status=absen_masuk_sudah_ada");
            exit();
        }
        $stmt_check->close();

        $sql = "INSERT INTO absensi (id_karyawan, nik_karyawan, tanggal, jam_masuk, alamat_masuk, status_absensi, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert = $conn->prepare($sql);
        $status_absensi = 'Hadir'; 
        $stmt_insert->bind_param("issssss", $id_karyawan, $nik_karyawan_target, $tanggal, $formatted_now, $lokasi, $status_absensi, $formatted_now);

        if ($stmt_insert->execute()) {
            header("Location: absensi.php?status=manual_absen_success");
        } else {
            header("Location: absensi.php?status=error");
        }
        $stmt_insert->close();

    } elseif ($jenis_absen === 'pulang') {
        $stmt_check = $conn->prepare("SELECT id_absensi FROM absensi WHERE id_karyawan = ? AND tanggal = ? AND jam_pulang IS NULL");
        $stmt_check->bind_param("is", $id_karyawan, $tanggal);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            $sql = "UPDATE absensi SET jam_pulang = ?, alamat_pulang = ?, updated_at = ? WHERE id_karyawan = ? AND tanggal = ?";
            $stmt_update = $conn->prepare($sql);
            $stmt_update->bind_param("sssis", $formatted_now, $lokasi, $formatted_now, $id_karyawan, $tanggal);

            if ($stmt_update->execute()) {
                header("Location: absensi.php?status=manual_absen_success");
            } else {
                header("Location: absensi.php?status=error");
            }
            $stmt_update->close();
        } else {
            header("Location: absensi.php?status=absen_pulang_gagal");
        }
        $stmt_check->close();
    }
}

// === LOGIKA UNTUK EDIT STATUS ABSENSI ===
elseif ($action === 'edit_status') {
    $status_baru = $_POST['status'] ?? '';
    
    $sql = "UPDATE absensi SET status_absensi = ?, updated_at = ? WHERE id_karyawan = ? AND tanggal = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        header("Location: absensi.php?status=error");
        exit();
    }
    
    $stmt->bind_param("ssis", $status_baru, $formatted_now, $id_karyawan, $tanggal);

    if ($stmt->execute()) {
        header("Location: absensi.php?status=edit_status_success");
    } else {
        header("Location: absensi.php?status=error");
    }
    $stmt->close();
}

$conn->close();
?>