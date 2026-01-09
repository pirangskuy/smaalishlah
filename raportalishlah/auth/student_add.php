<?php
require '../config.php';
require '../auth/auth_admin.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = $_POST['nama'];
    $nis = $_POST['nis'];
    $kelas = $_POST['kelas'];

    $stmt = $pdo->prepare("INSERT INTO siswa (nama, nis, kelas) VALUES (?,?,?)");
    $stmt->execute([$nama, $nis, $kelas]);

    echo "<script>alert('Siswa ditambahkan!'); window.location='student_list.php';</script>";
}
?>

<h2>Tambah Siswa</h2>
<form method="post">
    Nama: <br>
    <input type="text" name="nama" required><br><br>

    NIS: <br>
    <input type="text" name="nis" required><br><br>

    Kelas: <br>
    <input type="text" name="kelas" required><br><br>

    <button type="submit">Simpan</button>
</form>
