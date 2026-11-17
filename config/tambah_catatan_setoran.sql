-- Kolom catatan / keterangan opsional pada transaksi setoran teller.
-- Jalankan bila kolom ini belum ada di lingkungan Anda.
ALTER TABLE deposit_transactions
  ADD COLUMN catatan TEXT NULL AFTER total_berat;
