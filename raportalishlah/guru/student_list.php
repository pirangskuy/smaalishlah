<?php
require 'config.php';
require 'auth.php';
requireLogin();

// Ambil semua siswa
$students = $pdo->query("SELECT * FROM students ORDER BY kelas, name")->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>Daftar Siswa</h2>
<a href="add_student.php">+ Tambah Siswa</a>
<table border="1" cellpadding="8" cellspacing="0">
    <tr>
        <th>No</th>
        <th>Nama</th>
        <th>NIS</th>
        <th>Kelas</th>
        <th>Aksi</th>
    </tr>

    <?php $no=1; foreach ($students as $s): ?>
    <tr>
        <td><?= $no++; ?></td>
        <td><?= $s['name']; ?></td>
        <td><?= $s['nis']; ?></td>
        <td><?= $s['kelas']; ?></td>
        <td>
            <a href="edit_student.php?id=<?= $s['id']; ?>">Edit</a> |
            <a href="delete_student.php?id=<?= $s['id']; ?>" 
               onclick="return confirm('Yakin ingin menghapus siswa ini?');">
               Hapus
            </a>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
