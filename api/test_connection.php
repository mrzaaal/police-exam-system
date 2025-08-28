<?php
/**
 * api/db_config.php
 * File konfigurasi dan koneksi ke database.
 */

// --- Pengaturan Database ---
// Sesuaikan nilai-nilai ini dengan konfigurasi server database Anda.
define('DB_HOST', 'localhost');    // Biasanya 'localhost' atau alamat IP server DB
define('DB_NAME', 'police_exam_db'); // Nama database Anda
define('DB_USER', 'root');         // Username database Anda
define('DB_PASS', '');             // Password database Anda
define('DB_CHARSET', 'utf8mb4');

// --- Opsi untuk PDO (PHP Data Objects) ---
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Tampilkan error sebagai exception
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Hasil query sebagai associative array
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Gunakan prepared statements asli
];

// --- Membuat Koneksi Database (DSN) ---
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

try {
    // Membuat instance PDO
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (\PDOException $e) {
    // Jika koneksi gagal, hentikan aplikasi dan tampilkan error
    http_response_code(500); // Internal Server Error
    // Jangan tampilkan detail error di produksi
    error_log("Koneksi Database Gagal: " . $e->getMessage());
    die(json_encode(['success' => false, 'message' => 'Koneksi ke database gagal.']));
}

// Variabel $pdo sekarang siap digunakan di file API lain yang meng-include file ini.
?>
