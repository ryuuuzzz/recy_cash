<?php
session_start();
require_once __DIR__ . '/../config/koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$login = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($login) || empty($password)) {
    $_SESSION['error'] = "Email/No. telepon dan password wajib diisi.";
    header('Location: login.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = :login OR nomor_hp = :login LIMIT 1");
$stmt->execute([':login' => $login]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['error'] = "Email/No. telepon atau password salah.";
    header('Location: login.php');
    exit;
}

if (!password_verify($password, $user['password'])) {
    $_SESSION['error'] = "Email/No. telepon atau password salah.";
    header('Location: login.php');
    exit;
}

if (in_array($user['role'], ['admin', 'petugas'], true)) {
    $status_akun = $user['status_akun'] ?? 'approved';
    if ($status_akun === 'pending') {
        $_SESSION['error'] = "Akun kamu masih menunggu verifikasi Super Admin.";
        header('Location: login.php');
        exit;
    }
    if ($status_akun === 'rejected') {
        $_SESSION['error'] = "Pendaftaran akun kamu ditolak. Hubungi pengelola RecyCash.";
        header('Location: login.php');
        exit;
    }
}

$_SESSION['id_user']        = $user['id'];
$_SESSION['email']          = $user['email'];
$_SESSION['alamat']         = $user['alamat'];
$_SESSION['nama']           = $user['nama'] ?? '';
$_SESSION['nomor_hp']       = $user['nomor_hp'] ?? '';
$_SESSION['foto']           = $user['foto'] ?? '';
$_SESSION['role']           = $user['role'];
$_SESSION['is_super_admin'] = !empty($user['is_super_admin']);

// Simpan salinan data user dalam bentuk array agar mudah dipakai ulang
$_SESSION['user'] = [
    'id'        => $user['id'],
    'email'     => $user['email'],
    'nama'      => $user['nama'] ?? '',
    'no_hp'     => $user['nomor_hp'] ?? '',
    'nomor_hp'  => $user['nomor_hp'] ?? '',
    'alamat'    => $user['alamat'] ?? '',
    'foto'      => $user['foto'] ?? '',
    'role'      => $user['role'],
];

// Tandai user sedang login bertahan
$stmt = $pdo->prepare("UPDATE users SET status = 'bertahan' WHERE id = :id");
$stmt->execute([':id' => $user['id']]);

// Bersihkan sesi login lama yang menggantung (mis. browser ditutup tanpa logout)
$pdo->exec("DELETE FROM login_sessions WHERE login_at < NOW() - INTERVAL 12 HOUR");

// Catat sesi aktif user ke database (baris ini yang akan dihapus saat logout)
$stmt = $pdo->prepare("
    INSERT INTO login_sessions (session_id, user_id, ip_address, user_agent)
    VALUES (:sid, :uid, :ip, :ua)
");
$stmt->execute([
    ':sid' => session_id(),
    ':uid' => $user['id'],
    ':ip'  => $_SERVER['REMOTE_ADDR'] ?? null,
    ':ua'  => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
]);

switch ($user['role']) {
    case 'admin':
        $dashboard = '../views/Admin/dashboard.php';
        break;
    case 'petugas':
        $dashboard = '../views/Teller/dashboard.php';
        break;
    case 'nasabah':
        $dashboard = '../views/Nasabah/dashboard.php';
        break;
    case 'pengepul':
        $dashboard = '../views/Pengepul/dashboard.php';
        break;
    default:
        $_SESSION['error'] = "Role tidak dikenali.";
        header('Location: logout.php');
        exit;
}

// Perbaikan 2: Tambahkan exit setelah redirect
header("Location: $dashboard");
exit;