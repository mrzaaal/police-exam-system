<?php
/**
 * api/get_schedule_details.php
 * Endpoint untuk mengambil detail spesifik dari satu jadwal ujian,
 * termasuk daftar ID soal yang terhubung.
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

$schedule_id = $_GET['id'] ?? null;

if (!$schedule_id) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Schedule ID diperlukan.']));
}

try {
    // 1. Ambil data utama jadwal
    $stmt_main = $pdo->prepare("SELECT * FROM exam_schedules WHERE id = ?");
    $stmt_main->execute([$schedule_id]);
    $schedule_details = $stmt_main->fetch(PDO::FETCH_ASSOC);

    if (!$schedule_details) {
        http_response_code(404);
        exit(json_encode(['success' => false, 'message' => 'Jadwal tidak ditemukan.']));
    }

    // 2. Ambil semua ID soal yang terhubung
    $stmt_questions = $pdo->prepare("SELECT question_id FROM schedule_questions WHERE schedule_id = ?");
    $stmt_questions->execute([$schedule_id]);
    $question_ids = $stmt_questions->fetchAll(PDO::FETCH_COLUMN);

    // 3. Gabungkan data
    $response_data = [
        'details' => $schedule_details,
        'question_ids' => $question_ids
    ];

    echo json_encode(['success' => true, 'data' => $response_data]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Get Schedule Details Gagal: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Gagal mengambil detail jadwal.']);
}
?>
