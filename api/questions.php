<?php
/**
 * api/questions.php
 * Endpoint API untuk mengambil soal ujian peserta.
 * Versi ini menggunakan output buffering untuk memastikan output JSON yang bersih.
 */

// PERBAIKAN: Mulai output buffering untuk menangkap semua output
ob_start();

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/redis_config.php';

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'participant') {
    http_response_code(403); 
    exit(json_encode(['success' => false, 'message' => 'Akses ditolak.']));
}

$currentUser = $_SESSION['user'];

try {
    // 1. Cari jadwal ujian yang aktif saat ini
    $stmt_schedule = $pdo->prepare(
        "SELECT id, duration_minutes FROM exam_schedules 
         WHERE is_active = 1 AND NOW() BETWEEN start_time AND end_time 
         LIMIT 1"
    );
    $stmt_schedule->execute();
    $active_schedule = $stmt_schedule->fetch(PDO::FETCH_ASSOC);

    if (!$active_schedule) {
        http_response_code(403);
        exit(json_encode(['success' => false, 'message' => 'Tidak ada jadwal ujian yang aktif saat ini. Silakan coba lagi sesuai jadwal.']));
    }
    $schedule_id = $active_schedule['id'];

    $_SESSION['current_schedule_id'] = $schedule_id;

    // 2. Cek apakah peserta sudah memiliki sesi aktif di database
    $stmt_session = $pdo->prepare("SELECT id FROM exam_sessions WHERE user_id = ? AND status = 'active'");
    $stmt_session->execute([$currentUser['id']]);
    $active_session = $stmt_session->fetch();

    if ($active_session) {
        $session_id = $active_session['id'];
        $stmt_progress = $pdo->prepare("SELECT * FROM exam_progress WHERE session_id = ?");
        $stmt_progress->execute([$session_id]);
        $progress = $stmt_progress->fetch(PDO::FETCH_ASSOC);

        $response_data = [
            'questions' => json_decode($progress['shuffled_questions_json'], true),
            'saved_answers' => json_decode($progress['answers_json'], true),
            'saved_flags' => json_decode($progress['flags_json'], true)
        ];

    } else {
        $pdo->beginTransaction();

        $pdo->prepare("DELETE FROM exam_sessions WHERE user_id = ?")->execute([$currentUser['id']]);
        $stmt_new_session = $pdo->prepare("INSERT INTO exam_sessions (user_id, username) VALUES (?, ?)");
        $stmt_new_session->execute([$currentUser['id'], $currentUser['username']]);
        $session_id = $pdo->lastInsertId();

        $stmt_q_ids = $pdo->prepare("SELECT question_id FROM schedule_questions WHERE schedule_id = ?");
        $stmt_q_ids->execute([$schedule_id]);
        $question_ids = $stmt_q_ids->fetchAll(PDO::FETCH_COLUMN);

        if (empty($question_ids)) {
             http_response_code(404);
             exit(json_encode(['success' => false, 'message' => 'Paket soal untuk jadwal ini belum diatur.']));
        }

        $placeholders = implode(',', array_fill(0, count($question_ids), '?'));
        $sql_q_details = "SELECT id, question_text, image_url, question_type, options, correct_answer_index, topic FROM questions WHERE id IN ($placeholders) AND status = 'approved'";
        $stmt_q_details = $pdo->prepare($sql_q_details);
        $stmt_q_details->execute($question_ids);
        $questions_from_db = $stmt_q_details->fetchAll(PDO::FETCH_ASSOC);
        
        shuffle($questions_from_db);
        $prepared_questions = [];
        foreach ($questions_from_db as $q) {
            $options = json_decode($q['options'], true);
            
            if ($q['question_type'] === 'multiple-choice') {
                if (is_array($options) && isset($q['correct_answer_index']) && array_key_exists($q['correct_answer_index'], $options)) {
                    $correct_answer_text = $options[$q['correct_answer_index']];
                    shuffle($options);
                    $q['answer'] = array_search($correct_answer_text, $options);
                } else {
                    error_log("Soal Pilihan Ganda Rusak (ID: {$q['id']}) dilewati karena data tidak lengkap.");
                    continue; 
                }
            }

            $q['options'] = $options;
            unset($q['correct_answer_index']);
            $prepared_questions[] = $q;
        }
        
        $questionCount = count($prepared_questions);
        $initial_answers = array_fill(0, $questionCount, null);
        $initial_flags = array_fill(0, $questionCount, false);

        $stmt_new_progress = $pdo->prepare(
            "INSERT INTO exam_progress (session_id, user_id, shuffled_questions_json, answers_json, flags_json) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt_new_progress->execute([
            $session_id,
            $currentUser['id'],
            json_encode($prepared_questions),
            json_encode($initial_answers),
            json_encode($initial_flags)
        ]);
        
        $pdo->commit();

        if ($redis) {
            $redis_key = get_redis_key($currentUser['id'], $schedule_id);
            $redis_progress = [
                'answers' => $initial_answers,
                'flags' => $initial_flags
            ];
            $redis->setex($redis_key, 3 * 3600, json_encode($redis_progress));
        }

        $response_data = [
            'questions' => $prepared_questions,
            'saved_answers' => $initial_answers,
            'saved_flags' => $initial_flags
        ];
    }
    
    $response_data['duration_minutes'] = (int)$active_schedule['duration_minutes'];
    
    // PERBAIKAN: Bersihkan buffer dan kirim hanya JSON
    ob_end_clean();
    http_response_code(200);
    echo json_encode($response_data);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // PERBAIKAN: Bersihkan buffer sebelum mengirim pesan error
    ob_end_clean();
    http_response_code(500);
    error_log("Questions API Gagal: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Gagal memuat data ujian.']);
}
?>
