<?php
/**
 * api/reset_attempt.php
 * Endpoint untuk admin mereset percobaan ujian seorang peserta untuk jadwal tertentu.
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
$result_id = $input['result_id'] ?? null;

if (!$result_id) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Result ID diperlukan.']));
}

$pdo->beginTransaction();
try {
    // Ambil info hasil untuk logging sebelum dihapus
    $stmt_info = $pdo->prepare("SELECT u.username, r.schedule_id FROM results r JOIN users u ON r.user_id = u.id WHERE r.id = ?");
    $stmt_info->execute([$result_id]);
    $result_info = $stmt_info->fetch();

    if (!$result_info) {
        http_response_code(404);
        exit(json_encode(['success' => false, 'message' => 'Data hasil ujian tidak ditemukan.']));
    }

    // Hapus hasil ujian. Karena ada FOREIGN KEY ON DELETE CASCADE,
    // data di essay_submissions juga akan terhapus.
    $stmt_delete = $pdo->prepare("DELETE FROM results WHERE id = ?");
    $stmt_delete->execute([$result_id]);

    // Kita juga perlu menghapus sesi dan progres terkait jika masih ada
    // (Meskipun seharusnya sudah selesai, ini untuk kebersihan data)
    // Logika ini bisa diperluas jika diperlukan.

    $pdo->commit();

    log_action($pdo, 'RESET_ATTEMPT', "Percobaan ujian (Result ID: {$result_id}) untuk peserta '{$result_info['username']}' pada jadwal '{$result_info['schedule_id']}' telah direset.");
    echo json_encode(['success' => true, 'message' => 'Percobaan ujian peserta berhasil direset. Peserta kini dapat mengambil ujian kembali.']);

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    error_log("Reset Attempt Gagal: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan pada server.']);
}
?>
