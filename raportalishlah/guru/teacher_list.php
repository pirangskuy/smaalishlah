<?php
require 'config.php';
require 'auth.php';
requireLogin();

$teachers = $pdo->query("SELECT * FROM teachers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>Data Guru</h2>
<a href="add_teacher.php">+ Tambah Guru</a>

<table border="1" cellpadding="8" cellspacing="0">
    <tr>
        <th>No</th>
        <th>Nama</th>
        <th>Email</th>
        <th>Role</th>
        <th>Mapel</th>
        <th>Kelas</th>
        <th>Aksi</th>
    </tr>

    <?php $no=1; foreach ($teachers as $t): ?>
    <tr>
        <td><?= $no++; ?></td>
        <td><?= $t['name']; ?></td>
        <td><?= $t['email']; ?></td>
        <td><?= $t['role']; ?></td>
        <td><?= $t['mapel']; ?></td>
        <td><?= $t['kelas']; ?></td>
        <td>
            <a href="edit_teacher.php?id=<?= $t['id']; ?>">Edit</a> |
            <a href="delete_teacher.php?id=<?= $t['id']; ?>" onclick="return confirm('Yakin ingin menghapus guru ini?');">Hapus</a>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
