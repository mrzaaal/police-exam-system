<?php
/**
 * api/get_my_result_details.php
 * Endpoint untuk peserta mengambil rincian lengkap dari salah satu hasil ujian mereka.
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
$result_id = $_GET['result_id'] ?? null;

if (!$result_id) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Result ID diperlukan.']));
}

try {
    // 1. Validasi bahwa hasil ujian ini benar-benar milik pengguna yang login
    $stmt_validate = $pdo->prepare("SELECT * FROM results WHERE id = ? AND user_id = ?");
    $stmt_validate->execute([$result_id, $currentUser['id']]);
    $main_result = $stmt_validate->fetch(PDO::FETCH_ASSOC);

    if (!$main_result) {
        http_response_code(404);
        exit(json_encode(['success' => false, 'message' => 'Hasil ujian tidak ditemukan atau bukan milik Anda.']));
    }

    // 2. Ambil progres ujian (urutan soal dan jawaban peserta) dari database
    // Ini memerlukan link antara `results` dan `exam_progress` melalui `exam_sessions`
    $stmt_progress = $pdo->prepare(
        "SELECT ep.shuffled_questions_json, ep.answers_json 
         FROM exam_progress ep
         JOIN exam_sessions es ON ep.session_id = es.id
         WHERE es.user_id = ? AND es.start_time <= ?
         ORDER BY es.start_time DESC LIMIT 1"
    );
    $stmt_progress->execute([$currentUser['id'], $main_result['completed_at']]);
    $progress = $stmt_progress->fetch(PDO::FETCH_ASSOC);

    if (!$progress) {
        http_response_code(404);
        exit(json_encode(['success' => false, 'message' => 'Data rincian pengerjaan tidak ditemukan.']));
    }

    $shuffled_questions = json_decode($progress['shuffled_questions_json'], true);
    $user_answers = json_decode($progress['answers_json'], true);

    // 3. Gabungkan data untuk laporan
    $detailed_report = [];
    foreach ($shuffled_questions as $index => $question) {
        $report_item = [
            'question_text' => $question['question_text'],
            'image_url' => $question['image_url'] ?? null,
            'question_type' => $question['question_type'],
            'options' => $question['options'] ?? null,
            'user_answer' => $user_answers[$index] ?? null,
            'correct_answer' => $question['answer'] ?? null // Untuk PG
        ];
        $detailed_report[] = $report_item;
    }

    // 4. Ambil juga rincian esai jika ada
    $stmt_essays = $pdo->prepare("SELECT q.question_text, es.answer_text, es.score FROM essay_submissions es JOIN questions q ON es.question_id = q.id WHERE es.result_id = ?");
    $stmt_essays->execute([$result_id]);
    $essay_details = $stmt_essays->fetchAll(PDO::FETCH_ASSOC);


    echo json_encode([
        'success' => true,
        'data' => [
            'main' => $main_result,
            'report' => $detailed_report,
            'essays' => $essay_details
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Get My Result Details Gagal: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Gagal mengambil detail hasil ujian.']);
}
?>
