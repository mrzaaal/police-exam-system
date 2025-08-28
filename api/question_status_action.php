<?php
/**
 * api/question_status_action.php
 * Endpoint untuk admin mengubah status sebuah soal (misal: menyetujui draf).
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
$question_id = $input['question_id'] ?? null;
$new_status = $input['status'] ?? null;

if (!$question_id || !in_array($new_status, ['draft', 'approved'])) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'ID Soal dan status yang valid diperlukan.']));
}

try {
    $sql = "UPDATE questions SET status = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$new_status, $question_id]);

    if ($stmt->rowCount() > 0) {
        $action_text = ($new_status === 'approved') ? 'APPROVE_QUESTION' : 'REVERT_QUESTION_TO_DRAFT';
        $details_text = "Status soal ID: {$question_id} diubah menjadi '{$new_status}'.";
        log_action($pdo, $action_text, $details_text);

        echo json_encode(['success' => true, 'message' => 'Status soal berhasil diperbarui.']);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Soal tidak ditemukan atau status tidak berubah.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Update Status Soal Gagal: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan pada server.']);
}
?>
