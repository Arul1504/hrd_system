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

// Menampilkan pesan dari URL
$message = '';
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'success') {
        $message = '<div class="alert success">Karyawan berhasil ditambahkan!</div>';
    } elseif ($_GET['status'] == 'deleted') {
        $message = '<div class="alert success">Karyawan berhasil dihapus!</div>';
    } elseif ($_GET['status'] == 'updated') {
        $message = '<div class="alert success">Data karyawan berhasil diperbarui!</div>';
    }
}

// Logika untuk pencarian dan filter
$search_query = isset($_GET['search']) ? $_GET['search'] : '';
$filter_dept = isset($_GET['departemen']) ? $_GET['departemen'] : '';
$filter_jabatan = isset($_GET['jabatan']) ? $_GET['jabatan'] : '';
$filter_status_pegawai = isset($_GET['status_pegawai']) ? $_GET['status_pegawai'] : '';
$filter_tipe_karyawan = isset($_GET['tipe_karyawan']) ? $_GET['tipe_karyawan'] : '';

// Bangun query SQL
$sql = "SELECT * FROM karyawan WHERE 1=1";
$params = [];
$types = '';

if (!empty($search_query)) {
    $sql .= " AND (nik LIKE ? OR nama LIKE ?)";
    $search_param = "%" . $search_query . "%";
    $params[] = &$search_param;
    $params[] = &$search_param;
    $types .= "ss";
}
if (!empty($filter_dept)) {
    $sql .= " AND departemen = ?";
    $params[] = &$filter_dept;
    $types .= "s";
}
if (!empty($filter_jabatan)) {
    $sql .= " AND jabatan = ?";
    $params[] = &$filter_jabatan;
    $types .= "s";
}
if (!empty($filter_status_pegawai)) {
    $sql .= " AND status_pegawai = ?";
    $params[] = &$filter_status_pegawai;
    $types .= "s";
}
if ($filter_tipe_karyawan === 'Internal') {
    $sql .= " AND status_pegawai = 'Internal'";
} elseif ($filter_tipe_karyawan === 'Eksternal') {
    $sql .= " AND status_pegawai IN ('PKWT', 'Mitra')";
} elseif ($filter_tipe_karyawan === 'Outsource') {
    $sql .= " AND status_kerja = 'Outsourcing'";
}

// Siapkan dan jalankan prepared statement
$stmt = $conn->prepare($sql);
if ($stmt) {
    if (!empty($params)) {
        $bind_params = array_merge([$types], $params);
        call_user_func_array([$stmt, 'bind_param'], $bind_params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $employees = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $employees = [];
    echo "Error: " . $conn->error;
}

// Ambil daftar unik departemen, jabatan, dan status_pegawai
$departments_result = $conn->query("SELECT DISTINCT departemen FROM karyawan ORDER BY departemen");
$jabatan_result = $conn->query("SELECT DISTINCT jabatan FROM karyawan ORDER BY jabatan");
$status_pegawai_result = $conn->query("SELECT DISTINCT status_pegawai FROM karyawan ORDER BY status_pegawai");

$all_departments = $departments_result ? $departments_result->fetch_all(MYSQLI_ASSOC) : [];
$all_jabatan = $jabatan_result ? $jabatan_result->fetch_all(MYSQLI_ASSOC) : [];
$all_status_pegawai = $status_pegawai_result ? $status_pegawai_result->fetch_all(MYSQLI_ASSOC) : [];

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Karyawan</title>
    <link rel="stylesheet" href="../style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .action-buttons {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .download-btn {
            padding: 8px 14px;
            border-radius: 6px;
            background: #3498db;
            color: #fff;
            text-decoration: none;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: background 0.2s ease-in-out;
        }

        .download-btn:hover {
            background: #2980b9;
        }

        .download-btn i {
            font-size: 16px;
        }

        .download-btn:nth-child(1) {
            background: #e74c3c;
        }

        /* merah untuk PDF */
        .download-btn:nth-child(1):hover {
            background: #c0392b;
        }

        .download-btn:nth-child(2) {
            background: #27ae60;
        }

        /* hijau untuk Excel */
        .download-btn:nth-child(2):hover {
            background: #1e8449;
        }

        .sidebar-nav .dropdown-trigger {
            position: relative;
        }

        .sidebar-nav .dropdown-link {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .sidebar-nav .dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            min-width: 200px;
            background-color: #2c3e50;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            padding: 0;
            margin: 0;
            list-style: none;
            z-index: 1000;
            border-radius: 0 0 8px 8px;
            overflow: hidden;
        }

        .sidebar-nav .dropdown-menu li a {
            padding: 12px 20px;
            display: block;
            color: #ecf0f1;
            text-decoration: none;
            transition: background-color 0.3s;
        }

        .sidebar-nav .dropdown-menu li a:hover {
            background-color: #34495e;
        }

        .sidebar-nav .dropdown-trigger:hover .dropdown-menu {
            display: block;
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
                <div class="user-avatar">SN</div>
                <div class="user-details">
                    <p class="user-name">Siti Nurhaliza</p>
                    <p class="user-id">HRD001</p>
                    <p class="user-role">HR Manager</p>
                </div>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="../index.php"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li class="active dropdown-trigger">
                        <a href="#" class="dropdown-link"><i class="fas fa-users"></i> Data Karyawan <i
                                class="fas fa-caret-down"></i></a>
                        <ul class="dropdown-menu">
                            
                            <li><a href="karyawan_nonaktif.php">Non-Aktif</a></li>
                        </ul>
                    </li>
                    <li><a href="#"><i class="fas fa-edit"></i> Kelola Pengajuan</a></li>
                    <li><a href="../monitoring_kontrak/monitoring_kontrak.php"><i class="fas fa-calendar-alt"></i> Monitoring Kontrak</a></li>
                    <li><a href="../payslip/e_payslip_hrd.php"><i class="fas fa-money-check-alt"></i> E-Pay Slip</a></li>
                    
                </ul>
            </nav>
            <div class="logout-link">
                <a href="#"><i class="fas fa-sign-out-alt"></i> Keluar</a>
            </div>
        </aside>

       
    </div>
</body>
</html>