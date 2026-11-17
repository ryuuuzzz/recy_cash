<?php
session_start();
require_once __DIR__ . '/../config/koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: verifikasi_otp.php');
    exit;
}

$email = $_SESSION['otp_email'] ?? '';

if ($email === '') {
    $_SESSION['error'] = "Sesi tidak valid. Silakan ulangi dari awal.";
    header('Location: lupa_password.php');
    exit;
}

$otp = trim($_POST['otp'] ?? '');

if (!preg_match('/^\d{6}$/', $otp)) {
    $_SESSION['error'] = "Kode OTP harus berupa 6 digit angka.";
    header('Location: verifikasi_otp.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT kode_otp, expires_at FROM password_resets
    WHERE email = :email AND used = 0
    ORDER BY id DESC LIMIT 1
");
$stmt->execute([':email' => $email]);
$row = $stmt->fetch();

if (!$row) {
    $_SESSION['error'] = "Kode OTP tidak ditemukan. Silakan minta kode baru.";
    header('Location: lupa_password.php');
    exit;
}

if (strtotime($row['expires_at']) < time()) {
    $_SESSION['error'] = "Kode OTP sudah kadaluarsa. Silakan minta kode baru.";
    header('Location: lupa_password.php');
    exit;
}

if (!password_verify($otp, $row['kode_otp'])) {
    $_SESSION['error'] = "Kode OTP salah. Silakan coba lagi.";
    header('Location: verifikasi_otp.php');
    exit;
}

$_SESSION['otp_verified'] = true;
$_SESSION['success']      = "Kode valid! Silakan buat password baru.";
header('Location: reset_password.php');
exit;