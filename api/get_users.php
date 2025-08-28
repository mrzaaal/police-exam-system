<?php
/**
 * api/get_users.php
 * Endpoint untuk mengambil daftar peserta dengan paginasi dan pencarian.
 * Fitur: Keamanan, Paginasi, Pencarian.
 */

require_once __DIR__ . '/security_headers.php';
require_once __DIR__ . '/db_config.php';

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Keamanan: Pastikan hanya admin yang bisa mengakses
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Akses ditolak.']));
}

try {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 15; // Tampilkan 15 peserta per halaman
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $offset = ($page - 1) * $limit;

    $sql_base = "FROM users WHERE role = 'participant'";
    $where_clause = "";
    $params = [];

    if (!empty($search)) {
        $where_clause = " AND (username LIKE :search OR name LIKE :search)";
        $params[':search'] = "%{$search}%";
    }

    // Query untuk total records
    $sql_count = "SELECT COUNT(*) " . $sql_base . $where_clause;
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total_records = $stmt_count->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    // Query untuk data per halaman
    $sql_data = "SELECT id, username, name, created_at " . $sql_base . $where_clause . " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
    $stmt_data = $pdo->prepare($sql_data);
    
    // Bind search and pagination parameters using named placeholders
    foreach ($params as $key => $value) {
        $stmt_data->bindValue($key, $value);
    }
    $stmt_data->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt_data->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt_data->execute();
    $users = $stmt_data->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $users,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_records' => $total_records
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Get Users Gagal (DB Error): " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Gagal mengambil data peserta.']);
}
?>
