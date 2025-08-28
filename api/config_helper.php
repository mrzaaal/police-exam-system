<?php
/**
 * api/config_helper.php
 * Helper untuk memuat konfigurasi aplikasi dari file JSON.
 */

function get_app_config() {
    $config_path = __DIR__ . '/../data/config.json';
    if (!file_exists($config_path)) {
        // Konfigurasi default jika file tidak ditemukan
        return ['passingScore' => 75];
    }
    $config_json = file_get_contents($config_path);
    $config = json_decode($config_json, true);

    // Pastikan nilai passingScore ada
    if (!isset($config['passingScore'])) {
        $config['passingScore'] = 75;
    }

    return $config;
}
?>
