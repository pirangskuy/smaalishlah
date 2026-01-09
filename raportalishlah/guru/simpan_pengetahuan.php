<?php
// guru/simpan_pengetahuan.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth.php';

requireLogin();
requireGuru();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: nilai_pengetahuan.php');
    exit;
}

$mapelId      = (int)($_POST['mapel_id'] ?? 0);
$kelas        = trim($_POST['kelas'] ?? '');
$tahunAjaran  = trim($_POST['tahun_ajaran'] ?? '');
$semester     = trim($_POST['semester'] ?? '');
$komponen     = $_POST['komponen'] ?? [];  // [siswa_id][kd_id][p1/p2/p3/u1/u2/rem1/rem2]
$nilaiPos     = $_POST['nilai'] ?? [];     // fallback: [siswa_id][kd_id] => nilai akhir (kalau dipakai)

if (!$mapelId || $kelas === '' || $tahunAjaran === '' || $semester === '') {
    die('Parameter tidak lengkap. Silakan kembali ke halaman sebelumnya.');
}

if (!is_array($komponen) && !is_array($nilaiPos)) {
    die('Data nilai tidak valid.');
}

// Helper: parsing angka
function parse_num($v) {
    if ($v === null || $v === '') return null;
    if (is_array($v)) return null;
    $v = str_replace(',', '.', (string)$v);
    if (!is_numeric($v)) return null;
    return floatval($v);
}

// Helper: rata-rata
function avg_non_null(array $values) {
    $valid = [];
    foreach ($values as $v) {
        if ($v !== null) {
            $valid[] = $v;
        }
    }
    if (count($valid) === 0) return null;
    return array_sum($valid) / count($valid);
}

// Ambil KKM mapel (kalau mau dipakai untuk logika, minimal untuk info)
$stmtMapel = $pdo->prepare("SELECT kkm FROM mapel WHERE id = ? LIMIT 1");
$stmtMapel->execute([$mapelId]);
$mapelRow = $stmtMapel->fetch(PDO::FETCH_ASSOC);
$kkmMapel = $mapelRow ? (int)$mapelRow['kkm'] : 0;

// Menentukan predikat dari nilai_akhir
function hitung_predikat($nilai) {
    if ($nilai === null) return null;
    if ($nilai >= 90) return 'A';
    if ($nilai >= 80) return 'B';
    if ($nilai >= 70) return 'C';
    return 'D';
}

// Hasil akhir yang akan disimpan ke tabel nilai_pengetahuan
// Struktur: [siswa_id][kd_id] = ['p1'=>..,'p2'=>..,'p3'=>..,'rata_penugasan'=>..,'u1'=>..,'u2'=>..,'rata_ulangan'=>..,'nilai_akhir'=>..,'predikat'=>..]
$nilaiFinal = [];

// 1. Utama: hitung dari komponen (P1–P3, U1–U2, Rem1–Rem2)
if (is_array($komponen) && !empty($komponen)) {

    foreach ($komponen as $siswaId => $perKd) {
        $siswaId = (int)$siswaId;
        if (!is_array($perKd)) continue;

        foreach ($perKd as $kdId => $row) {
            $kdId = (int)$kdId;
            if (!is_array($row)) continue;

            $p1   = isset($row['p1'])   ? parse_num($row['p1'])   : null;
            $p2   = isset($row['p2'])   ? parse_num($row['p2'])   : null;
            $p3   = isset($row['p3'])   ? parse_num($row['p3'])   : null;
            $u1   = isset($row['u1'])   ? parse_num($row['u1'])   : null;
            $u2   = isset($row['u2'])   ? parse_num($row['u2'])   : null;
            $rem1 = isset($row['rem1']) ? parse_num($row['rem1']) : null;
            $rem2 = isset($row['rem2']) ? parse_num($row['rem2']) : null;

            // Clamp 0–100
            foreach (['p1','p2','p3','u1','u2','rem1','rem2'] as $k) {
                if ($$k !== null) {
                    if ($$k < 0) $$k = 0;
                    if ($$k > 100) $$k = 100;
                }
            }

            // Rata-rata tugas & ulangan
            $rataTugas   = avg_non_null([$p1, $p2, $p3]);
            $rataUlangan = avg_non_null([$u1, $u2]);

            // Nilai awal KD sebelum remedial
            $na = null;
            if ($rataTugas !== null && $rataUlangan !== null) {
                $na = ($rataTugas + $rataUlangan) / 2;
            } elseif ($rataTugas !== null) {
                $na = $rataTugas;
            } elseif ($rataUlangan !== null) {
                $na = $rataUlangan;
            }

            // Terapkan remedial: ambil yang tertinggi
            if ($rem1 !== null) {
                if ($na === null || $rem1 > $na) {
                    $na = $rem1;
                }
            }
            if ($rem2 !== null) {
                if ($na === null || $rem2 > $na) {
                    $na = $rem2;
                }
            }

            if ($rataTugas !== null) {
                if ($rataTugas < 0)   $rataTugas = 0;
                if ($rataTugas > 100) $rataTugas = 100;
            }
            if ($rataUlangan !== null) {
                if ($rataUlangan < 0)   $rataUlangan = 0;
                if ($rataUlangan > 100) $rataUlangan = 100;
            }

            if ($na === null) {
                // tidak semua diisi, boleh lewati (tidak simpan)
                continue;
            }

            // Clamp & pembulatan nilai akhir
            if ($na < 0)   $na = 0;
            if ($na > 100) $na = 100;
            $na = round($na);

            $pred = hitung_predikat($na);

            $nilaiFinal[$siswaId][$kdId] = [
                'p1'             => $p1,
                'p2'             => $p2,
                'p3'             => $p3,
                'rata_penugasan' => $rataTugas,
                'u1'             => $u1,
                'u2'             => $u2,
                'rata_ulangan'   => $rataUlangan,
                'nilai_akhir'    => $na,
                'predikat'       => $pred,
            ];
        }
    }
}

// 2. Fallback: kalau komponen kosong tapi ada nilai akhir langsung
if (empty($nilaiFinal) && is_array($nilaiPos)) {
    foreach ($nilaiPos as $siswaId => $perKd) {
        $siswaId = (int)$siswaId;
        if (!is_array($perKd)) continue;

        foreach ($perKd as $kdId => $v) {
            $kdId = (int)$kdId;
            $n = parse_num($v);
            if ($n === null) continue;

            if ($n < 0)   $n = 0;
            if ($n > 100) $n = 100;
            $n = round($n);

            $pred = hitung_predikat($n);

            $nilaiFinal[$siswaId][$kdId] = [
                'p1'             => null,
                'p2'             => null,
                'p3'             => null,
                'rata_penugasan' => null,
                'u1'             => null,
                'u2'             => null,
                'rata_ulangan'   => null,
                'nilai_akhir'    => $n,
                'predikat'       => $pred,
            ];
        }
    }
}

if (empty($nilaiFinal)) {
    $qs = http_build_query([
        'mapel_id'      => $mapelId,
        'kelas'         => $kelas,
        'tahun_ajaran'  => $tahunAjaran,
        'semester'      => $semester,
        'success'       => 0,
        'msg'           => 'Tidak ada nilai yang diisi.'
    ]);
    header("Location: nilai_pengetahuan.php?$qs");
    exit;
}

try {
    $pdo->beginTransaction();

    // Cek apakah record sudah ada
    $stmtSelect = $pdo->prepare("
        SELECT id
        FROM nilai_pengetahuan
        WHERE siswa_id = ?
          AND mapel_id = ?
          AND kd_id = ?
          AND tahun_ajaran = ?
          AND semester = ?
        LIMIT 1
    ");

    // INSERT baru
    $stmtInsert = $pdo->prepare("
        INSERT INTO nilai_pengetahuan
        (siswa_id, mapel_id, kd_id,
         p1, p2, p3, rata_penugasan,
         u1, u2, rata_ulangan,
         nilai_akhir, predikat,
         tahun_ajaran, semester)
        VALUES
        (?, ?, ?,
         ?, ?, ?, ?,
         ?, ?, ?,
         ?, ?,
         ?, ?)
    ");

    // UPDATE existing
    $stmtUpdate = $pdo->prepare("
        UPDATE nilai_pengetahuan
        SET p1 = ?, p2 = ?, p3 = ?, rata_penugasan = ?,
            u1 = ?, u2 = ?, rata_ulangan = ?,
            nilai_akhir = ?, predikat = ?
        WHERE id = ?
    ");

    foreach ($nilaiFinal as $siswaId => $perKd) {
        foreach ($perKd as $kdId => $data) {

            $p1   = $data['p1'];
            $p2   = $data['p2'];
            $p3   = $data['p3'];
            $rt   = $data['rata_penugasan'];
            $u1   = $data['u1'];
            $u2   = $data['u2'];
            $ru   = $data['rata_ulangan'];
            $na   = $data['nilai_akhir'];
            $pred = $data['predikat'];

            $stmtSelect->execute([$siswaId, $mapelId, $kdId, $tahunAjaran, $semester]);
            $existing = $stmtSelect->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // update
                $stmtUpdate->execute([
                    $p1, $p2, $p3, $rt,
                    $u1, $u2, $ru,
                    $na, $pred,
                    $existing['id']
                ]);
            } else {
                // insert baru
                $stmtInsert->execute([
                    $siswaId, $mapelId, $kdId,
                    $p1, $p2, $p3, $rt,
                    $u1, $u2, $ru,
                    $na, $pred,
                    $tahunAjaran, $semester
                ]);
            }
        }
    }

    $pdo->commit();

    $qs = http_build_query([
        'mapel_id'      => $mapelId,
        'kelas'         => $kelas,
        'tahun_ajaran'  => $tahunAjaran,
        'semester'      => $semester,
        'success'       => 1,
    ]);
    header("Location: nilai_pengetahuan.php?$qs");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    echo "Terjadi kesalahan saat menyimpan nilai: " . htmlspecialchars($e->getMessage());
}
