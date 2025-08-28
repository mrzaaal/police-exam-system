<?php
/**
 * api/get_exam_forensics.php
 * Endpoint untuk mengambil timeline event lengkap dari sebuah sesi ujian.
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

$result_id = $_GET['result_id'] ?? null;

if (!$result_id) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Result ID diperlukan.']));
}

try {
    // Cari session_id yang terkait dengan result_id
    // Asumsi: 1 hasil = 1 sesi. Cari sesi berdasarkan user_id dan waktu.
    $stmt_session = $pdo->prepare(
        "SELECT es.id FROM exam_sessions es JOIN results r ON es.user_id = r.user_id 
         WHERE r.id = ? AND es.start_time <= r.completed_at
         ORDER BY es.start_time DESC LIMIT 1"
    );
    $stmt_session->execute([$result_id]);
    $session = $stmt_session->fetch();

    if (!$session) {
        http_response_code(404);
        exit(json_encode(['success' => false, 'message' => 'Sesi ujian terkait tidak ditemukan.']));
    }
    $session_id = $session['id'];
    
    // Ambil semua event untuk sesi tersebut
    $stmt_events = $pdo->prepare("SELECT event_type, details, event_timestamp FROM exam_events WHERE session_id = ? ORDER BY event_timestamp ASC");
    $stmt_events->execute([$session_id]);
    $events = $stmt_events->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $events]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Get Forensics Gagal: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Gagal mengambil data forensik.']);
}
?>
