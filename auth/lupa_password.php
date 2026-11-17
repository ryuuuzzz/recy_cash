<?php
session_start();
$error   = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);

$authEyebrow = 'Akun aman, tenang';
$authTitle = 'Tenang,<br><span class="accent">kami bantu</span>.';
$authDescription = 'Masukkan email terdaftar, lalu kami kirimkan kode OTP untuk mengatur ulang passwordmu dengan aman.';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0d3823">
    <title>Lupa Password - RecyCash</title>
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
                <h1>Lupa password?</h1>
                <p class="subtitle">Masukkan email terdaftar untuk menerima kode OTP.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert-error" role="alert"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert-success" role="status"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form action="proses_lupa.php" method="POST">
                <div class="form-group">
                    <label for="email">Email</label>
                    <div class="input-box">
                        <span class="icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="5" width="18" height="14" rx="3"/>
                                <path d="m4 7 8 6 8-6"/>
                            </svg>
                        </span>
                        <input type="email" id="email" name="email" placeholder="Masukkan email terdaftar" autocomplete="email" required>
                    </div>
                </div>

                <button type="submit" class="btn-primary">
                    <span>Kirim Kode OTP</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M5 12h14"/>
                        <path d="m13 6 6 6-6 6"/>
                    </svg>
                </button>
            </form>

            <p class="register-box">
                Ingat password? <a href="login.php">Masuk di sini</a>
            </p>

            <div class="terms">
                Kode OTP bersifat rahasia. Jangan bagikan kode kepada siapa pun, termasuk pihak RecyCash.
            </div>
        </section>

    </div>

    <script src="../assets/js/login.js"></script>
</body>
</html>
