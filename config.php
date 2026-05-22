<?php
/**
 * File Konfigurasi Database
 * Tool Room QR System
 */

// Konfigurasi Database
define('DB_HOST', 'localhost');
define('DB_USER', 'root');           // Username MySQL default XAMPP
define('DB_PASS', '');                // Password MySQL default XAMPP (kosong)
define('DB_NAME', 'toolroom');
define('DB_CHARSET', 'utf8mb4');

// Timezone
date_default_timezone_set('Asia/Jakarta');

/**
 * Fungsi untuk membuat koneksi database
 * @return mysqli
 */
function getConnection() {
    // Buat koneksi
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Cek koneksi
    if ($conn->connect_error) {
        // Jika gagal, kirim error JSON
        http_response_code(500);
        die(json_encode([
            'success' => false,
            'error' => 'Koneksi database gagal',
            'detail' => $conn->connect_error
        ]));
    }
    
    // Set charset
    $conn->set_charset(DB_CHARSET);
    
    return $conn;
}

/**
 * Fungsi untuk sanitasi input
 * @param mysqli $conn
 * @param string $data
 * @return string
 */
function sanitize($conn, $data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}

/**
 * Fungsi untuk log error
 * @param string $message
 */
function logError($message) {
    $logFile = __DIR__ . '/error.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Enable error reporting untuk development
// Matikan saat production
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

?>