<?php
// ============================
// edit_employee.php (FINAL SEDERHANA SEMUA KOLOM)
// ============================

$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "db_hrd2";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Koneksi gagal: " . $conn->connect_error); }

$id_karyawan = isset($_GET['id']) ? intval($_GET['id']) : (isset($_GET['id_karyawan']) ? intval($_GET['id_karyawan']) : 0);
if ($id_karyawan <= 0) { echo "Parameter ID tidak valid."; exit; }

$stmt = $conn->prepare("SELECT * FROM karyawan WHERE id_karyawan = ?");
$stmt->bind_param("i", $id_karyawan);
$stmt->execute();
$res = $stmt->get_result();
$employee = $res->fetch_assoc();
$stmt->close();
$conn->close();

if (!$employee) { echo "Data karyawan tidak ditemukan."; exit; }

function e($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

$allColumns = array_keys($employee);
// Buang kolom id_karyawan
$allColumns = array_filter($allColumns, fn($c) => $c !== 'id_karyawan');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Edit Data Karyawan - <?= e($employee['nama_karyawan'] ?? '') ?></title>
<link rel="stylesheet" href="../style.css">
<style>
.form-container{background:#fff;border-radius:12px;padding:20px;box-shadow:0 4px 12px rgba(0,0,0,.05)}
.form-section{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.form-group label{font-weight:600;margin-bottom:6px;display:block}
.form-group input[type=text],
.form-group input[type=date],
.form-group input[type=email],
.form-group textarea,
.form-group select{padding:10px;border:1px solid #ddd;border-radius:8px;background:#f9f9f9;font:inherit;width:100%}
.submit-btn{background:#28a745;color:#fff;padding:12px 16px;border:none;border-radius:8px;font-weight:600;cursor:pointer}
.submit-btn:hover{background:#218838}
@media(max-width:768px){.form-section{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="container">
    <main class="main-content">
        <h1>Edit Data Karyawan</h1>

        <div class="form-container">
            <form action="process_edit_employee.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id_karyawan" value="<?= e($employee['id_karyawan']) ?>">

                <div class="form-section">
                    <?php foreach ($allColumns as $col): ?>
                        <div class="form-group">
                            <label><?= ucwords(str_replace('_',' ', $col)) ?></label>
                            <?php if ($col === 'alamat' || $col === 'alamat_tinggal'): ?>
                                <textarea name="<?= e($col) ?>"><?= e($employee[$col]) ?></textarea>
                            <?php elseif ($col === 'alamat_email'): ?>
                                <input type="email" name="<?= e($col) ?>" value="<?= e($employee[$col]) ?>">
                            <?php elseif (strpos($col,'tanggal') !== false || strpos($col,'tgl') !== false || strpos($col,'date') !== false): ?>
                                <input type="date" name="<?= e($col) ?>" value="<?= e($employee[$col]) ?>">
                            <?php elseif ($col === 'foto_path'): ?>
                                <input type="file" name="foto" accept="image/*">
                                <?php if (!empty($employee[$col])): ?>
                                    <small>Foto saat ini: <?= e($employee[$col]) ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                <input type="text" name="<?= e($col) ?>" value="<?= e($employee[$col]) ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="margin-top:20px;">
                    <button type="submit" class="submit-btn">Simpan Perubahan</button>
                    <a href="all_employees.php" class="submit-btn" style="background:#777;margin-left:10px;">Batal</a>
                </div>
            </form>
        </div>
    </main>
</div>
</body>
</html>
