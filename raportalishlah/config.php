<?php
// Nyalakan error untuk debugging di hosting (boleh dimatikan nanti)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// panggil koneksi PDO
require __DIR__ . '/config/database.php';

// zona waktu
date_default_timezone_set('Asia/Jakarta');
