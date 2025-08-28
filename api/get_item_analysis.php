<?php
/**
 * api/get_item_analysis.php
 * Endpoint untuk mengambil hasil analisis butir soal yang sudah dijalankan.
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

$schedule_id = $_GET['schedule_id'] ?? null;

if (!$schedule_id) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Schedule ID diperlukan.']));
}

try {
    $sql = "SELECT 
                qa.question_id,
                qa.difficulty_index,
                qa.discrimination_index,
                q.question_text
            FROM question_analytics qa
            JOIN questions q ON qa.question_id = q.id
            WHERE qa.schedule_id = ?
            ORDER BY qa.question_id ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$schedule_id]);
    $analysis_results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $analysis_results]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Get Item Analysis Gagal: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Gagal mengambil data analisis.']);
}
?>
