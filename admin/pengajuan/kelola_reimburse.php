<?php


require '../config.php';

// Fungsi bantuan untuk HTML escaping
if (!function_exists('e')) {
    function e($text)
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

// Periksa hak akses ADMIN
if (!isset($_SESSION['id_karyawan']) || $_SESSION['role'] !== 'ADMIN') {
    header("Location: ../../index.php");
    exit();
}

// Ambil data user dari sesi untuk sidebar
$id_karyawan_admin = $_SESSION['id_karyawan'];
$nama_user_admin = $_SESSION['nama'];
$role_user_admin = $_SESSION['role'];

$stmt_admin_info = $conn->prepare("SELECT nik_ktp, jabatan FROM karyawan WHERE id_karyawan = ?");
$stmt_admin_info->bind_param("i", $id_karyawan_admin);
$stmt_admin_info->execute();
$result_admin_info = $stmt_admin_info->get_result();
$admin_info = $result_admin_info->fetch_assoc();

if ($admin_info) {
    $nik_user_admin = $admin_info['nik_ktp'];
    $jabatan_user_admin = $admin_info['jabatan'];
} else {
    $nik_user_admin = 'Tidak Ditemukan';
    $jabatan_user_admin = 'Tidak Ditemukan';
}
$stmt_admin_info->close();

// Logika untuk menampilkan pesan
$message = '';
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'approved') {
        $message = '<div class="alert success">Pengajuan Reimburse berhasil disetujui.</div>';
    } elseif ($_GET['status'] == 'rejected') {
        $message = '<div class="alert error">Pengajuan Reimburse berhasil ditolak.</div>';
    }
}

// Ambil data pengajuan HANYA JENIS REIMBURSE, DENGAN LOG NOTES TERAKHIR
$sql_pengajuan = "
    SELECT 
        p.*, k.nama_karyawan, k.proyek, k.alamat_email, k.nik_ktp,
        -- Subquery untuk mendapatkan catatan (notes) terakhir dari pengajuan_log
        (SELECT notes FROM pengajuan_log AS pl 
         WHERE pl.id_pengajuan = p.id_pengajuan 
         ORDER BY pl.tanggal DESC 
         LIMIT 1) AS last_notes
    FROM pengajuan p
    LEFT JOIN karyawan k ON p.id_karyawan = k.id_karyawan
    WHERE p.jenis_pengajuan = 'Reimburse'
    ORDER BY p.tanggal_diajukan DESC
";

$result_pengajuan = $conn->query($sql_pengajuan);

// Ambil data untuk badge di sidebar, KECUALI Reimburse
$sql_pending_requests = "SELECT COUNT(*) AS total_pending FROM pengajuan WHERE status_pengajuan = 'Menunggu' AND jenis_pengajuan != 'Reimburse'";
$result_pending_requests = $conn->query($sql_pending_requests);
$total_pending = $result_pending_requests->fetch_assoc()['total_pending'] ?? 0;

// Query BARU untuk MENGHITUNG HANYA Reimburse yang Menunggu
$sql_pending_reimburse = "SELECT COUNT(*) AS total_pending FROM pengajuan WHERE jenis_pengajuan = 'Reimburse' AND status_pengajuan = 'Menunggu'";
$result_pending_reimburse = $conn->query($sql_pending_reimburse);
$total_pending_reimburse = $result_pending_reimburse->fetch_assoc()['total_pending'] ?? 0;
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Reimburse</title>
    <link rel="stylesheet" href="../style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .data-table-container {
            margin-top: 20px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9em;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #2e82d6ff;
            font-weight: 600;
        }
        
        .action-buttons a, .action-buttons button {
            padding: 6px 10px;
            font-size: 13px;
            border-radius: 4px;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: #fff;
            line-height: 1;
        }
        
        /* Warna Tombol */
        .approve-btn { background-color: #2ecc71; }
        .reject-btn { background-color: #e74c3c; }
        .detail-btn { background-color: #f39c12; }
        
        /* Detail Row (Toggle) */
        .detail-row {
            background-color: #f4f4f4;
            display: none; 
        }
        .detail-content {
            padding: 15px 20px;
            font-size: 0.9em;
        }
        .item-list {
            list-style: none;
            padding: 0;
            margin-bottom: 15px;
        }
        .item-list li {
            padding: 5px 0;
            border-bottom: 1px dotted #ccc;
        }
        
        /* Kwitansi Button Style in Detail Row */
        .kwitansi-btn-detail {
            padding: 3px 6px; 
            font-size: 11px; 
            margin-left: 10px; 
            line-height: 1.2;
            color: #fff;
            background-color: #3498db;
            border-radius: 3px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        
        /* Style untuk log persetujuan */
        .approval-log-container { margin-top: 20px; padding-top: 10px; border-top: 1px solid #ddd; }
        .log-table th, .log-table td { padding: 8px 10px; }
        .log-table th { background-color: #a9d1f9ff; }
        .log-table td:nth-child(3) { white-space: nowrap; } 
        
        /* Tambahkan style untuk kolom notes */
        .notes-column {
            max-width: 250px; /* Batasi lebar kolom notes */
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
           .badge { background: #ef4444; color: #fff; padding: 2px 8px; border-radius: 999px; font-size: 12px; }

        /* Style untuk Sidebar dan Dropdown (Tidak diubah, hanya memastikan konsistensi) */
        .sidebar-nav .dropdown-menu { display: none; }
        .sidebar-nav .dropdown-trigger:hover .dropdown-menu { display: block; }
    </style>
    </style>
</head>

<body>
    <div class="container">
        <aside class="sidebar">
            <div>
                <div class="company-brand">
                    <img src="../image/manu.png" alt="Logo PT Mandiri Andalan Utama" class="company-logo">
                    <p class="company-name">PT Mandiri Andalan Utama</p>
                </div>
                <div class="user-info">
                    <div class="user-avatar"><?= e(strtoupper(substr($nama_user_admin, 0, 2))) ?></div>
                    <div class="user-details">
                        <p class="user-name"><?= e($nama_user_admin) ?></p>
                        <p class="user-id"><?= e($nik_user_admin) ?></p>
                        <p class="user-role"><?= e($role_user_admin) ?></p>
                    </div>
                </div>
                <nav class="sidebar-nav">
                    <ul>
                        <li><a href="../dashboard_admin.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <li><a href="../absensi/absensi.php"><i class="fas fa-edit"></i> Absensi </span></a></li>
                        <li class="dropdown-trigger">
                            <a href="#" class="dropdown-link"><i class="fas fa-users"></i> Data Pengajuan <i class="fas fa-caret-down"><span class="badge"><?= $total_pending ?></span></i></a>
                            <ul class="dropdown-menu">
                                <li><a href="../pengajuan/pengajuan.php">Pengajuan</a></li>
                                <li><a href="../pengajuan/kelola_pengajuan.php">Kelola Pengajuan<span class="badge"><?= $total_pending ?></span></a></li>
                                <li><a href="../pengajuan/kelola_reimburse.php">Kelola Reimburse<span class="badge"><?= $total_pending_reimburse ?></span></a></li>
                            </ul>
                        </li>
                        <li><a href="../monitoring_kontrak/monitoring_kontrak.php"><i class="fas fa-calendar-alt"></i> Monitoring Kontrak</a></li>
                        <li><a href="../payslip/e_payslip_admin.php"><i class="fas fa-money-check-alt"></i> E-Pay Slip</a></li>
                        <li><a href="../invoice/invoice.php"><i class="fas fa-money-check-alt"></i> Invoice</a></li>
                    </ul>
                </nav>
                <div class="logout-link">
                    <a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a>
                </div>
        </aside>

        <main class="main-content">
            <header class="main-header">
                <h1>Kelola Reimbursement</h1>
                <p>Daftar pengajuan Reimbursement multi-item yang menunggu persetujuan.</p>
            </header>

            <?= $message ?>

            <div class="data-table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Karyawan</th>
                            <th>Tanggal Reimb.</th>
                            <th>Total Item</th>
                            <th>Status</th>
                            <th>Notes Persetujuan</th> <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result_pengajuan->num_rows > 0): ?>
                            <?php while ($row = $result_pengajuan->fetch_assoc()): 
                                
                                $item_count = substr_count($row['keterangan'], '|~|') + 1;
                                
                                $total_nominal_display = 0;
                                $detail_html = '';
                                
                                // --- HEADER DETAIL ---
                                $detail_html .= '<h4>Rincian Pengajuan Reimbursement ID: ' . e($row['id_pengajuan']) . '</h4>';
                                $detail_html .= '<p><strong>Diajukan Oleh:</strong> ' . e($row['nama_karyawan']) . ' (NIK: ' . e($row['nik_ktp']) . ')</p>';
                                $detail_html .= '<p><strong>Project:</strong> ' . e($row['proyek']) . '</p>';

                                $detail_html .= '<h4>Rincian Item (' . $item_count . ' item)</h4>';
                                $detail_html .= '<ul class="item-list">';
                                
                                // --- PROSES LOOP ITEM DAN EKSTRAKSI DATA ---
                                $reimburse_items = explode(' |~| ', $row['keterangan']);
                                foreach($reimburse_items as $item) {
                                    if (empty(trim($item))) continue; 

                                    $item_parts = explode(' | ', $item);
                                    
                                    $deskripsi = e(trim($item_parts[0] ?? ''));
                                    $nominal_part = e(trim($item_parts[1] ?? 'Nominal: Rp 0'));
                                    $kwitansi_part = e(trim($item_parts[2] ?? 'Kwitansi:'));

                                    // Ekstraksi Nominal
                                    $nominal_match = [];
                                    preg_match('/(Rp [\d\.,]+)/', $nominal_part, $nominal_match);
                                    $nominal_display = $nominal_match[0] ?? 'N/A';
                                    
                                    $raw_nominal = str_replace(['Rp ', '.'], '', $nominal_display);
                                    $raw_nominal = str_replace(',', '.', $raw_nominal);
                                    $current_nominal = floatval($raw_nominal);
                                    $total_nominal_display += $current_nominal;

                                    // Ekstraksi Nama File Kwitansi
                                    $file_name = '';
                                    if (strpos($kwitansi_part, 'Kwitansi:') === 0) {
                                        $file_name = trim(str_replace('Kwitansi:', '', $kwitansi_part));
                                    }

                                    // Buat Tombol Unduh
                                    $download_button = '';
                                    if (!empty($file_name)) {
                                        $download_path = '../../uploads/' . $file_name;
                                        $download_button = ' 
                                            <a href="' . $download_path . '" target="_blank" download 
                                               class="kwitansi-btn-detail"
                                               title="Unduh Kwitansi ' . $file_name . '">
                                               <i class="fas fa-download"></i>
                                            </a>';
                                    }

                                    // Tampilkan rincian item dengan tombol di samping nominal
                                    $detail_html .= '
                                        <li style="display: flex; justify-content: space-between; align-items: center; padding-right: 0;">
                                            <span style="flex-grow: 1;">' . $deskripsi . '</span>
                                            <span style="font-weight: 600; white-space: nowrap;">
                                                ' . $nominal_display . $download_button . '
                                            </span>
                                        </li>';
                                }
                                
                                $detail_html .= '</ul>';
                                $detail_html .= '<p><strong>TOTAL KESELURUHAN: ' . number_format($total_nominal_display, 0, ',', '.') . '</strong></p>';
                                
                                // Placeholder untuk Log Persetujuan
                                $detail_html .= '<div id="log-' . e($row['id_pengajuan']) . '"></div>';
                                
                                $status_style = strtolower($row['status_pengajuan']);
                                
                                // Tentukan Notes yang akan ditampilkan di kolom Notes
                                if ($row['status_pengajuan'] === 'Menunggu') {
                                    $display_notes = "Menunggu aksi...";
                                } elseif (!empty($row['last_notes'])) {
                                    $display_notes = e($row['last_notes']);
                                } else {
                                    $display_notes = e($row['status_pengajuan']);
                                }
                            ?>
                                <tr id="row-<?= e($row['id_pengajuan']) ?>">
                                    <td><?= e($row['id_pengajuan']) ?></td>
                                    <td><?= e($row['nama_karyawan']) ?></td>
                                    <td><?= e(date('d M Y', strtotime($row['tanggal_mulai']))) ?></td>
                                    <td><?= $item_count ?> item</td>
                                    <td>
                                        <span class="status-badge status-<?= $status_style ?>">
                                            <?= e($row['status_pengajuan']) ?>
                                        </span>
                                    </td>
                                    <td class="notes-column" title="<?= $display_notes ?>">
                                        <?= $display_notes ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 5px;">
                                            <button type="button" class="action-btn detail-btn" title="Lihat Detail & Riwayat Persetujuan"
                                                onclick="toggleDetailRow(<?= e($row['id_pengajuan']) ?>, true)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <?php if ($row['status_pengajuan'] === 'Menunggu'): ?>
                                                <a href="#" class="action-btn approve-btn" title="Setujui"
                                                    onclick="processAction('approve', '<?= e($row['id_pengajuan']) ?>'); return false;">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                                <a href="#" class="action-btn reject-btn" title="Tolak"
                                                    onclick="processAction('reject', '<?= e($row['id_pengajuan']) ?>'); return false;">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <tr id="detail-<?= e($row['id_pengajuan']) ?>" class="detail-row">
                                    <td colspan="7"> <div class="detail-content">
                                            <?= $detail_html ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align:center;">Tidak ada pengajuan Reimbursement ditemukan.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    <div id="loadingOverlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); color: white; z-index: 10000; justify-content: center; align-items: center; flex-direction: column; gap: 15px; font-family: 'Poppins', sans-serif;">
        <div class="loader-spinner" style="width: 50px; height: 50px; border: 5px solid #f3f3f3; border-top: 5px solid #3498db; border-radius: 50%; animation: spin 1s linear infinite;"></div>
        <h3>Memproses Pengajuan...</h3>
        <p>Mohon tunggu sebentar, sistem sedang memperbarui status.</p>
    </div>
    <script>
        function processAction(action, id) {
            let notes = prompt(`Masukkan catatan untuk ${action === 'approve' ? 'Persetujuan' : 'Penolakan'} (wajib diisi):`);
            
            if (notes === null || notes.trim() === "") {
                alert("Aksi dibatalkan. Catatan wajib diisi.");
                return; 
            }
            
            const encodedNotes = encodeURIComponent(notes.trim());
            const url = `process_pengajuan.php?action=${action}&id=${id}&notes=${encodedNotes}`;

            document.getElementById('loadingOverlay').style.display = 'flex';

            setTimeout(function () {
                window.location.href = url;
            }, 50); 
        }

        function fetchApprovalLog(id) {
            const logContainer = document.getElementById('log-' + id);
            logContainer.innerHTML = '<p style="text-align: center; color: #555;">Memuat riwayat persetujuan...</p>';
            
            fetch('fetch_approval_log.php?id=' + id)
                .then(response => response.text())
                .then(data => {
                    logContainer.innerHTML = data;
                })
                .catch(error => {
                    logContainer.innerHTML = '<p style="color: red; text-align: center;">Gagal memuat log persetujuan.</p>';
                    console.error('Error fetching log:', error);
                });
        }

        function toggleDetailRow(id, shouldFetchLog = false) {
            const detailRow = document.getElementById('detail-' + id);
            const isVisible = detailRow.style.display === 'table-row';
            
            document.querySelectorAll('.detail-row').forEach(row => {
                if (row.id !== 'detail-' + id) {
                    row.style.display = 'none';
                }
            });

            if (!isVisible) {
                detailRow.style.display = 'table-row';
                if (shouldFetchLog) {
                    fetchApprovalLog(id);
                }
            } else {
                detailRow.style.display = 'none';
            }
        }
        
        function showLoadingAndRedirect(url) {
            document.getElementById('loadingOverlay').style.display = 'flex';
            setTimeout(function () {
                window.location.href = url;
            }, 50);
        }
    </script>
</body>

</html>