<?php
/**
 * api/import_questions.php
 * Endpoint untuk mengimpor soal dari file CSV ke database.
 */

require_once __DIR__ . '/db_config.php';

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Keamanan: Pastikan hanya admin yang bisa mengakses dan metodenya POST
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Akses ditolak.']));
}

// Cek apakah file diunggah
if (!isset($_FILES['questions_csv']) || $_FILES['questions_csv']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'File tidak ditemukan atau terjadi error saat unggah.']));
}

$file_path = $_FILES['questions_csv']['tmp_name'];
$file_handle = fopen($file_path, 'r');

if ($file_handle === false) {
    http_response_code(500);
    exit(json_encode(['success' => false, 'message' => 'Gagal membuka file CSV.']));
}

$pdo->beginTransaction();
try {
    // Lewati baris header
    fgetcsv($file_handle, 1000, ",");

    $imported_count = 0;
    $sql = "INSERT INTO questions (question_type, question_text, topic, options, correct_answer_index) VALUES (?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);

    while (($row = fgetcsv($file_handle, 1000, ",")) !== false) {
        $question_type = $row[0];
        $question_text = $row[1];
        $topic = $row[2];

        if ($question_type === 'multiple-choice') {
            $options = json_encode([$row[3], $row[4], $row[5], $row[6]]);
            $correct_index = !empty($row[7]) ? (int)$row[7] : null;
            $stmt->execute([$question_type, $question_text, $topic, $options, $correct_index]);
        } elseif ($question_type === 'essay') {
            $stmt->execute([$question_type, $question_text, $topic, null, null]);
        }
        $imported_count++;
    }

    $pdo->commit();
    fclose($file_handle);
    echo json_encode(['success' => true, 'message' => "$imported_count soal berhasil diimpor."]);

} catch (Exception $e) {
    $pdo->rollBack();
    fclose($file_handle);
    http_response_code(500);
    error_log("Import Soal Gagal: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan saat impor data. Pastikan format CSV sudah benar.']);
}
?>
