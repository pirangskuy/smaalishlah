<?php
require '../config.php';
require '../auth/auth_admin.php';

$id = $_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM siswa WHERE id=?");
$stmt->execute([$id]);
$s = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = $_POST['nama'];
    $nis = $_POST['nis'];
    $kelas = $_POST['kelas'];

    $update = $pdo->prepare("UPDATE siswa SET nama=?, nis=?, kelas=? WHERE id=?");
    $update->execute([$nama, $nis, $kelas, $id]);

    echo "<script>alert('Data berhasil diperbarui!'); window.location='student_list.php';</script>";
}
?>

<h2>Edit Siswa</h2>
<form method="post">
    Nama: <br>
    <input type="text" name="nama" value="<?= $s['nama']; ?>" required><br><br>

    NIS: <br>
    <input type="text" name="nis" value="<?= $s['nis']; ?>" required><br><br>

    Kelas: <br>
    <input type="text" name="kelas" value="<?= $s['kelas']; ?>" required><br><br>

    <button type="submit">Simpan</button>
</form>
