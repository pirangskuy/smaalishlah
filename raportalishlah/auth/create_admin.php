<?php
// HANYA UNTUK PEMBUATAN AKUN ADMIN BARU
// Setelah berhasil, HAPUS FILE INI demi keamanan.

ob_start();
session_start();

require '../config/database.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if ($username === '' || $password === '') {
        $message = "Semua field wajib diisi!";
    } else {

        // Cek apakah username sudah ada
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $message = "Username sudah digunakan!";
        } else {

            // Hash password
            $hash = password_hash($password, PASSWORD_BCRYPT);

            // Masukkan user baru
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'admin')");
            $stmt->execute([$username, $hash]);

            $message = "Akun admin berhasil dibuat!<br>Username: <b>$username</b><br>Password: <b>$password</b>";
        }
    }
}
?>

<h2>Buat Akun Admin Baru</h2>

<?php if ($message): ?>
    <p style="color:blue;"><?= $message ?></p>
<?php endif; ?>

<form method="post">
    Username Admin Baru:<br>
    <input type="text" name="username" required><br><br>

    Password Baru:<br>
    <input type="password" name="password" required><br><br>

    <button type="submit">Buat Admin</button>
</form>

<p style="color:red;">
Setelah akun berhasil dibuat, HARAP hapus file ini untuk keamanan:<br>
<b>auth/create_admin.php</b>
</p>
