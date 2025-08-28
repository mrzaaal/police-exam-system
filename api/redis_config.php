<?php
/**
 * api/redis_config.php
 * File konfigurasi dan koneksi ke server Redis.
 */

try {
    $redis = new Redis();
    // Ganti dengan host dan port Redis Anda jika berbeda
    $redis->connect('127.0.0.1', 6379); 
} catch (RedisException $e) {
    // Jika Redis tidak tersedia, aplikasi tidak boleh berhenti total,
    // tapi kita perlu mencatat error ini.
    error_log("Koneksi Redis Gagal: " . $e->getMessage());
    $redis = null; // Set ke null agar bisa dicek di file lain
}

// Fungsi helper untuk mendapatkan kunci Redis yang unik per pengguna
function get_redis_key($user_id, $schedule_id) {
    return "exam_progress:user:{$user_id}:schedule:{$schedule_id}";
}
?>
