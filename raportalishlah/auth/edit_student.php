<?php
require 'config.php';
require 'auth.php';
requireLogin();

$id = $_GET['id'];

// Ambil data siswa
$stmt = $pdo->prepare("SELECT * FROM students WHERE id=?");
$stmt->execute([$id]);
$s = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = $_POST['name'];
    $nis   = $_POST['nis'];
    $kelas = $_POST['kelas'];

    $update = $pdo->prepare("UPDATE students SET name=?, nis=?, kelas=? WHERE id=?");
    $update->execute([$name, $nis, $kelas, $id]);

    echo "<script>alert('Data siswa berhasil diperbarui!'); window.location='student_list.php';</script>";
}
?>

<h2>Edit Siswa</h2>
<form method="post">
    Nama: <input type="text" name="name" value="<?= $s['name']; ?>" required><br>
    NIS: <input type="text" name="nis" value="<?= $s['nis']; ?>" required><br>
    Kelas: <input type="text" name="kelas" value="<?= $s['kelas']; ?>" required><br>
    <button type="submit">Simpan</button>
</form>
