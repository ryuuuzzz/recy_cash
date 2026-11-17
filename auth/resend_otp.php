<?php
session_start();
require_once __DIR__ . '/../includes/otp_helper.php';

$email = $_SESSION['otp_email'] ?? '';

if ($email === '') {
    $_SESSION['error'] = "Sesi tidak valid. Silakan ulangi dari awal.";
    header('Location: lupa_password.php');
    exit;
}

$hasil = kirim_otp_reset($email);

if ($hasil['success']) {
    $_SESSION['success'] = "Kode OTP baru telah dikirim ke email kamu.";
} else {
    $_SESSION['error'] = "Gagal mengirim email OTP. " . $hasil['message'];
}

header('Location: verifikasi_otp.php');
exit;