<?php
// Konfigurasi koneksi database
$host     = "localhost";          // server database
$dbname   = "raportdi_rapor";     // nama database (sama seperti di phpMyAdmin)
$username = "raportdi_user";      // user database
$password = "SmaAlishlahJaya123"; // password database

try {
    // Buat objek PDO
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );

    // Mode error: lempar exception kalau ada masalah
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Kalau koneksi gagal, tampilkan pesan jelas
    die("Koneksi database gagal: " . $e->getMessage());
}
