<?php
// public_pengajuan.php
// Form pengajuan cuti/izin/sakit TANPA LOGIN

// Debug (opsional matikan di production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- Konfigurasi Database ---
$servername = "localhost";
$db_username = "root";
$db_password = "";
$dbname = "db_hrd2";

$conn = new mysqli($servername, $db_username, $db_password, $dbname);
if ($conn->connect_error) { die("Koneksi gagal: " . $conn->connect_error); }

$success = $error = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Data pengaju (WAJIB)
    $nama_pengaju = trim($_POST['nama_pengaju'] ?? '');
    $nik_pengaju = trim($_POST['nik_pengaju'] ?? '');
    $telp_pengaju = trim($_POST['telp_pengaju'] ?? '');
    $email_pengaju = trim($_POST['email_pengaju'] ?? '');

    // Data pengajuan
    $jenis_pengajuan = trim($_POST['submission-type'] ?? '');
    $tanggal_mulai = trim($_POST['start-date'] ?? '');
    $tanggal_berakhir = trim($_POST['end-date'] ?? '');
    $keterangan = trim($_POST['reason'] ?? '');

    // Opsional pengganti
    $nama_pengganti = trim($_POST['replacement-name'] ?? '');
    $nik_pengganti = trim($_POST['replacement-nik'] ?? '');
    $wa_pengganti = trim($_POST['replacement-wa'] ?? '');

    // Validasi minimal
    if ($nama_pengaju === '' || $nik_pengaju === '' || $telp_pengaju === '' || $jenis_pengajuan === '' || $tanggal_mulai === '' || $tanggal_berakhir === '' || $keterangan === '') {
        $error = "Semua field wajib diisi kecuali yang bertanda opsional.";
    } else {
        // Upload PDF (opsional tapi direkomendasikan)
        $dokumen_pendukung = NULL;
        if (isset($_FILES['surat-file']) && $_FILES['surat-file']['error'] === 0) {
            $upload_dir = __DIR__ . '/uploads/';
            if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }

            $file_name = uniqid('lampiran_') . '-' . basename($_FILES['surat-file']['name']);
            $file_path = $upload_dir . $file_name;
            $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
            if ($ext !== 'pdf') {
                $error = "Hanya file PDF yang diizinkan.";
            } else {
                if (!move_uploaded_file($_FILES['surat-file']['tmp_name'], $file_path)) {
                    $error = "Gagal mengunggah file.";
                } else {
                    $dokumen_pendukung = $file_name;
                }
            }
        }

        if (!$error) {
            // Insert ke tabel pengajuan
            $status_pengajuan = 'Menunggu';
            $sumber_pengajuan = 'TANPA_LOGIN';

            // id_karyawan & nik_karyawan dibuat NULL (karena tanpa akun)
            $sql = "INSERT INTO pengajuan
                    (id_karyawan, nik_karyawan, jenis_pengajuan, tanggal_mulai, tanggal_berakhir, keterangan, dokumen_pendukung,
                     nama_pengganti, nik_pengganti, wa_pengganti,
                     status_pengajuan, tanggal_diajukan,
                     nama_pengaju, nik_pengaju, telepon_pengaju, email_pengaju, sumber_pengajuan)
                    VALUES (NULL, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                $error = "Kesalahan server: " . $conn->error;
            } else {
                $stmt->bind_param(
                    "ssssssssssssss",
                    $jenis_pengajuan, $tanggal_mulai, $tanggal_berakhir, $keterangan, $dokumen_pendukung,
                    $nama_pengganti, $nik_pengganti, $wa_pengganti,
                    $status_pengajuan,
                    $nama_pengaju, $nik_pengaju, $telp_pengaju, $email_pengaju, $sumber_pengajuan
                );
                if ($stmt->execute()) {
                    $success = "Pengajuan berhasil dikirim. Anda Akan Mendapatkan Email Untuk Informasi Lebih Lanjut.";
                    // Kosongkan form
                    $_POST = [];
                } else {
                    $error = "Gagal menyimpan: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ajukan Cuti tanpa Login - PT Mandiri Andalan Utama</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
    :root {
        --c1: #1f2937;
        --c2: #4b5563;
        --p: #16a34a;
        --b: #e5e7eb;
        --bg: #f3f4f6;
    }
    * { box-sizing: border-box; }
    body {
        font-family: 'Inter', sans-serif;
        background: var(--bg);
        margin: 0;
        padding: 40px 24px;
        color: var(--c1);
    }
    .container {
        max-width: 900px;
        margin: 0 auto;
    }
    .card {
        background: #fff;
        border: 1px solid #d1d5db;
        border-radius: 16px;
        padding: 40px;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }
    .topbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
    }
    .logo-container {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .logo {
        height: 40px;
    }
    .company-name {
        font-weight: 700;
        font-size: 1.25rem;
    }
    .topbar a {
        display: flex;
        align-items: center;
        gap: 8px;
        color: var(--c2);
        text-decoration: none;
        font-weight: 500;
        transition: color 0.2s;
    }
    .topbar a:hover {
        color: var(--c1);
    }
    h1 {
        margin: 0 0 4px;
        font-size: 1.75rem;
        font-weight: 700;
        line-height: 1.2;
    }
    p.sub {
        color: var(--c2);
        margin: 0 0 24px;
        font-size: 1rem;
    }
    h3 {
        font-size: 1.25rem;
        font-weight: 600;
        margin: 24px 0 12px;
    }
    h3:first-of-type {
        margin-top: 0;
    }
    .grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 24px;
    }
    .form-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    label {
        font-weight: 600;
        font-size: 0.9rem;
    }
    input, select, textarea {
        width: 100%;
        padding: 12px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-family: inherit;
        font-size: 1rem;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    input:focus, select:focus, textarea:focus {
        outline: none;
        border-color: var(--p);
        box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.2);
    }
    textarea {
        min-height: 120px;
        resize: vertical;
    }
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 14px 24px;
        border-radius: 10px;
        border: none;
        background: var(--p);
        color: #fff;
        font-weight: 700;
        font-size: 1rem;
        cursor: pointer;
        transition: background-color 0.2s, transform 0.1s;
    }
    .btn:hover {
        background: #15803d;
    }
    .btn:active {
        transform: scale(0.98);
    }
    .muted {
        color: var(--c2);
        font-size: 0.85rem;
        margin-top: 4px;
    }
    .alert {
        padding: 14px 20px;
        border-radius: 10px;
        margin: 16px 0;
        font-weight: 500;
    }
    .alert-success {
        background: #d1fae5;
        border: 1px solid #34d399;
        color: #065f46;
    }
    .alert-error {
        background: #fee2e2;
        border: 1px solid #f87171;
        color: #991b1b;
    }
</style>
</head>
<body>

<div class="container">
    <header class="topbar">
        <a href="index.php">
            <i class="fas fa-arrow-left"></i> Kembali ke Halaman Login
        </a>
        <div class="logo-container">
            <img src="./hrd/image/manu.png" alt="Logo PT Mandiri Andalan Utama" class="logo">
            <span class="company-name">PT Mandiri Andalan Utama</span>
        </div>
    </header>

    <div class="card">
        <h1>Form Pengajuan Tanpa Login</h1>
        <p class="sub">Isi data di bawah ini untuk mengajukan cuti, izin, atau sakit.</p>

        <?php if($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data">
            <h3>Data Pengaju</h3>
            <div class="grid">
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="nama_pengaju" value="<?= htmlspecialchars($_POST['nama_pengaju'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>NIK</label>
                    <input type="text" name="nik_pengaju" value="<?= htmlspecialchars($_POST['nik_pengaju'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>No. Telepon/WA</label>
                    <input type="text" name="telp_pengaju" value="<?= htmlspecialchars($_POST['telp_pengaju'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email_pengaju" value="<?= htmlspecialchars($_POST['email_pengaju'] ?? '') ?>" required>
                    <div class="muted">Kami akan mengirimkan informasi ke email ini.</div>
                </div>
            </div>

            <h3>Detail Pengajuan</h3>
            <div class="grid">
                <div class="form-group">
                    <label>Jenis Pengajuan</label>
                    <select name="submission-type" required>
                        <option value="">Pilih jenis...</option>
                        <option value="Cuti" <?= (($_POST['submission-type'] ?? '')==='Cuti')?'selected':'' ?>>Cuti</option>
                        <option value="Izin" <?= (($_POST['submission-type'] ?? '')==='Izin')?'selected':'' ?>>Izin</option>
                        <option value="Sakit" <?= (($_POST['submission-type'] ?? '')==='Sakit')?'selected':'' ?>>Sakit</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Tanggal Mulai</label>
                    <input type="date" name="start-date" value="<?= htmlspecialchars($_POST['start-date'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Tanggal Berakhir</label>
                    <input type="date" name="end-date" value="<?= htmlspecialchars($_POST['end-date'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Dokumen Pendukung (opsional)</label>
                    <input type="file" name="surat-file" accept=".pdf">
                    <div class="muted">Contoh: surat dokter, dll.</div>
                </div>
            </div>
            
            <div class="form-group" style="margin-top: 24px;">
                <label>Keterangan</label>
                <textarea name="reason" placeholder="Contoh: Cuti tahunan / Izin keluarga / Sakit demam" required><?= htmlspecialchars($_POST['reason'] ?? '') ?></textarea>
            </div>

            <h3>Data Pengganti (Opsional)</h3>
            <div class="grid">
                <div class="form-group">
                    <label>Nama Pengganti</label>
                    <input type="text" name="replacement-name" value="<?= htmlspecialchars($_POST['replacement-name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>NIK Pengganti</label>
                    <input type="text" name="replacement-nik" value="<?= htmlspecialchars($_POST['replacement-nik'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>No. WA Pengganti</label>
                    <input type="text" name="replacement-wa" value="<?= htmlspecialchars($_POST['replacement-wa'] ?? '') ?>">
                </div>
            </div>

            <div style="margin-top: 32px;">
                <button class="btn" type="submit">
                    <i class="fas fa-paper-plane"></i> Kirim Pengajuan
                </button>
            </div>
        </form>
    </div>
</div>

</body>
</html>