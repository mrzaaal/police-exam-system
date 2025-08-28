<?php
/**
 * api/grading.php
 * Endpoint API untuk mengambil dan menilai jawaban esai.
 * Fitur: Keamanan (Admin & Grader), Transaksi DB, Kalkulasi Ulang Skor, Audit Trail.
 */

require_once __DIR__ . '/security_headers.php';
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/audit_logger.php';
require_once __DIR__ . '/config_helper.php';

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Keamanan: Izinkan akses untuk admin ATAU grader
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['admin', 'grader'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Akses ditolak.']));
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        // --- Mengambil semua jawaban esai yang menunggu penilaian ---
        $sql = "SELECT 
                    es.id, es.user_id, es.question_id, es.answer_text,
                    q.question_text,
                    u.username
                FROM essay_submissions es
                JOIN questions q ON es.question_id = q.id
                JOIN users u ON es.user_id = u.id
                WHERE es.status = 'pending'
                ORDER BY es.submitted_at ASC";
        
        $stmt = $pdo->query($sql);
        $pending_essays = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $pending_essays]);

    } elseif ($method === 'POST') {
        // --- Menyimpan skor untuk satu jawaban esai ---
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['submission_id']) || !isset($input['score'])) {
            http_response_code(400);
            exit(json_encode(['success' => false, 'message' => 'ID submission dan skor diperlukan.']));
        }

        $submission_id = $input['submission_id'];
        $score = $input['score'];
        $grader_id = $_SESSION['user']['id'];
        $config = get_app_config();
        $passing_score = $config['passingScore'];

        $pdo->beginTransaction();

        // 1. Update skor esai
        $stmt_update_essay = $pdo->prepare(
            "UPDATE essay_submissions SET score = ?, status = 'graded', graded_by = ?, graded_at = NOW() WHERE id = ? AND status = 'pending'"
        );
        $stmt_update_essay->execute([$score, $grader_id, $submission_id]);

        if ($stmt_update_essay->rowCount() == 0) {
            throw new Exception("Submission tidak ditemukan atau sudah dinilai.");
        }

        // 2. Ambil result_id untuk update skor total
        $stmt_get_result = $pdo->prepare("SELECT result_id FROM essay_submissions WHERE id = ?");
        $stmt_get_result->execute([$submission_id]);
        $submission = $stmt_get_result->fetch();
        $result_id = $submission['result_id'];

        // 3. Hitung ulang skor total
        // Ambil skor PG awal dan semua skor esai yang sudah dinilai untuk hasil ini
        $stmt_recalculate = $pdo->prepare(
            "SELECT 
                r.score as initial_mc_score,
                (SELECT SUM(score) FROM essay_submissions WHERE result_id = r.id AND status = 'graded') as total_essay_score,
                (SELECT COUNT(*) FROM essay_submissions WHERE result_id = r.id) as total_essays
            FROM results r
            WHERE r.id = ?"
        );
        $stmt_recalculate->execute([$result_id]);
        $scores = $stmt_recalculate->fetch();
        
        // Asumsi bobot PG 60% dan Esai 40% (bisa disesuaikan)
        // Skor esai dinormalisasi ke skala 100
        $final_score_pg = ($scores['initial_mc_score'] / 100) * 60;
        $normalized_essay_score = ($scores['total_essays'] > 0) ? ($scores['total_essay_score'] / ($scores['total_essays'] * 100)) * 100 : 0;
        $final_score_essay = ($normalized_essay_score / 100) * 40;
        $final_score = $final_score_pg + $final_score_essay;

        // 4. Update skor akhir di tabel results
        $stmt_update_result = $pdo->prepare("UPDATE results SET score = ? WHERE id = ?");
        $stmt_update_result->execute([$final_score, $result_id]);

        // 5. Cek apakah semua esai sudah dinilai, lalu update status
        $stmt_check_all_graded = $pdo->prepare("SELECT COUNT(*) as pending_count FROM essay_submissions WHERE result_id = ? AND status = 'pending'");
        $stmt_check_all_graded->execute([$result_id]);
        $pending_count = $stmt_check_all_graded->fetchColumn();

        if ($pending_count == 0) {
            $final_status = ($final_score >= $passing_score) ? 'Lulus' : 'Gagal';
            $stmt_finalize = $pdo->prepare("UPDATE results SET grading_status = 'fully-graded', status = ? WHERE id = ?");
            $stmt_finalize->execute([$final_status, $result_id]);
        }

        log_action($pdo, 'GRADE_ESSAY', "Jawaban esai (Submission ID: {$submission_id}) dinilai dengan skor: {$score}.");
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Esai berhasil dinilai.']);

    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan.']);
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    error_log("Grading API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
