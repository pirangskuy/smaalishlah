<?php
// dev_reset_password_all.php
// Reset password SEMUA admin, guru, dan wali kelas menjadi 12345
// HAPUS FILE INI setelah selesai dipakai demi keamanan!

require __DIR__ . '/config.php';

echo "<pre>";

// Password baru untuk semua role
$newPass = "12345";
$newHash = password_hash($newPass, PASSWORD_DEFAULT);

echo "Reset password semua ADMIN, GURU, dan WALI KELAS menjadi: {$newPass}\n\n";

// Ambil semua user dengan role admin/guru/walikelas
$stmt = $pdo->query("
    SELECT id, username, role, guru_id 
    FROM users 
    WHERE role IN ('admin', 'guru', 'walikelas')
");
$users = $stmt->fetchAll();

if (!$users) {
    echo "Tidak ditemukan user dengan role admin/guru/walikelas!\n";
    exit;
}

foreach ($users as $u) {
    $userId = $u['id'];
    $username = $u['username'];
    $role = $u['role'];
    $guruId = $u['guru_id'];

    // Update password di tabel USERS
    $updateUser = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $updateUser->execute([$newHash, $userId]);

    echo "UPDATED USER: {$username} (role: {$role}, id: {$userId})\n";

    // Jika guru atau wali dan punya guru_id, sync ke TEACHERS
    if (($role === 'guru' || $role === 'walikelas') && $guruId) {
        $updateTeacher = $pdo->prepare("UPDATE teachers SET password = ? WHERE id = ?");
        $updateTeacher->execute([$newHash, $guruId]);

        echo "  -> SYNC TEACHER: guru_id={$guruId} (password di TEACHERS ikut diupdate)\n";
    }
}

echo "\nSelesai reset password.\n";
echo "Password baru untuk semua akun tersebut adalah: {$newPass}\n";

echo "</pre>";
