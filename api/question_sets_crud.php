<?php
/**
 * api/question_sets_crud.php
 * Endpoint API untuk mengelola paket soal (CRUD).
 */

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
            // Mengambil semua paket soal beserta jumlah soal di dalamnya
            $sql = "SELECT qs.*, COUNT(qsi.question_id) as question_count 
                    FROM question_sets qs
                    LEFT JOIN question_set_items qsi ON qs.id = qsi.set_id
                    GROUP BY qs.id
                    ORDER BY qs.created_at DESC";
            $stmt = $pdo->query($sql);
            $question_sets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $question_sets]);
            break;

        case 'POST':
            // Menambah paket soal baru
            $pdo->beginTransaction();
            
            $sql_set = "INSERT INTO question_sets (name, description, created_by) VALUES (?, ?, ?)";
            $stmt_set = $pdo->prepare($sql_set);
            $stmt_set->execute([$input['name'], $input['description'], $_SESSION['user']['id']]);
            $set_id = $pdo->lastInsertId();

            if (!empty($input['question_ids'])) {
                $sql_items = "INSERT INTO question_set_items (set_id, question_id) VALUES (?, ?)";
                $stmt_items = $pdo->prepare($sql_items);
                foreach ($input['question_ids'] as $question_id) {
                    $stmt_items->execute([$set_id, $question_id]);
                }
            }
            
            $pdo->commit();
            log_action($pdo, 'CREATE_QUESTION_SET', "Paket soal baru '{$input['name']}' (ID: {$set_id}) dibuat.");
            http_response_code(201);
            echo json_encode(['success' => true, 'message' => 'Paket soal berhasil dibuat.']);
            break;

        case 'DELETE':
            // Menghapus paket soal
            $id = $_GET['id'] ?? null;
            if (!$id) { http_response_code(400); exit(json_encode(['success' => false, 'message' => 'ID paket soal diperlukan.'])); }

            // Karena ada ON DELETE CASCADE, item di question_set_items akan terhapus otomatis
            $stmt = $pdo->prepare("DELETE FROM question_sets WHERE id = ?");
            $stmt->execute([$id]);

            log_action($pdo, 'DELETE_QUESTION_SET', "Paket soal (ID: {$id}) dihapus.");
            echo json_encode(['success' => true, 'message' => 'Paket soal berhasil dihapus.']);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan.']);
            break;
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    error_log("Question Set CRUD Gagal: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan pada server.']);
}
?>
