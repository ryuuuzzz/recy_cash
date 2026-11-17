<?php
/**
 * Export laporan ke CSV (Excel-compatible).
 * Akses: admin & petugas. Query param:
 *   jenis = setoran | penarikan | penjualan | jemputan | kategori
 *   tgl_awal, tgl_akhir (opsional, format Y-m-d)
 */
session_start();

require_once __DIR__ . '/../config/koneksi.php';

$role = $_SESSION['role'] ?? null;
if (!in_array($role, ['admin', 'petugas'], true)) {
    http_response_code(403);
    exit('Akses ditolak.');
}

$jenis = $_GET['jenis'] ?? 'setoran';
$tglAwal  = $_GET['tgl_awal'] ?? '';
$tglAkhir = $_GET['tgl_akhir'] ?? '';

function validTgl(?string $d): string {
    if (!$d) return '';
    $o = DateTime::createFromFormat('Y-m-d', $d);
    return ($o && $o->format('Y-m-d') === $d) ? $d : '';
}

$tglAwal  = validTgl($tglAwal);
$tglAkhir = validTgl($tglAkhir);
if ($tglAwal !== '' && $tglAkhir !== '' && $tglAwal > $tglAkhir) {
    [$tglAwal, $tglAkhir] = [$tglAkhir, $tglAwal];
}

function filterSql(string $alias, string $tAwal, string $tAkhir): array {
    $w = [];
    $p = [];
    if ($tAwal !== '')  { $w[] = "$alias.created_at >= :tgla"; $p[':tgla'] = $tAwal . ' 00:00:00'; }
    if ($tAkhir !== '') { $w[] = "$alias.created_at <= :tglb"; $p[':tglb'] = $tAkhir . ' 23:59:59'; }
    return [count($w) ? 'WHERE ' . implode(' AND ', $w) : '', $p];
}

function csvRow(array $row): string {
    $out = [];
    foreach ($row as $v) {
        if (is_null($v)) $v = '';
        if (is_float($v) || is_int($v)) {
            $v = str_replace('.', ',', (string) $v);
        }
        $out[] = '"' . str_replace('"', '""', (string) $v) . '"';
    }
    return implode(';', $out) . "\r\n";
}

$judul = '';
$header = [];
$rows = [];
$params = [];

switch ($jenis) {
    case 'setoran':
        [$f, $params] = filterSql('dt', $tglAwal, $tglAkhir);
        $judul = 'Laporan Setoran Sampah';
        $header = ['Kode Transaksi', 'Tanggal', 'Nasabah', 'Petugas', 'Berat (kg)', 'Nominal (Rp)'];
        $stmt = $pdo->prepare("
            SELECT dt.kode_transaksi, dt.created_at, nsb.nama, ptg.nama, dt.total_berat, dt.total_nominal
            FROM deposit_transactions dt
            INNER JOIN users nsb ON nsb.id = dt.nasabah_id
            INNER JOIN users ptg ON ptg.id = dt.petugas_id
            $f ORDER BY dt.created_at ASC
        ");
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $r) {
            $rows[] = [$r['kode_transaksi'], date('d/m/Y H:i', strtotime($r['created_at'])), $r['nama'], $r['nama'], (float) $r['total_berat'], (float) $r['total_nominal']];
        }
        break;

    case 'penarikan':
        [$f, $params] = filterSql('w', $tglAwal, $tglAkhir);
        $judul = 'Laporan Penarikan Saldo';
        $header = ['ID', 'Tanggal', 'Nasabah', 'Tipe', 'Tujuan', 'Nominal (Rp)', 'Status'];
        $stmt = $pdo->prepare("
            SELECT w.id, w.created_at, nsb.nama, w.tipe_penarikan, w.detail_tujuan, w.nominal, w.status
            FROM withdrawals w
            INNER JOIN users nsb ON nsb.id = w.nasabah_id
            $f ORDER BY w.created_at ASC
        ");
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $r) {
            $rows[] = ['PNK-' . str_pad((string) $r['id'], 4, '0', STR_PAD_LEFT), date('d/m/Y H:i', strtotime($r['created_at'])), $r['nama'], $r['tipe_penarikan'], $r['detail_tujuan'], (float) $r['nominal'], $r['status']];
        }
        break;

    case 'penjualan':
        [$f, $params] = filterSql('pp', $tglAwal, $tglAkhir);
        $judul = 'Laporan Penjualan Pengepul';
        $header = ['Tanggal', 'Pengepul', 'Kategori', 'Berat (kg)', 'Harga/kg (Rp)', 'Total (Rp)', 'Catatan'];
        $stmt = $pdo->prepare("
            SELECT pp.created_at, pp.nama_pengepul, cs.nama_kategori, pp.total_berat, pp.harga_jual_per_kg, pp.total_pendapatan, pp.catatan
            FROM penjualan_pengepul pp
            INNER JOIN categories_sampah cs ON cs.id = pp.category_id
            $f ORDER BY pp.created_at ASC
        ");
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $r) {
            $rows[] = [date('d/m/Y H:i', strtotime($r['created_at'])), $r['nama_pengepul'], $r['nama_kategori'], (float) $r['total_berat'], (float) $r['harga_jual_per_kg'], (float) $r['total_pendapatan'], $r['catatan']];
        }
        break;

    case 'jemputan':
        [$f, $params] = filterSql('pr', $tglAwal, $tglAkhir);
        $judul = 'Laporan Request Jemputan';
        $header = ['ID', 'Tanggal', 'Nasabah', 'Alamat', 'Estimasi (kg)', 'Jadwal', 'Status'];
        $stmt = $pdo->prepare("
            SELECT pr.id, pr.created_at, nsb.nama, pr.alamat_jemput, pr.estimasi_berat, pr.jadwal_jemput, pr.status
            FROM pickup_requests pr
            INNER JOIN users nsb ON nsb.id = pr.nasabah_id
            $f ORDER BY pr.created_at ASC
        ");
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $r) {
            $rows[] = ['JMP-' . $r['id'], date('d/m/Y H:i', strtotime($r['created_at'])), $r['nama'], $r['alamat_jemput'], (float) $r['estimasi_berat'], $r['jadwal_jemput'] ? date('d/m/Y H:i', strtotime($r['jadwal_jemput'])) : 'Fleksibel', $r['status']];
        }
        break;

    case 'kategori':
        [$f, $params] = filterSql('dt', $tglAwal, $tglAkhir);
        $judul = 'Rekapitulasi per Kategori';
        $header = ['Kategori', 'Satuan', 'Berat Total (kg)', 'Jumlah Item', 'Nilai (Rp)'];
        $stmt = $pdo->prepare("
            SELECT cs.nama_kategori, cs.satuan, COALESCE(SUM(dd.berat_kg),0) AS tb, COUNT(dd.id) AS ji, COALESCE(SUM(dd.subtotal),0) AS tn
            FROM deposit_details dd
            INNER JOIN categories_sampah cs ON cs.id = dd.category_id
            INNER JOIN deposit_transactions dt ON dt.id = dd.transaction_id
            $f GROUP BY cs.id, cs.nama_kategori, cs.satuan ORDER BY tb DESC
        ");
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $r) {
            $rows[] = [$r['nama_kategori'], $r['satuan'], (float) $r['tb'], (int) $r['ji'], (float) $r['tn']];
        }
        break;

    default:
        http_response_code(400);
        exit('Jenis laporan tidak dikenal.');
}

$namaFile = 'laporan_' . $jenis . '_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $namaFile . '"');

// BOM agar Excel membaca UTF-8 dengan benar
echo "\xEF\xBB\xBF";
echo csvRow([$judul]);
if ($tglAwal !== '' || $tglAkhir !== '') {
    echo csvRow(['Periode: ' . ($tglAwal !== '' ? $tglAwal : 'awal') . ' s/d ' . ($tglAkhir !== '' ? $tglAkhir : 'sekarang')]);
}
echo csvRow(['Diekspor: ' . date('d/m/Y H:i')]);
echo csvRow([]);
echo csvRow($header);
foreach ($rows as $row) {
    echo csvRow($row);
}
exit;
