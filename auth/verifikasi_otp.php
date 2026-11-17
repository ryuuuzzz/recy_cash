<?php
session_start();
$email   = $_SESSION['otp_email'] ?? '';
$error   = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);

if ($email === '') {
    header('Location: lupa_password.php');
    exit;
}

$authEyebrow = 'Satu langkah lagi';
$authTitle = 'Cek emailmu,<br>lalu <span class="accent">verifikasi</span>.';
$authDescription = 'Kode OTP 6 digit telah dikirim ke <strong>' . htmlspecialchars($email) . '</strong>. Jangan bagikan kode ini ke siapa pun.';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0d3823">
    <title>Verifikasi OTP - RecyCash</title>
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
                <h1>Verifikasi OTP</h1>
                <p class="subtitle">Masukkan 6 digit kode yang dikirim ke email kamu.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert-error" role="alert"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert-success" role="status"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <div class="alert-info" role="note">
                <span>Belum menemukan kode? Periksa folder <b>Spam</b> atau <b>Promosi</b>.
                Jika kode ada di Spam, tandai email sebagai <b>"Bukan spam"</b> agar email berikutnya masuk ke Inbox.</span>
            </div>

            <form action="proses_verifikasi_otp.php" method="POST">
                <div class="form-group">
                    <label for="otp">Kode OTP</label>
                    <div class="input-box">
                        <span class="icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="4" y="10" width="16" height="11" rx="3"/>
                                <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                                <circle cx="12" cy="15.5" r="1.6"/>
                            </svg>
                        </span>
                        <input type="text" id="otp" name="otp" class="otp-code" placeholder="Contoh: 123456" maxlength="6" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" required>
                    </div>
                </div>

                <button type="submit" class="btn-primary">
                    <span>Verifikasi Kode</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M5 12h14"/>
                        <path d="m13 6 6 6-6 6"/>
                    </svg>
                </button>
            </form>

            <p class="register-box">
                Belum menerima kode? <a href="resend_otp.php">Kirim ulang</a>
            </p>

            <div class="terms">
                Kode OTP hanya berlaku untuk satu kali verifikasi dan memiliki batas waktu.
            </div>
        </section>

    </div>

    <script src="../assets/js/login.js"></script>
</body>
</html>
