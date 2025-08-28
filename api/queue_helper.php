<?php
/**
 * api/queue_helper.php
 * Helper untuk menambahkan pekerjaan ke antrian BullMQ via Predis.
 */

// Menggunakan autoloader dari Composer
require_once __DIR__ . '/vendor/autoload.php';

use Predis\Client;

function add_job_to_queue($queue_name, $job_data) {
    try {
        $redis = new Client([
            'scheme' => 'tcp',
            'host'   => '127.0.0.1',
            'port'   => 6379,
        ]);

        // BullMQ menyimpan pekerjaan dalam format tertentu
        $job_id = uniqid();
        $job_payload = json_encode([
            'id' => $job_id,
            'name' => '__default__', // Nama job default
            'data' => json_encode($job_data),
            'opts' => [],
        ]);

        // Tambahkan pekerjaan ke daftar tunggu
        $redis->lpush("bull:{$queue_name}:wait", $job_payload);
        
        return true;
    } catch (Exception $e) {
        error_log("Gagal menambahkan job ke Redis: " . $e->getMessage());
        return false;
    }
}
?>
