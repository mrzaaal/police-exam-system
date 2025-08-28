<?php
/**
 * api/users_crud.php
 * Endpoint terpusat untuk mengelola peserta (CRUD).
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
            // Logika dari get_users.php dipindahkan ke sini
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 15;
            $search = isset($_GET['search']) ? trim($_GET['search']) : '';
            $offset = ($page - 1) * $limit;

            $sql_base = "FROM users WHERE role = 'participant'";
            $where_clause = "";
            $params = [];

            if (!empty($search)) {
                $where_clause = " AND (username LIKE :search OR name LIKE :search)";
                $params[':search'] = "%{$search}%";
            }

            $sql_count = "SELECT COUNT(*) " . $sql_base . $where_clause;
            $stmt_count = $pdo->prepare($sql_count);
            $stmt_count->execute($params);
            $total_records = $stmt_count->fetchColumn();
            $total_pages = ceil($total_records / $limit);

            $sql_data = "SELECT id, username, name, email, created_at " . $sql_base . $where_clause . " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
            $stmt_data = $pdo->prepare($sql_data);
            
            foreach ($params as $key => $value) {
                $stmt_data->bindValue($key, $value);
            }
            $stmt_data->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt_data->bindValue(':offset', $offset, PDO::PARAM_INT);
            
            $stmt_data->execute();
            $users = $stmt_data->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'data' => $users, 'pagination' => ['current_page' => $page, 'total_pages' => $total_pages, 'total_records' => $total_records]]);
            break;

        case 'POST':
            // Logika untuk menambah peserta baru
            if (empty($input['username']) || empty($input['name'])) {
                http_response_code(400);
                exit(json_encode(['success' => false, 'message' => 'Username dan Nama Lengkap harus diisi.']));
            }

            // Cek duplikat username
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt_check->execute([$input['username']]);
            if ($stmt_check->fetchColumn() > 0) {
                http_response_code(409); // Conflict
                exit(json_encode(['success' => false, 'message' => 'Username sudah digunakan.']));
            }

            $default_password = 'password123';
            $hashed_password = password_hash($default_password, PASSWORD_BCRYPT);

            $sql = "INSERT INTO users (username, name, email, password, role) VALUES (?, ?, ?, ?, 'participant')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$input['username'], $input['name'], $input['email'], $hashed_password]);
            
            log_action($pdo, 'CREATE_USER', "Peserta baru '{$input['username']}' ditambahkan.");
            http_response_code(201);
            echo json_encode(['success' => true, 'message' => "Peserta berhasil ditambahkan. Password default: '$default_password'"]);
            break;
        
        case 'DELETE':
             // Logika untuk menghapus peserta
            $id = $_GET['id'] ?? null;
            if (!$id) { http_response_code(400); exit(json_encode(['success' => false, 'message' => 'ID peserta diperlukan.'])); }

            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'participant'");
            $stmt->execute([$id]);

            log_action($pdo, 'DELETE_USER', "Peserta dengan ID: {$id} dihapus.");
            echo json_encode(['success' => true, 'message' => 'Peserta berhasil dihapus.']);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan.']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Users CRUD Gagal: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan pada server.']);
}
?>
