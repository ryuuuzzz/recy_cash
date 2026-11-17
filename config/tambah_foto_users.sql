-- Menambahkan kolom foto profil pada tabel users
ALTER TABLE users
    ADD COLUMN foto VARCHAR(255) NULL DEFAULT NULL AFTER alamat;
