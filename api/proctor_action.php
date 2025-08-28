<?php
/**
 * api/proctor_action.php
 * Endpoint untuk admin/pengawas melakukan aksi terhadap sesi peserta,
 * seperti memaksa ujian selesai.
 * Fitur: Keamanan, Transaksi Database, Skoring Sisi Server, Audit Trail.
 */

require_once __DIR__ . '/security_headers.php';
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/audit_logger.php';
require_once __DIR__ . '/config_helper.php';

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Keamanan: Pastikan hanya admin yang bisa mengakses dan metodenya POST
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Akses ditolak.']));
}

$input = json_decode(file_get_contents('php://input'), true);
$user_id = $input['user_id'] ?? null;
$action = $input['action'] ?? null;

if (!$user_id || $action !== 'force_finish') {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'User ID dan aksi yang valid diperlukan.']));
}

$pdo->beginTransaction();
try {
    // 1. Dapatkan sesi aktif peserta
    $stmt_session = $pdo->prepare("SELECT id FROM exam_sessions WHERE user_id = ? AND status = 'active'");
    $stmt_session->execute([$user_id]);
    $session = $stmt_session->fetch();

    if (!$session) {
        http_response_code(404);
        exit(json_encode(['success' => false, 'message' => 'Peserta tidak memiliki sesi ujian aktif.']));
    }
    $session_id = $session['id'];

    // 2. Ambil progres ujian dari database
    $stmt_progress = $pdo->prepare("SELECT * FROM exam_progress WHERE session_id = ?");
    $stmt_progress->execute([$session_id]);
    $progress = $stmt_progress->fetch(PDO::FETCH_ASSOC);

    if (!$progress) {
        http_response_code(404);
        exit(json_encode(['success' => false, 'message' => 'Data progres ujian peserta tidak ditemukan.']));
    }

    $shuffled_questions = json_decode($progress['shuffled_questions_json'], true);
    $user_answers = json_decode($progress['answers_json'], true);

    // 3. Lakukan skoring (logika yang sama seperti di results.php)
    $config = get_app_config();
    $passing_score = $config['passingScore'];
    $mc_score_count = 0;
    $mc_total = 0;
    $has_essay = false;
    
    foreach($shuffled_questions as $index => $question) {
        if ($question['question_type'] === 'multiple-choice') {
            $mc_total++;
            if (isset($user_answers[$index]) && $user_answers[$index] == $question['answer']) {
                $mc_score_count++;
            }
        } else {
            $has_essay = true;
        }
    }
    $final_score = ($mc_total > 0) ? ($mc_score_count / $mc_total) * 100 : 0;
    $grading_status = $has_essay ? 'pending-review' : 'auto-graded';
    $status = ($final_score >= $passing_score && !$has_essay) ? 'Lulus' : 'Gagal';

    // 4. Simpan hasil ujian
    $stmt_user = $pdo->prepare("SELECT username, name FROM users WHERE id = ?");
    $stmt_user->execute([$user_id]);
    $user_info = $stmt_user->fetch();

    // Ambil schedule_id dari progres
    $schedule_id = $_SESSION['current_schedule_id'] ?? null; // Ambil dari sesi jika ada, atau perlu cara lain

    // Hitung nomor percobaan
    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM results WHERE user_id = ? AND schedule_id = ?");
    $stmt_count->execute([$user_id, $schedule_id]);
    $attempt_number = $stmt_count->fetchColumn() + 1;

    $sql_insert = "INSERT INTO results (user_id, schedule_id, attempt_number, username, name, score, status, grading_status, completed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt_insert = $pdo->prepare($sql_insert);
    $stmt_insert->execute([$user_id, $schedule_id, $attempt_number, $user_info['username'], $user_info['name'], $final_score, $status, $grading_status]);

    // 5. Update status sesi menjadi selesai
    $stmt_finish = $pdo->prepare("UPDATE exam_sessions SET status = 'finished' WHERE id = ?");
    $stmt_finish->execute([$session_id]);

    // Catat aksi ke audit trail
    log_action($pdo, 'FORCE_FINISH_EXAM', "Ujian untuk pengguna '{$user_info['username']}' (ID: {$user_id}) dihentikan secara paksa.");

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Ujian peserta berhasil dihentikan dan dinilai.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    error_log("Proctor Action Gagal: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan pada server.']);
}
?>
