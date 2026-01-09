<?php
if (!function_exists('mode_value')) {
    function mode_value(array $values): ?int {
        // buang null/kosong
        $filtered = [];
        foreach ($values as $v) {
            if ($v === null || $v === '') continue;
            $n = (int)$v;
            if ($n <= 0) continue;
            $filtered[] = $n;
        }
        if (empty($filtered)) return null;

        $counts = [];
        foreach ($filtered as $n) {
            if (!isset($counts[$n])) $counts[$n] = 0;
            $counts[$n]++;
        }
        // cari nilai dengan frekuensi tertinggi (kalau seri ambil yang terkecil, mirip MODE Excel)
        arsort($counts);              // urut desc by frekuensi
        $maxFreq = reset($counts);    // nilai pertama
        $candidates = array_keys(array_filter($counts, fn($f) => $f === $maxFreq));
        sort($candidates);            // ambil yang paling kecil
        return $candidates[0];
    }
}

if (!function_exists('predikat_sikap')) {
    function predikat_sikap(?int $nilai): ?string {
        if ($nilai === null) return null;
        if ($nilai <= 1) return 'D';
        if ($nilai <= 2) return 'C';
        if ($nilai <= 3) return 'B';
        return 'A';
    }
}

if (!function_exists('kategori_sikap')) {
    function kategori_sikap(?int $nilai): ?string {
        if ($nilai === null) return null;
        if ($nilai >= 4) return 'sudah berkembang';
        if ($nilai >= 3) return 'mulai berkembang';
        return 'perlu bimbingan';
    }
}

/**
 * Hitung sikap spiritual PAI (mirip sheet "Sikap Spiritual" Excel)
 * Input: array nilai 1-4 per aspek
 */
function hitung_sikap_spiritual(array $n): array {
    $list = [
        $n['taat_beribadah'] ?? null,
        $n['rasa_syukur'] ?? null,
        $n['berdoa'] ?? null,
        $n['toleransi'] ?? null,
        $n['peduli_sesama'] ?? null,
        $n['tawakal'] ?? null,
        $n['memberi_salam'] ?? null,
    ];

    $dominan  = mode_value($list);
    $pred     = predikat_sikap($dominan);
    $kategori = kategori_sikap($dominan);

    // kalimat deskripsi: adaptasi dari AA13 di Excel
    $deskripsi = null;
    if ($kategori !== null) {
        $deskripsi = "Sikap peserta didik dalam taat beribadah, rasa syukur, "
                   . "berdoa, toleransi, peduli sesama, berserah diri (tawakal), "
                   . "dan memberi salam {$kategori}.";
    }

    return [
        'nilai_dominan' => $dominan,
        'predikat'      => $pred,
        'kategori'      => $kategori,
        'deskripsi'     => $deskripsi,
    ];
}

/**
 * Hitung sikap sosial PPKn (adaptasi sheet "Sikap Sosial")
 */
function hitung_sikap_sosial(array $n): array {
    $list = [
        $n['jujur'] ?? null,
        $n['disiplin'] ?? null,
        $n['santun'] ?? null,
        $n['peduli_lingkungan'] ?? null,
        $n['tanggung_jawab'] ?? null,
        $n['responsif'] ?? null,
        $n['pro_aktif'] ?? null,
    ];

    $dominan  = mode_value($list);
    $pred     = predikat_sikap($dominan);
    $kategori = kategori_sikap($dominan);

    $deskripsi = null;
    if ($kategori !== null) {
        $deskripsi = "Sikap peserta didik dalam jujur, disiplin, santun, "
                   . "peduli lingkungan, tanggung jawab, responsif, dan pro aktif "
                   . "{$kategori}.";
    }

    return [
        'nilai_dominan' => $dominan,
        'predikat'      => $pred,
        'kategori'      => $kategori,
        'deskripsi'     => $deskripsi,
    ];
}
