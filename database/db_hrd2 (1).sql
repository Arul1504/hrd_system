-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 27, 2025 at 05:31 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_hrd2`
--

-- --------------------------------------------------------

--
-- Table structure for table `absensi`
--

CREATE TABLE `absensi` (
  `id_absensi` bigint(20) UNSIGNED NOT NULL,
  `id_karyawan` bigint(20) UNSIGNED NOT NULL,
  `nik_karyawan` varchar(20) NOT NULL,
  `tanggal` date NOT NULL,
  `jam_masuk` time DEFAULT NULL,
  `jam_pulang` time DEFAULT NULL,
  `alamat_masuk` text DEFAULT NULL,
  `alamat_pulang` text DEFAULT NULL,
  `lat_masuk` varchar(50) DEFAULT NULL,
  `lon_masuk` varchar(50) DEFAULT NULL,
  `lat_pulang` varchar(50) DEFAULT NULL,
  `lon_pulang` varchar(50) DEFAULT NULL,
  `status_absensi` varchar(50) DEFAULT 'Tidak Hadir',
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `provinsi` varchar(100) DEFAULT NULL,
  `zona_waktu` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `absensi`
--

INSERT INTO `absensi` (`id_absensi`, `id_karyawan`, `nik_karyawan`, `tanggal`, `jam_masuk`, `jam_pulang`, `alamat_masuk`, `alamat_pulang`, `lat_masuk`, `lon_masuk`, `lat_pulang`, `lon_pulang`, `status_absensi`, `keterangan`, `created_at`, `updated_at`, `provinsi`, `zona_waktu`) VALUES
(25, 10, '2323457432345431', '2025-09-26', '17:21:35', '17:24:35', 'Jalan Vila Ubud, Ubud Village, Sudimara Timur, Ciledug, Tangerang, Banten, Java, 15151, Indonesia', 'Jalan Vila Ubud, Ubud Village, Sudimara Timur, Ciledug, Tangerang, Banten, Java, 15151, Indonesia', '-6.2300532', '106.7221359', '-6.2300466', '106.7221577', 'Terlambat', NULL, '2025-09-26 10:21:35', '2025-09-26 10:24:36', 'Banten', 'Asia/Jakarta'),
(26, 11, '3330199201992999', '2025-09-26', '17:24:53', NULL, 'Jalan Vila Ubud, Ubud Village, Sudimara Timur, Ciledug, Tangerang, Banten, Java, 15154, Indonesia', NULL, '-6.229844979817402', '106.72253985059464', NULL, NULL, 'Terlambat', NULL, '2025-09-26 10:24:53', '2025-09-26 10:25:39', 'Banten', 'Asia/Jakarta'),
(27, 28, '7613876376187837', '2025-09-26', '19:41:38', '19:41:42', 'SCBD Fairgrounds, Sudirman Central Business District Eastway, RW 03, Senayan, Kebayoran Baru, South Jakarta, Special capital Region of Jakarta, Java, 12190, Indonesia', 'SCBD Fairgrounds, Sudirman Central Business District Eastway, RW 03, Senayan, Kebayoran Baru, South Jakarta, Special capital Region of Jakarta, Java, 12190, Indonesia', '-6.22592', '106.807296', '-6.22592', '106.807296', 'Terlambat', NULL, '2025-09-26 10:41:39', '2025-09-26 10:41:43', 'maluku', 'Asia/Jayapura');

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id_invoice` int(11) NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `invoice_date` date NOT NULL,
  `project_key` varchar(10) NOT NULL,
  `bill_to_bank` varchar(100) NOT NULL,
  `bill_to_address1` varchar(255) DEFAULT NULL,
  `bill_to_address2` varchar(255) DEFAULT NULL,
  `bill_to_address3` varchar(255) DEFAULT NULL,
  `person_up_name` varchar(100) DEFAULT NULL,
  `person_up_title` varchar(100) DEFAULT NULL,
  `sub_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `mgmt_fee_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `mgmt_fee_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `ppn_percent` decimal(5,2) NOT NULL DEFAULT 11.00,
  `ppn_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `transfer_bank` varchar(100) NOT NULL DEFAULT 'CIMB Niaga',
  `transfer_account_no` varchar(50) NOT NULL DEFAULT '800140878000',
  `transfer_account_name` varchar(100) NOT NULL DEFAULT 'PT Mandiri Andalan Utama',
  `footer_date` date NOT NULL,
  `manu_signatory_name` varchar(100) NOT NULL DEFAULT 'Oktafian Farhan',
  `manu_signatory_title` varchar(100) NOT NULL DEFAULT 'Direktur Utama',
  `status_pembayaran` enum('Belum Dibayar','Sudah Dibayar','Batal') NOT NULL DEFAULT 'Belum Dibayar',
  `created_by_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoice_items`
--

CREATE TABLE `invoice_items` (
  `id_item` int(11) NOT NULL,
  `id_invoice` int(11) NOT NULL,
  `item_number` int(3) NOT NULL COMMENT 'Nomor urut item di dalam invoice (1, 2, 3, dst)',
  `description` text NOT NULL COMMENT 'Deskripsi item/jasa yang ditagih',
  `amount` decimal(15,2) NOT NULL COMMENT 'Jumlah nominal untuk item ini'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `karyawan`
--

CREATE TABLE `karyawan` (
  `id_karyawan` bigint(20) UNSIGNED NOT NULL,
  `nama_karyawan` varchar(255) DEFAULT NULL,
  `jabatan` varchar(100) DEFAULT NULL,
  `jenis_kelamin` varchar(50) DEFAULT NULL,
  `tempat_lahir` varchar(100) DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `alamat` varchar(255) DEFAULT NULL,
  `alamat_tinggal` varchar(255) DEFAULT NULL,
  `rt_rw` varchar(10) DEFAULT NULL,
  `kelurahan` varchar(100) DEFAULT NULL,
  `kecamatan` varchar(100) DEFAULT NULL,
  `kota_kabupaten` varchar(100) DEFAULT NULL,
  `nik_ktp` varchar(20) DEFAULT NULL,
  `pendidikan_terakhir` varchar(100) DEFAULT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `alamat_email` varchar(255) DEFAULT NULL,
  `no_kk` varchar(20) DEFAULT NULL,
  `nama_ayah` varchar(100) DEFAULT NULL,
  `nama_ibu` varchar(100) DEFAULT NULL,
  `nik_karyawan` varchar(50) DEFAULT NULL,
  `nip` varchar(50) DEFAULT NULL,
  `penempatan` varchar(100) DEFAULT NULL,
  `nama_user` varchar(100) DEFAULT NULL,
  `kota` varchar(100) DEFAULT NULL,
  `area` varchar(100) DEFAULT NULL,
  `nomor_kontrak` varchar(100) DEFAULT NULL,
  `tanggal_pembuatan_pks` date DEFAULT NULL,
  `nomor_surat_tugas` varchar(100) DEFAULT NULL,
  `masa_penugasan` varchar(100) DEFAULT NULL,
  `tgl_aktif_masuk` date DEFAULT NULL,
  `join_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `end_date_pks` date DEFAULT NULL,
  `end_of_contract` date DEFAULT NULL,
  `status_karyawan` varchar(50) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `tgl_resign` date DEFAULT NULL,
  `cabang` varchar(100) DEFAULT NULL,
  `job` varchar(100) DEFAULT NULL,
  `channel` varchar(100) DEFAULT NULL,
  `tgl_rmu` date DEFAULT NULL,
  `nomor_rekening` varchar(50) DEFAULT NULL,
  `nama_bank` varchar(50) DEFAULT NULL,
  `gapok` decimal(10,2) DEFAULT NULL,
  `umk_ump` decimal(10,2) DEFAULT NULL,
  `tanggal_pernyataan` date DEFAULT NULL,
  `npwp` varchar(50) DEFAULT NULL,
  `status_pajak` varchar(50) DEFAULT NULL,
  `recruitment_officer` varchar(100) DEFAULT NULL,
  `team_leader` varchar(100) DEFAULT NULL,
  `recruiter` varchar(100) DEFAULT NULL,
  `tl` varchar(100) DEFAULT NULL,
  `manager` varchar(100) DEFAULT NULL,
  `nama_sm` varchar(100) DEFAULT NULL,
  `nama_sh` varchar(100) DEFAULT NULL,
  `sales_code` varchar(50) DEFAULT NULL,
  `nomor_reff` varchar(50) DEFAULT NULL,
  `no_bpjamsostek` varchar(50) DEFAULT NULL,
  `no_bpjs_kes` varchar(50) DEFAULT NULL,
  `role` enum('HRD','KARYAWAN','ADMIN') DEFAULT NULL,
  `proyek` enum('ALLO','MOLADIN','NOBU','CIMB','CNAF','BNIF','SMBCI','INTERNAL') DEFAULT NULL,
  `surat_tugas` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `karyawan`
--

INSERT INTO `karyawan` (`id_karyawan`, `nama_karyawan`, `jabatan`, `jenis_kelamin`, `tempat_lahir`, `tanggal_lahir`, `alamat`, `alamat_tinggal`, `rt_rw`, `kelurahan`, `kecamatan`, `kota_kabupaten`, `nik_ktp`, `pendidikan_terakhir`, `no_hp`, `alamat_email`, `no_kk`, `nama_ayah`, `nama_ibu`, `nik_karyawan`, `nip`, `penempatan`, `nama_user`, `kota`, `area`, `nomor_kontrak`, `tanggal_pembuatan_pks`, `nomor_surat_tugas`, `masa_penugasan`, `tgl_aktif_masuk`, `join_date`, `end_date`, `end_date_pks`, `end_of_contract`, `status_karyawan`, `status`, `tgl_resign`, `cabang`, `job`, `channel`, `tgl_rmu`, `nomor_rekening`, `nama_bank`, `gapok`, `umk_ump`, `tanggal_pernyataan`, `npwp`, `status_pajak`, `recruitment_officer`, `team_leader`, `recruiter`, `tl`, `manager`, `nama_sm`, `nama_sh`, `sales_code`, `nomor_reff`, `no_bpjamsostek`, `no_bpjs_kes`, `role`, `proyek`, `surat_tugas`) VALUES
(9, 'cindy kartika', 'dgdg', 'Perempuan', 'jawa tengah', '2000-10-10', 'muara tabun', 'Desa Muara Tabun', '009/008', 'dnjg io', 'jhjdiu', 'tebi', '8976386529663971', 'smk', '081297386592', 'cindy@gmail.com', ' 5852576817576129', NULL, 'sumarni', NULL, NULL, NULL, NULL, NULL, NULL, '088685392', '2025-09-26', NULL, NULL, NULL, '2025-09-21', '2026-05-21', NULL, '2027-06-18', NULL, 'AKTIF', NULL, 'jakarta', NULL, NULL, NULL, '7921526', 'bca', 3000000.00, NULL, NULL, '63578378826', 'AKTIF', NULL, 'minaa', NULL, 'tl', 'astrid', NULL, NULL, NULL, NULL, NULL, NULL, 'KARYAWAN', 'INTERNAL', NULL),
(10, 'Puput', 'Manager', 'Perempuan', 'Jaksel', '2004-11-28', 'Jl. Ciledug Raya No.168, RT.10/RW.4, Ulujami, Kec. Pesanggrahan, Kota Jakarta Selatan, Daerah Khusus Ibukota Jakarta 12250', 'Jl. Ciledug Raya No.168, RT.10/RW.4, Ulujami, Kec. Pesanggrahan, Kota Jakarta Selatan, Daerah Khusus Ibukota Jakarta 12250', '004/005', 'Jaksel', 'Jaksel', 'KOTA JAKARTA SELATAN', '2323457432345431', 's1', '0978776666667', 'puput@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '123212323', '2025-09-22', NULL, NULL, NULL, '2025-10-01', '2025-10-11', NULL, '2025-10-11', NULL, 'AKTIF', NULL, 'Gandaria', NULL, NULL, NULL, '12345678', 'nobu', 1999999.00, NULL, NULL, '3212342', NULL, NULL, NULL, NULL, 'tl', 'yuni', NULL, NULL, NULL, NULL, NULL, NULL, 'HRD', 'INTERNAL', NULL),
(11, 'Arul rahmadan', 'hrd', 'Laki-laki', 'cilacap', '2004-02-18', 'Jl. Ciledug Raya No.168, RT.10/RW.4, Ulujami, Kec. Pesanggrahan, Kota Jakarta Selatan, Daerah Khusus Ibukota Jakarta 12250', 'Jl. Ciledug Raya No.168, RT.10/RW.4, Ulujami, Kec. Pesanggrahan, Kota Jakarta Selatan, Daerah Khusus Ibukota Jakarta 12250', '007/008', 'sudimara', 'sidareja', 'KOTA JAKARTA SELATAN', '3330199201992999', 's1', '08167267726716', 'arul12@gmail.com', '1234123443211234', 'ayahh', 'ibuu', NULL, NULL, NULL, NULL, NULL, NULL, '1234321', '2025-09-22', NULL, NULL, NULL, '2025-09-30', '2026-10-02', NULL, '2025-10-04', NULL, 'AKTIF', NULL, 'gandaria', NULL, NULL, NULL, '12345678', 'mandiri', 20000000.00, NULL, NULL, '1234321234', 'aktif', NULL, NULL, NULL, '221', 'yuni', NULL, NULL, NULL, NULL, NULL, NULL, 'ADMIN', 'INTERNAL', NULL),
(12, 'putri', 'gjdges', 'Perempuan', 'jawa tengah', '2025-09-22', 'bajgdugfavnm', NULL, '098/097', 'mdgajhdgui', 'bhjgh', 'nbdnsd', '1472758429573499', 'sma', '089757529772', 'put@gmail.com', '7585296734377892', 'jooko', 'suma', NULL, NULL, 'jawa', NULL, 'tangerang', 'cimno', '63624692', '2025-09-22', NULL, NULL, NULL, '2025-09-23', NULL, NULL, NULL, 'MITRA', 'AKTIF', '2025-10-01', NULL, NULL, NULL, NULL, '0979464', 'bca', NULL, NULL, NULL, NULL, NULL, 'nbbja', 'bdd', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ALLO', NULL),
(13, 'zidan', 'ffdsjh', 'Laki-laki', 'fgue', '2025-09-05', 'dhjgdwqh', NULL, '098/097', 'bajhsu', 'jhsgbw', 'jsbdjwq', '1792098765432789', 'smk', '08972483534', 'zi@gmail.com', '86352962927393729', 'bjhg', 'vshjd', NULL, NULL, NULL, NULL, NULL, NULL, '7384683', '2025-09-22', NULL, NULL, NULL, '2025-09-27', NULL, NULL, NULL, 'MITRA', 'AKTIF', '2025-09-24', 'hshh', NULL, NULL, NULL, '53873267', 'bca', NULL, NULL, NULL, NULL, NULL, NULL, 'jashdgaj', 'msbj', NULL, NULL, NULL, NULL, '621163', NULL, NULL, NULL, NULL, 'MOLADIN', NULL),
(15, 'bagol', 'gaajhj', 'Laki-laki', 'shss', '2025-09-03', 'sahbcjb', 'bshuhsj', '657/788', 'dwjbh', ' jbefenej', 'dbjdjgnj', '7893569879727282', 'sma', '089762649758', 'Gol@gmail.com', '64797985278738', 'vajjdg', 'snbnsj', '6761368718718783', NULL, NULL, NULL, NULL, NULL, '5744634', '2025-09-22', NULL, NULL, NULL, '2025-09-26', '2025-10-11', '2025-10-11', '2025-09-30', NULL, 'AKTIF', NULL, '46333', NULL, NULL, NULL, '9279246274', 'bri', NULL, 9.00, NULL, '923879884978', 'jhjdh', NULL, 'sfbkjsn', 'bshjdhjs', NULL, NULL, NULL, NULL, NULL, NULL, '686838', '7628482798', NULL, 'CNAF', NULL),
(16, 'cello', 'ghfj', 'Laki-laki', 'hvhfhf', '2025-09-12', 'hgjhj', 'dfhhjbnb', '867/786', 'cfghjj', 'fhjkhjkhj', 'gfghgjhj', '6587674898454678', 'smk', '089647479653', 'ce@gmail.com', '5894579886225689', 'fhjjk', 'ghfhg', '6774548934787354', '7565788', NULL, NULL, NULL, NULL, '23624', '2025-10-01', NULL, NULL, NULL, '2025-09-27', '2026-10-14', '2025-10-10', '2025-10-11', NULL, 'AKTIF', NULL, 'sbkans', NULL, NULL, NULL, '57857778', 'bni', NULL, 8.00, NULL, '568874', 'hjjj', NULL, 'gfygj', 'vhjbj', NULL, NULL, NULL, NULL, NULL, NULL, '47678', '4878', NULL, 'BNIF', NULL),
(17, 'along', 'mvkdf', 'Laki-laki', 'fhdgjshj', '2025-09-02', 'xnsbcnkd', 'bcghfghh', '356/768', 'nbdmnsb', 'dbnsbcjs', 'nbfjddf', '8973949387682978', 's1', '08968396752', 'long@gmail.com', '5878678268778927', 'bsvnd', ' ns', NULL, NULL, NULL, NULL, NULL, NULL, '894798749', '2025-09-09', NULL, NULL, NULL, '2025-09-23', '2025-10-10', NULL, '2025-10-10', NULL, 'AKTIF', NULL, 'dvsbjs', NULL, NULL, NULL, '6428723', 'bca', 5.00, NULL, NULL, '6872472847', 'sbjs', NULL, NULL, NULL, 'ghg', 'bvhg', NULL, NULL, NULL, NULL, NULL, NULL, 'KARYAWAN', 'INTERNAL', 'surat_tugas_17_68d11c6733b91.pdf'),
(18, 'resti', 'hsgjsh', 'Perempuan', 'aggjnsns', '2025-09-05', 'bsjbjksk', NULL, '744/903', 'hdjsjn', 'sbdjj', NULL, '5447827878992629', 'smk', '0877362536', 'res@gmail.com', '8695778686878673', 'dbjd', 'bdjsjs', '9789898786779811', NULL, 'shjdhd', 'res', 'sd ms', NULL, '268929789', '2025-09-22', '682872', 'bsnjsh', NULL, '2025-09-27', '2025-10-11', NULL, '2025-10-10', NULL, 'AKTIF', NULL, NULL, NULL, NULL, NULL, '728782699689889', 'bri', NULL, 2.00, '2025-10-07', '68279839', 'nsmjd', 'dbhd', 'djdj', NULL, NULL, NULL, NULL, NULL, NULL, '678287832', '727298392', '648798', NULL, 'SMBCI', NULL),
(19, 'lita', 'vhjja', 'Perempuan', 'gsjahjk', '2025-09-26', 'xbjk', NULL, '282/272', 'djgsd', 'dbsdhkj', 'dshdkj', '6287892588916979', 'sma', '088758957246', 'lita@gmail.com', '689797879889571', 'sjjka', 'djhdj', NULL, NULL, NULL, NULL, NULL, NULL, '68282', '2025-09-23', NULL, NULL, NULL, '2025-10-10', NULL, NULL, NULL, 'MITRA', 'AKTIF', '2025-09-26', 'nndmndj', 'vbbja', 'shjsh', '2025-09-12', '7868789', 'bca', NULL, NULL, NULL, NULL, NULL, NULL, 'dgshjdj', 'bsvbbsh', NULL, NULL, 'jhaj', 'hdj', '6787892', NULL, NULL, NULL, NULL, 'CIMB', NULL),
(20, 'anggraini', 'ahjs', 'Perempuan', 'adhvdhu', '2025-09-03', 'jsdgjhjk', NULL, '683/324', 'shjjdjdshuj', 'gdjhdhh', 'nbddj', '8139678678757828', 'sma', '08963968527', 'ang@gmail.com', '89176489858892589', 'hgsjhdi', 'vxhjh', NULL, NULL, 'hjssa', NULL, 'sbds', 'sabdja', '37127184', '2025-09-25', NULL, NULL, NULL, '2025-09-23', NULL, NULL, NULL, 'MITRA', 'AKTIF', '2025-10-10', NULL, NULL, NULL, NULL, '6298672', 'bca', NULL, NULL, NULL, NULL, NULL, 'hdjh', 'nbxjdjs', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ALLO', NULL),
(21, 'sinta', 'znxb', 'Perempuan', 'aghjd', '2025-09-24', 'agha', NULL, '767/877', 'bxchjks', 'bdsjhd', 'bdhjb', '6843389673698397', 'smk', '08962568682', 'sin@gmail.com', '8728362798789179', 'svdsh', ' nsbhj', NULL, NULL, NULL, NULL, NULL, NULL, '763274', '2025-09-28', NULL, NULL, NULL, '2025-09-25', NULL, NULL, NULL, 'PKWT', 'AKTIF', '2025-10-03', 'gdjh', NULL, NULL, NULL, '7278732', 'bca', NULL, NULL, NULL, NULL, NULL, NULL, 'xbsn', 'nsbh', NULL, NULL, NULL, NULL, '638727', NULL, NULL, NULL, NULL, 'MOLADIN', NULL),
(22, 'gibran', 'bzn', 'Laki-laki', 'bhjsh', '2025-09-09', 'asjh', NULL, '774/487', 'hsg', 'xbsnd', 'xsbhjsbj', '8628472846897768', 's1', '089675955365', 'gib@gmail.com', '687386896871573', ' zxn', 'hvjxagh', NULL, NULL, NULL, NULL, NULL, NULL, '36782', '2025-09-25', NULL, NULL, '2025-09-26', '2025-09-27', NULL, NULL, NULL, 'PKWT', 'AKTIF', '2025-09-30', 'baan', NULL, NULL, NULL, '5733871', 'bca', NULL, NULL, NULL, NULL, NULL, NULL, 'bsjg', 'sdbjh', NULL, NULL, NULL, NULL, '736278', NULL, NULL, NULL, NULL, 'NOBU', NULL),
(23, 'maulana', 'hdgui', 'Laki-laki', 'njsbjs', '2025-09-01', 'bxjhsj', NULL, '335/774', 'sbhjs', 'mxnj', 'xsnbs', '7487498364728947', 'd3', '089262578243', 'ma@gmail.com', '6328617738167181333', 'nbbdjs', 'nbsndj', NULL, NULL, NULL, NULL, NULL, NULL, '6272638', '2025-09-24', NULL, NULL, NULL, '2025-09-24', NULL, NULL, NULL, 'MITRA', 'AKTIF', '2025-10-10', 'jsjdjhs', 'sbjd', 'jsj', '2025-10-03', '2747427', 'bca', NULL, NULL, NULL, NULL, NULL, NULL, 'bsdjhsj', 'vvhjab', NULL, NULL, 'dbshdh', 'nbshd', '3353', NULL, NULL, NULL, NULL, 'CIMB', NULL),
(24, 'aulia', 'gah', 'Perempuan', 'bdshjds', '2025-09-02', 'nsmnn', 'dhgwhjs', '334/466', 'sbjabd', 'nxamjk', 'sndn', '3728947868286782', 's1', '08862596262', 'au@gmail.com', '73187381839398394', 'nbjbsj', 'nx sndb', '7367268262878789', NULL, NULL, NULL, NULL, NULL, '38782', '2025-09-17', NULL, NULL, NULL, '2025-09-26', '2025-09-25', '2025-10-09', '2025-10-08', NULL, 'TIDAK AKTIF', NULL, 'basjjahs', NULL, NULL, NULL, '68277827282324', 'bri', NULL, 3.00, NULL, '46873', 'xnnbxn', NULL, 'ncnebj', 'dnsbdj', NULL, NULL, NULL, NULL, NULL, NULL, '7268', '7298', NULL, 'CNAF', NULL),
(25, 'keysa', 'bsbdbsjdh', 'Perempuan', 'bjbj', '2004-07-02', 'bhjbdhjw', 'jdj', '283/937', 'dbnsdbjw', 'hwjgdhjw', 'bhjqw', '7868364735878961', 'sma', '08528375892', 'key@gmail.com', '89391898836738171', 'bdjhd', 'sbdj', '6373862766827665', '979379077863279', NULL, NULL, NULL, NULL, '63828367', '2026-03-07', NULL, NULL, NULL, '2025-09-24', '2025-10-10', '2025-10-11', '2025-10-10', NULL, 'AKTIF', NULL, 'jdjw', NULL, NULL, NULL, '2687278', 'bca', NULL, 5.00, NULL, '387289792', 'dbdjeh', NULL, ' dwhj', 'dbjsdh', NULL, NULL, NULL, NULL, NULL, NULL, '87372738', '686382', NULL, 'BNIF', NULL),
(26, 'siska', 'sjajshaj', 'Perempuan', 'hjhsjahdij', '2025-08-31', 'bnsdnsnk', NULL, '683/344', 'bshjd', 'nsbdhjs', NULL, '7367836265772717', 'smk', '08968659786', 'sis@gmail.com', '783917818977891', 'nbdhjsd', 'ssbjshjs', '8763286372468996', NULL, 'hdsh', 'sis', 'xnjsbhjs', NULL, '32876382', '2025-09-18', '73893', 'bjhjkd', NULL, '2025-09-23', '2025-10-06', NULL, '2025-10-09', NULL, 'AKTIF', NULL, NULL, NULL, NULL, NULL, '9889277289288', 'bri', NULL, 7.00, '2025-09-24', '778391', 'sjdjksjdk', 'bnsjksiw', 'hhsjksnj', NULL, NULL, NULL, NULL, NULL, NULL, '76487847264', '988973891', '187381', NULL, 'SMBCI', NULL),
(27, 'mutiara', 'hdvjs', 'Perempuan', 'hsdhs', '2025-09-09', 'hgdfh', 'dgdhw', '556/865', 'jdjshdj', 'djshjks', 'jdhsjsh', '9278469827732867', 's1', '0827672886982', 'rahmadansyahrul214@gmail.com', '9264829789898918', 'ndk', 'snjsh', NULL, NULL, NULL, NULL, NULL, NULL, '834738', '2025-09-17', NULL, NULL, NULL, '2025-10-03', '2025-11-01', NULL, '2025-10-07', NULL, 'AKTIF', NULL, 'djsnjc', NULL, NULL, NULL, '7487279', 'bca', 8.00, NULL, NULL, '83628', 'sn', NULL, NULL, NULL, 'sbd', 'djsh', NULL, NULL, NULL, NULL, NULL, NULL, 'KARYAWAN', 'INTERNAL', NULL),
(28, 'cindy', 'jhdjhdj', 'Perempuan', 'rimbo bujang', '2025-09-01', 'bdhsdjks', 'bdjdj', '978/090', ' wdjwhd', 'hugwdhw', 'hwguhiw', '7613876376187837', 'bdjksdksdjhjd', '085657671378786', 'Cindy@gmail.com', '787386989778724', 'hgdjd', 'gjdhjsk', NULL, NULL, NULL, NULL, NULL, NULL, '78434389', '2025-09-24', NULL, NULL, NULL, '2025-09-25', '2025-10-03', NULL, '2025-10-10', NULL, 'AKTIF', NULL, 'hghahjhs', NULL, NULL, NULL, '7879287', 'bca', 938.00, NULL, NULL, '7817398', 'jdhjd', NULL, NULL, NULL, 'hd', 'hjhd', NULL, NULL, NULL, NULL, NULL, NULL, 'KARYAWAN', 'INTERNAL', NULL),
(29, 'Bintang', 'hrd', 'Laki-laki', 'jakatra', '2025-09-17', 'Jl. Ciledug Raya No.168, RT.10/RW.4, Ulujami, Kec. Pesanggrahan, Kota Jakarta Selatan, Daerah Khusus Ibukota Jakarta 12250', 'Jl. Ciledug Raya No.168, RT.10/RW.4, Ulujami, Kec. Pesanggrahan, Kota Jakarta Selatan, Daerah Khusus Ibukota Jakarta 12250', '003/009', 'hakagd', 'dfsdgd', 'KOTA JAKARTA SELATAN', '1212121212121212', 's1', '098765432112', 'bintang@gmail.com', '12121212121211212', 'ayah', 'ibu', NULL, NULL, NULL, NULL, NULL, NULL, '1234567', '2025-09-18', NULL, NULL, NULL, '2025-10-02', '2025-09-27', NULL, '2025-10-11', NULL, 'AKTIF', NULL, 'Gandaria', NULL, NULL, NULL, '112345678', 'asdf', 60000.00, NULL, NULL, '1223455', 'aktig', NULL, NULL, NULL, 'tes', 'hgf', NULL, NULL, NULL, NULL, NULL, NULL, 'KARYAWAN', 'INTERNAL', NULL),
(30, 'puja', 'jhoto', 'Perempuan', 'hjgvjytf', '2025-09-24', 'kjhdfvblhue', 'wjefpiq', '000/123', 'shgdwy', 'kfm[if', 'tebi', '3434343434343434', 'jhubiy', '081297386592', 'hafysggs@gmail.com', '3434343434343434', 'kldsmg;', 'wnpl', NULL, NULL, NULL, NULL, NULL, NULL, '123456', '2025-09-24', NULL, NULL, NULL, '2025-09-24', '2025-10-03', NULL, '2025-10-08', NULL, 'AKTIF', NULL, 'jakarta', NULL, NULL, NULL, '7845297', 'ksdmf;aj', 678967.00, NULL, NULL, '7613476', 'nsvalg', NULL, NULL, NULL, '7hjv', ',mnavm;lgj', NULL, NULL, NULL, NULL, NULL, NULL, 'KARYAWAN', 'INTERNAL', NULL),
(31, 'intan', 'gfdjdfjddk', 'Perempuan', 'avabdan', '2025-09-02', 'sdbdjshdkj', 'whjehjkwhekjwgj', '467/787', 'dbshbsjnfjk', 'anbdkjnjkd', 'dbjnrje', '8968798768894789', 'bjahdkjhskiurh', '0809765432134', 'intan@gmail.com', '1234567890098765', 'nmdbdjbwk', 'dbwdjw', NULL, NULL, NULL, NULL, NULL, NULL, '82484798248', '2025-09-24', NULL, NULL, NULL, '2025-09-25', '2025-10-09', NULL, '2025-10-10', NULL, 'AKTIF', NULL, 'bskakj', NULL, NULL, NULL, '098765', 'bca', 7.00, NULL, NULL, '76284', 'bjkdwh', NULL, NULL, NULL, 'hvdg', 'bdjwbdj', NULL, NULL, NULL, NULL, NULL, NULL, 'KARYAWAN', 'INTERNAL', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `karyawan_nonaktif`
--

CREATE TABLE `karyawan_nonaktif` (
  `id_karyawan` int(11) NOT NULL,
  `nama_karyawan` varchar(255) NOT NULL,
  `jabatan` varchar(255) DEFAULT NULL,
  `jenis_kelamin` varchar(50) DEFAULT NULL,
  `tempat_lahir` varchar(255) DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `alamat_tinggal` text DEFAULT NULL,
  `rt_rw` varchar(10) DEFAULT NULL,
  `kelurahan` varchar(255) DEFAULT NULL,
  `kecamatan` varchar(255) DEFAULT NULL,
  `kota_kabupaten` varchar(255) DEFAULT NULL,
  `nik_ktp` varchar(20) DEFAULT NULL,
  `pendidikan_terakhir` varchar(50) DEFAULT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `alamat_email` varchar(255) DEFAULT NULL,
  `no_kk` varchar(255) DEFAULT NULL,
  `nama_ayah` varchar(255) DEFAULT NULL,
  `nama_ibu` varchar(255) DEFAULT NULL,
  `nik_karyawan` varchar(255) DEFAULT NULL,
  `nip` varchar(255) DEFAULT NULL,
  `penempatan` varchar(255) DEFAULT NULL,
  `nama_user` varchar(255) DEFAULT NULL,
  `kota` varchar(255) DEFAULT NULL,
  `area` varchar(255) DEFAULT NULL,
  `nomor_kontrak` varchar(255) DEFAULT NULL,
  `tanggal_pembuatan_pks` date DEFAULT NULL,
  `nomor_surat_tugas` varchar(255) DEFAULT NULL,
  `masa_penugasan` varchar(255) DEFAULT NULL,
  `tgl_aktif_masuk` date DEFAULT NULL,
  `join_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `end_date_pks` date DEFAULT NULL,
  `end_of_contract` date DEFAULT NULL,
  `status_karyawan` varchar(50) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'TIDAK AKTIF',
  `tgl_resign` date DEFAULT NULL,
  `cabang` varchar(255) DEFAULT NULL,
  `job` varchar(255) DEFAULT NULL,
  `channel` varchar(255) DEFAULT NULL,
  `tgl_rmu` date DEFAULT NULL,
  `nomor_rekening` varchar(255) DEFAULT NULL,
  `nama_bank` varchar(255) DEFAULT NULL,
  `gapok` decimal(10,2) DEFAULT NULL,
  `umk_ump` decimal(10,2) DEFAULT NULL,
  `tanggal_pernyataan` date DEFAULT NULL,
  `npwp` varchar(255) DEFAULT NULL,
  `status_pajak` varchar(50) DEFAULT NULL,
  `recruitment_officer` varchar(255) DEFAULT NULL,
  `team_leader` varchar(255) DEFAULT NULL,
  `recruiter` varchar(255) DEFAULT NULL,
  `tl` varchar(255) DEFAULT NULL,
  `manager` varchar(255) DEFAULT NULL,
  `nama_sm` varchar(255) DEFAULT NULL,
  `nama_sh` varchar(255) DEFAULT NULL,
  `sales_code` varchar(255) DEFAULT NULL,
  `nomor_reff` varchar(255) DEFAULT NULL,
  `no_bpjamsostek` varchar(255) DEFAULT NULL,
  `no_bpjs_kes` varchar(255) DEFAULT NULL,
  `role` varchar(50) DEFAULT NULL,
  `proyek` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `kontrak_history`
--

CREATE TABLE `kontrak_history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_karyawan` bigint(20) UNSIGNED NOT NULL,
  `start_new` date DEFAULT NULL,
  `end_new` date DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `kontrak_history`
--

INSERT INTO `kontrak_history` (`id`, `id_karyawan`, `start_new`, `end_new`, `note`, `created_at`) VALUES
(1, 11, '2025-09-30', '2026-10-02', '', '2025-09-24 11:14:06'),
(2, 15, '2025-09-26', '2025-10-11', '', '2025-09-24 11:14:34'),
(3, 16, '2025-09-27', '2026-10-14', '', '2025-09-24 12:07:02'),
(4, 27, '2025-10-03', '2025-11-01', '', '2025-09-24 12:07:38');

-- --------------------------------------------------------

--
-- Table structure for table `payroll`
--

CREATE TABLE `payroll` (
  `id` int(11) NOT NULL,
  `id_karyawan` bigint(20) UNSIGNED NOT NULL,
  `periode_bulan` tinyint(4) NOT NULL,
  `periode_tahun` smallint(6) NOT NULL,
  `components_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`components_json`)),
  `total_pendapatan` bigint(20) NOT NULL DEFAULT 0,
  `total_potongan` bigint(20) NOT NULL DEFAULT 0,
  `total_payroll` bigint(20) NOT NULL DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payroll`
--

INSERT INTO `payroll` (`id`, `id_karyawan`, `periode_bulan`, `periode_tahun`, `components_json`, `total_pendapatan`, `total_potongan`, `total_payroll`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 17, 9, 2025, '{\"Gaji Pokok\":55555,\"Rapel Gaji Bulan Sebelumnya\":0,\"Tunjangan Kesehatan\":0,\"Tunjangan Kehadiran\":0,\"Tunjangan Komunikasi\":0,\"Tunjangan Transportasi\":0,\"Tunjangan Jabatan\":0,\"Incentive\":0,\"Bonus Performance\":0,\"Konsistensi\":0,\"Booster\":0,\"Reimbursment\":0,\"Pengembalian Incentive\":0,\"Tunjangan Hari Raya\":0,\"Lembur\":0,\"Biaya Admin\":0,\"Total tax (PPh21)\":0,\"BPJS Kesehatan\":0,\"BPJS Ketenagakerjaan\":0,\"Dana Pensiun\":0,\"Keterlambatan Kehadiran\":0,\"Potongan Lainnya\":0,\"Potongan Loan (Mobil\\/Motor\\/Lainnya\\/SPPI)\":0}', 55555, 0, 55555, 11, '2025-09-24 11:15:56', NULL),
(2, 17, 8, 2025, '{\"Gaji Pokok\":4444,\"Rapel Gaji Bulan Sebelumnya\":0,\"Tunjangan Kesehatan\":0,\"Tunjangan Kehadiran\":0,\"Tunjangan Komunikasi\":0,\"Tunjangan Transportasi\":0,\"Tunjangan Jabatan\":0,\"Incentive\":0,\"Bonus Performance\":0,\"Konsistensi\":0,\"Booster\":0,\"Reimbursment\":0,\"Pengembalian Incentive\":0,\"Tunjangan Hari Raya\":0,\"Lembur\":0,\"Biaya Admin\":0,\"Total tax (PPh21)\":0,\"BPJS Kesehatan\":0,\"BPJS Ketenagakerjaan\":0,\"Dana Pensiun\":0,\"Keterlambatan Kehadiran\":0,\"Potongan Lainnya\":0,\"Potongan Loan (Mobil\\/Motor\\/Lainnya\\/SPPI)\":0}', 4444, 0, 4444, 11, '2025-09-24 11:17:03', NULL),
(3, 28, 9, 2025, '{\"Gaji Pokok\":9999999,\"Rapel Gaji Bulan Sebelumnya\":0,\"Tunjangan Kesehatan\":0,\"Tunjangan Kehadiran\":0,\"Tunjangan Komunikasi\":0,\"Tunjangan Transportasi\":0,\"Tunjangan Jabatan\":0,\"Incentive\":0,\"Bonus Performance\":0,\"Konsistensi\":0,\"Booster\":0,\"Reimbursment\":0,\"Pengembalian Incentive\":0,\"Tunjangan Hari Raya\":0,\"Lembur\":0,\"Biaya Admin\":0,\"Total tax (PPh21)\":0,\"BPJS Kesehatan\":0,\"BPJS Ketenagakerjaan\":0,\"Dana Pensiun\":0,\"Keterlambatan Kehadiran\":0,\"Potongan Lainnya\":0,\"Potongan Loan (Mobil\\/Motor\\/Lainnya\\/SPPI)\":0}', 9999999, 0, 9999999, 11, '2025-09-24 11:24:40', NULL),
(4, 28, 1, 2025, '{\"Gaji Pokok\":12345,\"Rapel Gaji Bulan Sebelumnya\":0,\"Tunjangan Kesehatan\":0,\"Tunjangan Kehadiran\":0,\"Tunjangan Komunikasi\":0,\"Tunjangan Transportasi\":0,\"Tunjangan Jabatan\":0,\"Incentive\":0,\"Bonus Performance\":0,\"Konsistensi\":0,\"Booster\":0,\"Reimbursment\":0,\"Pengembalian Incentive\":0,\"Tunjangan Hari Raya\":0,\"Lembur\":0,\"Biaya Admin\":0,\"Total tax (PPh21)\":0,\"BPJS Kesehatan\":0,\"BPJS Ketenagakerjaan\":0,\"Dana Pensiun\":0,\"Keterlambatan Kehadiran\":0,\"Potongan Lainnya\":0,\"Potongan Loan (Mobil\\/Motor\\/Lainnya\\/SPPI)\":0}', 12345, 0, 12345, 11, '2025-09-24 11:37:14', NULL),
(5, 9, 2, 2025, '{\"Gaji Pokok\":0,\"Rapel Gaji Bulan Sebelumnya\":0,\"Tunjangan Kesehatan\":0,\"Tunjangan Kehadiran\":0,\"Tunjangan Komunikasi\":0,\"Tunjangan Transportasi\":0,\"Tunjangan Jabatan\":22222,\"Incentive\":0,\"Bonus Performance\":0,\"Konsistensi\":0,\"Booster\":0,\"Reimbursment\":0,\"Pengembalian Incentive\":0,\"Tunjangan Hari Raya\":0,\"Lembur\":0,\"Biaya Admin\":0,\"Total tax (PPh21)\":0,\"BPJS Kesehatan\":0,\"BPJS Ketenagakerjaan\":0,\"Dana Pensiun\":0,\"Keterlambatan Kehadiran\":0,\"Potongan Lainnya\":0,\"Potongan Loan (Mobil\\/Motor\\/Lainnya\\/SPPI)\":0}', 22222, 0, 22222, 11, '2025-09-24 11:37:49', NULL),
(6, 28, 3, 2025, '{\"Gaji Pokok\":33333,\"Rapel Gaji Bulan Sebelumnya\":0,\"Tunjangan Kesehatan\":0,\"Tunjangan Kehadiran\":0,\"Tunjangan Komunikasi\":0,\"Tunjangan Transportasi\":0,\"Tunjangan Jabatan\":0,\"Incentive\":0,\"Bonus Performance\":0,\"Konsistensi\":0,\"Booster\":0,\"Reimbursment\":0,\"Pengembalian Incentive\":0,\"Tunjangan Hari Raya\":0,\"Lembur\":0,\"Biaya Admin\":0,\"Total tax (PPh21)\":0,\"BPJS Kesehatan\":0,\"BPJS Ketenagakerjaan\":0,\"Dana Pensiun\":0,\"Keterlambatan Kehadiran\":0,\"Potongan Lainnya\":0,\"Potongan Loan (Mobil\\/Motor\\/Lainnya\\/SPPI)\":0}', 33333, 0, 33333, 11, '2025-09-24 11:38:11', NULL),
(7, 28, 4, 2025, '{\"Gaji Pokok\":4444,\"Rapel Gaji Bulan Sebelumnya\":0,\"Tunjangan Kesehatan\":0,\"Tunjangan Kehadiran\":0,\"Tunjangan Komunikasi\":0,\"Tunjangan Transportasi\":0,\"Tunjangan Jabatan\":0,\"Incentive\":0,\"Bonus Performance\":0,\"Konsistensi\":0,\"Booster\":0,\"Reimbursment\":0,\"Pengembalian Incentive\":0,\"Tunjangan Hari Raya\":0,\"Lembur\":0,\"Biaya Admin\":0,\"Total tax (PPh21)\":0,\"BPJS Kesehatan\":0,\"BPJS Ketenagakerjaan\":0,\"Dana Pensiun\":0,\"Keterlambatan Kehadiran\":0,\"Potongan Lainnya\":0,\"Potongan Loan (Mobil\\/Motor\\/Lainnya\\/SPPI)\":0}', 4444, 0, 4444, 11, '2025-09-24 11:38:27', NULL),
(8, 28, 1, 2024, '{\"Gaji Pokok\":989898,\"Rapel Gaji Bulan Sebelumnya\":0,\"Tunjangan Kesehatan\":0,\"Tunjangan Kehadiran\":0,\"Tunjangan Komunikasi\":0,\"Tunjangan Transportasi\":0,\"Tunjangan Jabatan\":0,\"Incentive\":0,\"Bonus Performance\":0,\"Konsistensi\":0,\"Booster\":0,\"Reimbursment\":0,\"Pengembalian Incentive\":0,\"Tunjangan Hari Raya\":0,\"Lembur\":0,\"Biaya Admin\":0,\"Total tax (PPh21)\":0,\"BPJS Kesehatan\":0,\"BPJS Ketenagakerjaan\":0,\"Dana Pensiun\":0,\"Keterlambatan Kehadiran\":0,\"Potongan Lainnya\":0,\"Potongan Loan (Mobil\\/Motor\\/Lainnya\\/SPPI)\":0}', 989898, 0, 989898, 11, '2025-09-24 11:38:56', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `pengajuan`
--

CREATE TABLE `pengajuan` (
  `id_pengajuan` int(11) NOT NULL,
  `id_karyawan` int(11) DEFAULT NULL,
  `nik_karyawan` varchar(20) DEFAULT NULL,
  `jenis_pengajuan` enum('Cuti','Izin','Sakit') NOT NULL,
  `tanggal_mulai` date NOT NULL,
  `tanggal_berakhir` date NOT NULL,
  `keterangan` text NOT NULL,
  `dokumen_pendukung` varchar(255) DEFAULT NULL,
  `nama_pengganti` varchar(100) DEFAULT NULL,
  `nik_pengganti` varchar(20) DEFAULT NULL,
  `wa_pengganti` varchar(20) DEFAULT NULL,
  `status_pengajuan` enum('Menunggu','Disetujui','Ditolak') NOT NULL DEFAULT 'Menunggu',
  `tanggal_diajukan` datetime NOT NULL DEFAULT current_timestamp(),
  `tanggal_update` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `sumber_pengajuan` varchar(255) DEFAULT NULL,
  `nama_pengaju` varchar(255) DEFAULT NULL,
  `email_pengaju` varchar(255) DEFAULT NULL,
  `telepon_pengaju` varchar(255) DEFAULT NULL,
  `nik_pengaju` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pengajuan`
--

INSERT INTO `pengajuan` (`id_pengajuan`, `id_karyawan`, `nik_karyawan`, `jenis_pengajuan`, `tanggal_mulai`, `tanggal_berakhir`, `keterangan`, `dokumen_pendukung`, `nama_pengganti`, `nik_pengganti`, `wa_pengganti`, `status_pengajuan`, `tanggal_diajukan`, `tanggal_update`, `sumber_pengajuan`, `nama_pengaju`, `email_pengaju`, `telepon_pengaju`, `nik_pengaju`) VALUES
(30, 11, '3330199201992999', 'Izin', '2025-09-25', '2025-09-26', 'izin', '68d3d2939acc1-CV CINDY KARTIKA PUTRI88.pdf', 'nanik', '0987654321123456', '089765432134', 'Disetujui', '2025-09-24 18:14:27', '2025-09-24 18:14:36', NULL, NULL, NULL, NULL, NULL),
(31, NULL, NULL, 'Cuti', '2025-09-27', '2025-09-28', 'cuti', 'lampiran_68d60d3198a7c-payroll_cindy_09_2025.pdf', '', '', '', 'Disetujui', '2025-09-26 10:49:05', '2025-09-26 10:49:32', 'TANPA_LOGIN', 'Arul', 'kartikacindy141@gmail.com', '098765432123', '9867829283398998'),
(32, 28, '7613876376187837', 'Cuti', '2025-09-27', '2025-09-29', 'cuti', '68d60fb03b1e6-payroll_along_09_2025 (4).pdf', NULL, NULL, NULL, 'Disetujui', '2025-09-26 10:59:44', '2025-09-26 13:42:00', NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `surat_tugas`
--

CREATE TABLE `surat_tugas` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_karyawan` bigint(20) UNSIGNED NOT NULL,
  `no_surat` varchar(128) NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `posisi` varchar(128) DEFAULT NULL,
  `penempatan` varchar(128) DEFAULT NULL,
  `sales_code` varchar(64) DEFAULT NULL,
  `alamat_penempatan` text DEFAULT NULL,
  `tgl_pembuatan` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `surat_tugas`
--

INSERT INTO `surat_tugas` (`id`, `id_karyawan`, `no_surat`, `file_path`, `posisi`, `penempatan`, `sales_code`, `alamat_penempatan`, `tgl_pembuatan`, `created_at`) VALUES
(3, 11, 'ST/INTERNAL/2025/09/060', 'uploads/surat_tugas/ST-INTERNAL-2025-09-060-20250924_175858.pdf', 'hrd', '', '', '', '2025-09-24', '2025-09-24 10:58:02'),
(4, 11, 'ST/INTERNAL/2025/09/060', NULL, 'hrd', '', '', '', '2025-09-24', '2025-09-24 10:58:09'),
(5, 11, 'ST/INTERNAL/2025/09/151', NULL, 'hrd', '', '', '', '2025-09-24', '2025-09-24 10:59:26'),
(6, 11, 'ST/INTERNAL/2025/09/151', NULL, 'hrd', '', '', '', '2025-09-24', '2025-09-24 11:03:11'),
(7, 11, 'ST/INTERNAL/2025/09/151', NULL, 'hrd', '', '', '', '2025-09-24', '2025-09-24 11:03:15'),
(8, 11, 'ST/INTERNAL/2025/09/056', NULL, 'hrd', '', '', '', '2025-09-24', '2025-09-24 11:03:20'),
(9, 11, 'ST/INTERNAL/2025/09/056', NULL, 'hrd', '', '', '', '2025-09-24', '2025-09-24 11:04:17'),
(10, 11, 'ST/INTERNAL/2025/09/056', NULL, 'hrd', '', '', '', '2025-09-24', '2025-09-24 11:04:22'),
(11, 11, 'ST/INTERNAL/2025/09/056', NULL, 'hrd', '', '', '', '2025-09-24', '2025-09-24 11:06:55'),
(12, 11, 'ST/INTERNAL/2025/09/311', NULL, 'hrd', '', '', '', '2025-09-24', '2025-09-24 11:07:33'),
(13, 16, 'ST/BNIF/2025/09/625', NULL, 'ghfj', '', '', '', '2025-09-24', '2025-09-24 12:05:20'),
(14, 29, 'ST/INTERNAL/2025/09/906', NULL, 'hrd', 'gandaria', '123456', 'jaksel', '2025-09-26', '2025-09-26 03:50:58');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `absensi`
--
ALTER TABLE `absensi`
  ADD PRIMARY KEY (`id_absensi`),
  ADD KEY `fk_absensi_karyawan` (`id_karyawan`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id_invoice`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`),
  ADD KEY `created_by_id` (`created_by_id`);

--
-- Indexes for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD PRIMARY KEY (`id_item`),
  ADD KEY `id_invoice` (`id_invoice`);

--
-- Indexes for table `karyawan`
--
ALTER TABLE `karyawan`
  ADD PRIMARY KEY (`id_karyawan`),
  ADD KEY `idx_karyawan_status` (`status`),
  ADD KEY `idx_karyawan_proyek` (`proyek`),
  ADD KEY `idx_karyawan_end_date` (`end_date`),
  ADD KEY `idx_karyawan_end_of_cont` (`end_of_contract`),
  ADD KEY `idx_karyawan_nama` (`nama_karyawan`);

--
-- Indexes for table `karyawan_nonaktif`
--
ALTER TABLE `karyawan_nonaktif`
  ADD PRIMARY KEY (`id_karyawan`);

--
-- Indexes for table `kontrak_history`
--
ALTER TABLE `kontrak_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_hist_karyawan` (`id_karyawan`);

--
-- Indexes for table `payroll`
--
ALTER TABLE `payroll`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_payroll` (`id_karyawan`,`periode_bulan`,`periode_tahun`);

--
-- Indexes for table `pengajuan`
--
ALTER TABLE `pengajuan`
  ADD PRIMARY KEY (`id_pengajuan`),
  ADD KEY `idx_id_karyawan` (`id_karyawan`);

--
-- Indexes for table `surat_tugas`
--
ALTER TABLE `surat_tugas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_st_karyawan` (`id_karyawan`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `absensi`
--
ALTER TABLE `absensi`
  MODIFY `id_absensi` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id_invoice` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoice_items`
--
ALTER TABLE `invoice_items`
  MODIFY `id_item` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `karyawan`
--
ALTER TABLE `karyawan`
  MODIFY `id_karyawan` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `kontrak_history`
--
ALTER TABLE `kontrak_history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `payroll`
--
ALTER TABLE `payroll`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `pengajuan`
--
ALTER TABLE `pengajuan`
  MODIFY `id_pengajuan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `surat_tugas`
--
ALTER TABLE `surat_tugas`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `absensi`
--
ALTER TABLE `absensi`
  ADD CONSTRAINT `fk_absensi_karyawan` FOREIGN KEY (`id_karyawan`) REFERENCES `karyawan` (`id_karyawan`) ON DELETE CASCADE;

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`created_by_id`) REFERENCES `karyawan` (`id_karyawan`);

--
-- Constraints for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD CONSTRAINT `invoice_items_ibfk_1` FOREIGN KEY (`id_invoice`) REFERENCES `invoices` (`id_invoice`) ON DELETE CASCADE;

--
-- Constraints for table `kontrak_history`
--
ALTER TABLE `kontrak_history`
  ADD CONSTRAINT `fk_hist_karyawan` FOREIGN KEY (`id_karyawan`) REFERENCES `karyawan` (`id_karyawan`) ON DELETE CASCADE;

--
-- Constraints for table `payroll`
--
ALTER TABLE `payroll`
  ADD CONSTRAINT `fk_payroll_karyawan` FOREIGN KEY (`id_karyawan`) REFERENCES `karyawan` (`id_karyawan`) ON DELETE CASCADE;

--
-- Constraints for table `surat_tugas`
--
ALTER TABLE `surat_tugas`
  ADD CONSTRAINT `fk_st_karyawan` FOREIGN KEY (`id_karyawan`) REFERENCES `karyawan` (`id_karyawan`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
