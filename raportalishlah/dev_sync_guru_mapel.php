<?php
// dev_sync_guru_mapel.php
// Sinkron otomatis:
// 1) users.guru_id  <-> teachers.id  (via username)
// 2) mapel.teacher_id <-> teachers.id (via nama mapel)

require __DIR__ . '/config.php';

echo "<pre>";

// 1) SYNC USERS <-> TEACHERS
echo "=== SYNC users.guru_id dengan teachers.id (berdasarkan username) ===\n\n";

$stmtTeachers = $pdo->query("
    SELECT id, username, name
    FROM teachers
    WHERE username IS NOT NULL AND username <> ''
");
$teachers = $stmtTeachers->fetchAll(PDO::FETCH_ASSOC);

if (!$teachers) {
    echo "Tidak ada data guru di tabel teachers.\n\n";
} else {

    $updateUserStmt = $pdo->prepare("
        UPDATE users
        SET guru_id = ?, 
            role = CASE 
                     WHEN role IN ('admin','walikelas') THEN role
                     ELSE 'guru'
                   END
        WHERE username = ?
    ");

    foreach ($teachers as $t) {
        $teacherId = $t['id'];
        $username  = $t['username'];

        // Cek apakah user dengan username ini ada
        $cekUser = $pdo->prepare("SELECT id, role, guru_id FROM users WHERE username = ? LIMIT 1");
        $cekUser->execute([$username]);
        $user = $cekUser->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $updateUserStmt->execute([$teacherId, $username]);
            echo "OK  : username '{$username}' -> users.guru_id = {$teacherId}\n";
        } else {
            echo "SKIP: username '{$username}' belum ada di tabel users.\n";
        }
    }

    echo "\nSelesai sync users.guru_id.\n\n";
}

// 2) SYNC MAPEL <-> TEACHERS
echo "=== SYNC mapel.teacher_id dengan teachers.id (berdasarkan nama mapel) ===\n\n";

// Ambil semua mapel
$stmtMapel = $pdo->query("SELECT id, nama, teacher_id FROM mapel ORDER BY nama");
$mapelList = $stmtMapel->fetchAll(PDO::FETCH_ASSOC);

if (!$mapelList) {
    echo "Tidak ada data mapel di tabel mapel.\n";
    echo "</pre>";
    exit;
}

// Siapkan query cari guru berdasarkan nama mapel
// Kita samakan lower-case dari kolom 'mapel' di teachers dengan 'nama' di mapel
$findTeacherByMapel = $pdo->prepare("
    SELECT id, name, mapel 
    FROM teachers
    WHERE LOWER(TRIM(mapel)) = LOWER(TRIM(?))
");

// Prepared statement update mapel
$updateMapelStmt = $pdo->prepare("
    UPDATE mapel
    SET teacher_id = ?
    WHERE id = ?
");

foreach ($mapelList as $m) {
    $mapelId   = $m['id'];
    $namaMapel = $m['nama'];

    // Cari guru yang mapel-nya sama dengan nama mapel ini
    $findTeacherByMapel->execute([$namaMapel]);
    $guruList = $findTeacherByMapel->fetchAll(PDO::FETCH_ASSOC);

    if (count($guruList) === 1) {
        $guru = $guruList[0];
        $updateMapelStmt->execute([$guru['id'], $mapelId]);
        echo "OK  : mapel '{$namaMapel}' -> teacher_id = {$guru['id']} ({$guru['name']})\n";
    } elseif (count($guruList) > 1) {
        echo "AMBIGU: mapel '{$namaMapel}' cocok dengan lebih dari satu guru di tabel teachers. Tidak di-update.\n";
    } else {
        echo "SKIP: mapel '{$namaMapel}' belum ada guru dengan kolom teachers.mapel = '{$namaMapel}'.\n";
    }
}

echo "\nSelesai sync mapel.teacher_id.\n";
echo "\nCATATAN:\n";
echo "- Pastikan kolom 'mapel' di tabel teachers isinya sama dengan kolom 'nama' di tabel mapel.\n";
echo "- Contoh: teachers.mapel = 'Penjas' dan mapel.nama = 'Penjas'.\n";
echo "- Jika nama berbeda (misal 'PJOK' vs 'Penjas'), sesuaikan dulu salah satunya.\n";

echo "\nSELESAI.\n";
echo "</pre>";
