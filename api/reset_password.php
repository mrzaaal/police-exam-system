<?php
/**
 * api/reset_password.php
 * Endpoint untuk admin mereset password seorang peserta.
 * Fitur: Keamanan, Audit Trail, Notifikasi Email.
 */

require_once __DIR__ . '/security_headers.php';
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/audit_logger.php';
require_once __DIR__ . '/email_helper.php'; // Memuat helper email

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Keamanan: Pastikan hanya admin yang bisa mengakses dan metodenya POST
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Akses ditolak.']));
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['user_id'])) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'User ID diperlukan.']));
}

$user_id = $input['user_id'];
$default_password = 'password123'; // Password default yang mudah diingat
$hashed_password = password_hash($default_password, PASSWORD_BCRYPT);

try {
    // Ambil info lengkap pengguna untuk log dan email
    $stmt_user = $pdo->prepare("SELECT username, name, email FROM users WHERE id = ?");
    $stmt_user->execute([$user_id]);
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        exit(json_encode(['success' => false, 'message' => 'Peserta tidak ditemukan.']));
    }

    // Update password di database
    $sql = "UPDATE users SET password = ? WHERE id = ? AND role = 'participant'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$hashed_password, $user_id]);

    if ($stmt->rowCount() > 0) {
        // Catat aksi ke audit trail
        log_action($pdo, 'RESET_PASSWORD', "Password untuk pengguna '{$user['username']}' direset oleh admin.");

        // Kirim notifikasi email jika alamat email ada
        $email_sent_message = '';
        if (!empty($user['email'])) {
            $subject = "Notifikasi Reset Password - Sistem Ujian";
            $body = "
                <p>Halo {$user['name']},</p>
                <p>Password Anda di Sistem Ujian telah direset oleh admin.</p>
                <p>Password baru Anda adalah: <strong>{$default_password}</strong></p>
                <p>Silakan segera login dan ganti password Anda melalui halaman profil demi keamanan.</p>
                <p>Terima kasih.</p>
            ";
            if (send_email($user['email'], $user['name'], $subject, $body)) {
                $email_sent_message = ' Notifikasi telah dikirim ke email peserta.';
            } else {
                $email_sent_message = ' Namun, notifikasi email gagal dikirim.';
            }
        }

        echo json_encode(['success' => true, 'message' => "Password berhasil direset ke: '$default_password'." . $email_sent_message]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Peserta tidak ditemukan atau tidak ada perubahan.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Reset Password Gagal: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan pada server.']);
}
?>
