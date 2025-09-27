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

// Ambil data user dari sesi untuk digunakan di halaman ini
$id_karyawan = $_SESSION['id_karyawan'];
$nama_user = $_SESSION['nama'];

// Ambil NIK dan Jabatan dari database berdasarkan ID karyawan
$stmt_user_info = $conn->prepare("SELECT nik_ktp, jabatan FROM karyawan WHERE id_karyawan = ?");
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
$jabatan_user = $user_info['jabatan'];

// --- HANDLE FORM SUBMISSION ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $jenis_pengajuan = $_POST['submission-type'];
    $tanggal_mulai = $_POST['start-date'];
    $tanggal_berakhir = $_POST['end-date'];
    $keterangan = $_POST['reason'];

    $nama_pengganti = !empty($_POST['replacement-name']) ? $_POST['replacement-name'] : NULL;
    $nik_pengganti = !empty($_POST['replacement-nik']) ? $_POST['replacement-nik'] : NULL;
    $wa_pengganti = !empty($_POST['replacement-wa']) ? $_POST['replacement-wa'] : NULL;

    // Proses unggah file PDF
    $dokumen_pendukung = NULL;
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
                $dokumen_pendukung = $file_name;
            } else {
                echo "<script>alert('Gagal mengunggah file.');</script>";
            }
        } else {
            echo "<script>alert('Hanya file PDF yang diizinkan.');</script>";
        }
    }

    // Insert data ke database
    $status_pengajuan = 'Menunggu';
    $sql = "INSERT INTO pengajuan (id_karyawan, nik_karyawan, jenis_pengajuan, tanggal_mulai, tanggal_berakhir, keterangan, dokumen_pendukung, nama_pengganti, nik_pengganti, wa_pengganti, status_pengajuan, tanggal_diajukan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($sql);
    
    $stmt->bind_param("issssssssss", $id_karyawan, $nik_user, $jenis_pengajuan, $tanggal_mulai, $tanggal_berakhir, $keterangan, $dokumen_pendukung, $nama_pengganti, $nik_pengganti, $wa_pengganti, $status_pengajuan);
    
    if ($stmt->execute()) {
        echo "<script>alert('Pengajuan berhasil dikirim!'); window.location.href='pengajuan.php';</script>";
    } else {
        echo "<script>alert('Error: " . $stmt->error . "');</script>";
    }
    $stmt->close();
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
        /* General page layout (unchanged) */
        .content-wrapper {
            display: grid;
            grid-template-areas: "form history";
            grid-template-columns: 1fr 2fr;
            gap: 30px;
        }
        @media (max-width: 768px) {
            .content-wrapper {
                grid-template-areas: "form" "history";
                grid-template-columns: 1fr;
            }
        }
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
        
        /* Form Styling */
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
        .form-group select,
        .form-group input[type="text"],
        .form-group input[type="date"],
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
            background-color: #f9f9f9;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        .form-group select:focus,
        .form-group input[type="text"]:focus,
        .form-group input[type="date"]:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #4285f4;
            box-shadow: 0 0 0 3px rgba(66, 133, 244, 0.2);
        }
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        .btn-submit {
            background-color: #28a745;
            color: #fff;
            padding: 15px;
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
        
        /* History Styling */
        .submission-history {
            grid-area: history;
        }
        .history-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .history-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            transition: box-shadow 0.3s, transform 0.3s;
        }
        .history-item:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transform: translateY(-2px);
        }
        .history-details {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .submission-type {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
        }
        .submission-date {
            font-size: 0.9rem;
            color: #666;
        }
        .submission-note {
            font-size: 0.85rem;
            color: #888;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }
        .history-status {
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #fff;
            text-align: center;
            min-width: 90px;
            text-transform: capitalize;
        }
        .status-menunggu { background-color: #fbbc05; }
        .status-disetujui { background-color: #34a853; }
        .status-ditolak { background-color: #e74c3c; }
        .history-empty {
            text-align: center;
            color: #888;
            padding: 40px 20px;
            background-color: #f7f7f7;
            border-radius: 8px;
            border: 1px dashed #ddd;
        }
        .history-empty p { margin: 0; }
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
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="submission-type">Jenis Pengajuan</label>
                            <select id="submission-type" name="submission-type" required>
                                <option value="">Pilih jenis...</option>
                                <option value="Cuti">Cuti</option>
                                <option value="Izin">Izin</option>
                                <option value="Sakit">Sakit</option>
                            </select>
                        </div>
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
                            <textarea id="reason" name="reason" placeholder="Contoh: Cuti tahunan, Izin keperluan keluarga, Sakit demam..." required></textarea>
                        </div>
                        <div class="form-group">
                            <label for="surat-file">Unggah Dokumen Pendukung (PDF)</label>
                            <input type="file" id="surat-file" name="surat-file" accept=".pdf" required>
                            <small style="color: #777; font-size: 0.8rem; margin-top: 5px;">*Pastikan dokumen berformat PDF dan tidak lebih dari 2MB.</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="replacement-name">Nama Pengganti (Opsional)</label>
                            <input type="text" id="replacement-name" name="replacement-name" placeholder="Masukkan nama pengganti">
                        </div>
                        <div class="form-group">
                            <label for="replacement-nik">NIK Pengganti (Opsional)</label>
                            <input type="text" id="replacement-nik" name="replacement-nik" placeholder="Masukkan NIK pengganti">
                        </div>
                        <div class="form-group">
                            <label for="replacement-wa">No. WA Pengganti (Opsional)</label>
                            <input type="text" id="replacement-wa" name="replacement-wa" placeholder="Masukkan nomor WhatsApp pengganti">
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
                                $pengganti_display = "";
                                if (!empty($row['nama_pengganti'])) {
                                    $pengganti_display = " | Pengganti: " . e($row['nama_pengganti']);
                                }

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
                                        <p class="submission-note">Keterangan: ' . e($row['keterangan']) . $pengganti_display . '</p>
                                    </div>
                                    <div class="history-status ' . $status_style . '">' . e($row['status_pengajuan']) . '</div>
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
</body>
</html>

<?php
// Tutup statement dan koneksi database
$stmt_user_info->close();
$stmt_history->close();
$conn->close();
?>