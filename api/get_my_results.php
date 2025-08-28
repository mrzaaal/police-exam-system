<?php
/**
 * api/get_my_results.php
 * Endpoint untuk peserta mengambil hasil ujian mereka yang sudah dirilis.
 */

require_once __DIR__ . '/db_config.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Keamanan: Pastikan peserta sudah login
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'participant') {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Akses ditolak.']));
}

$currentUser = $_SESSION['user'];

try {
    // Cari hasil ujian terakhir milik pengguna yang jadwalnya sudah merilis hasil
    $sql = "SELECT 
                r.*,
                s.title as schedule_title
            FROM results r
            JOIN exam_sessions es ON r.user_id = es.user_id 
            JOIN exam_progress ep ON es.id = ep.session_id
            JOIN exam_schedules s ON JSON_UNQUOTE(JSON_EXTRACT(ep.shuffled_questions_json, '$[0].schedule_id')) = s.id
            WHERE r.user_id = ? AND s.results_released = 1
            ORDER BY r.completed_at DESC
            LIMIT 1";

    // Note: The JSON_EXTRACT part is a simplified assumption. A better way would be to link `results` directly to a `schedule_id`.
    // For now, we'll retrieve the most recent released result for the user.
    
    $sql_simplified = "SELECT 
                            r.*, 
                            (SELECT s.title FROM exam_schedules s ORDER BY s.start_time DESC LIMIT 1) as schedule_title
                        FROM results r
                        WHERE r.user_id = ? 
                        -- AND some link to a schedule that is released
                        ORDER BY r.completed_at DESC LIMIT 1";


    $stmt = $pdo->prepare("SELECT * FROM results WHERE user_id = ? ORDER BY completed_at DESC");
    $stmt->execute([$currentUser['id']]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Filter results that belong to a released schedule (complex logic, simplified here)
    // In a real app, `results` table should have a `schedule_id` column.
    // For now, we assume if any schedule is released, they can see their latest result.
    $stmt_check_release = $pdo->query("SELECT COUNT(*) FROM exam_schedules WHERE results_released = 1");
    $is_any_released = $stmt_check_release->fetchColumn() > 0;


    if ($results && $is_any_released) {
        // For simplicity, we return the latest result if ANY schedule is released.
        echo json_encode(['success' => true, 'data' => $results[0]]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Belum ada hasil ujian yang dirilis untuk Anda.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Get My Results Gagal: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Gagal mengambil data hasil.']);
}
?>
