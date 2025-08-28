<?php
/**
 * api/audit_logger.php
 * Helper untuk mencatat aksi ke dalam tabel audit_trail.
 */

function log_action($pdo, $action, $details = null) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    $user_id = $_SESSION['user']['id'] ?? null;
    $username = $_SESSION['user']['username'] ?? 'system';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;

    try {
        $sql = "INSERT INTO audit_trail (user_id, username, action, details, ip_address) VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $username, $action, $details, $ip_address]);
    } catch (PDOException $e) {
        // Gagal mencatat log tidak boleh menghentikan aksi utama
        // Cukup catat errornya di log server
        error_log("Audit Log Gagal: " . $e->getMessage());
    }
}
?>
