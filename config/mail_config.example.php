<?php
/**
 * TEMPLATE konfigurasi SMTP Gmail.
 * Salin file ini menjadi "mail_config.php" lalu isi dengan kredensial Anda.
 *
 * cp config/mail_config.example.php config/mail_config.php
 *
 * Cara membuat App Password Gmail:
 * 1. Aktifkan "Verifikasi 2 Langkah" pada akun Gmail pengirim.
 * 2. Buka https://myaccount.google.com/apppasswords
 * 3. Buat password aplikasi baru (nama bebas, mis. "RecyCash").
 * 4. Salin 16 karakter password tersebut ke SMTP_PASS di bawah.
 */
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);

// Ganti dengan alamat Gmail pengirim
define('SMTP_USER', 'pengirim@gmail.com');

// Ganti dengan App Password 16 karakter (ada spasi antar 4 karakter)
define('SMTP_PASS', 'AAAA BBBB CCCC DDDD');

// Nama pengirim yang tampil di email
define('SMTP_FROM_NAME', 'RecyCash');