<?php
require 'config.php';
require 'auth.php';
requireLogin();
requireWaliKelas();

$guru = $_SESSION['user'];
?>

<h2>Dashboard Wali Kelas</h2>
<p>Selamat datang, <?= $guru['name']; ?>.</p>
<p>Wali Kelas: <strong><?= $guru['kelas']; ?></strong></p>

<ul>
    <li><a href="rekap_nilai.php">Rekap Nilai Raport</a></li>
</ul>
