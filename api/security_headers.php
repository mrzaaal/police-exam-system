<?php
/**
 * api/security_headers.php
 * Mengatur header keamanan HTTP, termasuk Content Security Policy (CSP).
 * File ini harus di-include di bagian paling atas dari setiap file API yang menghasilkan output.
 */

// Content Security Policy (CSP)
// Kebijakan ini secara ketat membatasi sumber daya yang dapat dimuat oleh browser.
$csp_directives = [
    // Secara default, hanya izinkan konten dari domain aplikasi itu sendiri ('self').
    "default-src 'self'", 
    
    // Izinkan skrip hanya dari domain sendiri dan CDN yang kita gunakan.
    // 'unsafe-inline' diperlukan untuk konfigurasi Tailwind di index.html.
    "script-src 'self' https://cdn.jsdelivr.net https://cdn.tailwindcss.com 'unsafe-inline'", 
    
    // Izinkan stylesheet dari domain sendiri, CDN, dan Google Fonts.
    // 'unsafe-inline' diperlukan karena Tailwind CSS dan Toastify menggunakan beberapa gaya inline.
    "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com", 
    
    // Izinkan font untuk dimuat hanya dari server font Google.
    "font-src 'self' https://fonts.gstatic.com", 
    
    // Izinkan gambar dari domain sendiri dan dari sumber data URI.
    "img-src 'self' data:", 
    
    // Izinkan koneksi (fetch/XHR) hanya ke domain aplikasi sendiri.
    "connect-src 'self'", 
    
    // Mencegah situs lain menyematkan aplikasi Anda dalam frame (mencegah clickjacking).
    "frame-ancestors 'none'", 
    
    // Izinkan form untuk mengirim data hanya ke domain aplikasi sendiri.
    "form-action 'self'", 
    
    // Blokir plugin lama seperti Flash.
    "object-src 'none'",
    
    // Batasi URL yang dapat digunakan di elemen <base>.
    "base-uri 'self'",
];
$csp_policy = implode('; ', $csp_directives);
header("Content-Security-Policy: " . $csp_policy);

// Header Keamanan Tambahan
header("X-Content-Type-Options: nosniff"); // Mencegah browser menebak tipe konten.
header("X-Frame-Options: DENY"); // Lapisan tambahan untuk mencegah clickjacking.
header("X-XSS-Protection: 1; mode=block"); // Mengaktifkan filter XSS di browser lama.
header("Referrer-Policy: no-referrer-when-downgrade"); // Mengontrol informasi referrer.
header("Strict-Transport-Security: max-age=31536000; includeSubDomains"); // Memaksa koneksi HTTPS (penting untuk produksi).
?>
