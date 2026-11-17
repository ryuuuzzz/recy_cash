-- Dukung metode penarikan "Cair Tunai di Lokasi" dan kolom alasan penolakan
ALTER TABLE withdrawals
  MODIFY COLUMN tipe_penarikan ENUM('e-wallet','bank_transfer','voucher','tunai') NOT NULL;

ALTER TABLE withdrawals
  ADD COLUMN alasan_penolakan TEXT NULL AFTER status;