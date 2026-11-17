<?php
session_start();
require_once __DIR__ . '/../config/koneksi.php';

$success = $_SESSION['success'] ?? '';
$error   = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama           = trim($_POST['nama'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $no_telp        = trim($_POST['no_telp'] ?? '');
    $alamat         = trim($_POST['alamat'] ?? '');
    $role           = trim($_POST['role'] ?? '');
    $password       = $_POST['password'] ?? '';
    $konfirmasi     = $_POST['konfirmasi_password'] ?? '';

    if (empty($nama) || empty($email) || empty($no_telp) || empty($alamat) || empty($role) || empty($password) || empty($konfirmasi)) {
        $_SESSION['error'] = "Semua field wajib diisi.";
        header('Location: register.php');
        exit;
    }

    if (!preg_match('/^[0-9+\-\s]{8,15}$/', $no_telp)) {
        $_SESSION['error'] = "Format nomor telepon tidak valid (8-15 digit).";
        header('Location: register.php');
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE nomor_hp = :no_telp LIMIT 1");
    $stmt->execute([':no_telp' => $no_telp]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = "Nomor telepon sudah terdaftar. Silakan login.";
        header('Location: register.php');
        exit;
    }

    $valid_roles = ['petugas', 'nasabah'];
    if (!in_array($role, $valid_roles)) {
        $_SESSION['error'] = "Role tidak valid.";
        header('Location: register.php');
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Format email tidak valid.";
        header('Location: register.php');
        exit;
    }

    if (strlen($password) < 6) {
        $_SESSION['error'] = "Password minimal 6 karakter.";
        header('Location: register.php');
        exit;
    }

    if ($password !== $konfirmasi) {
        $_SESSION['error'] = "Konfirmasi password tidak cocok.";
        header('Location: register.php');
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = "Email sudah terdaftar. Silakan login.";
        header('Location: register.php');
        exit;
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);

    $status_akun = ($role === 'petugas') ? 'pending' : 'approved';

    $stmt = $pdo->prepare("INSERT INTO users (nama, email, nomor_hp, password, alamat, role, status_akun) VALUES (:nama, :email, :no_telp, :password, :alamat, :role, :status_akun)");
    $stmt->execute([
        ':nama'        => $nama,
        ':email'       => $email,
        ':no_telp'     => $no_telp,
        ':password'    => $hashed,
        ':alamat'      => $alamat,
        ':role'        => $role,
        ':status_akun' => $status_akun,
    ]);

    if ($role === 'petugas') {
        $_SESSION['success'] = "Registrasi berhasil! Akun petugas kamu menunggu verifikasi Super Admin sebelum bisa digunakan.";
    } else {
        $_SESSION['success'] = "Registrasi berhasil! Silakan login.";
    }
    header('Location: login.php');
    exit;
}

$authEyebrow = 'Mulai langkah hijau';
$authTitle = 'Daftar sekali,<br>untung <span class="accent">setiap hari</span>.';
$authDescription = 'Buat akun RecyCash dan mulai menabung sampah bersama keluarga. Setiap setoran jadi koin dan dampak baik untuk bumi.';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0d3823">
    <title>Daftar - RecyCash</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 48 48'%3E%3Crect width='48' height='48' rx='14' fill='%2314532D'/%3E%3Cpath d='M32 12c-8 1-13 6-13 14 7 0 12-5 13-14Z' fill='%23fff'/%3E%3Ccircle cx='36' cy='36' r='8' fill='%23F2C55C'/%3E%3C/svg%3E">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/login.css">
</head>
<body>

    <div class="auth-container">

        <!-- LEFT: Illustration -->
        <?php include __DIR__ . '/../includes/auth_illustration.php'; ?>

        <!-- RIGHT: Form -->
        <section class="form-panel">

            <?php include __DIR__ . '/../includes/auth_mobile_brand.php'; ?>

            <div class="form-head">
                <h1>Buat akun baru</h1>
                <p class="subtitle">Isi data dirimu untuk mulai menabung sampah bersama RecyCash.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert-error" role="alert"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert-success" role="status"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form action="register.php" method="POST">

                <div class="form-group">
                    <label for="nama">Nama Lengkap</label>
                    <div class="input-box">
                        <span class="icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="8" r="3.5"/>
                                <path d="M5 20a7 7 0 0 1 14 0"/>
                            </svg>
                        </span>
                        <input type="text" id="nama" name="nama" placeholder="Masukkan nama lengkap" value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>" autocomplete="name" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <div class="input-box">
                        <span class="icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="5" width="18" height="14" rx="3"/>
                                <path d="m4 7 8 6 8-6"/>
                            </svg>
                        </span>
                        <input type="email" id="email" name="email" placeholder="Masukkan email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" autocomplete="email" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="no_telp">Nomor Telepon</label>
                    <div class="input-box">
                        <span class="icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M7 3h3l1.6 4.2-2 1.5a13 13 0 0 0 5.7 5.7l1.5-2L21 14v3a2 2 0 0 1-2.2 2A16.5 16.5 0 0 1 5 5.2 2 2 0 0 1 7 3Z"/>
                            </svg>
                        </span>
                        <input type="tel" id="no_telp" name="no_telp" placeholder="Masukkan nomor telepon" value="<?= htmlspecialchars($_POST['no_telp'] ?? '') ?>" autocomplete="tel" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="alamat">Alamat</label>
                    <div class="input-box">
                        <span class="icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 10c0 5.2-8 11-8 11S4 15.2 4 10a8 8 0 1 1 16 0Z"/>
                                <circle cx="12" cy="10" r="2.8"/>
                            </svg>
                        </span>
                        <input type="text" id="alamat" name="alamat" placeholder="Masukkan alamat lengkap" value="<?= htmlspecialchars($_POST['alamat'] ?? '') ?>" autocomplete="street-address" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="role">Daftar Sebagai</label>
                    <div class="input-box">
                        <span class="icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="5" width="18" height="14" rx="3"/>
                                <path d="M8 11h8M8 15h5"/>
                            </svg>
                        </span>
                        <select id="role" name="role" required>
                            <option value="">Pilih role akun</option>
                            <option value="nasabah" <?= ($_POST['role'] ?? '') === 'nasabah' ? 'selected' : '' ?>>Nasabah</option>
                            <option value="petugas" <?= ($_POST['role'] ?? '') === 'petugas' ? 'selected' : '' ?>>Petugas</option>
                        </select>
                        <span class="select-caret" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m6 9 6 6 6-6"/>
                            </svg>
                        </span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-box">
                        <span class="icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="4" y="10" width="16" height="11" rx="3"/>
                                <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                                <circle cx="12" cy="15.5" r="1.6"/>
                            </svg>
                        </span>
                        <input type="password" id="password" name="password" placeholder="Minimal 6 karakter" autocomplete="new-password" required>
                        <button type="button" class="show-password" onclick="togglePassword('password', this)" aria-label="Tampilkan password" aria-pressed="false">
                            <svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/>
                                <circle cx="12" cy="12" r="3.2"/>
                            </svg>
                            <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M3 3l18 18"/>
                                <path d="M10.6 5.8A9.5 9.5 0 0 1 12 5.5c6 0 9.5 6.5 9.5 6.5a17 17 0 0 1-3.2 4.1"/>
                                <path d="M6.4 6.9A17.2 17.2 0 0 0 2.5 12S6 18.5 12 18.5c1.3 0 2.5-.3 3.6-.8"/>
                                <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="konfirmasi_password">Konfirmasi Password</label>
                    <div class="input-box">
                        <span class="icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="4" y="10" width="16" height="11" rx="3"/>
                                <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                                <path d="m9.5 15.5 1.7 1.7 3.3-3.4"/>
                            </svg>
                        </span>
                        <input type="password" id="konfirmasi_password" name="konfirmasi_password" placeholder="Ulangi password" autocomplete="new-password" required>
                        <button type="button" class="show-password" onclick="togglePassword('konfirmasi_password', this)" aria-label="Tampilkan password" aria-pressed="false">
                            <svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/>
                                <circle cx="12" cy="12" r="3.2"/>
                            </svg>
                            <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M3 3l18 18"/>
                                <path d="M10.6 5.8A9.5 9.5 0 0 1 12 5.5c6 0 9.5 6.5 9.5 6.5a17 17 0 0 1-3.2 4.1"/>
                                <path d="M6.4 6.9A17.2 17.2 0 0 0 2.5 12S6 18.5 12 18.5c1.3 0 2.5-.3 3.6-.8"/>
                                <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-primary">
                    <span>Daftar Sekarang</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M5 12h14"/>
                        <path d="m13 6 6 6-6 6"/>
                    </svg>
                </button>
            </form>

            <p class="register-box">
                Sudah punya akun? <a href="login.php">Masuk di sini</a>
            </p>

            <div class="terms">
                Dengan mendaftar, kamu menyetujui <a href="#">Ketentuan Layanan</a> dan <a href="#">Kebijakan Privasi</a>.
            </div>
        </section>

    </div>

    <script src="../assets/js/login.js"></script>
</body>
</html>
