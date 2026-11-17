<?php
/**
 * Export mutasi stok gudang ke CSV (admin saja).
 */
session_start();

require_once __DIR__ . '/../config/koneksi.php';

if (($_SESSION['role'] ?? null) !== 'admin') {
    http_response_code(403);
    exit('Akses ditolak.');
}

$fKategori = (int) ($_GET['kategori'] ?? 0);
$fTipe     = $_GET['tipe'] ?? '';

$where = [];
$params = [];
if ($fKategori > 0) { $where[] = 'm.category_id = :cid'; $params[':cid'] = $fKategori; }
if (in_array($fTipe, ['masuk', 'keluar'], true)) { $where[] = 'm.tipe = :tipe'; $params[':tipe'] = $fTipe; }
$whereSql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT m.id, m.created_at, cs.nama_kategori, m.tipe, m.berat_kg, m.keterangan, m.referensi, u.nama AS nama_user
    FROM stok_mutasi m
    INNER JOIN categories_sampah cs ON cs.id = m.category_id
    LEFT JOIN users u ON u.id = m.user_id
    $whereSql
    ORDER BY m.created_at DESC
");
$stmt->execute($params);

function csvRowGudang(array $row): string {
    $out = [];
    foreach ($row as $v) {
        if (is_null($v)) $v = '';
        if (is_float($v) || is_int($v)) $v = str_replace('.', ',', (string) $v);
        $out[] = '"' . str_replace('"', '""', (string) $v) . '"';
    }
    return implode(';', $out) . "\r\n";
}

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="stok_gudang_' . date('Ymd_His') . '.csv"');

echo "\xEF\xBB\xBF";
echo csvRowGudang(['ID', 'Waktu', 'Kategori', 'Tipe', 'Berat (kg)', 'Keterangan', 'Referensi', 'Petugas']);
foreach ($stmt->fetchAll() as $r) {
    echo csvRowGudang([
        $r['id'],
        date('d/m/Y H:i', strtotime($r['created_at'])),
        $r['nama_kategori'],
        $r['tipe'],
        (float) $r['berat_kg'],
        $r['keterangan'] ?? '',
        $r['referensi'] ?? '',
        $r['nama_user'] ?? '',
    ]);
}
exit;
