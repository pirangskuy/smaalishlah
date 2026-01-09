<?php
require 'config.php';
require 'auth.php';
requireLogin();

$id = $_GET['id'];

$stmt = $pdo->prepare("DELETE FROM teachers WHERE id=?");
$stmt->execute([$id]);

echo "<script>alert('Guru berhasil dihapus!'); window.location='teacher_list.php';</script>";
