<?php
/**
 * api/questions_crud.php
 * Endpoint API untuk mengelola bank soal (CRUD).
 * Fitur: Paginasi, Pencarian, Tipe Soal, Metadata, Gambar, Alur Persetujuan.
 */

require_once __DIR__ . '/security_headers.php';
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/audit_logger.php';

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Keamanan: Pastikan hanya admin yang bisa mengakses
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Akses ditolak.']));
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

try {
    switch ($method) {
        case 'GET':
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $search = isset($_GET['search']) ? trim($_GET['search']) : '';
            $status_filter = isset($_GET['status']) ? $_GET['status'] : null;
            $offset = ($page - 1) * $limit;

            // Logika khusus untuk mengambil semua soal yang disetujui (untuk modal)
            if ($status_filter === 'approved' && !isset($_GET['page'])) {
                $stmt = $pdo->prepare("SELECT id, question_text FROM questions WHERE status = 'approved' ORDER BY id DESC");
                $stmt->execute();
                $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $questions]);
                break;
            }

            // PERBAIKAN: Logika paginasi dan pencarian untuk tabel Bank Soal
            $sql_base = "FROM questions";
            $where_clauses = [];
            $params = []; // Satu array untuk semua parameter

            if (!empty($search)) {
                $where_clauses[] = "(question_text LIKE :search OR topic LIKE :search OR question_type LIKE :search)";
                $params['search'] = "%{$search}%"; // Kunci tanpa titik dua
            }
            
            $where_sql = '';
            if (!empty($where_clauses)) {
                $where_sql = " WHERE " . implode(' AND ', $where_clauses);
            }

            $sql_count = "SELECT COUNT(*) " . $sql_base . $where_sql;
            $stmt_count = $pdo->prepare($sql_count);
            $stmt_count->execute($params);
            $total_records = $stmt_count->fetchColumn();
            $total_pages = ceil($total_records / $limit);

            $sql_data = "SELECT id, question_text, image_url, question_type, options, correct_answer_index, topic, difficulty, competency, status " . $sql_base . $where_sql . " ORDER BY id DESC LIMIT :limit OFFSET :offset";
            $stmt_data = $pdo->prepare($sql_data);
            
            // Bind search params
            foreach ($params as $key => $value) {
                $stmt_data->bindValue(":$key", $value); // Tambahkan titik dua di sini
            }
            // Bind pagination params
            $stmt_data->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt_data->bindValue(':offset', $offset, PDO::PARAM_INT);
            
            $stmt_data->execute();
            $questions = $stmt_data->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => $questions,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $total_pages,
                    'total_records' => $total_records
                ]
            ]);
            break;

        case 'POST':
            $sql = "INSERT INTO questions (question_text, image_url, question_type, options, correct_answer_index, topic, difficulty, competency, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'draft')";
            $stmt = $pdo->prepare($sql);
            $image_url = $input['image_url'] ?? null;

            if ($input['question_type'] === 'multiple-choice') {
                $stmt->execute([$input['question_text'], $image_url, $input['question_type'], json_encode($input['options']), $input['correct_answer_index'], $input['topic'], $input['difficulty'], $input['competency']]);
            } else { // 'essay'
                $stmt->execute([$input['question_text'], $image_url, $input['question_type'], null, null, $input['topic'], $input['difficulty'], $input['competency']]);
            }
            
            $new_question_id = $pdo->lastInsertId();
            log_action($pdo, 'CREATE_QUESTION', "Soal baru ditambahkan sebagai draf dengan ID: {$new_question_id}.");
            
            http_response_code(201);
            echo json_encode(['success' => true, 'message' => 'Soal berhasil ditambahkan sebagai draf.']);
            break;

        case 'PUT':
            $id = $_GET['id'] ?? null;
            if (!$id) { http_response_code(400); exit(json_encode(['success' => false, 'message' => 'ID soal diperlukan.'])); }
            $image_url = $input['image_url'] ?? null;

            $sql = "UPDATE questions SET question_text = ?, image_url = ?, question_type = ?, options = ?, correct_answer_index = ?, topic = ?, difficulty = ?, competency = ?, status = 'draft' WHERE id = ?";
            $stmt = $pdo->prepare($sql);

            if ($input['question_type'] === 'multiple-choice') {
                $stmt->execute([$input['question_text'], $image_url, $input['question_type'], json_encode($input['options']), $input['correct_answer_index'], $input['topic'], $input['difficulty'], $input['competency'], $id]);
            } else { // 'essay'
                $stmt->execute([$input['question_text'], $image_url, $input['question_type'], null, null, $input['topic'], $input['difficulty'], $input['competency'], $id]);
            }

            log_action($pdo, 'UPDATE_QUESTION', "Soal dengan ID: {$id} diperbarui dan statusnya dikembalikan ke draf.");
            echo json_encode(['success' => true, 'message' => 'Soal berhasil diperbarui.']);
            break;

        case 'DELETE':
            $id = $_GET['id'] ?? null;
            if (!$id) { http_response_code(400); exit(json_encode(['success' => false, 'message' => 'ID soal diperlukan.'])); }
            
            $stmt = $pdo->prepare("DELETE FROM questions WHERE id = ?");
            $stmt->execute([$id]);

            log_action($pdo, 'DELETE_QUESTION', "Soal dengan ID: {$id} dihapus.");
            echo json_encode(['success' => true, 'message' => 'Soal berhasil dihapus.']);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan.']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log("CRUD Soal Gagal (DB Error): " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan pada server.']);
}
?>
