<?php
// dev_sync_all_wali.php
// Jalankan sekali-sekali untuk sinkron akun wali kelas dari teachers -> users
// HAPUS / NONAKTIFKAN file ini setelah tidak diperlukan untuk alasan keamanan.

require __DIR__ . '/config.php';

echo "<pre>";

// Ambil semua guru yang punya kelas_wali (wali kelas aktif)
$sql = "SELECT id, name, username, kelas_wali 
        FROM teachers 
        WHERE kelas_wali IS NOT NULL 
          AND TRIM(kelas_wali) <> ''";
$stmt = $pdo->query($sql);
$waliList = $stmt->fetchAll();

if (empty($waliList)) {
    echo "Tidak ada data guru dengan kelas_wali terisi.\n";
    echo "Pastikan di tabel TEACHERS kolom kelas_wali sudah diisi.\n";
    exit;
}

echo "Ditemukan " . count($waliList) . " wali kelas di tabel TEACHERS.\n\n";

$defaultPasswordPlain = 'wali123'; // password default utk wali baru
$defaultPasswordHash  = password_hash($defaultPasswordPlain, PASSWORD_DEFAULT);

foreach ($waliList as $wali) {
    $teacherId = $wali['id'];
    $nama      = $wali['name'];
    $username  = $wali['username'];
    $kelasWali = $wali['kelas_wali'];

    if (!$username || trim($username) === '') {
        echo "SKIP: Guru ID {$teacherId} ({$nama}) punya kelas_wali '{$kelasWali}' tapi USERNAME kosong.\n";
        continue;
    }

    // Cek apakah user dengan username ini sudah ada
    $cekUser = $pdo->prepare("SELECT id, username, role, guru_id FROM users WHERE username = ? LIMIT 1");
    $cekUser->execute([$username]);
    $user = $cekUser->fetch();

    if ($user) {
        // Update role & guru_id saja, JANGAN ganti password
        $update = $pdo->prepare("
            UPDATE users 
            SET role = 'walikelas', guru_id = ?
            WHERE id = ?
        ");
        $update->execute([$teacherId, $user['id']]);

        echo "UPDATE: User '{$username}' sudah ada. Set role='walikelas', guru_id={$teacherId}. Kelas wali: {$kelasWali}\n";

    } else {
        // Buat user baru dengan password default
        $insert = $pdo->prepare("
            INSERT INTO users (username, password, role, guru_id)
            VALUES (?, ?, 'walikelas', ?)
        ");
        $insert->execute([$username, $defaultPasswordHash, $teacherId]);

        echo "INSERT: User baru dibuat untuk wali '{$nama}' (username: {$username}), kelas_wali: {$kelasWali}. ";
        echo "Password awal: {$defaultPasswordPlain}\n";
    }
}

echo "\nSelesai sinkronisasi akun wali kelas.\n";
echo "Catatan: Untuk user wali yang baru dibuat, password awal = '{$defaultPasswordPlain}'.\n";
echo "</pre>";
