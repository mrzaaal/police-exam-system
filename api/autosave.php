<?php
/**
 * api/autosave.php
 * Endpoint autosave yang dioptimalkan dengan Redis untuk mengurangi beban database.
 * Menyimpan jawaban, status ragu-ragu, dan mencatat event forensik.
 */

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/redis_config.php'; // Memuat koneksi Redis

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Keamanan: Hanya izinkan metode POST dan pastikan peserta sudah login
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Metode tidak diizinkan.']));
}

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'participant') {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Akses ditolak.']));
}

$currentUser = $_SESSION['user'];
$schedule_id = $_SESSION['current_schedule_id'] ?? null;
$input = json_decode(file_get_contents('php://input'), true);
$questionIndex = $input['questionIndex'] ?? null;

// Validasi input dasar dan ketersediaan Redis
if (!$schedule_id || $questionIndex === null || !$redis) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Sesi tidak valid atau layanan penyimpanan sementara tidak tersedia.']));
}

try {
    $redis_key = get_redis_key($currentUser['id'], $schedule_id);

    // Ambil progres saat ini dari Redis
    $progress_json = $redis->get($redis_key);
    $progress = $progress_json ? json_decode($progress_json, true) : [];

    // Inisialisasi jika ini adalah data pertama
    if (empty($progress)) {
        // Ambil data awal dari database jika ada (untuk kasus resume sesi)
        $stmt_db_progress = $pdo->prepare("SELECT answers_json, flags_json FROM exam_progress WHERE user_id = ? AND session_id = (SELECT id FROM exam_sessions WHERE user_id = ? AND status = 'active' LIMIT 1)");
        $stmt_db_progress->execute([$currentUser['id'], $currentUser['id']]);
        $db_progress = $stmt_db_progress->fetch(PDO::FETCH_ASSOC);
        if ($db_progress) {
            $progress['answers'] = json_decode($db_progress['answers_json'], true);
            $progress['flags'] = json_decode($db_progress['flags_json'], true);
        }
    }

    // Dapatkan session_id untuk logging
    $stmt_session = $pdo->prepare("SELECT id FROM exam_sessions WHERE user_id = ? AND status = 'active'");
    $stmt_session->execute([$currentUser['id']]);
    $session = $stmt_session->fetch();
    $session_id = $session ? $session['id'] : null;

    $event_type = null;
    $details = null;

    // Update data progres berdasarkan input
    if (array_key_exists('answerValue', $input)) {
        $progress['answers'][$questionIndex] = $input['answerValue'];
        $event_type = 'ANSWER_CHANGED';
        $details = json_encode(['questionIndex' => $questionIndex]);
    }
    if (array_key_exists('isFlagged', $input)) {
        $progress['flags'][$questionIndex] = (bool)$input['isFlagged'];
        $event_type = $input['isFlagged'] ? 'QUESTION_FLAGGED' : 'QUESTION_UNFLAGGED';
        $details = json_encode(['questionIndex' => $questionIndex]);
    }
    if (array_key_exists('eventType', $input) && $input['eventType'] === 'QUESTION_VIEWED') {
        $event_type = 'QUESTION_VIEWED';
        $details = json_encode(['questionIndex' => $questionIndex]);
    }

    // Simpan kembali progres yang sudah diperbarui ke Redis
    // Set expiry time (misal: 3 jam) untuk membersihkan data lama secara otomatis
    $redis->setex($redis_key, 3 * 3600, json_encode($progress));

    // Update progres di tabel exam_sessions (ini tetap penting untuk monitoring)
    $progress_count = isset($progress['answers']) ? count(array_filter($progress['answers'])) : 0;
    if ($session_id) {
        $stmt_update_session = $pdo->prepare("UPDATE exam_sessions SET progress = ? WHERE id = ?");
        $stmt_update_session->execute([$progress_count, $session_id]);
    }
    
    // Catat event ke database jika ada
    if ($event_type && $session_id) {
        $sql_log = "INSERT INTO exam_events (session_id, user_id, event_type, details) VALUES (?, ?, ?, ?)";
        $stmt_log = $pdo->prepare($sql_log);
        $stmt_log->execute([$session_id, $currentUser['id'], $event_type, $details]);
    }

    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Progres berhasil disimpan.']);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Autosave Redis Gagal: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan pada server.']);
}
?>
