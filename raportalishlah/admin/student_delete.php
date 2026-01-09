<?php
// admin/student_delete.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth.php';

requireLogin();
requireAdmin();

if (!isset($_GET['id'])) {
    die("ID siswa tidak ditemukan.");
}

$id = (int)$_GET['id'];
if ($id <= 0) {
    die("ID tidak valid.");
}

// (opsional) bisa cek dulu apakah siswa ini punya nilai, dsb.

try {
    $stmt = $pdo->prepare("DELETE FROM siswa WHERE id = ?");
    $stmt->execute([$id]);

    // Kembali ke daftar siswa
    header("Location: student_list.php");
    exit;

} catch (PDOException $e) {
    die("Gagal menghapus siswa: " . htmlspecialchars($e->getMessage()));
}
