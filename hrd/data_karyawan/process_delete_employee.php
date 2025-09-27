<?php
// Konfigurasi database
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "db_hrd2"; // Sesuaikan dengan nama database Anda

// Buat koneksi ke database
$conn = new mysqli($servername, $username, $password, $dbname);

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Ambil nik_karyawan dari parameter URL
$nik_karyawan = $_GET['nik_karyawan'] ?? '';

if (!empty($nik_karyawan)) {
    // LANGKAH 1: Ambil path foto sebelum menghapus data dari database
    $foto_path = null;
    $stmt_select = $conn->prepare("SELECT foto_path FROM karyawan WHERE nik_karyawan = ?");
    if ($stmt_select) {
        $stmt_select->bind_param("s", $nik_karyawan);
        if ($stmt_select->execute()) {
            $result = $stmt_select->get_result();
            if ($row = $result->fetch_assoc()) {
                $foto_path = $row['foto_path'];
            }
        }
        $stmt_select->close();
    }

    // LANGKAH 2: Hapus data karyawan dari database
    $stmt_delete = $conn->prepare("DELETE FROM karyawan WHERE nik_karyawan = ?");
    if (!$stmt_delete) {
        die("Error menyiapkan statement SQL: " . $conn->error);
    }
    $stmt_delete->bind_param("s", $nik_karyawan);

    if ($stmt_delete->execute()) {
        // LANGKAH 3: Jika data berhasil dihapus, hapus juga file foto jika ada
        if (!empty($foto_path) && file_exists($foto_path)) {
            unlink($foto_path);
        }
        // Redirect ke halaman dengan status sukses hapus
        header("Location: internal.php?status=deleted");
        exit();
    } else {
        // Tangani error foreign key constraint jika ada
        if ($conn->errno == 1451) {
            echo "Error: Tidak bisa menghapus data ini. Data karyawan terikat dengan data lain. Hapus data terkait terlebih dahulu.";
        } else {
            echo "Error saat menghapus data: " . $stmt_delete->error;
        }
    }
    $stmt_delete->close();
} else {
    echo "Error: NIK Karyawan tidak valid atau tidak ditemukan.";
}

$conn->close();
?>
