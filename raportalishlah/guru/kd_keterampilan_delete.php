<?php
// guru/kd_keterampilan_delete.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth.php';

requireLogin();
requireGuru();

$guruId = $_SESSION['user']['guru_id'] ?? null;
if (!$guruId) {
    die("Akun guru tidak valid.");
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header('Location: kd_keterampilan_list.php');
    exit;
}

// Opsional: pastikan KD yang dihapus memang milik mapel yang diampu guru ini
$stmt = $pdo->prepare("
    SELECT kk.id
    FROM kd_keterampilan kk
    JOIN mapel m ON kk.mapel_id = m.id
    WHERE kk.id = ? AND m.teacher_id = ?
    LIMIT 1
");
$stmt->execute([$id, $guruId]);
$cek = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cek) {
    die("KD tidak ditemukan atau bukan milik mapel Anda.");
}

// Hapus
$stmtDel = $pdo->prepare("DELETE FROM kd_keterampilan WHERE id = ? LIMIT 1");
$stmtDel->execute([$id]);

header('Location: kd_keterampilan_list.php');
exit;
