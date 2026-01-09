<?php
// dev_create_test_guru.php
// HANYA UNTUK PENGUJIAN. HAPUS / NONAKTIFKAN SETELAH BERHASIL.

require __DIR__ . '/config.php';

// 1. Buat guru di tabel teachers
// Struktur teachers (dari SQL kamu): id, name, username, password, created_at, mapel, kelas_wali

$namaGuru   = 'Guru Tes';
$username   = 'testguru';
$password   = 'guru123';      // password yang akan dipakai login
$mapel      = 'PAI';          // contoh mapel
$kelasWali  = null;           // bukan wali kelas

// Cek apakah teacher dengan username ini sudah ada
$cek = $pdo->prepare("SELECT id FROM teachers WHERE username = ? LIMIT 1");
$cek->execute([$username]);
$existingTeacher = $cek->fetch();

if ($existingTeacher) {
    $teacherId = $existingTeacher['id'];
    echo "Teacher sudah ada. ID: " . $teacherId . "<br>";
} else {
    // Simpan password guru di tabel teachers juga pakai hash (opsional, tergantung kamu mau pakai atau tidak)
    $hashPasswordGuru = password_hash($password, PASSWORD_DEFAULT);

    // ❗ PERHATIKAN: kolomnya `name`, BUKAN `nama`
    $insertTeacher = $pdo->prepare("
        INSERT INTO teachers (name, username, password, mapel, kelas_wali)
        VALUES (?, ?, ?, ?, ?)
    ");
    $insertTeacher->execute([
        $namaGuru,
        $username,
        $hashPasswordGuru,
        $mapel,
        $kelasWali
    ]);

    $teacherId = $pdo->lastInsertId();
    echo "Teacher baru dibuat. ID: " . $teacherId . "<br>";
}

// 2. Buat user di tabel users
// Struktur users: id, username, password, role, guru_id, created_at

$hashPasswordUser = password_hash($password, PASSWORD_DEFAULT);

// Cek apakah user sudah ada
$cekUser = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
$cekUser->execute([$username]);
$existingUser = $cekUser->fetch();

if ($existingUser) {
    // Update saja password & guru_id
    $updateUser = $pdo->prepare("
        UPDATE users 
        SET password = ?, role = 'guru', guru_id = ?
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
        VALUES (?, ?, 'guru', ?)
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
