<?php
/**
 * api/auth.php
 * Endpoint otentikasi dengan keamanan Rate Limiting dan Audit Trail.
 */

require_once __DIR__ . '/security_headers.php';
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/audit_logger.php';

header('Content-Type: application/json');

// --- Konfigurasi Rate Limiting ---
define('MAX_LOGIN_ATTEMPTS', 5); // Jumlah maksimal percobaan gagal
define('LOCKOUT_PERIOD_SECONDS', 300); // Waktu blokir dalam detik (5 menit)

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Metode request tidak diizinkan.']));
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['username']) || !isset($input['password'])) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Username dan password diperlukan.']));
}

$username = $input['username'];
$password = $input['password'];
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

try {
    // 1. Cek apakah pengguna sedang diblokir
    $stmt_check = $pdo->prepare(
        "SELECT COUNT(*) FROM login_attempts WHERE username = ? AND attempt_time > (NOW() - INTERVAL ? SECOND)"
    );
    $stmt_check->execute([$username, LOCKOUT_PERIOD_SECONDS]);
    $failed_attempts = $stmt_check->fetchColumn();

    if ($failed_attempts >= MAX_LOGIN_ATTEMPTS) {
        http_response_code(429); // Too Many Requests
        exit(json_encode(['success' => false, 'message' => 'Terlalu banyak percobaan login. Silakan coba lagi dalam 5 menit.']));
    }

    // 2. Proses login
    $stmt_user = $pdo->prepare("SELECT id, username, password, name, role FROM users WHERE username = ?");
    $stmt_user->execute([$username]);
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        // Login berhasil
        // 3. Hapus catatan percobaan gagal untuk pengguna ini
        $stmt_clear = $pdo->prepare("DELETE FROM login_attempts WHERE username = ?");
        $stmt_clear->execute([$username]);

        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        // Hapus password dari data sebelum disimpan ke sesi dan dikirim ke klien
        unset($user['password']);
        $_SESSION['user'] = $user;
        
        // Catat aksi login sukses
        log_action($pdo, 'LOGIN_SUCCESS', "Pengguna '{$username}' (Peran: {$user['role']}) berhasil masuk.");

        http_response_code(200);
        echo json_encode(['success' => true, 'user' => $user]);

    } else {
        // Login gagal
        // 4. Catat percobaan yang gagal
        $stmt_log_attempt = $pdo->prepare("INSERT INTO login_attempts (username, ip_address) VALUES (?, ?)");
        $stmt_log_attempt->execute([$username, $ip_address]);

        // Catat ke audit trail
        // Kita tidak memiliki sesi user, jadi kita buat sesi sementara untuk logger
        $_SESSION['user'] = ['id' => null, 'username' => $username];
        log_action($pdo, 'LOGIN_FAILURE', "Upaya masuk gagal untuk pengguna '{$username}'.");
        unset($_SESSION['user']); // Hapus sesi sementara

        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Username atau password salah.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Auth Gagal (DB Error): " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan pada server.']);
}
?>
