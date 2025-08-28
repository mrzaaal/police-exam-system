<?php
/**
 * api/get_result_details.php
 * Endpoint untuk mengambil detail lengkap dari satu hasil ujian,
 * termasuk semua jawaban esai yang terkait.
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

// Ambil result_id dari query parameter
$result_id = $_GET['id'] ?? null;

if (!$result_id) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Result ID diperlukan.']));
}

try {
    // 1. Ambil data utama dari tabel 'results'
    $stmt_main = $pdo->prepare("SELECT * FROM results WHERE id = ?");
    $stmt_main->execute([$result_id]);
    $main_result = $stmt_main->fetch(PDO::FETCH_ASSOC);

    if (!$main_result) {
        http_response_code(404);
        exit(json_encode(['success' => false, 'message' => 'Hasil ujian tidak ditemukan.']));
    }

    // 2. Ambil semua jawaban esai yang terkait dengan hasil ini
    $stmt_essays = $pdo->prepare(
        "SELECT 
            es.answer_text, es.status, es.score,
            q.question_text
        FROM essay_submissions es
        JOIN questions q ON es.question_id = q.id
        WHERE es.result_id = ?"
    );
    $stmt_essays->execute([$result_id]);
    $essay_submissions = $stmt_essays->fetchAll(PDO::FETCH_ASSOC);

    // 3. Gabungkan data menjadi satu respons
    $response_data = [
        'main' => $main_result,
        'essays' => $essay_submissions
    ];

    echo json_encode(['success' => true, 'data' => $response_data]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Get Result Details Gagal (DB Error): " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Gagal mengambil detail hasil ujian.']);
}
?>
