<?php
/**
 * api/db_config.php
 * File konfigurasi dan koneksi ke database.
 */

// --- Pengaturan Database ---
// SESUAIKAN NILAI-NILAI INI DENGAN KONFIGURASI SERVER DATABASE ANDA.
define('DB_HOST', 'localhost');    // Biasanya 'localhost'
define('DB_NAME', 'police_exam_db'); // Pastikan nama database ini BENAR
define('DB_USER', 'root');         // Username database Anda (biasanya 'root' di XAMPP)
define('DB_PASS', '');             // Password database Anda (biasanya KOSONG di XAMPP)
define('DB_CHARSET', 'utf8mb4');

// --- Opsi untuk PDO (PHP Data Objects) ---
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// --- Membuat Koneksi Database (DSN) ---
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (\PDOException $e) {
    // Jika koneksi gagal, hentikan aplikasi dan tampilkan error
    http_response_code(500);
    error_log("Koneksi Database Gagal: " . $e->getMessage());
    // Kirim pesan error dalam format JSON agar bisa ditangani front-end
    die(json_encode(['success' => false, 'message' => 'Koneksi ke database gagal. Periksa kembali db_config.php.']));
}
?>
