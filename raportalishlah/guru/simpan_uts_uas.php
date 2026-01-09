<?php
// guru/simpan_uts_uas.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth.php';

requireLogin();
requireGuru();

$mapel_id = isset($_POST['mapel_id']) ? (int)$_POST['mapel_id'] : 0;
$kelas    = $_POST['kelas'] ?? '';
$utsArr   = $_POST['uts'] ?? [];
$uasArr   = $_POST['uas'] ?? [];

if ($mapel_id <= 0 || $kelas === '') {
    die("Data tidak lengkap.");
}

// Siapkan statement cek, insert, update
$sqlCek = $pdo->prepare("
    SELECT id 
    FROM nilai_uts_uas
    WHERE siswa_id = ? AND mapel_id = ?
    LIMIT 1
");

$sqlInsert = $pdo->prepare("
    INSERT INTO nilai_uts_uas (siswa_id, mapel_id, uts, uas)
    VALUES (:siswa_id, :mapel_id, :uts, :uas)
");

$sqlUpdate = $pdo->prepare("
    UPDATE nilai_uts_uas
    SET uts = :uts,
        uas = :uas
    WHERE id = :id
");

$pdo->beginTransaction();

try {

    // Kita gunakan gabungan key dari uts dan uas supaya tidak ada yang terlewat
    $allSiswaIds = array_unique(
        array_merge(
            array_keys($utsArr),
            array_keys($uasArr)
        )
    );

    foreach ($allSiswaIds as $sid) {
        $sid = (int)$sid;
        if ($sid <= 0) continue;

        $uts = $utsArr[$sid] ?? '';
        $uas = $uasArr[$sid] ?? '';

        // Jika dua-duanya kosong, lewati
        if ($uts === '' && $uas === '') {
            continue;
        }

        // Normalisasi nilai
        $nUTS = ($uts === '' ? null : max(0, min(100, (float)$uts)));
        $nUAS = ($uas === '' ? null : max(0, min(100, (float)$uas)));

        // Cek apakah sudah ada record
        $sqlCek->execute([$sid, $mapel_id]);
        $existing = $sqlCek->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Update
            $sqlUpdate->execute([
                ':uts' => $nUTS,
                ':uas' => $nUAS,
                ':id'  => $existing['id'],
            ]);
        } else {
            // Insert baru
            $sqlInsert->execute([
                ':siswa_id' => $sid,
                ':mapel_id' => $mapel_id,
                ':uts'      => $nUTS,
                ':uas'      => $nUAS,
            ]);
        }
    }

    $pdo->commit();

    $redirect = "nilai_uts_uas.php?mapel_id={$mapel_id}&kelas=" . urlencode($kelas) . "&saved=1";
    header("Location: " . $redirect);
    exit;

} catch (PDOException $e) {

    $pdo->rollBack();
    die("Terjadi kesalahan saat menyimpan nilai UTS/UAS: " . htmlspecialchars($e->getMessage()));
}
