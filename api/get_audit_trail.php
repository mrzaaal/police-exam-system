<?php
/**
 * api/get_audit_trail.php
 * Endpoint untuk mengambil semua data log dari audit trail.
 */

require_once __DIR__ . '/db_config.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Keamanan: Pastikan hanya admin yang bisa mengakses
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Akses ditolak.']));
}

try {
    $stmt = $pdo->query("SELECT username, action, details, ip_address, timestamp FROM audit_trail ORDER BY timestamp DESC LIMIT 200"); // Batasi 200 log terbaru
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $logs]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Get Audit Trail Gagal (DB Error): " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Gagal mengambil data log.']);
}
?>
