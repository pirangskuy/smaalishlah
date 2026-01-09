<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../includes/sikap_helper.php';

requireLogin();
requireGuru();

$guruId       = $_SESSION['user']['guru_id'] ?? null;
$tahunAjaran  = $_POST['tahun_ajaran'] ?? '2024/2025';
$semester     = $_POST['semester'] ?? 'Genap';
$mapelId      = (int)($_POST['mapel_id'] ?? 0);   // mapel PAI

// array nilai per siswa per aspek, misal:
// $_POST['taat_beribadah'][siswa_id] = 4
$taat   = $_POST['taat_beribadah'] ?? [];
$syukur = $_POST['rasa_syukur'] ?? [];
$berdoa = $_POST['berdoa'] ?? [];
$toler  = $_POST['toleransi'] ?? [];
$peduli = $_POST['peduli_sesama'] ?? [];
$tawakal= $_POST['tawakal'] ?? [];
$salam  = $_POST['memberi_salam'] ?? [];

$pdo->beginTransaction();

$upsert = $pdo->prepare("
    INSERT INTO sikap_spiritual
      (siswa_id,mapel_id,tahun_ajaran,semester,
       taat_beribadah,rasa_syukur,berdoa,toleransi,
       peduli_sesama,tawakal,memberi_salam,
       nilai_dominan,predikat,kategori,deskripsi)
    VALUES
      (:siswa_id,:mapel_id,:ta,:sem,
       :taat,:syukur,:berdoa,:toler,
       :peduli,:tawakal,:salam,
       :dom,:pred,:kat,:desk)
    ON DUPLICATE KEY UPDATE
       taat_beribadah=VALUES(taat_beribadah),
       rasa_syukur=VALUES(rasa_syukur),
       berdoa=VALUES(berdoa),
       toleransi=VALUES(toleransi),
       peduli_sesama=VALUES(peduli_sesama),
       tawakal=VALUES(tawakal),
       memberi_salam=VALUES(memberi_salam),
       nilai_dominan=VALUES(nilai_dominan),
       predikat=VALUES(predikat),
       kategori=VALUES(kategori),
       deskripsi=VALUES(deskripsi)
");

foreach ($taat as $siswaId => $nilaiTaat) {
    $siswaId = (int)$siswaId;

    $rowNilai = [
        'taat_beribadah'  => $nilaiTaat,
        'rasa_syukur'     => $syukur[$siswaId] ?? null,
        'berdoa'          => $berdoa[$siswaId] ?? null,
        'toleransi'       => $toler[$siswaId] ?? null,
        'peduli_sesama'   => $peduli[$siswaId] ?? null,
        'tawakal'         => $tawakal[$siswaId] ?? null,
        'memberi_salam'   => $salam[$siswaId] ?? null,
    ];

    $hasil = hitung_sikap_spiritual($rowNilai);

    $upsert->execute([
        ':siswa_id' => $siswaId,
        ':mapel_id' => $mapelId,
        ':ta'       => $tahunAjaran,
        ':sem'      => $semester,
        ':taat'     => $rowNilai['taat_beribadah'],
        ':syukur'   => $rowNilai['rasa_syukur'],
        ':berdoa'   => $rowNilai['berdoa'],
        ':toler'    => $rowNilai['toleransi'],
        ':peduli'   => $rowNilai['peduli_sesama'],
        ':tawakal'  => $rowNilai['tawakal'],
        ':salam'    => $rowNilai['memberi_salam'],
        ':dom'      => $hasil['nilai_dominan'],
        ':pred'     => $hasil['predikat'],
        ':kat'      => $hasil['kategori'],
        ':desk'     => $hasil['deskripsi'],
    ]);
}

$pdo->commit();

// redirect balik ke halaman input sikap
header("Location: sikap_spiritual_pai.php?mapel_id={$mapelId}&tahun_ajaran={$tahunAjaran}&semester={$semester}");
exit;
