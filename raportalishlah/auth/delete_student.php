<?php
require 'config.php';
require 'auth.php';
requireLogin();

$id = $_GET['id'];

$stmt = $pdo->prepare("DELETE FROM students WHERE id=?");
$stmt->execute([$id]);

echo "<script>alert('Siswa berhasil dihapus!'); window.location='student_list.php';</script>";
