<?php
/**
 * api/get_analytics_data.php
 * Endpoint untuk menyediakan data agregat untuk analitik dasbor, seperti distribusi skor.
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

try {
    // Query untuk menghitung jumlah peserta dalam setiap rentang skor (interval 10)
    $sql = "SELECT 
                FLOOR(score / 10) * 10 AS score_range,
                COUNT(*) as count
            FROM results
            GROUP BY score_range
            ORDER BY score_range ASC";

    $stmt = $pdo->query($sql);
    $score_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Siapkan data untuk Chart.js
    $labels = [];
    $data = [];
    $raw_data = [];

    // Ubah hasil query menjadi array yang mudah diakses
    foreach ($score_distribution as $row) {
        $raw_data[(int)$row['score_range']] = (int)$row['count'];
    }

    // Buat label dan data untuk semua rentang dari 0 hingga 100
    for ($i = 0; $i <= 100; $i += 10) {
        $range_end = $i + 9;
        if ($i === 100) $range_end = 100;
        $labels[] = "{$i} - {$range_end}";
        $data[] = $raw_data[$i] ?? 0;
    }

    echo json_encode([
        'success' => true,
        'scoreDistribution' => [
            'labels' => $labels,
            'data' => $data
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Get Analytics Gagal (DB Error): " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Gagal mengambil data analitik.']);
}
?>
