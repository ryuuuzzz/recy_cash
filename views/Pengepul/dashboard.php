<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<?php
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/notifikasi.php';

// 1. Proteksi akses khusus pengepul mitra
if (($role ?? null) !== 'pengepul') {
    header('Location: ' . $baseUrl . '/auth/login.php');
    exit;
}

$mitraId   = (int) ($_SESSION['id_user'] ?? 0);
$namaMitra = $_SESSION['nama'] ?? 'Pengepul';

// 2. Ringkasan transaksi milik mitra ini
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS jumlah,
           COALESCE(SUM(total_berat), 0)      AS berat,
           COALESCE(SUM(total_pendapatan), 0) AS pendapatan
    FROM penjualan_pengepul
    WHERE pengepul_id = :pid
");
$stmt->execute([':pid' => $mitraId]);
$ringkasan = $stmt->fetch();

// 3. Filter tanggal + pagination riwayat
$tglAwal  = trim($_GET['tgl_awal'] ?? '');
$tglAkhir = trim($_GET['tgl_akhir'] ?? '');

function validTglMitra($d) {
    if (!$d) return '';
    $o = DateTime::createFromFormat('Y-m-d', $d);
    return ($o && $o->format('Y-m-d') === $d) ? $d : '';
}
$tglAwal  = validTglMitra($tglAwal);
$tglAkhir = validTglMitra($tglAkhir);
if ($tglAwal !== '' && $tglAkhir !== '' && $tglAwal > $tglAkhir) {
    [$tglAwal, $tglAkhir] = [$tglAkhir, $tglAwal];
}

$where = ['pp.pengepul_id = :pid'];
$params = [':pid' => $mitraId];
if ($tglAwal !== '') {
    $where[] = 'pp.created_at >= :tgla';
    $params[':tgla'] = $tglAwal . ' 00:00:00';
}
if ($tglAkhir !== '') {
    $where[] = 'pp.created_at <= :tglb';
    $params[':tglb'] = $tglAkhir . ' 23:59:59';
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$perPage = (int) ($_GET['per_page'] ?? 10);
if (!in_array($perPage, [10, 25, 50], true)) $perPage = 10;
$page = max(1, (int) ($_GET['page'] ?? 1));

$stmt = $pdo->prepare("SELECT COUNT(*) FROM penjualan_pengepul pp $whereSql");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();
$totalPage = max(1, (int) ceil($total / $perPage));
if ($page > $totalPage) $page = $totalPage;
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("
    SELECT pp.*, cs.nama_kategori, cs.satuan
    FROM penjualan_pengepul pp
    INNER JOIN categories_sampah cs ON cs.id = pp.category_id
    $whereSql
    ORDER BY pp.created_at DESC, pp.id DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$riwayat = $stmt->fetchAll();

function qsMitra(array $ganti = []): string {
    $q = array_merge($_GET, $ganti);
    unset($q['page']);
    $q = array_filter($q, static fn($v) => $v !== '' && $v !== null);
    return 'dashboard.php' . (count($q) ? '?' . http_build_query($q) : '');
}
?>

<!-- ======================== HEADER ======================== -->
<div class="mb-3">
    <div class="text-muted small text-uppercase fw-semibold">Portal Mitra</div>
    <h4 class="mb-0 fw-bold">Transaksi Penjualan RecyCash</h4>
    <p class="text-muted mb-0">Selamat datang, <strong><?= htmlspecialchars($namaMitra) ?></strong>. Berikut riwayat penjualan sampah yang disalurkan kepada Anda.</p>
</div>

<!-- ======================== STATISTIK ======================== -->
<div class="row g-4 mb-4">
    <div class="col-sm-4">
        <div class="card stat-card">
            <div class="stat-icon bg-success"><i class="bi bi-receipt"></i></div>
            <div class="stat-info">
                <span class="stat-label">Total Transaksi</span>
                <span class="stat-value"><?= number_format($ringkasan['jumlah']) ?>x</span>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card stat-card">
            <div class="stat-icon bg-info"><i class="bi bi-box-seam"></i></div>
            <div class="stat-info">
                <span class="stat-label">Total Berat Diterima</span>
                <span class="stat-value"><?= number_format((float) $ringkasan['berat'], 2, ',', '.') ?> kg</span>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card stat-card">
            <div class="stat-icon bg-warning"><i class="bi bi-cash-coin"></i></div>
            <div class="stat-info">
                <span class="stat-label">Total Pendapatan</span>
                <span class="stat-value">Rp <?= number_format((float) $ringkasan['pendapatan'], 0, ',', '.') ?></span>
            </div>
        </div>
    </div>
</div>

<!-- ======================== FILTER ======================== -->
<div class="card mb-3">
    <div class="card-body">
        <form class="row g-3 align-items-end" method="get" action="dashboard.php">
            <div class="col-6 col-lg-3">
                <label class="form-label small text-muted mb-1">Dari Tanggal</label>
                <input type="date" class="form-control form-control-sm" name="tgl_awal" value="<?= htmlspecialchars($tglAwal) ?>">
            </div>
            <div class="col-6 col-lg-3">
                <label class="form-label small text-muted mb-1">Sampai Tanggal</label>
                <input type="date" class="form-control form-control-sm" name="tgl_akhir" value="<?= htmlspecialchars($tglAkhir) ?>">
            </div>
            <div class="col-12 col-lg-auto d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-funnel me-1"></i>Tampilkan</button>
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- ======================== TABEL RIWAYAT ======================== -->
<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h6 class="mb-0"><i class="bi bi-truck me-2 text-success"></i>Riwayat Penjualan</h6>
        <small class="text-muted">Total <?= number_format($total) ?> transaksi</small>
    </div>
    <div class="card-body p-0">
        <?php if (empty($riwayat)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox" style="font-size:40px;"></i>
                <p class="mt-2">Belum ada transaksi penjualan yang tercatat untuk akun Anda.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width:50px;">No</th>
                            <th>Tanggal</th>
                            <th>Jenis Sampah</th>
                            <th class="text-end">Berat</th>
                            <th class="text-end">Harga / kg</th>
                            <th class="text-end">Total</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($riwayat as $i => $r): ?>
                            <tr>
                                <td class="text-center"><?= $offset + $i + 1 ?></td>
                                <td class="text-nowrap small">
                                    <div><?= date('d M Y', strtotime($r['created_at'])) ?></div>
                                    <small class="text-muted"><?= date('H:i', strtotime($r['created_at'])) ?></small>
                                </td>
                                <td><strong><?= htmlspecialchars($r['nama_kategori']) ?></strong></td>
                                <td class="text-end text-nowrap"><?= number_format((float) $r['total_berat'], 2, ',', '.') ?> <?= htmlspecialchars($r['satuan'] ?? 'kg') ?></td>
                                <td class="text-end text-nowrap">Rp <?= number_format((float) $r['harga_jual_per_kg'], 0, ',', '.') ?></td>
                                <td class="text-end text-nowrap"><span class="badge text-bg-success">Rp <?= number_format((float) $r['total_pendapatan'], 0, ',', '.') ?></span></td>
                                <td class="small text-muted" style="max-width:240px;"><?= htmlspecialchars($r['catatan'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="3" class="text-end">Total Halaman Ini</th>
                            <th class="text-end text-nowrap"><?= number_format(array_sum(array_column($riwayat, 'total_berat')), 2, ',', '.') ?> kg</th>
                            <th></th>
                            <th class="text-end text-nowrap">Rp <?= number_format(array_sum(array_column($riwayat, 'total_pendapatan')), 0, ',', '.') ?></th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ======================== PAGINATION ======================== -->
<?php if ($totalPage > 1): ?>
    <nav class="mt-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="text-muted small">Halaman <strong><?= $page ?></strong> dari <strong><?= $totalPage ?></strong></div>
        <div class="d-flex gap-1 align-items-center">
            <select class="form-select form-select-sm w-auto" onchange="window.location.href=this.value">
                <?php foreach ([10, 25, 50] as $n): ?>
                    <option value="<?= qsMitra(['per_page' => $n]) ?>" <?= $perPage === $n ? 'selected' : '' ?>><?= $n ?> / halaman</option>
                <?php endforeach; ?>
            </select>
            <a href="<?= qsMitra(['page' => max(1, $page - 1)]) ?>" class="btn btn-outline-success btn-sm <?= $page <= 1 ? 'disabled' : '' ?>"><i class="bi bi-chevron-left"></i></a>
            <span class="btn btn-sm btn-success disabled"><?= $page ?> / <?= $totalPage ?></span>
            <a href="<?= qsMitra(['page' => min($totalPage, $page + 1)]) ?>" class="btn btn-outline-success btn-sm <?= $page >= $totalPage ? 'disabled' : '' ?>"><i class="bi bi-chevron-right"></i></a>
        </div>
    </nav>
<?php endif; ?>

<style>
    .stat-card {
        border: none;
        border-radius: 14px;
        padding: 22px;
        display: flex;
        align-items: center;
        gap: 16px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        transition: transform 0.2s;
    }
    .stat-card:hover { transform: translateY(-3px); }
    .stat-icon {
        width: 52px; height: 52px; border-radius: 14px;
        display: flex; align-items: center; justify-content: center;
        font-size: 22px; color: #fff; flex-shrink: 0;
    }
    .stat-info { display: flex; flex-direction: column; }
    .stat-label { font-size: 13px; color: var(--text-muted); font-weight: 500; }
    .stat-value { font-size: 20px; font-weight: 700; color: var(--text-strong); }
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
