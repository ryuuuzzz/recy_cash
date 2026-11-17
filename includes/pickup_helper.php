<?php
// Helper untuk kolom opsional pada pickup_requests

function pastikanTabelAudit(PDO $pdo): void {
    static $siap = false;
    // DDL di tengah transaksi memicu implicit commit MySQL yang memutus transaksi aktif
    if ($siap || $pdo->inTransaction()) {
        return;
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS audit_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            aksi VARCHAR(50) NOT NULL,
            tabel VARCHAR(50) NOT NULL,
            record_id INT NULL,
            detail TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (user_id, tabel)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $siap = true;
    } catch (PDOException $e) {
        // abaikan
    }
}

function pastikanTabelNotifikasi(PDO $pdo): void {
    static $siap = false;
    // DDL di tengah transaksi memicu implicit commit MySQL yang memutus transaksi aktif
    if ($siap || $pdo->inTransaction()) {
        return;
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS notifikasi (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            pesan VARCHAR(255) NOT NULL,
            link VARCHAR(255) NULL,
            dibaca TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (user_id, dibaca)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $siap = true;
    } catch (PDOException $e) {
        // abaikan
    }
}
function kolomPickupAda(PDO $pdo, string $kolom): bool {
    $s = $pdo->prepare("SHOW COLUMNS FROM pickup_requests LIKE :k");
    $s->execute([':k' => $kolom]);
    return (bool) $s->fetch();
}

function pastikanKolomPickup(PDO $pdo, string $kolom, string $definisi): void {
    if (!kolomPickupAda($pdo, $kolom)) {
        try {
            $pdo->exec("ALTER TABLE pickup_requests ADD COLUMN $kolom $definisi");
        } catch (PDOException $e) {
        }
    }
}
