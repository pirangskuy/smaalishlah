<?php
session_start();
require '../config/database.php';

// Cek admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    die("<h3>Akses ditolak!</h3>");
}

// Pastikan ada ID
if (!isset($_GET['id'])) {
    die("ID tidak ditemukan.");
}

$id = $_GET['id'];

// Set kelas_wali menjadi NULL tanpa menghapus akun gurunya
$stmt = $pdo->prepare("UPDATE teachers SET kelas_wali = NULL WHERE id = ?");
$stmt->execute([$id]);

header("Location: wali_list.php");
exit;
?>
