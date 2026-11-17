<?php
/**
 * TEMPLATE konfigurasi Payment Gateway (Xendit).
 * Salin file ini menjadi "payment_config.php" lalu isi dengan kredensial Anda.
 *
 * cp config/payment_config.example.php config/payment_config.php
 *
 * Cara mengaktifkan Xendit:
 * 1. Daftar akun Xendit → Dashboard → Developers → API Keys.
 * 2. Salin "Secret Key" (mode development gratis untuk uji coba) ke bawah.
 * 3. Set PAYOUT_PROVIDER ke 'xendit'.
 * 4. Kode bank mengikuti daftar Xendit (BCA, BNI, BRI, MANDIRI, PERMATA, dll),
 *    diisi saat verifikasi penarikan (kolom Kode Bank).
 *
 * Jika PAYOUT_PROVIDER kosong, aplikasi memakai mode "manual"
 * (petugas membayar sendiri lalu menekan "Tandai Selesai").
 */
// '' = manual (gateway belum aktif) | 'xendit' = transfer otomatis
define('PAYOUT_PROVIDER', '');

// Secret key Xendit (JANGAN dibagikan). Kosongkan untuk mode manual.
define('PAYOUT_XENDIT_SECRET', '');

// Ambang nominal: penarikan >= nilai ini WAJIB verifikasi manual sebelum payout.
define('PAYOUT_MIN_AUTO', 0);