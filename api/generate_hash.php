<?php
/**
 * api/generate_hash.php
 * Alat bantu sederhana untuk membuat password hash yang aman.
 */

// Password yang ingin Anda gunakan (misalnya, untuk akun admin dan peserta)
$password_to_hash = 'password123';

// Membuat hash menggunakan algoritma BCRYPT yang aman
$hashed_password = password_hash($password_to_hash, PASSWORD_BCRYPT);

// Tampilkan hasilnya
echo "Password: " . $password_to_hash . "<br>";
echo "Generated Hash: <br>";
echo "<textarea rows='3' cols='80' readonly>" . htmlspecialchars($hashed_password) . "</textarea>";
?>
