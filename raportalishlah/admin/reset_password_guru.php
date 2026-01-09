<?php
// Mulai session
session_start();

// Hanya admin yang boleh akses (opsional)
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    die("<h3 style='color:red;'>Akses ditolak! Hanya admin yang boleh mereset password guru.</h3>");
}

require '../config/database.php';

// Password default baru untuk semua guru
$passwordBaru = "12345";

// Hash password
$hashBaru = password_hash($passwordBaru, PASSWORD_BCRYPT);

// Update semua guru
$stmt = $pdo->prepare("UPDATE teachers SET password = ?");

if ($stmt->execute([$hashBaru])) {
    
    echo "<h2>Password Semua Guru Berhasil Direset</h2>";
    echo "<p>Password baru untuk semua guru adalah: <b>$passwordBaru</b></p><br>";

    // Tampilkan daftar guru
    $dataGuru = $pdo->query("SELECT id, name, username, mapel FROM teachers ORDER BY id ASC");

    echo "<table border='1' cellpadding='8' cellspacing='0'>
            <tr>
                <th>ID</th>
                <th>Nama Guru</th>
                <th>Username</th>
                <th>Mapel</th>
            </tr>";

    foreach ($dataGuru as $guru) {
        echo "<tr>
                <td>{$guru['id']}</td>
                <td>{$guru['name']}</td>
                <td>{$guru['username']}</td>
                <td>{$guru['mapel']}</td>
              </tr>";
    }

    echo "</table><br>";
    echo "<a href='admin_dashboard.php'>Kembali ke Dashboard Admin</a>";

} else {
    echo "<h3 style='color:red;'>Gagal mereset password guru!</h3>";
}
?>
