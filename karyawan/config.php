<?php

// --- GLOBAL SETTINGS ---
// Set the default timezone for Indonesia.
date_default_timezone_set('Asia/Jakarta');

// Start the session at the beginning of the script.
// This makes sure the session is available on all pages that include this file.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


// --- DATABASE CONNECTION SETTINGS ---
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'db_hrd2');
define("LOCATIONIQ_API_KEY", "pk.f34bd28da628b813aa99b8a5ef85b0df");

// --- ESTABLISH DATABASE CONNECTION USING MYSQLI ---
// Suppress the default error message to handle it custom.
$conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// --- CHECK FOR CONNECTION ERRORS ---
if ($conn->connect_error) {
    // Log the detailed error to the server for debugging purposes.
    error_log("Database connection failed: " . $conn->connect_error, 0);

    // Display a user-friendly error message.
    die("<h3>Terjadi masalah koneksi ke server.</h3><p>Silakan coba lagi nanti.</p>");
}


// --- HELPER FUNCTIONS ---

/**
 * Escapes HTML characters to prevent XSS attacks.
 * @param string $string The string to escape.
 * @return string The escaped string.
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Formats a number into Indonesian Rupiah currency format.
 * @param float|int $number The number to be formatted.
 * @return string The number formatted as "Rp. xxx.xxx".
 */
function format_rupiah($number) {
    // Check if the input is a valid number.
    if (!is_numeric($number)) {
        return 'Rp. 0';
    }
    return 'Rp. ' . number_format($number, 0, ',', '.');
}

?>