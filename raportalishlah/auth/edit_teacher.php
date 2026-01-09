<?php
require 'config.php';
require 'auth.php';
requireLogin();

$id = $_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM teachers WHERE id=?");
$stmt->execute([$id]);
$t = $stmt->fetch();

if (!$t) {
    die("Guru tidak ditemukan.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name  = $_POST['name'];
    $email = $_POST['email'];
    $role  = $_POST['role'];
    $mapel = $_POST['mapel'];
    $kelas = $_POST['kelas'];

    $update = $pdo->prepare("UPDATE teachers SET name=?, email=?, role=?, mapel=?, kelas=? WHERE id=?");
    $update->execute([$name, $email, $role, $mapel, $kelas, $id]);

    echo "<script>alert('Data guru berhasil diperbarui!'); window.location='teacher_list.php';</script>";
}
?>

<h2>Edit Guru</h2>
<form method="post">
    Nama: <input type="text" name="name" value="<?= $t['name']; ?>" required><br>
    Email: <input type="email" name="email" value="<?= $t['email']; ?>" required><br>

    Role:
    <select name="role">
        <option value="guru_mapel" <?= $t['role']=='guru_mapel'?'selected':'' ?>>Guru Mapel</option>
        <option value="wali_kelas" <?= $t['role']=='wali_kelas'?'selected':'' ?>>Wali Kelas</option>
    </select><br>

    Mapel: <input type="text" name="mapel" value="<?= $t['mapel']; ?>"><br>
    Kelas: <input type="text" name="kelas" value="<?= $t['kelas']; ?>"><br>

    <button type="submit">Simpan</button>
</form>
