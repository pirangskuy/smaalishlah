<?php
// auth/auth.php

// Pastikan session aktif
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Fungsi: cek apakah user sudah login
 */
function requireLogin() {
    if (!isset($_SESSION['user'])) {
        // Redirect ke halaman login
        // Catatan: sesuaikan "../auth/login.php" ini jika struktur foldermu beda
        header("Location: ../auth/login.php?error=Silakan login dahulu");
        exit;
    }
}

/**
 * Fungsi: cek apakah role user = admin
 */
function requireAdmin() {
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        echo "<p style='color:red;'>Akses ditolak! Hanya admin yang dapat membuka halaman ini.</p>";
        exit;
    }
}

/**
 * Fungsi: cek apakah role user = guru
 */
function requireGuru() {
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'guru') {
        echo "<p style='color:red;'>Akses ditolak! Hanya guru yang dapat membuka halaman ini.</p>";
        exit;
    }
}

/**
 * Fungsi: cek apakah role user = wali kelas
 */
function requireWaliKelas() {
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'walikelas') {
        echo "<p style='color:red;'>Akses ditolak! Halaman ini untuk wali kelas.</p>";
        exit;
    }
}
