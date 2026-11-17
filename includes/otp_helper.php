<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/mailer.php';

global $pdo;
$pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    kode_otp VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function email_body_otp(string $nama, string $otp): string
{
    return '
    <!DOCTYPE html>
    <html lang="id">
    <head><meta charset="UTF-8"></head>
    <body style="margin:0;padding:0;background:#e8f5e9;font-family:Arial,Helvetica,sans-serif;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#e8f5e9;padding:30px 10px;">
            <tr>
                <td align="center">
                    <table width="480" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;">
                        <tr>
                            <td style="text-align:center;background:#f1f8f1;padding:28px 24px;">
                                <div style="font-size:36px;line-height:1;">&#9851;&#65039;</div>
                                <h1 style="color:var(--eco-700);font-size:22px;margin:8px 0 4px;">RecyCash</h1>
                                <p style="color:var(--eco-600);font-size:13px;margin:0;">Reset Password Akun</p>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:24px 28px;color:var(--text-body);font-size:14px;line-height:1.7;">
                                <p>Halo <b>' . htmlspecialchars($nama) . '</b>,</p>
                                <p>Kami menerima permintaan untuk mereset password akun Anda. Gunakan kode OTP berikut untuk melanjutkan:</p>
                                <div style="text-align:center;padding:22px 0;">
                                    <span style="display:inline-block;background:var(--eco-50);border:2px dashed var(--eco-600);border-radius:12px;padding:14px 30px;font-size:34px;font-weight:bold;letter-spacing:10px;color:var(--eco-700);">' . $otp . '</span>
                                </div>
                                <p>Kode ini <b>berlaku selama 10 menit</b> dan hanya sekali pakai. Jangan bagikan kode ini kepada siapa pun.</p>
                                <p>Jika Anda tidak meminta reset password, abaikan email ini.</p>
                            </td>
                        </tr>
                        <tr>
                            <td style="text-align:center;padding:18px 24px;border-top:1px solid var(--border);color:var(--text-muted);font-size:11px;">
                                &copy; ' . date('Y') . ' RecyCash &middot; Daur Ulang Jadi Cuan<br>Email ini dikirim otomatis, harap tidak dibalas.
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>
    ';
}

function email_body_otp_teks(string $nama, string $otp): string
{
    return "Halo $nama,

Kami menerima permintaan untuk mereset password akun RecyCash Anda.

Kode OTP Anda: $otp

Kode ini berlaku selama 10 menit dan hanya sekali pakai.
Jangan bagikan kode ini kepada siapa pun.

Jika Anda tidak meminta reset password, abaikan email ini.

-- RecyCash · Daur Ulang Jadi Cuan
Email ini dikirim otomatis, harap tidak dibalas.";
}

function kirim_otp_reset(string $email): array
{
    global $pdo;

    $stmt = $pdo->prepare("SELECT id, nama FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['ditemukan' => false, 'success' => false, 'message' => 'Email tidak terdaftar.'];
    }

    $otp = (string) random_int(100000, 999999);

    // Nonaktifkan semua kode lama yang masih aktif untuk email ini
    $pdo->prepare("UPDATE password_resets SET used = 1 WHERE email = :email AND used = 0")
        ->execute([':email' => $email]);

    $pdo->prepare("INSERT INTO password_resets (email, kode_otp, expires_at) VALUES (:email, :otp, :exp)")
        ->execute([
            ':email' => $email,
            ':otp'   => password_hash($otp, PASSWORD_DEFAULT),
            ':exp'   => date('Y-m-d H:i:s', time() + 600),
        ]);

    $nama  = $user['nama'] ?? $email;
    $hasil = kirim_email(
        $email,
        'Kode OTP Reset Password - RecyCash',
        email_body_otp($nama, $otp),
        email_body_otp_teks($nama, $otp)
    );

    return [
        'ditemukan' => true,
        'success'   => $hasil['success'],
        'message'   => $hasil['message'],
    ];
}