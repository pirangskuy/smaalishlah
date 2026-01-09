<?php
// admin/teacher_delete.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth.php';

requireLogin();
requireAdmin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    die("ID guru tidak valid.");
}

try {
    $pdo->beginTransaction();

    // Hapus user terkait guru ini (kalau ada)
    $delUser = $pdo->prepare("DELETE FROM users WHERE guru_id = ?");
    $delUser->execute([$id]);

    // Hapus guru
    $delTeacher = $pdo->prepare("DELETE FROM teachers WHERE id = ?");
    $delTeacher->execute([$id]);

    $pdo->commit();

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Gagal menghapus guru: " . htmlspecialchars($e->getMessage()));
}

// Kembali ke daftar guru
header("Location: teacher_list.php");
exit;
