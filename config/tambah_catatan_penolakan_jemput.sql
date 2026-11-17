-- ============================================================
-- Migrasi fitur Kelola Jemput (Teller)
-- Jalankan sekali di phpMyAdmin / CLI MySQL.
-- ============================================================

-- 1. Tambah nilai 'disetujui' ke ENUM status (sebelumnya tidak ada)
ALTER TABLE pickup_requests
    MODIFY COLUMN status ENUM('pending','diproses','disetujui','selesai','ditolak') DEFAULT 'pending';

-- 2. Kolom alasan penolakan untuk request penjemputan (opsional)
-- Aman dijalankan berulang: cek dulu apakah kolom sudah ada sebelum ALTER.
ALTER TABLE pickup_requests
    ADD COLUMN catatan_penolakan VARCHAR(255) NULL AFTER foto_sampah;
