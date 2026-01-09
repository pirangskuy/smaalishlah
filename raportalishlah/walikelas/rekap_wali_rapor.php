<?php
// walikelas/rekap_wali_rapor.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once __DIR__ . '/../config/database.php'; // atau ../config.php, sesuaikan projekmu
require_once __DIR__ . '/../auth/wali_auth.php';

requireWaliKelas();

/**
 * 1. AMBIL DATA GURU WALI & KELAS WALI
 */

$waliGuru  = null;
$kelasWali = null;

// Coba pakai session guru kalau sudah ada
if (isset($_SESSION['guru']) && is_array($_SESSION['guru'])) {
    $waliGuru  = $_SESSION['guru'];
    $kelasWali = $waliGuru['kelas_wali'] ?? null;
}

// Kalau belum ada kelas wali di session, ambil dari tabel teachers
if (!$kelasWali) {
    if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? null) !== 'walikelas') {
        die("<p style='color:red;'>Session wali kelas tidak valid. Silakan login ulang.</p>");
    }

    $user   = $_SESSION['user'];
    $guruId = $user['guru_id'] ?? null;

    if ($guruId) {
        $stmt = $pdo->prepare("SELECT * FROM teachers WHERE id = ? LIMIT 1");
        $stmt->execute([$guruId]);
        $waliGuru = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM teachers WHERE username = ? LIMIT 1");
        $stmt->execute([$user['username']]);
        $waliGuru = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$waliGuru) {
        die("<p style='color:red;'>Data guru wali tidak ditemukan pada tabel <code>teachers</code>.</p>");
    }

    $_SESSION['guru'] = $waliGuru;
    $kelasWali        = $waliGuru['kelas_wali'] ?? null;
}

if (!$kelasWali) {
    die("<p style='color:red;'>Guru ini belum memiliki kelas wali. Isi kolom <code>kelas_wali</code> di tabel <code>teachers</code> terlebih dahulu.</p>");
}

/**
 * 2. JIKA FORM DISUBMIT → SIMPAN REKAP KE TABEL wali_rapor_rekap
 */

$msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rekapPost = $_POST['rekap'] ?? [];

    if (is_array($rekapPost) && !empty($rekapPost)) {
        $sql = "
            INSERT INTO wali_rapor_rekap (
                student_id,
                ekskul_nama, ekskul_nilai, ekskul_deskripsi,
                prestasi1_jenis, prestasi1_ket,
                prestasi2_jenis, prestasi2_ket,
                prestasi3_jenis, prestasi3_ket,
                prestasi4_jenis, prestasi4_ket,
                sakit, izin, alfa,
                catatan_wali
            ) VALUES (
                :sid,
                :ekskul_nama, :ekskul_nilai, :ekskul_deskripsi,
                :p1_jenis, :p1_ket,
                :p2_jenis, :p2_ket,
                :p3_jenis, :p3_ket,
                :p4_jenis, :p4_ket,
                :sakit, :izin, :alfa,
                :catatan
            )
            ON DUPLICATE KEY UPDATE
                ekskul_nama       = VALUES(ekskul_nama),
                ekskul_nilai      = VALUES(ekskul_nilai),
                ekskul_deskripsi  = VALUES(ekskul_deskripsi),
                prestasi1_jenis   = VALUES(prestasi1_jenis),
                prestasi1_ket     = VALUES(prestasi1_ket),
                prestasi2_jenis   = VALUES(prestasi2_jenis),
                prestasi2_ket     = VALUES(prestasi2_ket),
                prestasi3_jenis   = VALUES(prestasi3_jenis),
                prestasi3_ket     = VALUES(prestasi3_ket),
                prestasi4_jenis   = VALUES(prestasi4_jenis),
                prestasi4_ket     = VALUES(prestasi4_ket),
                sakit             = VALUES(sakit),
                izin              = VALUES(izin),
                alfa              = VALUES(alfa),
                catatan_wali      = VALUES(catatan_wali)
        ";

        $stmtSave = $pdo->prepare($sql);

        foreach ($rekapPost as $sid => $row) {
            $sid = (int)$sid;

            $ekskul_nama      = trim($row['ekskul_nama'] ?? '');
            $ekskul_nilai     = trim($row['ekskul_nilai'] ?? '');
            $ekskul_deskripsi = trim($row['ekskul_deskripsi'] ?? '');

            $p1_jenis = trim($row['prestasi1_jenis'] ?? '');
            $p1_ket   = trim($row['prestasi1_ket'] ?? '');
            $p2_jenis = trim($row['prestasi2_jenis'] ?? '');
            $p2_ket   = trim($row['prestasi2_ket'] ?? '');
            $p3_jenis = trim($row['prestasi3_jenis'] ?? '');
            $p3_ket   = trim($row['prestasi3_ket'] ?? '');
            $p4_jenis = trim($row['prestasi4_jenis'] ?? '');
            $p4_ket   = trim($row['prestasi4_ket'] ?? '');

            $sakit = isset($row['sakit']) ? (int)$row['sakit'] : 0;
            $izin  = isset($row['izin'])  ? (int)$row['izin']  : 0;
            $alfa  = isset($row['alfa'])  ? (int)$row['alfa']  : 0;

            $catatan = trim($row['catatan_wali'] ?? '');

            $stmtSave->execute([
                'sid'            => $sid,
                'ekskul_nama'    => $ekskul_nama,
                'ekskul_nilai'   => $ekskul_nilai,
                'ekskul_deskripsi' => $ekskul_deskripsi,
                'p1_jenis'       => $p1_jenis,
                'p1_ket'         => $p1_ket,
                'p2_jenis'       => $p2_jenis,
                'p2_ket'         => $p2_ket,
                'p3_jenis'       => $p3_jenis,
                'p3_ket'         => $p3_ket,
                'p4_jenis'       => $p4_jenis,
                'p4_ket'         => $p4_ket,
                'sakit'          => $sakit,
                'izin'           => $izin,
                'alfa'           => $alfa,
                'catatan'        => $catatan,
            ]);
        }

        $msg = "Rekap wali kelas berhasil disimpan.";
    } else {
        $msg = "Tidak ada data yang dikirim.";
    }
}

/**
 * 3. AMBIL DATA SISWA KELAS WALI
 */

$stmt = $pdo->prepare("
    SELECT * 
    FROM siswa 
    WHERE kelas = :kelas
    ORDER BY nama ASC
");
$stmt->execute(['kelas' => $kelasWali]);
$siswa_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * 4. AMBIL DATA REKAP WALI UNTUK SISWA TERSEBUT
 */

$waliData = [];

if (!empty($siswa_list)) {
    $ids = array_column($siswa_list, 'id');
    $in  = implode(',', array_fill(0, count($ids), '?'));

    $stmtRekap = $pdo->prepare("
        SELECT * FROM wali_rapor_rekap
        WHERE student_id IN ($in)
    ");
    $stmtRekap->execute($ids);
    $rows = $stmtRekap->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $waliData[(int)$row['student_id']] = $row;
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rekap Wali Kelas - Kelas <?= htmlspecialchars((string)$kelasWali); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        th, td {
            font-size: 11px;
            vertical-align: middle;
            white-space: nowrap;
        }
        textarea {
            font-size: 11px;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .header-top {
            background-color: #ffe699; /* mirip kuning di excel */
        }
        .header-ekskul {
            background-color: #c6efce; /* hijau muda */
        }
        .header-prestasi {
            background-color: #bdd7ee; /* biru muda */
        }
        .header-absen {
            background-color: #f8cbad; /* merah muda */
        }
        .header-catatan {
            background-color: #ffd966;
        }
    </style>
</head>
<body>
<div class="container-fluid mt-3">
    <h4>Rekap Wali Kelas - Kelas <?= htmlspecialchars((string)$kelasWali); ?></h4>

    <?php if ($msg): ?>
        <div class="alert alert-info py-2 mt-2"><?= htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="table-responsive mt-3">
            <table class="table table-bordered table-sm">
                <thead>
                    <tr class="text-center header-top">
                        <th rowspan="2">No</th>
                        <th rowspan="2">Nama Siswa</th>
                        <th rowspan="2">L/P</th>

                        <th colspan="3" class="header-ekskul">Ekstrakurikuler</th>

                        <th colspan="2" class="header-prestasi">Prestasi 1</th>
                        <th colspan="2" class="header-prestasi">Prestasi 2</th>
                        <th colspan="2" class="header-prestasi">Prestasi 3</th>
                        <th colspan="2" class="header-prestasi">Prestasi 4</th>

                        <th colspan="3" class="header-absen">Ketidakhadiran</th>

                        <th rowspan="2" class="header-catatan">Catatan Wali Kelas</th>
                    </tr>
                    <tr class="text-center">
                        <th class="header-ekskul">Nama</th>
                        <th class="header-ekskul">Nilai</th>
                        <th class="header-ekskul">Deskripsi</th>

                        <th class="header-prestasi">Jenis</th>
                        <th class="header-prestasi">Ket.</th>
                        <th class="header-prestasi">Jenis</th>
                        <th class="header-prestasi">Ket.</th>
                        <th class="header-prestasi">Jenis</th>
                        <th class="header-prestasi">Ket.</th>
                        <th class="header-prestasi">Jenis</th>
                        <th class="header-prestasi">Ket.</th>

                        <th class="header-absen">Sakit</th>
                        <th class="header-absen">Izin</th>
                        <th class="header-absen">Alfa</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($siswa_list)): ?>
                        <tr>
                            <td colspan="18" class="text-center text-muted">
                                Belum ada siswa di kelas ini.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($siswa_list as $index => $siswa): 
                            $sid = (int)$siswa['id'];
                            $d   = $waliData[$sid] ?? [];

                            // sesuaikan nama kolom jenis kelamin dengan tabelmu (jk / jenis_kelamin / gender)
                            $jk = $siswa['jk'] ?? ($siswa['jenis_kelamin'] ?? '');
                        ?>
                            <tr>
                                <td class="text-center"><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars($siswa['nama']); ?></td>
                                <td class="text-center"><?= htmlspecialchars($jk); ?></td>

                                <td>
                                    <input type="text"
                                           name="rekap[<?= $sid ?>][ekskul_nama]"
                                           class="form-control form-control-sm"
                                           value="<?= htmlspecialchars($d['ekskul_nama'] ?? ''); ?>">
                                </td>
                                <td style="max-width:60px;">
                                    <input type="text"
                                           name="rekap[<?= $sid ?>][ekskul_nilai]"
                                           class="form-control form-control-sm"
                                           value="<?= htmlspecialchars($d['ekskul_nilai'] ?? ''); ?>">
                                </td>
                                <td>
                                    <textarea name="rekap[<?= $sid ?>][ekskul_deskripsi]"
                                              class="form-control form-control-sm"
                                              rows="1"><?= htmlspecialchars($d['ekskul_deskripsi'] ?? ''); ?></textarea>
                                </td>

                                <!-- Prestasi 1 -->
                                <td>
                                    <input type="text"
                                           name="rekap[<?= $sid ?>][prestasi1_jenis]"
                                           class="form-control form-control-sm"
                                           value="<?= htmlspecialchars($d['prestasi1_jenis'] ?? ''); ?>">
                                </td>
                                <td>
                                    <textarea name="rekap[<?= $sid ?>][prestasi1_ket]"
                                              class="form-control form-control-sm"
                                              rows="1"><?= htmlspecialchars($d['prestasi1_ket'] ?? ''); ?></textarea>
                                </td>

                                <!-- Prestasi 2 -->
                                <td>
                                    <input type="text"
                                           name="rekap[<?= $sid ?>][prestasi2_jenis]"
                                           class="form-control form-control-sm"
                                           value="<?= htmlspecialchars($d['prestasi2_jenis'] ?? ''); ?>">
                                </td>
                                <td>
                                    <textarea name="rekap[<?= $sid ?>][prestasi2_ket]"
                                              class="form-control form-control-sm"
                                              rows="1"><?= htmlspecialchars($d['prestasi2_ket'] ?? ''); ?></textarea>
                                </td>

                                <!-- Prestasi 3 -->
                                <td>
                                    <input type="text"
                                           name="rekap[<?= $sid ?>][prestasi3_jenis]"
                                           class="form-control form-control-sm"
                                           value="<?= htmlspecialchars($d['prestasi3_jenis'] ?? ''); ?>">
                                </td>
                                <td>
                                    <textarea name="rekap[<?= $sid ?>][prestasi3_ket]"
                                              class="form-control form-control-sm"
                                              rows="1"><?= htmlspecialchars($d['prestasi3_ket'] ?? ''); ?></textarea>
                                </td>

                                <!-- Prestasi 4 -->
                                <td>
                                    <input type="text"
                                           name="rekap[<?= $sid ?>][prestasi4_jenis]"
                                           class="form-control form-control-sm"
                                           value="<?= htmlspecialchars($d['prestasi4_jenis'] ?? ''); ?>">
                                </td>
                                <td>
                                    <textarea name="rekap[<?= $sid ?>][prestasi4_ket]"
                                              class="form-control form-control-sm"
                                              rows="1"><?= htmlspecialchars($d['prestasi4_ket'] ?? ''); ?></textarea>
                                </td>

                                <!-- Ketidakhadiran -->
                                <td style="max-width:60px;">
                                    <input type="number" min="0"
                                           name="rekap[<?= $sid ?>][sakit]"
                                           class="form-control form-control-sm text-center"
                                           value="<?= (int)($d['sakit'] ?? 0); ?>">
                                </td>
                                <td style="max-width:60px;">
                                    <input type="number" min="0"
                                           name="rekap[<?= $sid ?>][izin]"
                                           class="form-control form-control-sm text-center"
                                           value="<?= (int)($d['izin'] ?? 0); ?>">
                                </td>
                                <td style="max-width:60px;">
                                    <input type="number" min="0"
                                           name="rekap[<?= $sid ?>][alfa]"
                                           class="form-control form-control-sm text-center"
                                           value="<?= (int)($d['alfa'] ?? 0); ?>">
                                </td>

                                <!-- Catatan wali -->
                                <td>
                                    <textarea name="rekap[<?= $sid ?>][catatan_wali]"
                                              class="form-control form-control-sm"
                                              rows="1"><?= htmlspecialchars($d['catatan_wali'] ?? ''); ?></textarea>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-2 mb-3">
            <button type="submit" class="btn btn-primary btn-sm">Simpan Rekap Wali</button>
            <a href="wali_dashboard.php" class="btn btn-secondary btn-sm">Kembali ke Dashboard</a>
            <!-- nanti tombol cetak rapor bisa diarahkan ke file cetak khusus -->
            <a href="cetak_rapor_kelas.php" class="btn btn-success btn-sm">Cetak Raport Kelas Ini</a>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
