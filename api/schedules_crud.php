<?php
/**
 * api/schedules_crud.php
 * Endpoint API untuk mengelola jadwal ujian (CRUD), terintegrasi dengan paket soal.
 * Fitur: Keamanan, CRUD, Integrasi Paket Soal, Audit Trail.
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
            // Mengambil semua jadwal beserta jumlah soalnya
            $sql = "SELECT s.*, COUNT(sq.question_id) as question_count 
                    FROM exam_schedules s
                    LEFT JOIN schedule_questions sq ON s.id = sq.schedule_id
                    GROUP BY s.id
                    ORDER BY s.start_time DESC";
            $stmt = $pdo->query($sql);
            $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $schedules]);
            break;

        case 'POST':
            // Menambah jadwal baru menggunakan paket soal
            if (empty($input['question_set_id'])) {
                http_response_code(400);
                exit(json_encode(['success' => false, 'message' => 'Paket soal harus dipilih.']));
            }
            
            $pdo->beginTransaction();
            
            // 1. Buat jadwal utama
            $sql_schedule = "INSERT INTO exam_schedules (title, start_time, end_time, duration_minutes, max_attempts, created_by) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_schedule = $pdo->prepare($sql_schedule);
            $stmt_schedule->execute([$input['title'], $input['start_time'], $input['end_time'], $input['duration_minutes'], $input['max_attempts'], $_SESSION['user']['id']]);
            $schedule_id = $pdo->lastInsertId();

            // 2. Ambil semua ID soal dari paket soal yang dipilih
            $stmt_get_ids = $pdo->prepare("SELECT question_id FROM question_set_items WHERE set_id = ?");
            $stmt_get_ids->execute([$input['question_set_id']]);
            $question_ids = $stmt_get_ids->fetchAll(PDO::FETCH_COLUMN);

            // 3. Hubungkan semua soal tersebut ke jadwal baru
            if (!empty($question_ids)) {
                $sql_questions = "INSERT INTO schedule_questions (schedule_id, question_id) VALUES (?, ?)";
                $stmt_questions = $pdo->prepare($sql_questions);
                foreach ($question_ids as $question_id) {
                    $stmt_questions->execute([$schedule_id, $question_id]);
                }
            }
            
            $pdo->commit();
            log_action($pdo, 'CREATE_SCHEDULE', "Jadwal ujian baru '{$input['title']}' (ID: {$schedule_id}) dibuat dari paket soal ID: {$input['question_set_id']}.");
            http_response_code(201);
            echo json_encode(['success' => true, 'message' => 'Jadwal ujian berhasil dibuat.']);
            break;

        case 'PUT':
            // Mengedit detail jadwal dan paket soalnya
            $id = $_GET['id'] ?? null;
            if (!$id) { http_response_code(400); exit(json_encode(['success' => false, 'message' => 'ID jadwal diperlukan.'])); }

            $pdo->beginTransaction();

            // 1. Update detail utama jadwal
            $sql_update = "UPDATE exam_schedules SET title = ?, start_time = ?, end_time = ?, duration_minutes = ?, max_attempts = ?, is_active = ? WHERE id = ?";
            $stmt_update = $pdo->prepare($sql_update);
            $is_active = isset($input['is_active']) ? (int)(bool)$input['is_active'] : 1;
            $stmt_update->execute([$input['title'], $input['start_time'], $input['end_time'], $input['duration_minutes'], $input['max_attempts'], $is_active, $id]);
            
            // 2. Jika ada paket soal baru, perbarui asosiasi soal
            if (!empty($input['question_set_id'])) {
                // Hapus semua asosiasi soal yang lama
                $stmt_delete_q = $pdo->prepare("DELETE FROM schedule_questions WHERE schedule_id = ?");
                $stmt_delete_q->execute([$id]);

                // Ambil ID soal dari paket baru
                $stmt_get_ids = $pdo->prepare("SELECT question_id FROM question_set_items WHERE set_id = ?");
                $stmt_get_ids->execute([$input['question_set_id']]);
                $question_ids = $stmt_get_ids->fetchAll(PDO::FETCH_COLUMN);

                // Masukkan asosiasi soal yang baru
                if (!empty($question_ids)) {
                    $sql_insert_q = "INSERT INTO schedule_questions (schedule_id, question_id) VALUES (?, ?)";
                    $stmt_insert_q = $pdo->prepare($sql_insert_q);
                    foreach ($question_ids as $question_id) {
                        $stmt_insert_q->execute([$id, $question_id]);
                    }
                }
            }
            
            $pdo->commit();
            log_action($pdo, 'UPDATE_SCHEDULE', "Jadwal ujian (ID: {$id}) diperbarui.");
            echo json_encode(['success' => true, 'message' => 'Jadwal berhasil diperbarui.']);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan.']);
            break;
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    error_log("Schedule CRUD Gagal: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan pada server.']);
}
?>
