<?php
/**
 * api/upload_image.php
 * Endpoint untuk menangani unggahan gambar untuk soal ujian.
 */

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Keamanan: Pastikan hanya admin yang bisa mengakses dan metodenya POST
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Akses ditolak.']));
}

// Cek apakah file diunggah
if (!isset($_FILES['question_image']) || $_FILES['question_image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'File tidak ditemukan atau terjadi error saat unggah.']));
}

$file = $_FILES['question_image'];
$upload_dir = __DIR__ . '/../assets/images/questions/';
$max_file_size = 5 * 1024 * 1024; // 5 MB
$allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif'];

// Validasi ukuran file
if ($file['size'] > $max_file_size) {
    http_response_code(413); // Payload Too Large
    exit(json_encode(['success' => false, 'message' => 'Ukuran file terlalu besar. Maksimal 5 MB.']));
}

// Validasi tipe file
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime_type = $finfo->file($file['tmp_name']);
if (!in_array($mime_type, $allowed_mime_types)) {
    http_response_code(415); // Unsupported Media Type
    exit(json_encode(['success' => false, 'message' => 'Format file tidak didukung. Hanya JPG, PNG, dan GIF.']));
}

// Buat nama file yang unik untuk menghindari penimpaan
$file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$unique_filename = uniqid('q_img_', true) . '.' . $file_extension;
$destination = $upload_dir . $unique_filename;

// Pindahkan file yang diunggah ke direktori tujuan
if (move_uploaded_file($file['tmp_name'], $destination)) {
    // Kembalikan path relatif yang dapat diakses dari web
    $web_path = 'assets/images/questions/' . $unique_filename;
    echo json_encode(['success' => true, 'filePath' => $web_path]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan file yang diunggah.']);
}
?>
