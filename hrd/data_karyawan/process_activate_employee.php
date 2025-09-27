<?php
// Konfigurasi database
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "hrd";

// Buat koneksi ke database
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Periksa apakah NIK tersedia dari parameter GET
if (isset($_GET['nik'])) {
    $nik_to_activate = $_GET['nik'];

    // 1. Ambil semua data karyawan dari tabel `karyawan_nonaktif`
    $sql_select = "SELECT * FROM karyawan_nonaktif WHERE nik = ?";
    $stmt_select = $conn->prepare($sql_select);
    $stmt_select->bind_param("s", $nik_to_activate);
    $stmt_select->execute();
    $result_select = $stmt_select->get_result();

    if ($result_select->num_rows > 0) {
        $employee_data = $result_select->fetch_assoc();
        $stmt_select->close();

        // 2. Siapkan query INSERT untuk memindahkan data ke tabel `karyawan`
        $sql_insert = "INSERT INTO karyawan (
            nik, nama, jenis_kelamin, tempat_lahir, tanggal_lahir, alamat, no_hp, status_pernikahan, agama, foto_path, 
            kenalan_serumah_nama, kenalan_serumah_no_hp, kenalan_serumah_hubungan, kenalan_serumah_alamat, 
            tidak_serumah_nama, tidak_serumah_no_hp, tidak_serumah_hubungan, tidak_serumah_alamat, 
            tanggal_masuk_kerja, tanggal_akhir_kontrak, departemen, jabatan, status_kerja, status_pegawai, jenis_skema, 
            lokasi_kerja, npwp, bpjs_kesehatan, bpjs_ketenagakerjaan, rekening, nama_bank, email, password_hash, role
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )";

        $stmt_insert = $conn->prepare($sql_insert);

        // Nilai yang diperbarui saat karyawan diaktifkan kembali
        $status_kerja_baru = 'Kontrak'; // Ganti 'Aktif' dengan salah satu dari: 'Kontrak', 'Tetap', 'Magang'
        $status_pegawai_baru = $employee_data['status_pegawai']; // Gunakan nilai yang sudah ada

        // Bind parameter, pastikan urutan dan tipe datanya cocok
        // String di bawah ini memiliki 34 karakter 's'
        $stmt_insert->bind_param("ssssssssssssssssssssssssssssssssss", 
            $employee_data['nik'],
            $employee_data['nama'],
            $employee_data['jenis_kelamin'],
            $employee_data['tempat_lahir'],
            $employee_data['tanggal_lahir'],
            $employee_data['alamat'],
            $employee_data['no_hp'],
            $employee_data['status_pernikahan'],
            $employee_data['agama'],
            $employee_data['foto_path'],
            $employee_data['kenalan_serumah_nama'],
            $employee_data['kenalan_serumah_no_hp'],
            $employee_data['kenalan_serumah_hubungan'],
            $employee_data['kenalan_serumah_alamat'],
            $employee_data['tidak_serumah_nama'],
            $employee_data['tidak_serumah_no_hp'],
            $employee_data['tidak_serumah_hubungan'],
            $employee_data['tidak_serumah_alamat'],
            $employee_data['tanggal_masuk_kerja'],
            $employee_data['tanggal_akhir_kontrak'],
            $employee_data['departemen'],
            $employee_data['jabatan'],
            $status_kerja_baru, // Nilai yang diperbarui
            $status_pegawai_baru, // Nilai yang sudah ada
            $employee_data['jenis_skema'],
            $employee_data['lokasi_kerja'],
            $employee_data['npwp'],
            $employee_data['bpjs_kesehatan'],
            $employee_data['bpjs_ketenagakerjaan'],
            $employee_data['rekening'],
            $employee_data['nama_bank'],
            $employee_data['email'],
            $employee_data['password_hash'], 
            $employee_data['role']
        );

        if ($stmt_insert->execute()) {
            // 3. Jika INSERT berhasil, hapus data dari tabel `karyawan_nonaktif`
            $sql_delete = "DELETE FROM karyawan_nonaktif WHERE nik = ?";
            $stmt_delete = $conn->prepare($sql_delete);
            $stmt_delete->bind_param("s", $nik_to_activate);
            
            if ($stmt_delete->execute()) {
                // Berhasil mengaktifkan dan menghapus
                header("Location: karyawan_nonaktif.php?status=success_activate");
            } else {
                // Gagal menghapus dari tabel nonaktif
                header("Location: karyawan_nonaktif.php?status=error_delete_nonaktif&message=" . urlencode($conn->error));
            }
            $stmt_delete->close();
        } else {
            // Gagal memasukkan ke tabel karyawan
            header("Location: karyawan_nonaktif.php?status=error_insert_karyawan&message=" . urlencode($stmt_insert->error));
        }
        $stmt_insert->close();

    } else {
        // Karyawan tidak ditemukan di tabel nonaktif
        header("Location: karyawan_nonaktif.php?status=error_not_found");
    }

} else {
    // NIK tidak ada di URL
    header("Location: karyawan_nonaktif.php");
}

$conn->close();
exit();
?>