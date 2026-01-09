<?php
session_start();
require '../config/database.php';

// OPTIONAL: hanya admin boleh akses
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    die("<h3 style='color:red;'>Akses ditolak! Halaman ini hanya untuk admin.</h3>");
}

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username']);
    $passwordBaru = trim($_POST['password']);

    if ($username === "" || $passwordBaru === "") {
        $message = "<p style='color:red;'>Username dan password baru wajib diisi.</p>";
    } else {

        // Cek guru berdasarkan username
        $stmt = $pdo->prepare("SELECT id FROM teachers WHERE username=? LIMIT 1");
        $stmt->execute([$username]);
        $guru = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$guru) {
            $message = "<p style='color:red;'>Guru dengan username <b>$username</b> tidak ditemukan!</p>";
        } else {
            // Hash password baru
            $hashBaru = password_hash($passwordBaru, PASSWORD_BCRYPT);

            // Update password guru tertentu
            $update = $pdo->prepare("UPDATE teachers SET password=? WHERE username=?");
            $update->execute([$hashBaru, $username]);

            $message = "<p style='color:green;'>Password untuk guru <b>$username</b> berhasil direset!</p>";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset Password Guru</title>
</head>
<body>

<h2>Reset Password untuk Satu Guru</h2>

<?= $message ?>

<form method="post">
    Username Guru:<br>
    <input type="text" name="username" required><br><br>

    Password Baru:<br>
    <input type="text" name="password" required><br><br>

    <button type="submit">Reset Password</button>
</form>

<br>
<a href="admin_dashboard.php">Kembali ke Dashboard Admin</a>

</body>
</html>
