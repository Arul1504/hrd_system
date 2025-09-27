<?php
/**
 * File Konfigurasi dan Fungsi Umum
 */

// --- PENGATURAN KONEKSI DATABASE ---
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'db_hrd2');
define("LOCATIONIQ_API_KEY", "pk.f34bd28da628b813aa99b8a5ef85b0df");
// --- MEMBUAT KONEKSI DATABASE DENGAN MYSQLI ---
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// --- CEK KONEKSI ---
if ($conn->connect_error) {
    // Di lingkungan produksi, jangan tampilkan error detail ke pengguna.
    // Cukup catat error di log server dan tampilkan pesan umum.
    error_log("Koneksi database gagal: " . $conn->connect_error);
    die("<h3>Terjadi masalah koneksi ke server.</h3><p>Silakan coba lagi nanti.</p>");
}

/**
 * Fungsi untuk memformat angka menjadi format Rupiah.
 * @param float|int $number Angka yang akan diformat.
 * @return string Angka dalam format "Rp. xxx.xxx".
 */
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
function format_rupiah($number) {
    if (!is_numeric($number)) {
        return 'Rp. 0';
    }
    return 'Rp. ' . number_format($number, 0, ',', '.');
}

// Memulai session di sini agar tersedia di semua halaman yang menyertakan file ini.
session_start();
?>