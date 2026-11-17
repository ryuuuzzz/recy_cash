<?php
/**
 * Export audit log ke CSV (admin saja).
 */
session_start();

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../includes/pickup_helper.php';

if (($_SESSION['role'] ?? null) !== 'admin') {
    http_response_code(403);
    exit('Akses ditolak.');
}
pastikanTabelAudit($pdo);

$cariAksi = trim($_GET['aksi'] ?? '');
$cariTabel = trim($_GET['tabel'] ?? '');
$cariUser = (int) ($_GET['user'] ?? 0);
$tglAwal  = trim($_GET['tgl_awal'] ?? '');
$tglAkhir = trim($_GET['tgl_akhir'] ?? '');

$where = [];
$params = [];
if ($cariAksi !== '') { $where[] = "a.aksi LIKE :aksi"; $params[':aksi'] = '%' . $cariAksi . '%'; }
if ($cariTabel !== '') { $where[] = "a.tabel = :tabel"; $params[':tabel'] = $cariTabel; }
if ($cariUser > 0)    { $where[] = "a.user_id = :uid";  $params[':uid'] = $cariUser; }
if ($tglAwal !== '')  { $where[] = "a.created_at >= :tgla"; $params[':tgla'] = $tglAwal . ' 00:00:00'; }
if ($tglAkhir !== '') { $where[] = "a.created_at <= :tglb"; $params[':tglb'] = $tglAkhir . ' 23:59:59'; }
$whereSql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT a.*, u.nama AS nama_user, u.role AS role_user
    FROM audit_log a
    LEFT JOIN users u ON u.id = a.user_id
    $whereSql
    ORDER BY a.created_at DESC
");
$stmt->execute($params);

function csvRowAudit(array $row): string {
    $out = [];
    foreach ($row as $v) {
        if (is_null($v)) $v = '';
        $out[] = '"' . str_replace('"', '""', (string) $v) . '"';
    }
    return implode(';', $out) . "\r\n";
}

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="audit_log_' . date('Ymd_His') . '.csv"');

echo "\xEF\xBB\xBF";
echo csvRowAudit(['Waktu', 'Pelaku', 'Role', 'Aksi', 'Tabel', 'Record ID', 'Detail']);
foreach ($stmt->fetchAll() as $r) {
    echo csvRowAudit([
        date('d/m/Y H:i:s', strtotime($r['created_at'])),
        $r['nama_user'] ?? ('Sistem #' . $r['user_id']),
        $r['role_user'] ?? '-',
        $r['aksi'],
        $r['tabel'],
        $r['record_id'] ?? '',
        $r['detail'] ?? '',
    ]);
}
exit;
