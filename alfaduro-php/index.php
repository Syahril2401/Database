<?php
session_start();

// Cek apakah user sudah login
if (isset($_SESSION['user_id'])) {
    // Redirect sesuai role
    if ($_SESSION['role'] == 'admin') {
        header("Location: admin/index.php");
        exit();
    } else {
        header("Location: customer/index.php");
        exit();
    }
} else {
    // Belum login, redirect ke login
    header("Location: auth/login.php");
    exit();
}
?>