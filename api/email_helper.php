<?php
/**
 * api/email_helper.php
 * Helper untuk mengirim email menggunakan PHPMailer.
 */

// Menggunakan autoloader dari Composer
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function send_email($recipient_email, $recipient_name, $subject, $body) {
    $mail = new PHPMailer(true);

    try {
        // --- Konfigurasi Server SMTP ---
        // Ganti dengan kredensial email Anda.
        // Untuk Gmail, disarankan menggunakan "App Password" bukan password utama Anda.
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; // Server SMTP Gmail
        $mail->SMTPAuth   = true;
        $mail->Username   = 'emailanda@gmail.com'; // Alamat email Anda
        $mail->Password   = 'password_aplikasi_anda'; // Gunakan App Password dari akun Google Anda
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        // --- Pengirim dan Penerima ---
        $mail->setFrom('emailanda@gmail.com', 'Admin Sistem Ujian');
        $mail->addAddress($recipient_email, $recipient_name);

        // --- Konten Email ---
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body); // Versi teks biasa untuk klien email yang tidak mendukung HTML

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Gagal mengirim email, catat errornya di log server
        error_log("Gagal mengirim email: {$mail->ErrorInfo}");
        return false;
    }
}
?>
