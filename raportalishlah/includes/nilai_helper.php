<?php
// includes/nilai_helper.php

// Bobot (bisa kamu sesuaikan nanti agar 100% sama dengan Excel)
const BOBOT_NH  = 0.60;   // nilai harian (KD)
const BOBOT_UTS = 0.20;
const BOBOT_UAS = 0.20;

function predikat_dari_nilai(?float $n): string {
    if ($n === null) return '-';
    if ($n >= 90) return 'A';
    if ($n >= 80) return 'B';
    if ($n >= 70) return 'C';
    return 'D';
}

function hitung_nilai_pengetahuan_rapor(?float $nh, ?float $uts, ?float $uas): ?float {
    if ($nh === null && $uts === null && $uas === null) {
        return null;
    }
    $nh  = $nh  ?? 0;
    $uts = $uts ?? 0;
    $uas = $uas ?? 0;

    $n = $nh * BOBOT_NH + $uts * BOBOT_UTS + $uas * BOBOT_UAS;
    return round($n, 1);
}

function deskripsi_pengetahuan(float $nilai, string $namaMapel): string {
    if ($nilai >= 90) {
        return "Peserta didik memiliki kemampuan sangat baik dalam penguasaan materi $namaMapel.";
    } elseif ($nilai >= 80) {
        return "Peserta didik memiliki kemampuan baik dalam memahami materi $namaMapel.";
    } elseif ($nilai >= 70) {
        return "Peserta didik cukup mampu memahami materi $namaMapel, namun masih perlu bimbingan pada beberapa kompetensi.";
    } else {
        return "Peserta didik masih perlu bimbingan dalam memahami materi $namaMapel.";
    }
}

function deskripsi_keterampilan(float $nilai, string $namaMapel): string {
    if ($nilai >= 90) {
        return "Peserta didik menunjukkan keterampilan sangat baik dalam melaksanakan tugas $namaMapel.";
    } elseif ($nilai >= 80) {
        return "Peserta didik menunjukkan keterampilan baik dalam melaksanakan tugas $namaMapel.";
    } elseif ($nilai >= 70) {
        return "Peserta didik cukup terampil dalam melaksanakan tugas $namaMapel, namun masih perlu latihan.";
    } else {
        return "Peserta didik masih perlu bimbingan dan latihan dalam keterampilan $namaMapel.";
    }
}

function deskripsi_sikap(float $nilai, bool $spiritual = true): string {
    if ($spiritual) {
        if ($nilai >= 90) {
            return "Sikap spiritual peserta didik sangat baik; kebiasaan beribadah, bersyukur, berdoa, dan tawakal sudah berkembang sangat baik.";
        } elseif ($nilai >= 80) {
            return "Sikap spiritual peserta didik baik; kebiasaan beribadah, bersyukur, berdoa, dan tawakal sudah berkembang.";
        } elseif ($nilai >= 70) {
            return "Sikap spiritual peserta didik cukup; beberapa kebiasaan ibadah dan rasa syukur mulai berkembang.";
        } else {
            return "Sikap spiritual peserta didik masih perlu bimbingan dalam membiasakan ibadah, berdoa, dan bersyukur.";
        }
    } else {
        if ($nilai >= 90) {
            return "Sikap sosial peserta didik sangat baik; jujur, disiplin, santun, peduli, dan bertanggung jawab sudah tampak konsisten.";
        } elseif ($nilai >= 80) {
            return "Sikap sosial peserta didik baik; jujur, disiplin, santun, dan tanggung jawab sudah berkembang.";
        } elseif ($nilai >= 70) {
            return "Sikap sosial peserta didik cukup; beberapa sikap jujur, disiplin, dan tanggung jawab mulai berkembang.";
        } else {
            return "Sikap sosial peserta didik masih perlu bimbingan dalam kejujuran, kedisiplinan, dan tanggung jawab.";
        }
    }
}
