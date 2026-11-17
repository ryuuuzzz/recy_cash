<?php
session_start();
$email   = $_SESSION['otp_email'] ?? '';
$error   = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);

if ($email === '' || empty($_SESSION['otp_verified'])) {
    header('Location: lupa_password.php');
    exit;
}

$authEyebrow = 'Amankan akunmu';
$authTitle = 'Buat password<br><span class="accent">baru</span> yang kuat.';
$authDescription = 'Buat password baru untuk akun <strong>' . htmlspecialchars($email) . '</strong>. Gunakan minimal 6 karakter agar akun tetap aman.';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0d3823">
    <title>Reset Password - RecyCash</title>
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
                <h1>Buat password baru</h1>
                <p class="subtitle">Password minimal 6 karakter dan mudah kamu ingat.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert-error" role="alert"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert-success" role="status"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form action="proses_reset_password.php" method="POST">
                <div class="form-group">
                    <label for="password">Password Baru</label>
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
                        <input type="password" id="konfirmasi_password" name="konfirmasi_password" placeholder="Ulangi password baru" autocomplete="new-password" required>
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
                    <span>Simpan Password Baru</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M5 12h14"/>
                        <path d="m13 6 6 6-6 6"/>
                    </svg>
                </button>
            </form>

            <p class="register-box">
                Sudah ingat password? <a href="login.php">Masuk di sini</a>
            </p>

            <div class="terms">
                Gunakan kombinasi huruf dan angka agar passwordmu lebih aman.
            </div>
        </section>

    </div>

    <script src="../assets/js/login.js"></script>
</body>
</html>
