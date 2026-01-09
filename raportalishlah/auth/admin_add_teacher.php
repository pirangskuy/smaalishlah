<?php
require '../config.php';
require '../auth/auth_admin.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = $_POST['name'];
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $mapel = $_POST['mapel'];
    $role = $_POST['role']; // guru / walikelas
    $kelas_wali = $_POST['kelas_wali'] ?? null;

    // 1. Masukkan data guru ke tabel teachers
    $stmt = $pdo->prepare("INSERT INTO teachers (name, username, password, mapel, kelas_wali)
                           VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$name, $username, $password, $mapel, $kelas_wali]);

    $teacher_id = $pdo->lastInsertId();

    // 2. Masukkan akun login ke tabel users
    $stmt2 = $pdo->prepare("INSERT INTO users (username, password, role, guru_id)
                            VALUES (?, ?, ?, ?)");
    $stmt2->execute([$username, $password, $role, $teacher_id]);

    echo "<script>alert('Guru berhasil ditambahkan!'); window.location='teacher_list.php';</script>";
}
?>

<h2>Tambah Guru</h2>

<form method="post">
    Nama Guru:<br>
    <input type="text" name="name" required><br><br>

    Username Login:<br>
    <input type="text" name="username" required><br><br>

    Password Login:<br>
    <input type="password" name="password" required><br><br>

    Mapel:<br>
    <input type="text" name="mapel" required><br><br>

    Role:<br>
    <select name="role" required>
        <option value="guru">Guru Mapel</option>
        <option value="walikelas">Wali Kelas</option>
    </select><br><br>

    Kelas Wali (isi jika role = wali kelas):<br>
    <input type="text" name="kelas_wali"><br><br>

    <button type="submit">Tambah Guru</button>
</form>
