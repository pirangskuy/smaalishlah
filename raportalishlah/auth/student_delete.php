<?php
require '../config.php';
require '../auth/auth_admin.php';

$id = $_GET['id'];

$stmt = $pdo->prepare("DELETE FROM siswa WHERE id=?");
$stmt->execute([$id]);

echo "<script>alert('Siswa dihapus!'); window.location='student_list.php';</script>";
