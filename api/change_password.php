<?php
/**
 * api/change_password.php
 * Endpoint untuk peserta mengubah password mereka sendiri.
 */

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/audit_logger.php';

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Keamanan: Peserta yang login, metode POST
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'participant' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Akses ditolak.']));
}

$input = json_decode(file_get_contents('php://input'), true);
$current_password = $input['current_password'] ?? '';
$new_password = $input['new_password'] ?? '';

if (empty($current_password) || empty($new_password)) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Semua field harus diisi.']));
}

if (strlen($new_password) < 8) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Password baru minimal harus 8 karakter.']));
}

$currentUser = $_SESSION['user'];

try {
    // 1. Ambil hash password saat ini dari database
    $stmt_check = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt_check->execute([$currentUser['id']]);
    $user = $stmt_check->fetch();

    // 2. Verifikasi password saat ini
    if (!$user || !password_verify($current_password, $user['password'])) {
        http_response_code(401); // Unauthorized
        exit(json_encode(['success' => false, 'message' => 'Password saat ini yang Anda masukkan salah.']));
    }

    // 3. Hash dan update password baru
    $new_hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
    $stmt_update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt_update->execute([$new_hashed_password, $currentUser['id']]);

    // 4. Catat ke audit trail
    log_action($pdo, 'CHANGE_PASSWORD_SUCCESS', "Pengguna '{$currentUser['username']}' berhasil mengubah passwordnya sendiri.");

    echo json_encode(['success' => true, 'message' => 'Password berhasil diubah.']);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Change Password Gagal: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan pada server.']);
}
?>
