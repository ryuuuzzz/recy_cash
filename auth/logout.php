<?php
session_start();

require_once __DIR__ . '/../config/koneksi.php';

// Hapus catatan sesi login aktif dari database
// -> jumlah baris pada tabel login_sessions di phpMyAdmin berkurang
if (session_status() === PHP_SESSION_ACTIVE) {
    $sid = session_id();
    if (!empty($sid)) {
        $stmt = $pdo->prepare("DELETE FROM login_sessions WHERE session_id = :sid");
        $stmt->execute([':sid' => $sid]);
    }

    // Ubah status user di tabel users menjadi "keluar"
    // -> data user TIDAK dihapus, jadi bisa login kembali
    $email = $_SESSION['email'] ?? '';
    if (!empty($email)) {
        $stmt = $pdo->prepare("UPDATE users SET status = 'keluar' WHERE email = :email");
        $stmt->execute([':email' => $email]);
    }
}

// Hapus semua data sesi
$_SESSION = [];

// Hapus cookie sesi di browser agar sesi PHP benar-benar baru di lain waktu
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_unset();
    session_destroy();
}

header('Location: login.php');
exit;