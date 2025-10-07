<?php
// Mulai sesi dan cek login
session_start();

// Periksa apakah user sudah login. Jika tidak, redirect ke halaman login.
if (!isset($_SESSION['id_karyawan'])) {
    header("Location: ../../index.php"); // Arahkan ke halaman login
    exit();
}

// Sertakan file konfigurasi dan helper
require '../config.php';

// Fungsi bantuan untuk HTML escaping
if (!function_exists('e')) {
    function e($text)
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

// Ambil data user dari sesi untuk digunakan di halaman ini
$id_karyawan = $_SESSION['id_karyawan'];
$nama_user = $_SESSION['nama'];

// Ambil NIK dan Jabatan dari database berdasarkan ID karyawan
$stmt_user_info = $conn->prepare("SELECT nik_ktp, jabatan, proyek, alamat_email FROM karyawan WHERE id_karyawan = ?");
$stmt_user_info->bind_param("i", $id_karyawan);
$stmt_user_info->execute();
$result_user_info = $stmt_user_info->get_result();
$user_info = $result_user_info->fetch_assoc();

if (!$user_info) {
    // Jika data user tidak ditemukan, arahkan kembali ke login
    header("Location: ../../index.php");
    exit();
}

$nik_user = $user_info['nik_ktp'];
$project = $user_info['proyek'];
$email = $user_info['alamat_email'];
$jabatan_user = $user_info['jabatan'];

// --- HANDLE FORM SUBMISSION ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $jenis_pengajuan = $_POST['submission-type'];
    $status_pengajuan = 'Menunggu';

    if ($jenis_pengajuan === 'Reimburse') {
        // =========================================================
        // --- LOGIKA UNTUK REIMBURSE MULTIPLE ITEMS (JSON) ---
        // =========================================================

        $lokasi = $_POST['lokasi'];
        $kategori_utama = $_POST['kategori-utama'];
        $tanggal_utama = $_POST['tanggal-transaksi-utama'];
        
        $deskripsi_arr = isset($_POST['deskripsi']) ? $_POST['deskripsi'] : [];
        $nominal_arr = isset($_POST['nominal']) ? $_POST['nominal'] : [];
        $kwitansi_arr = isset($_FILES['kwitansi_file']) ? $_FILES['kwitansi_file'] : [];
        $tanggal_transaksi_arr = isset($_POST['tanggal_transaksi']) ? $_POST['tanggal_transaksi'] : [];

        $jumlah_item = count($deskripsi_arr);
        $total_nominal_semua_item = 0;
        $detail_items_for_json = []; 
        $error_messages = [];
        $item_berhasil_diproses = 0;

        if ($jumlah_item == 0) {
            echo "<script>alert('Minimal harus ada satu item reimbursement yang diisi.');</script>";
            exit();
        }

        // --- LOOPING UNTUK PROSES UPLOAD DAN KOMPILASI DATA ---
        $upload_dir = '../../uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        for ($i = 0; $i < $jumlah_item; $i++) {

            // Cek ketersediaan input dasar
            if (!isset($deskripsi_arr[$i]) || !isset($nominal_arr[$i]) || !isset($tanggal_transaksi_arr[$i]) || !isset($kwitansi_arr['error'][$i])) {
                continue; 
            }

            $current_deskripsi = $deskripsi_arr[$i];
            $current_tanggal = $tanggal_transaksi_arr[$i];
            $current_nominal = floatval(preg_replace('/[^\d]/', '', $nominal_arr[$i]));
            $kwitansi_filename = NULL;

            if (empty(trim($current_deskripsi)) || $current_nominal <= 0) {
                $error_messages[] = "Item ke-" . ($i + 1) . " (Deskripsi: " . $current_deskripsi . ") tidak valid.";
                continue;
            }
            
            // A. Proses unggah file Kwitansi
            if ($kwitansi_arr['error'][$i] === 0) {
                $file_name = uniqid() . '-' . basename($kwitansi_arr['name'][$i]);
                $file_path = $upload_dir . $file_name;
                $allowed_types = ['pdf', 'jpg', 'jpeg', 'png'];
                $file_type = pathinfo($file_path, PATHINFO_EXTENSION);
                
                if (in_array(strtolower($file_type), $allowed_types)) {
                    if (move_uploaded_file($kwitansi_arr['tmp_name'][$i], $file_path)) {
                        $kwitansi_filename = $file_name;
                    } else {
                        $error_messages[] = "Gagal mengunggah kwitansi item ke-" . ($i + 1) . ".";
                        continue;
                    }
                } else {
                    $error_messages[] = "Tipe file kwitansi item ke-" . ($i + 1) . " tidak diizinkan.";
                    continue;
                }
            } else {
                 // Wajib ada kwitansi
                $error_messages[] = "Kwitansi item ke-" . ($i + 1) . " tidak terunggah atau ada error file.";
                continue; 
            }

            // B. Kumpulkan detail item dan hitung total
            $total_nominal_semua_item += $current_nominal;
            
            // Simpan detail ke array PHP untuk JSON
            $detail_items_for_json[] = [
                'deskripsi' => trim($current_deskripsi),
                'nominal' => $current_nominal,
                'kwitansi_file' => $kwitansi_filename,
                'tanggal_transaksi' => $current_tanggal
            ];

            $item_berhasil_diproses++;
        }

        // Validasi Akhir
        if ($item_berhasil_diproses <= 0) {
            $alert_message = "Gagal mengirim pengajuan. Tidak ada item yang valid atau semua item gagal diunggah.";
            echo "<script>alert('" . $alert_message . "');</script>";
            exit();
        }

        // 3. Konversi Data & Finalisasi
        $detail_reimburse_json = json_encode($detail_items_for_json, JSON_UNESCAPED_UNICODE);
        $keterangan_final = "Pengajuan Reimburse untuk " . $item_berhasil_diproses . " item. Lokasi: " . e($lokasi);
        $nominal_total_final = $total_nominal_semua_item; 
        $dokumen_pendukung_final = NULL; 

        // Data Lain
        $tanggal_mulai_final = $tanggal_utama; 
        $tanggal_berakhir_final = $tanggal_utama; 
        $category_final = $kategori_utama; 
        $nama_pengganti = NULL;
        $nik_pengganti = NULL;
        $wa_pengganti = NULL;

        // 4. Insert ke tabel pengajuan
        $sql = "INSERT INTO pengajuan (id_karyawan, nik_karyawan, jenis_pengajuan, tanggal_mulai, tanggal_berakhir, keterangan, dokumen_pendukung, nama_pengganti, nik_pengganti, wa_pengganti, status_pengajuan, tanggal_diajukan, nominal_total, detail_reimburse_json, category) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        // String Tipe yang Benar: i s s s s s s s s s s d s s (14 Karakter)
        $stmt->bind_param("issssssssssdss", 
            $id_karyawan, 
            $nik_user, 
            $jenis_pengajuan, 
            $tanggal_mulai_final, 
            $tanggal_berakhir_final, 
            $keterangan_final, 
            $dokumen_pendukung_final, 
            $nama_pengganti, 
            $nik_pengganti, 
            $wa_pengganti, 
            $status_pengajuan, 
            $nominal_total_final, 
            $detail_reimburse_json,
            $category_final
        );

        if ($stmt->execute()) {
            $alert_message = "Pengajuan Reimbursement berhasil dikirim dengan " . $item_berhasil_diproses . " item (Total Rp. " . number_format($total_nominal_semua_item, 0, ',', '.') . ").";
            if (!empty($error_messages)) {
                $alert_message .= " (Terdapat " . count($error_messages) . " item gagal diproses/diunggah).";
            }
            echo "<script>alert('" . $alert_message . "'); window.location.href='pengajuan.php';</script>";
        } else {
            $alert_message = "Gagal mengirim pengajuan reimburse: " . $stmt->error;
            echo "<script>alert('" . $alert_message . "');</script>";
        }
        if (isset($stmt))
            $stmt->close();

    } else {
        // =========================================================
        // --- LOGIKA UNTUK CUTI, IZIN, SAKIT ---
        // =========================================================
        $tanggal_mulai_final = $_POST['start-date'];
        $tanggal_berakhir_final = $_POST['end-date'];
        $keterangan_final = $_POST['reason'];
        $category_final = NULL; 

        $nama_pengganti = !empty($_POST['replacement-name']) ? $_POST['replacement-name'] : NULL;
        $nik_pengganti = !empty($_POST['replacement-nik']) ? $_POST['replacement-nik'] : NULL;
        $wa_pengganti = !empty($_POST['replacement-wa']) ? $_POST['replacement-wa'] : NULL;
        $nominal_total_final = 0.00; 
        $detail_reimburse_json = NULL; 

        // Proses unggah file PDF untuk Cuti/Izin/Sakit
        $dokumen_pendukung_final = NULL;
        if (isset($_FILES['surat-file']) && $_FILES['surat-file']['error'] == 0) {
            $upload_dir = '../../uploads/';
             if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $file_name = uniqid() . '-' . basename($_FILES['surat-file']['name']);
            $file_path = $upload_dir . $file_name;
            $file_type = pathinfo($file_path, PATHINFO_EXTENSION);
            $allowed_types = ['pdf'];

            if (in_array(strtolower($file_type), $allowed_types)) {
                if (move_uploaded_file($_FILES['surat-file']['tmp_name'], $file_path)) {
                    $dokumen_pendukung_final = $file_name;
                } else {
                    echo "<script>alert('Gagal mengunggah file surat.');</script>";
                }
            } else {
                echo "<script>alert('Hanya file PDF yang diizinkan untuk surat.');</script>";
            }
        }

        // Insert Cuti/Izin/Sakit data ke database 
        $sql = "INSERT INTO pengajuan (id_karyawan, nik_karyawan, jenis_pengajuan, tanggal_mulai, tanggal_berakhir, keterangan, dokumen_pendukung, nama_pengganti, nik_pengganti, wa_pengganti, status_pengajuan, tanggal_diajukan, nominal_total, detail_reimburse_json, category) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        // String Tipe yang Benar: i s s s s s s s s s s d s s (14 Karakter)
        $stmt->bind_param("issssssssssdss", 
            $id_karyawan, 
            $nik_user, 
            $jenis_pengajuan, 
            $tanggal_mulai_final, 
            $tanggal_berakhir_final, 
            $keterangan_final, 
            $dokumen_pendukung_final, 
            $nama_pengganti, 
            $nik_pengganti, 
            $wa_pengganti, 
            $status_pengajuan, 
            $nominal_total_final, 
            $detail_reimburse_json,
            $category_final
        );


        if ($stmt->execute()) {
            echo "<script>alert('Pengajuan berhasil dikirim!'); window.location.href='pengajuan.php';</script>";
        } else {
            echo "<script>alert('Error: " . $stmt->error . "');</script>";
        }
        if (isset($stmt))
            $stmt->close();
    }
}


// --- TAMPILKAN RIWAYAT PENGAJUAN ---
// Mengambil semua pengajuan untuk karyawan yang login
$sql_history = "SELECT * FROM pengajuan WHERE id_karyawan = ? ORDER BY tanggal_diajukan DESC";
$stmt_history = $conn->prepare($sql_history);
$stmt_history->bind_param("i", $id_karyawan);
$stmt_history->execute();
$result_history = $stmt_history->get_result();

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Halaman Pengajuan</title>
    <link rel="stylesheet" href="../style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* ============================================== */
        /* MODIFIKASI FINAL UNTUK MENGATASI KESEMPITAN */
        /* ============================================== */
        
        /* 1. LAYOUT HALAMAN: Form (70-80%) vs History (20-30%) */
        .content-wrapper {
            display: grid;
            grid-template-areas: "form history";
            /* Form 4 unit, History 1 unit */
            grid-template-columns: 4fr 1fr; 
            gap: 30px;
        }

        @media (max-width: 1024px) {
            .content-wrapper {
                grid-template-columns: 3fr 2fr; /* Sedikit lebih imbang di tablet */
            }
        }
        
        @media (max-width: 768px) {
            .content-wrapper {
                grid-template-areas: "form" "history";
                grid-template-columns: 1fr;
            }
        }

        /* 2. LAYOUT REIMBURSE ITEM: Alokasi ruang yang lebih baik untuk Deskripsi */
        .reimburse-item-row .reimburse-grid {
            /* 4 kolom: Deskripsi (3fr), Nominal (1.5fr), Kwitansi (1.5fr), Tanggal (1.5fr) */
            display: grid;
            grid-template-columns: 3fr 1.5fr 1.5fr 1.5fr; 
            gap: 15px;
            align-items: flex-end;
        }

        @media (max-width: 768px) {
            /* Di mobile/tablet, kembali ke layout 2 kolom */
            .reimburse-item-row .reimburse-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        /* 3. INPUT FIELD STYLING (Diperkecil sedikit) */
        .form-group select,
        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group input[type="date"],
        .form-group textarea {
            padding: 10px; 
            border-radius: 6px; 
            font-size: 0.95rem; 
        }

        /* 4. RIWAYAT PENGAJUAN (Diperkecil dan dibuat vertikal) */
        .submission-history {
            grid-area: history;
        }

        .history-list {
            gap: 8px; /* Kurangi gap antar item */
        }

        .history-item {
            display: flex;
            flex-direction: column; /* Menjadi vertikal untuk menghemat lebar */
            justify-content: flex-start;
            align-items: flex-start;
            padding: 10px 12px; /* Ringkas */
            border: 1px solid #e0e0e0;
            border-radius: 6px;
        }
        
        .history-details {
            flex-grow: 1;
            width: 100%;
            gap: 2px;
        }

        .submission-type {
            font-size: 0.95rem; 
        }

        .submission-date {
            font-size: 0.8rem; 
        }

        .submission-note {
            font-size: 0.75rem; /* Paling ringkas */
            color: #888;
            white-space: normal; /* Biarkan teks keterangan wrap */
            margin-top: 5px;
        }
        
        .history-status-wrapper {
            width: 100%;
            display: flex;
            justify-content: flex-end; /* Posisikan status di kanan */
            margin-top: 5px;
        }

        .history-status {
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.7rem; /* Paling kecil untuk status */
            min-width: 70px; 
        }

        /* CSS Lainnya (Unchanged or Minor Adjustments) */
        .section {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .section-header h2 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
        }

        .submission-form {
            grid-area: form;
            display: flex;
            flex-direction: column;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #555;
            font-size: 0.95rem;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .reimburse-item-row .form-group textarea {
            min-height: 40px !important; 
        }


        .btn-submit {
            background-color: #28a745;
            color: #fff;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
            width: 100%;
            margin-top: 10px;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit:hover {
            background-color: #218838;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .status-menunggu {
            background-color: #fbbc05;
        }

        .status-disetujui {
            background-color: #34a853;
        }

        .status-ditolak {
            background-color: #e74c3c;
        }

        .history-empty {
            text-align: center;
            color: #888;
            padding: 40px 20px;
            background-color: #f7f7f7;
            border-radius: 8px;
            border: 1px dashed #ddd;
        }
        
        .reimburse-item-row .form-group label {
            font-weight: 400;
        }
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
                <div class="user-avatar"><?= e(strtoupper(substr($nama_user, 0, 2))) ?></div>
                <div class="user-details">
                    <p class="user-name"><?= e($nama_user) ?></p>
                    <p class="user-id"><?= e($nik_user) ?></p>
                    <p class="user-role"><?= e($jabatan_user) ?></p>
                </div>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="../dashboard_karyawan.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
                    <li><a href="../absensi/absensi.php"><i class="fas fa-clipboard-list"></i> Absensi</a></li>
                    <li class="active"><a href="#"><i class="fas fa-file-invoice"></i> Pengajuan Saya</a></li>
                    <li><a href="../slipgaji/slipgaji.php"><i class="fas fa-money-check-alt"></i> Slip Gaji</a></li>
                </ul>
            </nav>
            <div class="logout-link">
                <a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a>
            </div>
        </aside>

        <main class="main-content">
            <header class="main-header">
                <h1>Pengajuan</h1>
                <p>Ajukan permohonan cuti, izin, atau sakit dan pantau statusnya</p>
            </header>

            <div class="content-wrapper">
                <div class="section submission-form">
                    <h2>Buat Pengajuan Baru</h2>
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST"
                        enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="submission-type">Jenis Pengajuan</label>
                            <select id="submission-type" name="submission-type" onchange="toggleForm()" required>
                                <option value="">Pilih jenis...</option>
                                <option value="Cuti">Cuti</option>
                                <option value="Izin">Izin</option>
                                <option value="Sakit">Sakit</option>
                                <option value="Reimburse">Reimburse</option>
                            </select>
                        </div>

                        <div id="standard-fields">
                            <div class="form-group">
                                <label for="start-date">Tanggal Mulai</label>
                                <input type="date" id="start-date" name="start-date" required>
                            </div>
                            <div class="form-group">
                                <label for="end-date">Tanggal Berakhir</label>
                                <input type="date" id="end-date" name="end-date" required>
                            </div>
                            <div class="form-group">
                                <label for="reason">Keterangan</label>
                                <textarea id="reason" name="reason"
                                    placeholder="Contoh: Cuti tahunan, Izin keperluan keluarga, Sakit demam..."
                                    required></textarea>
                            </div>
                            <div class="form-group">
                                <label for="surat-file">Unggah Dokumen Pendukung (PDF)</label>
                                <input type="file" id="surat-file" name="surat-file" accept=".pdf" required>
                                <small style="color: #777; font-size: 0.8rem; margin-top: 5px;">*Pastikan dokumen
                                    berformat PDF.</small>
                            </div>

                            <div class="form-group">
                                <label for="replacement-name">Nama Pengganti (Opsional)</label>
                                <input type="text" id="replacement-name" name="replacement-name"
                                    placeholder="Masukkan nama pengganti">
                            </div>
                            <div class="form-group">
                                <label for="replacement-nik">NIK Pengganti (Opsional)</label>
                                <input type="text" id="replacement-nik" name="replacement-nik"
                                    placeholder="Masukkan NIK pengganti">
                            </div>
                            <div class="form-group">
                                <label for="replacement-wa">No. WA Pengganti (Opsional)</label>
                                <input type="text" id="replacement-wa" name="replacement-wa"
                                    placeholder="Masukkan nomor WhatsApp pengganti">
                            </div>
                        </div>

                        <div id="reimburse-fields" style="display: none;">

                            <div class="reimburse-grid">
                                <div class="form-group">
                                    <label for="nama-lengkap-reimburse">Nama Lengkap</label>
                                    <input type="text" id="nama-lengkap-reimburse" name="nama-lengkap-reimburse"
                                        value="<?= e($nama_user) ?>" readonly required>
                                </div>
                                <div class="form-group">
                                    <label for="project-reimburse">Project</label>
                                    <input type="text" id="project-reimburse" name="project-reimburse"
                                        value="<?= e($project) ?>" readonly required>
                                </div>
                            </div>

                            <div class="reimburse-grid">
                                <div class="form-group">
                                    <label for="lokasi">Lokasi</label>
                                    <input type="text" id="lokasi" name="lokasi" placeholder="Masukkan lokasi transaksi"
                                        required>
                                </div>
                                <div class="form-group">
                                    <label for="kategori-utama">Kategori</label>
                                    <select id="kategori-utama" name="kategori-utama" required>
                                        <option value="">Pilih Kategori Utama...</option>
                                        <option value="f&b">F&B</option>
                                        <option value="iklan">Iklan</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                            </div>

                            <div class="reimburse-grid">
                                <div class="form-group">
                                    <label for="email-reimburse">Email</label>
                                    <input type="text" id="email-reimburse" name="email-reimburse"
                                        value="<?= e($email) ?>" readonly required>
                                </div>
                                <div class="form-group">
                                    <label for="tanggal-transaksi-utama">Tanggal Pengajuan</label>
                                    <input type="date" id="tanggal-transaksi-utama" name="tanggal-transaksi-utama"
                                        required>
                                </div>
                            </div>

                            <hr style="border: 0; border-top: 1px solid #eee; margin: 25px 0 10px;">

                            <h3>Rincian Pengeluaran</h3>
                            <div id="item-list">
                            </div>

                            <button type="button" onclick="addReimbursementRow(event)" class="btn-submit"
                                style="background-color: #007bff; max-width: 150px; margin-left: 0;">
                                <i class="fas fa-plus"></i> Tambah Item
                            </button>
                            <small style="color: #777; font-size: 0.8rem; margin-top: 5px;">*Semua rincian pengeluaran
                                di atas akan dikirim sebagai satu pengajuan.</small>

                        </div>

                        <button type="submit" class="btn-submit">
                            <i class="fas fa-paper-plane"></i> Kirim Pengajuan
                        </button>
                    </form>
                </div>

                <div class="section submission-history">
                    <div class="section-header">
                        <h2>Riwayat Pengajuan Saya</h2>
                    </div>
                    <ul class="history-list">
                        <?php
                        if ($result_history->num_rows > 0) {
                            while ($row = $result_history->fetch_assoc()) {

                                // --- LOGIKA RINGKASAN BARU UNTUK REIMBURSE ---
                                $keterangan_tampil = "";
                                $pengganti_display = "";

                                if ($row['jenis_pengajuan'] === 'Reimburse') {
                                    
                                    // Ambil Nominal Total
                                    $nominal_total_disp = isset($row['nominal_total']) ? number_format($row['nominal_total'], 0, ',', '.') : 'N/A';
                                    
                                    // Hitung jumlah item dari JSON 
                                    $reimburse_details = json_decode($row['detail_reimburse_json'] ?? '[]', true);
                                    $item_count = is_array($reimburse_details) ? count($reimburse_details) : 0;
                                    $category_disp = !empty($row['category']) ? " | Kategori: " . e($row['category']) : "";
                                    
                                    $keterangan_tampil = $item_count . " Item | Total Rp. " . $nominal_total_disp . $category_disp;

                                } else {
                                    // Cuti/Izin/Sakit
                                    if (!empty($row['nama_pengganti'])) {
                                        $pengganti_display = " | Pengganti: " . e($row['nama_pengganti']);
                                    }

                                    // Ambil 100 karakter pertama dari keterangan
                                    $keterangan_singkat_cuti = e($row['keterangan']);
                                    if (strlen($keterangan_singkat_cuti) > 100) {
                                        $keterangan_singkat_cuti = substr($keterangan_singkat_cuti, 0, 97) . '...';
                                    }

                                    $keterangan_tampil = "Keterangan: " . $keterangan_singkat_cuti . $pengganti_display;
                                }
                                // --- AKHIR LOGIKA RINGKASAN BARU ---
                        
                                // Menentukan kelas status
                                $status_class = strtolower($row['status_pengajuan']);
                                switch ($status_class) {
                                    case 'menunggu':
                                        $status_style = 'status-menunggu';
                                        break;
                                    case 'disetujui':
                                        $status_style = 'status-disetujui';
                                        break;
                                    case 'ditolak':
                                        $status_style = 'status-ditolak';
                                        break;
                                    default:
                                        $status_style = '';
                                        break;
                                }

                                echo '
                                <li class="history-item">
                                    <div class="history-details">
                                        <p class="submission-type">' . e($row['jenis_pengajuan']) . '</p>
                                        <p class="submission-date"><i class="fas fa-calendar-alt"></i> ' . date('d M Y', strtotime($row['tanggal_mulai'])) . ' - ' . date('d M Y', strtotime($row['tanggal_berakhir'])) . '</p>
                                        <p class="submission-note">' . $keterangan_tampil . '</p>
                                    </div>
                                    <div class="history-status-wrapper">
                                        <div class="history-status ' . $status_style . '">' . e($row['status_pengajuan']) . '</div>
                                    </div>
                                </li>';
                            }
                        } else {
                            echo '<li class="history-empty"><p>Tidak ada riwayat pengajuan.</p></li>';
                        }
                        ?>
                    </ul>
                </div>
            </div>
        </main>
    </div>
    <script>
        // Template HTML untuk baris item Reimburse yang akan diduplikasi
        const ITEM_TEMPLATE = `
        <div class="reimburse-item-row" style="border-top: 1px dashed #eee; padding-top: 15px; margin-top: 15px; position: relative;">
            
            <button type="button" onclick="removeReimbursementRow(event)" 
                style="position: absolute; top: -5px; right: 0; color: #e74c3c; background: none; border: none; cursor: pointer; font-size: 0.9rem;">
                <i class="fas fa-times-circle"></i> Hapus Item
            </button>

            <div class="reimburse-grid"> 
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Deskripsi Pengeluaran</label>
                    <textarea name="deskripsi[]" placeholder="Jelaskan rincian pengeluaran..." required></textarea> 
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label>Nominal (Rp)</label>
                    <input type="number" name="nominal[]" min="1" placeholder="0" required>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
            <label>Kwitansi</label>
            <input type="file" name="kwitansi_file[]" accept=".pdf, .jpg, .jpeg, .png" class="file-input-reimburse" required>
            <small class="file-note" style="color: #777; font-size: 0.8rem; margin-top: 5px; display: block;">*Maks 2MB, PDF/JPG/PNG.</small>
        </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label>Tanggal Transaksi</label>
                    <input type="date" name="tanggal_transaksi[]" required>
                </div>

            </div>
        </div>
`;


        document.addEventListener('DOMContentLoaded', function () {
            const submissionType = document.getElementById('submission-type');
            const standardFields = document.getElementById('standard-fields');
            const reimburseFields = document.getElementById('reimburse-fields');
            const itemList = document.getElementById('item-list');

            // Elemen-elemen yang perlu diatur `required` di form standard
            const standardInputs = standardFields.querySelectorAll('input:not([type="file"]), select, textarea');
            const standardFile = document.getElementById('surat-file');

            // Elemen-elemen yang perlu diatur `required` di form reimburse (base fields)
            const baseReimburseInputs = reimburseFields.querySelectorAll('.reimburse-grid input:not([type="file"]), .reimburse-grid select');


            function setRequired(elements, isRequired) {
                elements.forEach(el => {
                    // Kecuali field yang opsional (pengganti)
                    if (el.id !== 'replacement-name' && el.id !== 'replacement-nik' && el.id !== 'replacement-wa') {
                        if (isRequired) {
                            el.setAttribute('required', 'required');
                        } else {
                            el.removeAttribute('required');
                        }
                    }
                });
            }

            // Fungsi utama untuk menampilkan/menyembunyikan form
            window.toggleForm = function () {
                const selectedType = submissionType.value;

                // Atur visibility
                standardFields.style.display = (selectedType === 'Reimburse' || selectedType === '') ? 'none' : 'block';
                reimburseFields.style.display = (selectedType === 'Reimburse') ? 'block' : 'none';

                // Reset semua required
                setRequired(standardInputs, false);
                setRequired(baseReimburseInputs, false);
                standardFile.removeAttribute('required');

                // Hapus semua item list dan buat satu item baru setiap kali Reimburse dipilih
                if (itemList) {
                    itemList.innerHTML = '';
                }

                // Atur required untuk form yang aktif
                if (selectedType === 'Reimburse') {
                    setRequired(baseReimburseInputs, true);
                    // Tambahkan item pertama secara otomatis saat Reimburse dipilih
                    addReimbursementRow();
                } else if (selectedType !== '') {
                    // Cuti/Izin/Sakit
                    setRequired(standardInputs, true);
                    standardFile.setAttribute('required', 'required');
                }
            }

            // Fungsi untuk menghapus baris item
            window.removeReimbursementRow = function (event) {
                if (event) event.preventDefault();
                const rowToRemove = event.target.closest('.reimburse-item-row');
                if (rowToRemove) {
                    rowToRemove.remove();
                }
                // Pastikan setidaknya ada satu item yang tersisa
                if (itemList.children.length === 0) {
                    addReimbursementRow();
                }
            }

            // Fungsi untuk menambahkan baris item baru
            window.addReimbursementRow = function (event) {
                if (event) event.preventDefault();
                itemList.insertAdjacentHTML('beforeend', ITEM_TEMPLATE);
            }

            // Panggil saat halaman dimuat untuk memastikan tampilan awal sudah benar
            toggleForm();
        });
    </script>
</body>

</html>

<?php
// Tutup statement dan koneksi database
$stmt_user_info->close();
$stmt_history->close();
$conn->close();
?>