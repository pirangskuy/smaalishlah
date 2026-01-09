<?php
// auth/wali_auth.php

function requireWaliKelas() {
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'walikelas') {
        header("Location: ../auth/login_wali.php");
        exit;
    }
}
