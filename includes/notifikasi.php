<?php
/**
 * Helper fitur: notifikasi (in-app + email + WhatsApp), audit log, stok gudang,
 * dan migrasi otomatis untuk kolom/tabel baru.
 */

require_once __DIR__ . '/mailer.php';

/* ================================ MIGRASI ================================ */

function pastikanKolomTabel(PDO $pdo, string $tabel, string $kolom, string $definisi): void {
    try {
        $s = $pdo->prepare("SHOW COLUMNS FROM `$tabel` LIKE :k");
        $s->execute([':k' => $kolom]);
        if (!$s->fetch()) {
            $pdo->exec("ALTER TABLE `$tabel` ADD COLUMN `$kolom` $definisi");
        }
    } catch (PDOException $e) {
        // abaikan jika gagal (mis. izin DB)
    }
}

/**
 * Tambahkan role "pengepul" ke ENUM users.role (aman, ENUM yang diperluas
 * tidak mengubah data lama).
 */
function pastikanRolePengepul(PDO $pdo): void {
    try {
        $s = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'");
        $col = $s->fetch();
        if ($col && strpos((string) $col['Type'], 'pengepul') === false) {
            $pdo->exec("ALTER TABLE users MODIFY role ENUM('nasabah','petugas','admin','pengepul') NOT NULL");
        }
    } catch (PDOException $e) {
        // abaikan
    }
}

/** Kolom pengepul_id pada penjualan_pengepul + backfill dari nama yang cocok. */
function pastikanKolomPengepulId(PDO $pdo): void {
    pastikanKolomTabel($pdo, 'penjualan_pengepul', 'pengepul_id', 'INT NULL');
    try {
        // Backfill: cocokkan nama_pengepul dengan akun ber-role pengepul
        $pdo->exec("
            UPDATE penjualan_pengepul pp
            INNER JOIN users u ON u.role = 'pengepul' AND LOWER(u.nama) = LOWER(pp.nama_pengepul)
            SET pp.pengepul_id = u.id
            WHERE pp.pengepul_id IS NULL
        ");
    } catch (PDOException $e) {
        // abaikan
    }
}

/** Tabel stok gudang + mutasi, lalu backfill saldo dari data historis. */
function pastikanTabelStok(PDO $pdo): void {
    static $siap = false;
    // DDL di tengah transaksi memicu implicit commit MySQL yang memutus transaksi aktif
    if ($siap || $pdo->inTransaction()) {
        return;
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS stok_gudang (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category_id INT NOT NULL,
            stok_kg DECIMAL(12,2) NOT NULL DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_category (category_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS stok_mutasi (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category_id INT NOT NULL,
            tipe ENUM('masuk','keluar') NOT NULL,
            berat_kg DECIMAL(12,2) NOT NULL,
            keterangan VARCHAR(255) NULL,
            referensi VARCHAR(50) NULL,
            user_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_kategori (category_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (PDOException $e) {
        return; // tabel gagal dibuat
    }
    $siap = true;

    try {
        $kosong = (int) $pdo->query("SELECT COUNT(*) FROM stok_gudang")->fetchColumn();
        if ($kosong === 0) {
            // Backfill: masuk dari setoran, keluar dari penjualan pengepul
            $pdo->exec("
                INSERT INTO stok_gudang (category_id, stok_kg)
                SELECT cs.id, COALESCE(t.masuk, 0) - COALESCE(k.keluar, 0)
                FROM categories_sampah cs
                LEFT JOIN (SELECT category_id, SUM(berat_kg) AS masuk FROM deposit_details GROUP BY category_id) t ON t.category_id = cs.id
                LEFT JOIN (SELECT category_id, SUM(total_berat) AS keluar FROM penjualan_pengepul GROUP BY category_id) k ON k.category_id = cs.id
            ");
            $pdo->exec("
                INSERT INTO stok_mutasi (category_id, tipe, berat_kg, keterangan, referensi, user_id)
                SELECT category_id, 'masuk', masuk, 'Backfill saldo awal (setoran historis)', 'BACKFILL', NULL
                FROM (SELECT category_id, SUM(berat_kg) AS masuk FROM deposit_details GROUP BY category_id) t
            ");
            $pdo->exec("
                INSERT INTO stok_mutasi (category_id, tipe, berat_kg, keterangan, referensi, user_id)
                SELECT category_id, 'keluar', total_berat, 'Backfill saldo awal (penjualan historis)', 'BACKFILL', NULL
                FROM (SELECT category_id, SUM(total_berat) AS keluar FROM penjualan_pengepul GROUP BY category_id) k
            ");
        }
    } catch (PDOException $e) {
        // abaikan
    }
}

/* ================================ STOK ================================ */

/**
 * Catat mutasi stok dan update saldo stok_gudang.
 * Return: [ok(bool), stok_akhir(float)]
 */
function stokCatat(PDO $pdo, int $categoryId, string $tipe, float $berat, string $keterangan = '', ?string $referensi = null, ?int $userId = null): array {
    if ($categoryId <= 0 || $berat <= 0 || !in_array($tipe, ['masuk', 'keluar'], true)) {
        return [false, 0.0];
    }

    pastikanTabelStok($pdo);

    try {
        $s = $pdo->prepare("SELECT stok_kg FROM stok_gudang WHERE category_id = :cid FOR UPDATE");
        $s->execute([':cid' => $categoryId]);
        $row = $s->fetch();

        if (!$row) {
            $pdo->prepare("INSERT INTO stok_gudang (category_id, stok_kg) VALUES (:cid, 0)")
                ->execute([':cid' => $categoryId]);
            $stokSekarang = 0.0;
        } else {
            $stokSekarang = (float) $row['stok_kg'];
        }

        if ($tipe === 'keluar' && $berat > $stokSekarang) {
            return [false, $stokSekarang]; // stok tidak cukup
        }

        $stokBaru = $tipe === 'masuk' ? $stokSekarang + $berat : $stokSekarang - $berat;

        $pdo->prepare("UPDATE stok_gudang SET stok_kg = :s WHERE category_id = :cid")
            ->execute([':s' => $stokBaru, ':cid' => $categoryId]);

        $pdo->prepare("INSERT INTO stok_mutasi (category_id, tipe, berat_kg, keterangan, referensi, user_id)
                       VALUES (:cid, :tipe, :berat, :ket, :ref, :uid)")
            ->execute([
                ':cid'   => $categoryId,
                ':tipe'  => $tipe,
                ':berat' => $berat,
                ':ket'   => $keterangan !== '' ? mb_substr($keterangan, 0, 255) : null,
                ':ref'   => $referensi,
                ':uid'   => $userId,
            ]);

        return [true, $stokBaru];
    } catch (PDOException $e) {
        return [false, 0.0];
    }
}

/* ================================ AUDIT ================================ */

function auditCatat(PDO $pdo, ?int $userId, string $aksi, string $tabel, ?int $recordId, string $detail = ''): void {
    try {
        require_once __DIR__ . '/pickup_helper.php';
        pastikanTabelAudit($pdo);
        $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, aksi, tabel, record_id, detail) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $userId,
            mb_substr($aksi, 0, 50),
            mb_substr($tabel, 0, 50),
            $recordId,
            mb_substr($detail, 0, 2000),
        ]);
    } catch (PDOException $e) {
        // abaikan kegagalan audit
    }
}

/* ============================= NOTIFIKASI ============================= */

/** Notifikasi in-app. */
function notifBuat(PDO $pdo, int $userId, string $pesan, ?string $link = null): void {
    if ($userId <= 0) return;
    try {
        require_once __DIR__ . '/pickup_helper.php';
        pastikanTabelNotifikasi($pdo);
        $stmt = $pdo->prepare("INSERT INTO notifikasi (user_id, pesan, link) VALUES (?, ?, ?)");
        $stmt->execute([$userId, mb_substr($pesan, 0, 255), $link]);
    } catch (PDOException $e) {
        // abaikan
    }
}

/** Email best-effort ke user. Return true jika terkirim. */
function notifEmail(PDO $pdo, int $userId, string $subjek, string $html): bool {
    if ($userId <= 0) return false;
    try {
        $s = $pdo->prepare("SELECT email FROM users WHERE id = :id LIMIT 1");
        $s->execute([':id' => $userId]);
        $email = (string) ($s->fetchColumn() ?: '');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $res = kirim_email($email, $subjek, $html);
        return !empty($res['success']);
    } catch (Throwable $e) {
        return false;
    }
}

/** Kirim notifikasi in-app + email (email best-effort, tidak mengganggu alur). */
function notifKirim(PDO $pdo, int $userId, string $pesan, ?string $link = null, string $subjekEmail = '', ?string $htmlEmail = null): void {
    notifBuat($pdo, $userId, $pesan, $link);
    if ($subjekEmail !== '') {
        notifEmail($pdo, $userId, $subjekEmail, $htmlEmail ?: '<p>' . htmlspecialchars($pesan) . '</p>');
    }
}

/* ============================== WHATSAPP ============================== */

/** Normalisasi nomor HP Indonesia ke format 62xxxxxxxxxx. */
function nomorWa(string $hp): string {
    $hp = preg_replace('/[^0-9]/', '', (string) $hp);
    if ($hp === '') return '';
    if (str_starts_with($hp, '0'))   return '62' . substr($hp, 1);
    if (str_starts_with($hp, '62'))  return $hp;
    if (strlen($hp) >= 9)            return '62' . $hp; // nomor tanpa prefix
    return '';
}

/** URL klik-kirim WhatsApp (pesan via wa.me share). */
function waLink(string $hp, string $pesan): string {
    $no = nomorWa($hp);
    if ($no === '') return '';
    return 'https://wa.me/' . $no . '?text=' . rawurlencode($pesan);
}
