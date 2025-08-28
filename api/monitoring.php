<?php
/**
 * api/monitoring.php
 * Endpoint untuk dasbor pengawas dan pencatatan event pelanggaran.
 * Fitur: Keamanan, Kalkulasi Skor Risiko Real-time, Pencatatan Event.
 */

require_once __DIR__ . '/security_headers.php';
require_once __DIR__ . '/db_config.php';

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$method = $_SERVER['REQUEST_METHOD'];

// Keamanan: Pastikan pengguna sudah login
if (!isset($_SESSION['user'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Akses ditolak.']));
}

try {
    if ($method === 'GET') {
        // --- Menyediakan data untuk dasbor pengawas (hanya admin) ---
        if ($_SESSION['user']['role'] !== 'admin') {
            http_response_code(403);
            exit(json_encode(['success' => false, 'message' => 'Hanya admin yang dapat mengakses data ini.']));
        }

        // Query untuk mengambil sesi aktif dan menghitung skor risiko
        // Bobot Risiko: Keluar Fullscreen=5, Pindah Tab=5, Paste=3, Lainnya=1
        $sql = "SELECT 
                    s.id, s.user_id, s.username, s.start_time, s.last_update, s.progress,
                    (SELECT SUM(
                        CASE 
                            WHEN e.event_type = 'Keluar dari Mode Layar Penuh' THEN 5
                            WHEN e.event_type = 'Meninggalkan Tab Ujian' THEN 5
                            WHEN e.event_type LIKE '%Paste%' THEN 3
                            ELSE 1 
                        END
                    ) FROM exam_events e WHERE e.session_id = s.id) as risk_score
                FROM exam_sessions s
                WHERE s.status = 'active'
                ORDER BY risk_score DESC, s.last_update DESC";
        
        $stmt = $pdo->query($sql);
        $active_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Tambahkan level risiko berdasarkan skor
        foreach ($active_sessions as &$session) {
            $score = (int)$session['risk_score'];
            if ($score > 10) {
                $session['risk_level'] = 'Tinggi';
            } elseif ($score > 5) {
                $session['risk_level'] = 'Sedang';
            } elseif ($score > 0) {
                $session['risk_level'] = 'Rendah';
            } else {
                $session['risk_level'] = 'Aman';
            }
        }

        echo json_encode(['success' => true, 'data' => $active_sessions]);

    } elseif ($method === 'POST') {
        // --- Menerima laporan pelanggaran dari peserta ---
        if ($_SESSION['user']['role'] !== 'participant') {
            http_response_code(403);
            exit(json_encode(['success' => false, 'message' => 'Hanya peserta yang dapat melaporkan event.']));
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['eventType'])) {
            http_response_code(400);
            exit(json_encode(['success' => false, 'message' => 'Tipe event diperlukan.']));
        }

        // Cari sesi aktif pengguna ini
        $stmt = $pdo->prepare("SELECT id FROM exam_sessions WHERE user_id = ? AND status = 'active'");
        $stmt->execute([$_SESSION['user']['id']]);
        $session = $stmt->fetch();

        if ($session) {
            // Catat event ke database
            $sql_log = "INSERT INTO exam_events (session_id, user_id, event_type, details) VALUES (?, ?, ?, ?)";
            $stmt_log = $pdo->prepare($sql_log);
            $stmt_log->execute([
                $session['id'],
                $_SESSION['user']['id'],
                $input['eventType'],
                $input['details'] ?? null
            ]);
            echo json_encode(['success' => true, 'message' => 'Event berhasil dicatat.']);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Sesi ujian aktif tidak ditemukan.']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Monitoring API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan pada server.']);
}
?>
