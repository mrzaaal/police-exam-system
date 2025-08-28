<?php
/**
 * api/export_questions.php
 * Endpoint untuk mengekspor semua soal dari bank soal ke format CSV.
 */

require_once __DIR__ . '/db_config.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Keamanan: Pastikan hanya admin yang bisa mengakses
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    die('Akses ditolak.');
}

try {
    $stmt = $pdo->query("SELECT question_type, question_text, options, correct_answer_index, topic FROM questions ORDER BY id ASC");
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $filename = "bank_soal_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    
    // Tulis header CSV
    fputcsv($output, ['question_type', 'question_text', 'topic', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_answer_index (0-3)']);

    if (!empty($questions)) {
        foreach ($questions as $q) {
            $row = [
                $q['question_type'],
                $q['question_text'],
                $q['topic']
            ];

            if ($q['question_type'] === 'multiple-choice') {
                $options = json_decode($q['options'], true);
                $row[] = $options[0] ?? '';
                $row[] = $options[1] ?? '';
                $row[] = $options[2] ?? '';
                $row[] = $options[3] ?? '';
                $row[] = $q['correct_answer_index'];
            } else { // Essay
                $row[] = ''; // option_a
                $row[] = ''; // option_b
                $row[] = ''; // option_c
                $row[] = ''; // option_d
                $row[] = ''; // correct_answer_index
            }
            fputcsv($output, $row);
        }
    }

    fclose($output);
    exit();

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Export Soal Gagal (DB Error): " . $e->getMessage());
    die("Terjadi kesalahan pada server.");
}
?>
