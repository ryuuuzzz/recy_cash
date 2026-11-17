-- Kolom untuk verifikasi akun admin/petugas dan penanda Super Admin.
-- status_akun: 'pending' (menunggu verifikasi), 'approved' (aktif), 'rejected' (ditolak).
-- Default 'approved' supaya seluruh akun yang sudah ada tidak ikut terkunci.
ALTER TABLE users
  ADD COLUMN status_akun ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved' AFTER role,
  ADD COLUMN is_super_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER status_akun,
  ADD COLUMN catatan_verifikasi TEXT NULL AFTER is_super_admin,
  ADD COLUMN verified_by INT UNSIGNED NULL AFTER catatan_verifikasi,
  ADD COLUMN verified_at DATETIME NULL AFTER verified_by;

-- Tandai akun admin yang sudah ada sebagai Super Admin.
-- Sesuaikan WHERE ini bila hanya ingin 1 akun tertentu (mis. WHERE email = 'boss234@gmail.com').
UPDATE users SET is_super_admin = 1 WHERE role = 'admin';
