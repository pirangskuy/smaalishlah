<?php
require '../config/database.php';

// Ambil data guru
$stmt = $pdo->query("SELECT id, name, username, mapel, created_at FROM teachers ORDER BY name ASC");
$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Header untuk file download CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=daftar_akun_guru.csv');

// Buat output
$output = fopen('php://output', 'w');

// Header kolom
fputcsv($output, ['ID', 'Nama Guru', 'Username', 'Mata Pelajaran', 'Dibuat Pada']);

// Isi data
foreach ($teachers as $t) {
    fputcsv($output, $t);
}

fclose($output);
exit;
