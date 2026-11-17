<?php
session_start();
require_once __DIR__ . '/../includes/otp_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: lupa_password.php');
    exit;
}

$email = strtolower(trim($_POST['email'] ?? ''));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = "Format email tidak valid.";
    header('Location: lupa_password.php');
    exit;
}

$hasil = kirim_otp_reset($email);

if ($hasil['ditemukan']) {
    if ($hasil['success']) {
        $_SESSION['otp_email'] = $email;
        $_SESSION['success']   = "Kode OTP telah dikirim ke email kamu. Silakan cek inbox atau folder spam.";
        header('Location: verifikasi_otp.php');
        exit;
    }

    $_SESSION['error'] = "Gagal mengirim email OTP. " . $hasil['message'];
    header('Location: lupa_password.php');
    exit;
}

// Jangan bocorkan apakah email terdaftar atau tidak
$_SESSION['success'] = "Jika email terdaftar, kode OTP telah dikirim ke email kamu.";
header('Location: lupa_password.php');
exit;