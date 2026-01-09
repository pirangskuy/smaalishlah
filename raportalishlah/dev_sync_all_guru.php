<?php
// dev_sync_all_guru.php
// Sinkron semua guru mapel dari tabel TEACHERS ke USERS sebagai role='guru'.
// Jalankan lewat browser sekali-sekali. HAPUS setelah tidak diperlukan.

require __DIR__ . '/config.php';

echo "<pre>";

// Ambil semua guru yang BUKAN wali kelas (kelas_wali kosong/null)
$sql = "SELECT id, name, username, password, mapel, kelas_wali
        FROM teachers
        WHERE kelas_wali IS NULL
           OR TRIM(kelas_wali) = ''";
$stmt = $pdo->query($sql);
$guruList = $stmt->fetchAll();

if (empty($guruList)) {
    echo "Tidak ada guru mapel (kelas_wali kosong) di tabel TEACHERS.\n";
    exit;
}

echo "Ditemukan " . count($guruList) . " guru mapel di tabel TEACHERS.\n\n";

foreach ($guruList as $guru) {
    $teacherId  = $guru['id'];
    $nama       = $guru['name'];
    $username   = $guru['username'];
    $passHash   = $guru['password']; // sudah hash bcrypt
    $mapel      = $guru['mapel'];
    $kelasWali  = $guru['kelas_wali']; // harusnya null/kosong di sini

    if (!$username || trim($username) === '') {
        echo "SKIP: Guru ID {$teacherId} ({$nama}, mapel {$mapel}) USERNAME kosong.\n";
        continue;
    }

    // Cek di USERS
    $cekUser = $pdo->prepare("SELECT id, username, role, guru_id FROM users WHERE username = ? LIMIT 1");
    $cekUser->execute([$username]);
    $user = $cekUser->fetch();

    if ($user) {
        // Kalau sudah jadi wali kelas, jangan diubah ke guru
        if ($user['role'] === 'walikelas') {
            echo "SKIP: User '{$username}' sudah role='walikelas' (wali kelas). Tidak diubah ke guru.\n";
            continue;
        }

        // Update role & guru_id, jaga password
        $update = $pdo->prepare("
            UPDATE users
            SET role = 'guru', guru_id = ?
            WHERE id = ?
        ");
        $update->execute([$teacherId, $user['id']]);

        echo "UPDATE: User '{$username}' di-set role='guru', guru_id={$teacherId}. Nama: {$nama}, Mapel: {$mapel}\n";

    } else {
        // Insert user baru dengan password hash dari TEACHERS
        $insert = $pdo->prepare("
            INSERT INTO users (username, password, role, guru_id)
            VALUES (?, ?, 'guru', ?)
        ");
        $insert->execute([$username, $passHash, $teacherId]);

        echo "INSERT: User baru untuk guru '{$nama}' (username: {$username}), mapel: {$mapel}, guru_id={$teacherId}\n";
        echo "        Password login sama dengan yang tersimpan (hash) di TEACHERS.\n";
    }
}

echo "\nSelesai sinkronisasi guru mapel.\n";
echo "</pre>";
