<?php
// guru/simpan_keterampilan.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth.php';

requireLogin();
requireGuru();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: nilai_keterampilan.php');
    exit;
}

$mapelId     = (int)($_POST['mapel_id'] ?? 0);
$kelas       = trim($_POST['kelas'] ?? '');
$tahunAjaran = trim($_POST['tahun_ajaran'] ?? '2024/2025');  // kalau form belum ada TA/semester, boleh diset default
$semester    = trim($_POST['semester'] ?? 'Ganjil');
$nilaiPost   = $_POST['nilai'] ?? []; // [siswa_id][kd_id] => nilai akhir per KD

if (!$mapelId || $kelas === '') {
    die('Parameter tidak lengkap. Silakan kembali ke halaman sebelumnya.');
}

if (!is_array($nilaiPost) || empty($nilaiPost)) {
    die('Tidak ada nilai keterampilan yang dikirim.');
}

// Helper: parsing angka
function parse_num($v) {
    if ($v === null || $v === '') return null;
    if (is_array($v)) return null;
    $v = str_replace(',', '.', (string)$v);
    if (!is_numeric($v)) return null;
    return floatval($v);
}

// Predikat dari nilai
function hitung_predikat($nilai) {
    if ($nilai === null) return null;
    if ($nilai >= 90) return 'A';
    if ($nilai >= 80) return 'B';
    if ($nilai >= 70) return 'C';
    return 'D';
}

try {
    $pdo->beginTransaction();

    // Cek existing
    $stmtSelect = $pdo->prepare("
        SELECT id
        FROM nilai_keterampilan
        WHERE siswa_id = ?
          AND mapel_id = ?
          AND kd_id = ?
          AND tahun_ajaran = ?
          AND semester = ?
        LIMIT 1
    ");

    // Insert baru
    $stmtInsert = $pdo->prepare("
        INSERT INTO nilai_keterampilan
        (siswa_id, mapel_id, kd_id, nilai_akhir, predikat, tahun_ajaran, semester)
        VALUES
        (?, ?, ?, ?, ?, ?, ?)
    ");

    // Update existing
    $stmtUpdate = $pdo->prepare("
        UPDATE nilai_keterampilan
        SET nilai_akhir = ?, predikat = ?
        WHERE id = ?
    ");

    foreach ($nilaiPost as $siswaId => $perKd) {
        $siswaId = (int)$siswaId;
        if (!is_array($perKd)) continue;

        foreach ($perKd as $kdId => $v) {
            $kdId = (int)$kdId;
            $n = parse_num($v);
            if ($n === null) {
                // jika kosong, lewati (tidak simpan)
                continue;
            }

            // Clamp 0–100 & bulatkan
            if ($n < 0)   $n = 0;
            if ($n > 100) $n = 100;
            $n = round($n);

            $pred = hitung_predikat($n);

            // Cek apakah sudah ada nilai KD ini
            $stmtSelect->execute([$siswaId, $mapelId, $kdId, $tahunAjaran, $semester]);
            $existing = $stmtSelect->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $stmtUpdate->execute([$n, $pred, $existing['id']]);
            } else {
                $stmtInsert->execute([$siswaId, $mapelId, $kdId, $n, $pred, $tahunAjaran, $semester]);
            }
        }
    }

    $pdo->commit();

    // Kembali ke form dengan parameter
    $qs = http_build_query([
        'mapel_id'      => $mapelId,
        'kelas'         => $kelas,
        'tahun_ajaran'  => $tahunAjaran,
        'semester'      => $semester,
        'success'       => 1,
    ]);
    header("Location: nilai_keterampilan.php?$qs");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    echo "Terjadi kesalahan saat menyimpan nilai keterampilan: " . htmlspecialchars($e->getMessage());
}
