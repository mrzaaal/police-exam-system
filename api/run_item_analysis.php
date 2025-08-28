<?php
/**
 * api/run_item_analysis.php
 * Endpoint untuk menjalankan analisis butir soal pada jadwal ujian yang telah selesai.
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

if (!$schedule_id) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Schedule ID diperlukan.']));
}

try {
    $pdo->beginTransaction();

    // 1. Ambil semua hasil (skor total) untuk jadwal ini untuk menentukan kelompok
    $stmt_results = $pdo->prepare("SELECT user_id, score FROM results WHERE schedule_id = ?");
    $stmt_results->execute([$schedule_id]);
    $all_results = $stmt_results->fetchAll(PDO::FETCH_ASSOC);

    if (count($all_results) < 10) { // Butuh data yang cukup untuk analisis
        http_response_code(400);
        exit(json_encode(['success' => false, 'message' => 'Data tidak cukup untuk analisis (minimal 10 peserta).']));
    }

    // Urutkan peserta berdasarkan skor
    usort($all_results, function ($a, $b) {
        return $b['score'] <=> $a['score'];
    });

    // 2. Tentukan kelompok atas (high) dan bawah (low) - kita gunakan 27% teratas dan terbawah
    $group_size = floor(count($all_results) * 0.27);
    if ($group_size == 0) $group_size = 1;

    $high_group_users = array_slice($all_results, 0, $group_size);
    $low_group_users = array_slice($all_results, -$group_size);
    
    $high_group_user_ids = array_column($high_group_users, 'user_id');
    $low_group_user_ids = array_column($low_group_users, 'user_id');
    $all_user_ids = array_column($all_results, 'user_id');

    // 3. Ambil semua data progres (jawaban per soal) untuk semua peserta
    $placeholders = implode(',', array_fill(0, count($all_user_ids), '?'));
    $sql_progress = "SELECT p.user_id, p.shuffled_questions_json, p.answers_json FROM exam_progress p JOIN exam_sessions s ON p.session_id = s.id WHERE p.user_id IN ($placeholders) AND s.start_time >= (SELECT start_time FROM exam_schedules WHERE id = ?)";
    $stmt_progress = $pdo->prepare($sql_progress);
    $params = array_merge($all_user_ids, [$schedule_id]);
    $stmt_progress->execute($params);
    $all_progress = $stmt_progress->fetchAll(PDO::FETCH_KEY_PAIR); // user_id as key

    // 4. Lakukan analisis untuk setiap soal
    $questions_in_schedule = json_decode($all_progress[$all_user_ids[0]]['shuffled_questions_json'], true);

    foreach ($questions_in_schedule as $index => $question) {
        if ($question['question_type'] !== 'multiple-choice') continue;

        $question_id = $question['id'];
        $correct_answer = $question['answer'];
        $total_correct = 0;
        $high_group_correct = 0;
        $low_group_correct = 0;

        foreach ($all_user_ids as $user_id) {
            $user_progress = $all_progress[$user_id];
            $user_answers = json_decode($user_progress['answers_json'], true);
            
            if (isset($user_answers[$index]) && $user_answers[$index] == $correct_answer) {
                $total_correct++;
                if (in_array($user_id, $high_group_user_ids)) $high_group_correct++;
                if (in_array($user_id, $low_group_user_ids)) $low_group_correct++;
            }
        }
        
        // Hitung Indeks Kesulitan (p-value)
        $difficulty_index = $total_correct / count($all_user_ids);

        // Hitung Indeks Pembeda
        $discrimination_index = ($high_group_correct - $low_group_correct) / $group_size;

        // 5. Simpan hasil analisis ke database
        $sql_save = "INSERT INTO question_analytics (question_id, schedule_id, difficulty_index, discrimination_index) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE difficulty_index = VALUES(difficulty_index), discrimination_index = VALUES(discrimination_index)";
        $stmt_save = $pdo->prepare($sql_save);
        $stmt_save->execute([$question_id, $schedule_id, $difficulty_index, $discrimination_index]);
    }

    // 6. Tandai bahwa analisis untuk jadwal ini sudah selesai
    $stmt_update_schedule = $pdo->prepare("UPDATE exam_schedules SET analysis_status = 'completed' WHERE id = ?");
    $stmt_update_schedule->execute([$schedule_id]);

    $pdo->commit();
    log_action($pdo, 'RUN_ITEM_ANALYSIS', "Analisis butir soal dijalankan untuk jadwal ID: {$schedule_id}.");
    echo json_encode(['success' => true, 'message' => 'Analisis butir soal berhasil diselesaikan.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    error_log("Item Analysis Gagal: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan saat menjalankan analisis.']);
}
?>
