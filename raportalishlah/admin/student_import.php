<?php
// admin/student_import.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth.php';

requireLogin();
requireAdmin();

$message = '';
$error   = '';

/**
 * Fungsi kecil untuk konversi teks kelas bebas -> '10' / '11' / '12'
 */
function normalisasi_kelas(string $raw): ?string {
    $k = trim(strtoupper($raw));

    if ($k === '') {
        return null;
    }

    // Langsung angka
    if ($k === '10' || $k === 'XI' || $k === 'X' || strpos($k, '10') === 0 || strpos($k, 'X ') === 0) {
        return '10';
    }
    if ($k === '11' || $k === 'XI' || strpos($k, '11') === 0 || strpos($k, 'XI ') === 0) {
        return '11';
    }
    if ($k === '12' || $k === 'XII' || strpos($k, '12') === 0 || strpos($k, 'XII ') === 0) {
        return '12';
    }

    // Default: kalau isi angka 10/11/12
    if (in_array($k, ['10','11','12'], true)) {
        return $k;
    }

    // Kalau benar-benar aneh, kembalikan null (akan dianggap error)
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_FILES['file_siswa']) || $_FILES['file_siswa']['error'] !== UPLOAD_ERR_OK) {
        $error = "Upload file gagal. Pastikan Anda memilih file CSV.";
    } else {

        $tmpName = $_FILES['file_siswa']['tmp_name'];

        // Buka file CSV
        if (($handle = fopen($tmpName, 'r')) === false) {
            $error = "Tidak dapat membuka file CSV.";
        } else {

            $row          = 0;
            $inserted     = 0;
            $skipped      = 0;
            $pdo->beginTransaction();

            try {
                while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                    $row++;

                    // Lewati baris header jika ada teks "nama" di kolom pertama
                    if ($row === 1 && isset($data[0]) && stripos($data[0], 'nama') !== false) {
                        continue;
                    }

                    // Pastikan minimal 4 kolom: nama, nis, nisn, kelas, [tanggal_lahir optional]
                    if (count($data) < 4) {
                        $skipped++;
                        continue;
                    }

                    $nama          = trim($data[0] ?? '');
                    $nis           = trim($data[1] ?? '');
                    $nisn          = trim($data[2] ?? '');
                    $kelasRaw      = trim($data[3] ?? '');
                    $tanggal_lahir = trim($data[4] ?? ''); // bisa kosong

                    if ($nama === '' || $nis === '' || $kelasRaw === '') {
                        $skipped++;
                        continue;
                    }

                    $kelas = normalisasi_kelas($kelasRaw);
                    if ($kelas === null) {
                        $skipped++;
                        continue;
                    }

                    // Insert ke DB
                    $stmt = $pdo->prepare("
                        INSERT INTO siswa (nama, nis, nisn, kelas, tanggal_lahir)
                        VALUES (:nama, :nis, :nisn, :kelas, :tanggal_lahir)
                    ");

                    $stmt->execute([
                        ':nama'          => $nama,
                        ':nis'           => $nis,
                        ':nisn'          => $nisn !== '' ? $nisn : null,
                        ':kelas'         => $kelas,
                        ':tanggal_lahir' => $tanggal_lahir !== '' ? $tanggal_lahir : null,
                    ]);

                    $inserted++;
                }

                fclose($handle);
                $pdo->commit();

                $message = "Import selesai. Berhasil: {$inserted} baris, dilewati: {$skipped} baris.";

            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "Terjadi kesalahan saat import: " . htmlspecialchars($e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Import Siswa dari Excel (CSV)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/admin_student_import.css">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-islah mb-4">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="admin_dashboard.php">
            <div class="logo-placeholder">
                <img src="../media/AL ISLHAH.jpg" alt="Logo SMA Al Ishlah" class="logo-img">
            </div>
            <div class="d-flex flex-column lh-1 brand-text">
                <span class="brand-title-text">SMA AL ISHLAH</span>
                 <small class="badge-school mt-1">Sistem Rapor Digital</small>
            </div>
        </a>

        <div class="collapse navbar-collapse justify-content-end" id="navbarMain">
            <div class="d-flex align-items-center gap-3">
                <span class="admin-mode-badge">
                    <i class="bi bi-shield-lock"></i> Mode Admin
                </span>
                <a href="../auth/logout.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i> Logout
                </a>
            </div>
        </div>
    </div>
</nav>

<div class="wrapper">

    <div class="card">
        <div class="card-body">

            <h4 class="card-title">Import Siswa dari Excel (CSV)</h4>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <p class="description">
                Silakan upload file <b>CSV</b> yang diekspor dari Excel. Format kolom:
            </p>

            <pre class="code-block">nama,nis,nisn,kelas,tanggal_lahir
Budi Santoso,1001,99887766,10,2007-01-15</pre>


            <ul class="info-list">
                <li><b>kelas</b> boleh diisi 10 / 11 / 12 atau X / XI / XII / 10 IPA 1, dll.</li>
                <li><b>tanggal_lahir</b> format: <code>YYYY-MM-DD</code> (boleh dikosongkan).</li>
            </ul>

            <form method="post" enctype="multipart/form-data" class="form-import">
                <div class="form-group">
                    <label>Pilih File CSV</label>
                    <input type="file" name="file_siswa" accept=".csv" required>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn-primary">Import</button>
                    <a href="student_list.php" class="btn-secondary">Lihat Data Siswa</a>
                </div>
            </form>
        </div>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
