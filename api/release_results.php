<?php
/**
 * api/release_results.php
 * Endpoint untuk admin merilis atau menarik kembali hasil ujian untuk sebuah jadwal.
 */

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/audit_logger.php';

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Keamanan: Admin, POST method
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Akses ditolak.']));
}

$input = json_decode(file_get_contents('php://input'), true);
$schedule_id = $input['schedule_id'] ?? null;
$release_status = isset($input['release_status']) ? (bool)$input['release_status'] : false;

if (!$schedule_id) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Schedule ID diperlukan.']));
}

try {
    $sql = "UPDATE exam_schedules SET results_released = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$release_status, $schedule_id]);

    $action_text = $release_status ? 'RELEASE_RESULTS' : 'WITHDRAW_RESULTS';
    $details_text = "Hasil untuk jadwal ID: {$schedule_id} telah " . ($release_status ? "dirilis." : "ditarik.");
    log_action($pdo, $action_text, $details_text);

    echo json_encode(['success' => true, 'message' => 'Status rilis hasil berhasil diperbarui.']);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Release Results Gagal: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan pada server.']);
}
?>
