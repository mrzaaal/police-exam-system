<?php
/**
 * api/import_users.php
 * Endpoint untuk mengimpor data peserta baru dari file CSV.
 */

require_once __DIR__ . '/db_config.php';

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Keamanan: Pastikan hanya admin yang bisa mengakses dan metodenya POST
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Akses ditolak.']));
}

// Cek apakah file diunggah
if (!isset($_FILES['users_csv']) || $_FILES['users_csv']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'File tidak ditemukan atau terjadi error saat unggah.']));
}

$file_path = $_FILES['users_csv']['tmp_name'];
$file_handle = fopen($file_path, 'r');

if ($file_handle === false) {
    http_response_code(500);
    exit(json_encode(['success' => false, 'message' => 'Gagal membuka file CSV.']));
}

// Password default untuk semua pengguna yang diimpor
$default_password = 'password123';
$hashed_password = password_hash($default_password, PASSWORD_BCRYPT);

$pdo->beginTransaction();
try {
    // Lewati baris header
    fgetcsv($file_handle, 1000, ",");

    $imported_count = 0;
    $skipped_count = 0;
    $sql = "INSERT INTO users (username, name, password, role) VALUES (?, ?, ?, 'participant')";
    $stmt = $pdo->prepare($sql);

    // Cek duplikat
    $check_sql = "SELECT COUNT(*) FROM users WHERE username = ?";
    $check_stmt = $pdo->prepare($check_sql);

    while (($row = fgetcsv($file_handle, 1000, ",")) !== false) {
        $username = $row[0] ?? null;
        $name = $row[1] ?? null;

        if (empty($username) || empty($name)) {
            $skipped_count++;
            continue; // Lewati baris yang tidak lengkap
        }

        // Cek apakah username sudah ada
        $check_stmt->execute([$username]);
        if ($check_stmt->fetchColumn() > 0) {
            $skipped_count++;
            continue; // Lewati jika username sudah terdaftar
        }

        $stmt->execute([$username, $name, $hashed_password]);
        $imported_count++;
    }

    $pdo->commit();
    fclose($file_handle);
    
    $message = "$imported_count peserta berhasil diimpor. ";
    if ($skipped_count > 0) {
        $message .= "$skipped_count dilewati (data tidak lengkap atau username sudah ada). ";
    }
    $message .= "Password default untuk peserta baru adalah: '$default_password'";

    echo json_encode(['success' => true, 'message' => $message]);

} catch (Exception $e) {
    $pdo->rollBack();
    fclose($file_handle);
    http_response_code(500);
    error_log("Import Peserta Gagal: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan saat impor data. Pastikan format CSV sudah benar.']);
}
?>
