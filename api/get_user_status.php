<?php
/**
 * api/get_user_status.php
 * Endpoint untuk menentukan status dan halaman arahan bagi peserta setelah login.
 * File ini memeriksa sesi aktif, hasil yang dirilis, dan jadwal yang tersedia
 * dengan mempertimbangkan kebijakan ujian ulang.
 */

require_once __DIR__ . '/security_headers.php';
require_once __DIR__ . '/db_config.php';

header('Content-Type: application/json');

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
    // 1. Prioritas utama: Cek apakah ada sesi ujian yang sedang aktif untuk dilanjutkan
    $stmt_session = $pdo->prepare("SELECT id FROM exam_sessions WHERE user_id = ? AND status = 'active'");
    $stmt_session->execute([$currentUser['id']]);
    if ($stmt_session->fetch()) {
        echo json_encode(['success' => true, 'status' => 'active_session']);
        exit;
    }

    // 2. Cek apakah ada hasil ujian yang sudah dirilis untuk dilihat
    $sql_results = "SELECT r.*, s.title as schedule_title 
                    FROM results r 
                    JOIN exam_schedules s ON r.schedule_id = s.id 
                    WHERE r.user_id = ? AND s.results_released = 1 
                    ORDER BY r.completed_at DESC";
    $stmt_results = $pdo->prepare($sql_results);
    $stmt_results->execute([$currentUser['id']]);
    $released_results = $stmt_results->fetchAll(PDO::FETCH_ASSOC);
    if ($released_results) {
        echo json_encode(['success' => true, 'status' => 'results_available', 'data' => $released_results]);
        exit;
    }

    // 3. Cek apakah ada jadwal ujian yang aktif saat ini
    $stmt_schedule = $pdo->prepare("SELECT id, max_attempts FROM exam_schedules WHERE is_active = 1 AND NOW() BETWEEN start_time AND end_time LIMIT 1");
    $stmt_schedule->execute();
    $active_schedule = $stmt_schedule->fetch(PDO::FETCH_ASSOC);

    if ($active_schedule) {
        $schedule_id = $active_schedule['id'];
        $max_attempts = $active_schedule['max_attempts'];

        // Hitung berapa kali peserta sudah mengambil ujian untuk jadwal ini
        $stmt_taken = $pdo->prepare("SELECT COUNT(*) FROM results WHERE user_id = ? AND schedule_id = ?");
        $stmt_taken->execute([$currentUser['id'], $schedule_id]);
        $attempts_taken = $stmt_taken->fetchColumn();

        if ($attempts_taken < $max_attempts) {
            echo json_encode(['success' => true, 'status' => 'schedule_available']);
            exit;
        } else {
            // Jika sudah mencapai batas, statusnya idle (menunggu)
            echo json_encode(['success' => true, 'status' => 'idle', 'reason' => 'max_attempts_reached']);
            exit;
        }
    }

    // 4. Jika tidak ada kondisi di atas, berarti peserta dalam keadaan menunggu
    echo json_encode(['success' => true, 'status' => 'idle']);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Get User Status Gagal: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Gagal memeriksa status pengguna.']);
}
?>
