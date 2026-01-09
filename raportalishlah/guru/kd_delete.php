<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth.php';

requireLogin();
requireGuru();

$guruId = $_SESSION['user']['guru_id'] ?? null;

$id = $_GET['id'] ?? 0;

// Pastikan KD milik mapel guru
$stmt = $pdo->prepare("
    SELECT kd_pengetahuan.id 
    FROM kd_pengetahuan 
    JOIN mapel ON mapel.id = kd_pengetahuan.mapel_id
    WHERE kd_pengetahuan.id = ?
      AND mapel.teacher_id = ?
");
$stmt->execute([$id, $guruId]);
$check = $stmt->fetch();

if (!$check) {
    die("Akses ditolak.");
}

$pdo->prepare("DELETE FROM kd_pengetahuan WHERE id = ?")->execute([$id]);

header("Location: kd_list.php");
exit;
