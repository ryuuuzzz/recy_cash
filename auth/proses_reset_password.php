<?php
session_start();
require_once __DIR__ . '/../config/koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: reset_password.php');
    exit;
}

$email = $_SESSION['otp_email'] ?? '';

if ($email === '' || empty($_SESSION['otp_verified'])) {
    $_SESSION['error'] = "Sesi tidak valid. Silakan ulangi dari awal.";
    header('Location: lupa_password.php');
    exit;
}

$password   = $_POST['password'] ?? '';
$konfirmasi = $_POST['konfirmasi_password'] ?? '';

if (strlen($password) < 6) {
    $_SESSION['error'] = "Password baru minimal 6 karakter.";
    header('Location: reset_password.php');
    exit;
}

if ($password !== $konfirmasi) {
    $_SESSION['error'] = "Konfirmasi password tidak cocok.";
    header('Location: reset_password.php');
    exit;
}

$hashed = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("UPDATE users SET password = :pass WHERE email = :email");
$stmt->execute([':pass' => $hashed, ':email' => $email]);

// Tandai kode OTP yang dipakai sudah terpakai
$pdo->prepare("UPDATE password_resets SET used = 1 WHERE email = :email AND used = 0")
    ->execute([':email' => $email]);

unset($_SESSION['otp_email'], $_SESSION['otp_verified']);

$_SESSION['success'] = "Password berhasil diubah. Silakan login dengan password baru.";
header('Location: login.php');
exit;