<?php
/**
 * api/results.php
 * Endpoint untuk mengelola hasil ujian.
 * - GET: Mengambil data hasil dengan paginasi & pencarian untuk admin.
 * - POST: Menerima jawaban dari peserta, melakukan skoring di server, dan menyimpan hasil.
 */

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/redis_config.php';
require_once __DIR__ . '/config_helper.php';

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // --- Handle GET Request (untuk Admin) ---
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        http_response_code(403); 
        exit(json_encode(['success' => false, 'message' => 'Akses ditolak.']));
    }

    try {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $offset = ($page - 1) * $limit;

        $sql_base = "FROM results";
        $where_clause = "";
        $params = [];

        if (!empty($search)) {
            $where_clause = " WHERE username LIKE ? OR name LIKE ?";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        // Query untuk total records
        $sql_count = "SELECT COUNT(*) " . $sql_base . $where_clause;
        $stmt_count = $pdo->prepare($sql_count);
        $stmt_count->execute($params);
        $total_records = $stmt_count->fetchColumn();
        $total_pages = ceil($total_records / $limit);

        // Query untuk data per halaman
        $sql_data = "SELECT * " . $sql_base . $where_clause . " ORDER BY completed_at DESC LIMIT :limit OFFSET :offset";
        $stmt_data = $pdo->prepare($sql_data);
        
        $i = 1;
        foreach ($params as $param) {
            $stmt_data->bindValue($i++, $param);
        }
        $stmt_data->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt_data->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt_data->execute();
        $results = $stmt_data->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $results,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $total_pages,
                'total_records' => $total_records
            ]
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        error_log("Get Results Gagal (DB Error): " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Gagal mengambil data hasil ujian.']);
    }

} elseif ($method === 'POST') {
    // --- Handle POST Request (dari Peserta Ujian) ---
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'participant') {
        http_response_code(403); 
        exit(json_encode(['success' => false, 'message' => 'Akses ditolak.']));
    }

    $currentUser = $_SESSION['user'];
    $schedule_id = $_SESSION['current_schedule_id'] ?? null;
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$schedule_id || !isset($input['answers'])) {
        http_response_code(400);
        exit(json_encode(['success' => false, 'message' => 'Sesi ujian tidak valid atau data jawaban tidak lengkap.']));
    }

    $pdo->beginTransaction();
    try {
        // 1. Ambil progres final dari Redis atau DB sebagai fallback
        $redis_key = get_redis_key($currentUser['id'], $schedule_id);
        $progress_json = $redis ? $redis->get($redis_key) : false;

        if ($progress_json) {
            $progress = json_decode($progress_json, true);
            $shuffled_questions = $progress['questions']; // Asumsi soal disimpan di Redis saat sesi mulai
            $user_answers = $input['answers']; // Gunakan jawaban final yang dikirim
        } else {
            // Fallback ke database jika Redis tidak tersedia
            $stmt_progress = $pdo->prepare("SELECT * FROM exam_progress WHERE user_id = ? AND session_id = (SELECT id FROM exam_sessions WHERE user_id = ? AND status = 'active' LIMIT 1)");
            $stmt_progress->execute([$currentUser['id'], $currentUser['id']]);
            $db_progress = $stmt_progress->fetch(PDO::FETCH_ASSOC);
            $shuffled_questions = json_decode($db_progress['shuffled_questions_json'], true);
            $user_answers = $input['answers'];
        }

        // 2. Lakukan skoring di server
        $config = get_app_config();
        $passing_score = $config['passingScore'];
        $mc_score_count = 0;
        $mc_total = 0;
        $essay_answers = [];
        $has_essay = false;
        
        foreach($shuffled_questions as $index => $question) {
            $user_answer = $user_answers[$index] ?? null;
            if ($question['question_type'] === 'multiple-choice') {
                $mc_total++;
                if (isset($user_answer) && $user_answer == $question['answer']) {
                    $mc_score_count++;
                }
            } else {
                $has_essay = true;
                if (!empty($user_answer)) {
                    $essay_answers[] = ['question_id' => $question['id'], 'answer_text' => $user_answer];
                }
            }
        }

        $initial_score = ($mc_total > 0) ? ($mc_score_count / $mc_total) * 100 : 0;
        $grading_status = $has_essay ? 'pending-review' : 'auto-graded';
        $status = ($initial_score >= $passing_score && !$has_essay) ? 'Lulus' : 'Gagal';

        // 3. Hitung nomor percobaan
        $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM results WHERE user_id = ? AND schedule_id = ?");
        $stmt_count->execute([$currentUser['id'], $schedule_id]);
        $attempt_number = $stmt_count->fetchColumn() + 1;

        // 4. Simpan hasil utama
        $sql_res = "INSERT INTO results (user_id, schedule_id, attempt_number, username, name, score, status, grading_status, completed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt_res = $pdo->prepare($sql_res);
        $stmt_res->execute([$currentUser['id'], $schedule_id, $attempt_number, $currentUser['username'], $currentUser['name'], $initial_score, $status, $grading_status]);
        $result_id = $pdo->lastInsertId();

        // 5. Simpan jawaban esai
        if ($has_essay) {
            $sql_essay = "INSERT INTO essay_submissions (result_id, user_id, question_id, answer_text) VALUES (?, ?, ?, ?)";
            $stmt_essay = $pdo->prepare($sql_essay);
            foreach ($essay_answers as $essay) {
                $stmt_essay->execute([$result_id, $currentUser['id'], $essay['question_id'], $essay['answer_text']]);
            }
        }

        // 6. Update status sesi menjadi 'finished'
        $stmt_finish = $pdo->prepare("UPDATE exam_sessions SET status = 'finished' WHERE user_id = ? AND status = 'active'");
        $stmt_finish->execute([$currentUser['id']]);

        $pdo->commit();

        // 7. Bersihkan data dari Redis dan session
        if ($redis) $redis->del($redis_key);
        unset($_SESSION['exam_questions'], $_SESSION['user_answers'], $_SESSION['current_schedule_id'], $_SESSION['flagged_questions']);

        http_response_code(201);
        echo json_encode(['success' => true, 'message' => 'Hasil ujian berhasil disimpan.', 'initialScore' => $initial_score]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(500);
        error_log("Finish Exam Gagal: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan saat menyelesaikan ujian.']);
    }

} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan.']);
}
?>
