<?php
session_start();
$error   = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0d3823">
    <title>Masuk - RecyCash</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 48 48'%3E%3Crect width='48' height='48' rx='14' fill='%2314532D'/%3E%3Cpath d='M32 12c-8 1-13 6-13 14 7 0 12-5 13-14Z' fill='%23fff'/%3E%3Ccircle cx='36' cy='36' r='8' fill='%23F2C55C'/%3E%3C/svg%3E">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/login.css">
</head>
<body>

    <div class="auth-container">

        <!-- LEFT: Illustration -->
        <?php
        $authEyebrow = 'Ubah sampah jadi cuan';
        $authTitle = 'Pilah sampahnya,<br>panen <span class="accent">cuannya</span>.';
        $authDescription = 'Bergabung bersama keluarga hijau: setor sampah, kumpulkan koin, dan jadikan bumi lebih bersih.';
        include __DIR__ . '/../includes/auth_illustration.php';
        ?>

        <!-- RIGHT: Form -->
        <section class="form-panel">

            <?php include __DIR__ . '/../includes/auth_mobile_brand.php'; ?>

            <div class="form-head">
                <h1>Selamat datang kembali</h1>
                <p class="subtitle">Masuk ke akun RecyCash kamu dan lanjutkan misi hijau hari ini.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert-error" role="alert"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert-success" role="status"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form action="proses_login.php" method="POST">

                <div class="form-group">
                    <label for="loginEmail">Email atau No. Telepon</label>
                    <div class="input-box">
                        <span class="icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="5" width="18" height="14" rx="3"/>
                                <path d="m4 7 8 6 8-6"/>
                            </svg>
                        </span>
                        <input type="text" id="loginEmail" name="email" placeholder="Masukkan email atau nomor telepon" autocomplete="username" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="loginPassword">Password</label>
                    <div class="input-box">
                        <span class="icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="4" y="10" width="16" height="11" rx="3"/>
                                <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                                <circle cx="12" cy="15.5" r="1.6"/>
                            </svg>
                        </span>
                        <input type="password" id="loginPassword" name="password" placeholder="Masukkan password" autocomplete="current-password" required>
                        <button type="button" class="show-password" onclick="togglePassword('loginPassword', this)" aria-label="Tampilkan password" aria-pressed="false">
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

                <div class="forgot">
                    <a href="lupa_password.php">Lupa password?</a>
                </div>

                <button type="submit" class="btn-primary">
                    <span>Masuk</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M5 12h14"/>
                        <path d="m13 6 6 6-6 6"/>
                    </svg>
                </button>
            </form>

            <div class="divider"><span>atau masuk dengan</span></div>

            <button type="button" class="btn-google" id="googleLogin" aria-label="Masuk dengan Google">
                <svg class="google-icon" viewBox="0 0 48 48" aria-hidden="true">
                    <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                    <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                    <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                    <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
                </svg>
                <span>Masuk dengan Google</span>
            </button>
            <p class="google-note" id="googleNote" role="status">Login Google belum diaktifkan di server ini. Silakan masuk dengan email dan password.</p>

            <p class="register-box">
                Belum punya akun? <a href="register.php">Daftar di sini</a>
            </p>

            <div class="terms">
                Dengan masuk, kamu menyetujui <a href="#">Ketentuan Layanan</a> dan <a href="#">Kebijakan Privasi</a>.
            </div>
        </section>

    </div>

    <script src="../assets/js/login.js"></script>
</body>
</html>