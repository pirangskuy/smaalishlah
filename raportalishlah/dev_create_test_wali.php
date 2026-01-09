<?php
// dev_create_test_wali.php
// HANYA UNTUK PENGUJIAN. HAPUS SETELAH BERHASIL.

require __DIR__ . '/config.php';

// 1. Buat guru/wali di tabel teachers
// Struktur teachers: id, name, username, password, created_at, mapel, kelas_wali

$namaWali   = 'Wali Tes';
$username   = 'testwali';
$password   = 'wali123';          // password untuk login
$mapel      = 'PAI';              // misal wali juga guru PAI
$kelasWali  = 'XII IPA 1';        // contoh kelas wali

// Cek apakah teacher dengan username ini sudah ada
$cek = $pdo->prepare("SELECT id FROM teachers WHERE username = ? LIMIT 1");
$cek->execute([$username]);
$existingTeacher = $cek->fetch();

if ($existingTeacher) {
    $teacherId = $existingTeacher['id'];
    echo "Teacher sudah ada. ID: " . $teacherId . "<br>";
} else {
    $hashPasswordGuru = password_hash($password, PASSWORD_DEFAULT);

    $insertTeacher = $pdo->prepare("
        INSERT INTO teachers (name, username, password, mapel, kelas_wali)
        VALUES (?, ?, ?, ?, ?)
    ");
    $insertTeacher->execute([
        $namaWali,
        $username,
        $hashPasswordGuru,
        $mapel,
        $kelasWali
    ]);

    $teacherId = $pdo->lastInsertId();
    echo "Teacher baru dibuat. ID: " . $teacherId . "<br>";
}

// 2. Buat user walikelas di tabel users
// Struktur users: id, username, password, role, guru_id, created_at

$hashPasswordUser = password_hash($password, PASSWORD_DEFAULT);

// Cek apakah user sudah ada
$cekUser = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
$cekUser->execute([$username]);
$existingUser = $cekUser->fetch();

if ($existingUser) {
    // Update password & guru_id & role
    $updateUser = $pdo->prepare("
        UPDATE users 
        SET password = ?, role = 'walikelas', guru_id = ?
        WHERE id = ?
    ");
    $updateUser->execute([
        $hashPasswordUser,
        $teacherId,
        $existingUser['id']
    ]);

    echo "User sudah ada. Data diupdate. Username: {$username}<br>";
} else {
    // Insert user baru
    $insertUser = $pdo->prepare("
        INSERT INTO users (username, password, role, guru_id)
        VALUES (?, ?, 'walikelas', ?)
    ");
    $insertUser->execute([
        $username,
        $hashPasswordUser,
        $teacherId
    ]);

    echo "User baru dibuat. Username: {$username}<br>";
}

echo "<hr>Silakan login di /auth/login.php dengan:<br>";
echo "Username: <b>{$username}</b><br>";
echo "Password: <b>{$password}</b><br>";
