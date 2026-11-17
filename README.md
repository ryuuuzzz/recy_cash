# RecyCash — Bank Sampah Digital

Aplikasi web bank sampah digital berbasis PHP + MySQL. Mengelola setoran sampah, penarikan saldo, penjemputan, pengepul, stok gudang, dan payout otomatis.

## Fitur Utama

- **Nasabah**: saldo & poin, riwayat setoran, request penjemputan, penarikan, chat dengan teller.
- **Petugas (Teller)**: input setoran, kelola penjemputan, cetak nota, verifikasi penarikan.
- **Admin**: kelola user, verifikasi akun, harga sampah, gudang, laporan, audit log.
- **Pengepul**: dashboard mitra.
- **Cari Teller Terdekat**: pencarian outlet teller via Google Places / OpenStreetMap (Leaflet), auto-assign teller terdekat ala ojek online.
- **Chat & Telepon**: komunikasi dua arah nasabah ↔ teller saat penjemputan.
- **Payout otomatis** via Xendit Disbursement API.
- **PWA**: service worker untuk mode offline teller lapangan.

## Kebutuhan Sistem

- PHP 8.0+ (dengan ekstensi `pdo_mysql`, `curl`, `mbstring`)
- MySQL 8.0+ / MariaDB 10.4+
- Web server (Apache/Nginx) atau PHP built-in server
- (Opsional) Git, Composer

## Instalasi

1. **Clone repository**
   ```bash
   git clone https://github.com/USERNAME/RecyCash.git
   cd RecyCash
   ```

2. **Buat database**
   ```sql
   CREATE DATABASE db_bank_sampah CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. **Konfigurasi**
   Salin file template dan isi kredensial Anda:
   ```bash
   cp config/koneksi.example.php      config/koneksi.php
   cp config/mail_config.example.php   config/mail_config.php
   cp config/payment_config.example.php config/payment_config.php
   cp config/maps_config.example.php   config/maps_config.php
   ```
   Edit masing-masing file sesuai environment (DB host/user/pass, SMTP Gmail, Xendit secret, Google Maps API key).

4. **Jalankan migrasi SQL** (opsional, folder `config/*.sql`):
   ```bash
   mysql -u root -p db_bank_sampah < config/tambah_verifikasi_akun.sql
   # ... jalankan file SQL lain sesuai kebutuhan
   ```
   Tabel utama (`users`, `wallets`, `deposit_transactions`, `deposit_details`,
   `categories_sampah`, `pickup_requests`, `withdrawals`, `notifikasi`, `audit_log`,
   `stok_gudang`, `stok_mutasi`, `teller_locations`, `chat_pesan`,
   `penjualan_pengepul`, `login_sessions`, `password_resets`) dibuat otomatis
   saat aplikasi pertama kali diakses (helper `pastikanTabel*`).

5. **Jalankan server**
   ```bash
   php -S localhost:8000 -t .
   ```
   Buka `http://localhost:8000/`.

## Akun Awal

Setelah instalasi, buat akun admin pertama langsung di database atau via halaman registrasi (role `nasabah`/`petugas`). Akun `petugas` perlu disetujui super admin.

## Struktur Folder

```
RecyCash/
├── api/            # Endpoint JSON (lokasi, chat, status)
├── assets/         # CSS, JS, gambar
├── auth/           # Login, register, logout, lupa password
├── config/         # Konfigurasi (DB, mail, payment, maps) + migrasi SQL
├── includes/       # Helper (notifikasi, lokasi, payout, avatar, dll.)
├── uploads/        # Upload pengguna (foto profil, foto sampah)
├── views/          # Halaman per role (Admin, Teller, Nasabah, Pengepul, profile)
├── webhook/        # Webhook handler (Xendit, dll.)
├── sw.js           # Service worker PWA
└── index.php       # Landing page
```

## Keamanan

- **JANGAN commit** file `config/koneksi.php`, `config/mail_config.php`,
  `config/payment_config.php`, `config/maps_config.php` — sudah ada di `.gitignore`.
- Gunakan file `*.example.php` sebagai template.
- Untuk production, gunakan environment variable / `.env` (di luar webroot).

## Lisensi

Internal project. Semua hak dilindungi.