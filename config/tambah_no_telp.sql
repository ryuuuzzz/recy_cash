-- Kolom nomor_hp sudah tersedia di tabel users (db_bank_sampah).
-- Hanya perlu dijalankan bila database di lingkungan lain belum punya kolom ini:
ALTER TABLE users
  ADD COLUMN nomor_hp VARCHAR(20) NULL AFTER email;