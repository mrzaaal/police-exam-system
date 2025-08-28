<?php
/**
 * api/export_results.php
 * Endpoint untuk mengekspor semua hasil ujian ke dalam format file CSV.
 * Fitur: Keamanan, Pengambilan Data Lengkap.
 */

require_once __DIR__ . '/security_headers.php';
require_once __DIR__ . '/db_config.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Keamanan: Pastikan hanya admin yang bisa mengakses
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    // Hentikan eksekusi dengan pesan sederhana karena ini adalah endpoint download file
    die('Akses ditolak. Anda harus login sebagai admin.'); 
}

try {
    // 1. Ambil semua data hasil dari database, diurutkan berdasarkan waktu selesai
    $stmt = $pdo->query("SELECT username, name, score, attempt_number, status, grading_status, completed_at FROM results ORDER BY completed_at DESC");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Siapkan nama file dan header HTTP untuk memicu download
    $filename = "hasil_ujian_" . date('Y-m-d_H-i-s') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    // 3. Buka output stream PHP untuk menulis file secara langsung ke respons
    $output = fopen('php://output', 'w');

    // 4. Tulis baris header (judul kolom) ke dalam file CSV
    fputcsv($output, ['Username', 'Nama Lengkap', 'Skor Akhir', 'Percobaan Ke-', 'Status Kelulusan', 'Status Penilaian', 'Waktu Selesai']);

    // 5. Tulis setiap baris data hasil ujian ke dalam file CSV
    if (!empty($results)) {
        foreach ($results as $row) {
            fputcsv($output, $row);
        }
    }

    // 6. Tutup stream dan hentikan eksekusi skrip
    fclose($output);
    exit();

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Export Gagal (DB Error): " . $e->getMessage());
    // Hentikan eksekusi dengan pesan error jika terjadi masalah database
    die("Terjadi kesalahan pada server saat mencoba mengekspor data.");
}
?>
