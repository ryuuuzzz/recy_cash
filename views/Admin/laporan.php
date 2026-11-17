<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<?php
require_once __DIR__ . '/../../config/koneksi.php';

$tglAwal  = trim($_GET['tgl_awal']  ?? '');
$tglAkhir = trim($_GET['tgl_akhir'] ?? '');

function isValidDate($d) {
    $o = DateTime::createFromFormat('Y-m-d', $d);
    return $o && $o->format('Y-m-d') === $d;
}

if ($tglAwal  !== '' && !isValidDate($tglAwal))  $tglAwal  = '';
if ($tglAkhir !== '' && !isValidDate($tglAkhir)) $tglAkhir = '';
if ($tglAwal !== '' && $tglAkhir !== '' && $tglAwal > $tglAkhir) {
    [$tglAwal, $tglAkhir] = [$tglAkhir, $tglAwal];
}

// Menyusun klausa WHERE + parameter berdasarkan rentang tanggal
function filterSql($alias) {
    global $tglAwal, $tglAkhir;
    $where  = [];
    $params = [];
    if ($tglAwal !== '') {
        $where[]         = "$alias.created_at >= :tgla";
        $params[':tgla'] = $tglAwal . ' 00:00:00';
    }
    if ($tglAkhir !== '') {
        $where[]         = "$alias.created_at <= :tglb";
        $params[':tglb'] = $tglAkhir . ' 23:59:59';
    }
    return [(count($where) ? 'WHERE ' . implode(' AND ', $where) : ''), $params];
}

function rp($n) { return 'Rp ' . number_format((float)$n, 0, ',', '.'); }
function kg($n) { return number_format((float)$n, 2, ',', '.') . ' kg'; }
function fTgl($t, $withTime = true) {
    return $withTime ? date('d/m/Y H:i', strtotime($t)) : date('d/m/Y', strtotime($t));
}

// ==================== QUERY: STATISTIK UTAMA ====================
[$fSet, $pSet] = filterSql('dt');
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS jumlah,
           COALESCE(SUM(total_nominal), 0) AS nominal,
           COALESCE(SUM(total_berat), 0)   AS berat
    FROM deposit_transactions dt $fSet
");
$stmt->execute($pSet);
$statSetoran = $stmt->fetch();

[$fWang, $pWang] = filterSql('w');
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS jumlah,
           COUNT(CASE WHEN status = 'disetujui' THEN 1 END) AS jumlah_disetujui,
           COALESCE(SUM(CASE WHEN status = 'disetujui' THEN nominal ELSE 0 END), 0) AS nominal_keluar
    FROM withdrawals w $fWang
");
$stmt->execute($pWang);
$statPenarikan = $stmt->fetch();

[$fPj, $pPj] = filterSql('pp');
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS jumlah,
           COALESCE(SUM(total_pendapatan), 0) AS pendapatan,
           COALESCE(SUM(total_berat), 0)       AS berat
    FROM penjualan_pengepul pp $fPj
");
$stmt->execute($pPj);
$statPengepul = $stmt->fetch();

[$fPr, $pPr] = filterSql('pr');
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS jumlah,
           COUNT(CASE WHEN status = 'selesai' THEN 1 END) AS jumlah_selesai
    FROM pickup_requests pr $fPr
");
$stmt->execute($pPr);
$statJemputan = $stmt->fetch();

// ==================== PAGINATION ====================
$perPage = (int) ($_GET['per_page'] ?? 10);
if (!in_array($perPage, [10, 25, 50], true)) $perPage = 10;

function lapPage(string $key): int {
    return max(1, (int) ($_GET[$key] ?? 1));
}
function lapQs(array $ganti = []): string {
    $q = array_merge($_GET, $ganti);
    $q = array_filter($q, static fn($v) => $v !== '' && $v !== null);
    return 'laporan.php' . (count($q) ? '?' . http_build_query($q) : '');
}
function lapPagination(string $pageKey, int $page, int $totalPage, int $total): string {
    if ($totalPage <= 1) return '';
    $prev = max(1, $page - 1);
    $next = min($totalPage, $page + 1);
    $html = '<nav class="mt-3 d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 pb-3">';
    $html .= '<div class="text-muted small">Halaman <strong>' . $page . '</strong> dari <strong>' . $totalPage . '</strong> · ' . number_format($total) . ' data</div>';
    $html .= '<div class="d-flex gap-1 align-items-center">';
    $html .= '<select class="form-select form-select-sm w-auto" onchange="window.location.href=this.value">';
    foreach ([10, 25, 50] as $n) {
        $sel = $n === $GLOBALS['perPage'] ? 'selected' : '';
        $html .= '<option value="' . lapQs([$pageKey => 1, 'per_page' => $n]) . '"' . $sel . '>' . $n . ' / halaman</option>';
    }
    $html .= '</select>';
    $dis = $page <= 1 ? ' disabled' : '';
    $html .= '<a href="' . lapQs([$pageKey => $prev]) . '" class="btn btn-outline-success btn-sm' . $dis . '"><i class="bi bi-chevron-left"></i></a>';
    $html .= '<span class="btn btn-sm btn-success disabled">' . $page . ' / ' . $totalPage . '</span>';
    $dis = $page >= $totalPage ? ' disabled' : '';
    $html .= '<a href="' . lapQs([$pageKey => $next]) . '" class="btn btn-outline-success btn-sm' . $dis . '"><i class="bi bi-chevron-right"></i></a>';
    $html .= '</div></nav>';
    return $html;
}
function lapCsvLink(string $jenis): string {
    global $tglAwal, $tglAkhir;
    $q = ['jenis' => $jenis];
    if ($tglAwal !== '')  $q['tgl_awal']  = $tglAwal;
    if ($tglAkhir !== '') $q['tgl_akhir'] = $tglAkhir;
    return (isset($GLOBALS['baseUrl']) ? $GLOBALS['baseUrl'] : '') . '/api/export_laporan.php?' . http_build_query($q);
}

// ==================== QUERY: SETORAN SAMPAH ====================
$pageSetor = lapPage('ps');
$stmt = $pdo->prepare("SELECT COUNT(*) FROM deposit_transactions dt $fSet");
$stmt->execute($pSet);
$totalSetor = (int) $stmt->fetchColumn();
$totalPageSetor = max(1, (int) ceil($totalSetor / $perPage));
if ($pageSetor > $totalPageSetor) $pageSetor = $totalPageSetor;
$offSetor = ($pageSetor - 1) * $perPage;

$stmt = $pdo->prepare("
    SELECT dt.kode_transaksi, dt.created_at, dt.total_nominal, dt.total_berat,
           nsb.nama AS nama_nasabah, nsb.foto AS foto_nasabah,
           ptg.nama AS nama_petugas, ptg.foto AS foto_petugas
    FROM deposit_transactions dt
    INNER JOIN users nsb ON nsb.id = dt.nasabah_id
    INNER JOIN users ptg ON ptg.id = dt.petugas_id
    $fSet
    ORDER BY dt.created_at DESC, dt.id DESC
    LIMIT $perPage OFFSET $offSetor
");
$stmt->execute($pSet);
$setoranList = $stmt->fetchAll();

// ==================== QUERY: REKAP PER KATEGORI ====================
$stmt = $pdo->prepare("
    SELECT cs.nama_kategori, cs.satuan,
           COALESCE(SUM(dd.berat_kg), 0) AS total_berat,
           COUNT(dd.id)                  AS jumlah_item,
           COALESCE(SUM(dd.subtotal), 0) AS total_nilai
    FROM deposit_details dd
    INNER JOIN categories_sampah cs ON cs.id = dd.category_id
    INNER JOIN deposit_transactions dt ON dt.id = dd.transaction_id
    $fSet
    GROUP BY cs.id, cs.nama_kategori, cs.satuan
    ORDER BY total_berat DESC
");
$stmt->execute($pSet);
$rekapKategori = $stmt->fetchAll();

// ==================== QUERY: PENARIKAN ====================
$pageTarik = lapPage('pt');
$stmt = $pdo->prepare("SELECT COUNT(*) FROM withdrawals w $fWang");
$stmt->execute($pWang);
$totalTarik = (int) $stmt->fetchColumn();
$totalPageTarik = max(1, (int) ceil($totalTarik / $perPage));
if ($pageTarik > $totalPageTarik) $pageTarik = $totalPageTarik;
$offTarik = ($pageTarik - 1) * $perPage;

$stmt = $pdo->prepare("
    SELECT w.id, w.tipe_penarikan, w.nominal, w.detail_tujuan, w.status, w.created_at,
           nsb.nama AS nama_nasabah, nsb.foto AS foto_nasabah
    FROM withdrawals w
    INNER JOIN users nsb ON nsb.id = w.nasabah_id
    $fWang
    ORDER BY w.created_at DESC, w.id DESC
    LIMIT $perPage OFFSET $offTarik
");
$stmt->execute($pWang);
$penarikanList = $stmt->fetchAll();

// ==================== QUERY: PENJUALAN PENGEpul ====================
$pageJual = lapPage('pj');
$stmt = $pdo->prepare("SELECT COUNT(*) FROM penjualan_pengepul pp $fPj");
$stmt->execute($pPj);
$totalJual = (int) $stmt->fetchColumn();
$totalPageJual = max(1, (int) ceil($totalJual / $perPage));
if ($pageJual > $totalPageJual) $pageJual = $totalPageJual;
$offJual = ($pageJual - 1) * $perPage;

$stmt = $pdo->prepare("
    SELECT pp.id, pp.nama_pengepul, pp.total_berat, pp.harga_jual_per_kg,
           pp.total_pendapatan, pp.created_at, pp.catatan,
           cs.nama_kategori, cs.satuan
    FROM penjualan_pengepul pp
    INNER JOIN categories_sampah cs ON cs.id = pp.category_id
    $fPj
    ORDER BY pp.created_at DESC, pp.id DESC
    LIMIT $perPage OFFSET $offJual
");
$stmt->execute($pPj);
$penjualanList = $stmt->fetchAll();

// ==================== QUERY: JEMPUTAN SAMPAH ====================
$pageJemput = lapPage('pm');
$stmt = $pdo->prepare("SELECT COUNT(*) FROM pickup_requests pr $fPr");
$stmt->execute($pPr);
$totalJemput = (int) $stmt->fetchColumn();
$totalPageJemput = max(1, (int) ceil($totalJemput / $perPage));
if ($pageJemput > $totalPageJemput) $pageJemput = $totalPageJemput;
$offJemput = ($pageJemput - 1) * $perPage;

$stmt = $pdo->prepare("
    SELECT pr.id, pr.alamat_jemput, pr.estimasi_berat, pr.status, pr.created_at,
           nsb.nama AS nama_nasabah, nsb.foto AS foto_nasabah
    FROM pickup_requests pr
    INNER JOIN users nsb ON nsb.id = pr.nasabah_id
    $fPr
    ORDER BY pr.created_at DESC, pr.id DESC
    LIMIT $perPage OFFSET $offJemput
");
$stmt->execute($pPr);
$jemputanList = $stmt->fetchAll();

function badgeStatus($status) {
    $map = [
        'pending'   => 'text-bg-warning',
        'diproses'  => 'text-bg-info',
        'disetujui' => 'bg-success',
        'ditolak'   => 'bg-danger',
        'selesai'   => 'bg-success',
    ];
    $label = [
        'pending'   => 'Pending',
        'diproses'  => 'Diproses',
        'disetujui' => 'Disetujui',
        'ditolak'   => 'Ditolak',
        'selesai'   => 'Selesai',
    ];
    $cls = $map[$status] ?? 'bg-secondary';
    return '<span class="badge rounded-pill ' . $cls . '">' . ($label[$status] ?? ucfirst($status)) . '</span>';
}

function tipePenarikan($t) {
    $map = ['e-wallet' => 'E-Wallet', 'bank_transfer' => 'Transfer Bank', 'voucher' => 'Voucher', 'tunai' => 'Tunai'];
    return $map[$t] ?? ucfirst(str_replace('_', ' ', $t));
}
?>

<!-- ======================== KOP LAPORAN ======================== -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 no-print">
    <div>
            <div class="text-muted small text-uppercase fw-semibold">Rekapitulasi RecyCash</div>
        <h4 class="mb-0 fw-bold">Laporan &amp; Statistik</h4>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.print()" title="Pilih 'Simpan sebagai PDF' pada dialog cetak">
            <i class="bi bi-file-earmark-pdf me-1"></i>PDF
        </button>
        <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>Cetak Laporan
        </button>
    </div>
</div>

<?php if ($tglAwal !== '' || $tglAkhir !== ''): ?>
    <div class="alert alert-info py-2 small no-print d-flex justify-content-between align-items-center">
        <span><i class="bi bi-calendar-range me-1"></i>
            Periode: <strong><?= $tglAwal !== '' ? fTgl($tglAwal, false) : 'Awal' ?></strong> s/d
            <strong><?= $tglAkhir !== '' ? fTgl($tglAkhir, false) : 'Sekarang' ?></strong>
        </span>
        <a href="laporan.php" class="text-decoration-none">Reset filter</a>
    </div>
<?php endif; ?>

<!-- ======================== FILTER TANGGAL ======================== -->
<div class="card filter-card mb-4 no-print">
    <div class="card-body">
        <form class="row g-3 align-items-end" method="get" action="laporan.php">
            <div class="col-6 col-lg-3">
                <label class="form-label small text-muted mb-1">Dari Tanggal</label>
                <input type="date" class="form-control form-control-sm" name="tgl_awal"
                       value="<?= htmlspecialchars($tglAwal) ?>">
            </div>
            <div class="col-6 col-lg-3">
                <label class="form-label small text-muted mb-1">Sampai Tanggal</label>
                <input type="date" class="form-control form-control-sm" name="tgl_akhir"
                       value="<?= htmlspecialchars($tglAkhir) ?>">
            </div>
            <div class="col-12 col-lg-auto d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-success btn-sm">
                    <i class="bi bi-funnel me-1"></i>Tampilkan
                </button>
                <a href="laporan.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                </a>
            </div>
        </form>
    </div>
</div>

<div class="report-area">

<!-- ======================== CARD STATISTIK ======================== -->
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card">
            <div class="stat-icon bg-success"><i class="bi bi-box-arrow-in-right"></i></div>
            <div class="stat-info">
                <span class="stat-label">Total Setoran Sampah</span>
                <span class="stat-value"><?= kg($statSetoran['berat']) ?></span>
                <span class="stat-sub"><?= rp($statSetoran['nominal']) ?> · <?= number_format($statSetoran['jumlah']) ?> transaksi</span>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card">
            <div class="stat-icon bg-danger"><i class="bi bi-cash-stack"></i></div>
            <div class="stat-info">
                <span class="stat-label">Total Penarikan</span>
                <span class="stat-value"><?= rp($statPenarikan['nominal_keluar']) ?></span>
                <span class="stat-sub"><?= number_format($statPenarikan['jumlah_disetujui']) ?> disetujui dari <?= number_format($statPenarikan['jumlah']) ?> pengajuan</span>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card">
            <div class="stat-icon bg-warning"><i class="bi bi-truck"></i></div>
            <div class="stat-info">
                <span class="stat-label">Pendapatan Pengepul</span>
                <span class="stat-value"><?= rp($statPengepul['pendapatan']) ?></span>
                <span class="stat-sub"><?= kg($statPengepul['berat']) ?> terjual · <?= number_format($statPengepul['jumlah']) ?> transaksi</span>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card">
            <div class="stat-icon bg-info"><i class="bi bi-calendar-check"></i></div>
            <div class="stat-info">
                <span class="stat-label">Request Jemputan</span>
                <span class="stat-value"><?= number_format($statJemputan['jumlah']) ?>x</span>
                <span class="stat-sub"><?= number_format($statJemputan['jumlah_selesai']) ?> selesai diproses</span>
            </div>
        </div>
    </div>
</div>

<!-- ======================== NAV TAB LAPORAN ======================== -->
<ul class="nav nav-tabs report-tabs mb-3" id="reportTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-setor" role="tab">
            <i class="bi bi-box-arrow-in-right me-1"></i>Setoran Sampah
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-kategori" role="tab">
            <i class="bi bi-tags me-1"></i>Rekap Kategori
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-penarikan" role="tab">
            <i class="bi bi-cash-stack me-1"></i>Penarikan
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-pengepul" role="tab">
            <i class="bi bi-truck me-1"></i>Penjualan Pengepul
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-jemputan" role="tab">
            <i class="bi bi-calendar-check me-1"></i>Jemputan Sampah
        </button>
    </li>
</ul>

<div class="tab-content" id="reportTabContent">

    <!-- ======================== TAB: SETORAN SAMPAH ======================== -->
    <div class="tab-pane fade show active" id="tab-setor" role="tabpanel">
        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h6 class="mb-0"><i class="bi bi-box-arrow-in-right me-2 text-success"></i>Detail Transaksi Setoran</h6>
                <a href="<?= lapCsvLink('setoran') ?>" class="btn btn-sm btn-outline-success no-print">
                    <i class="bi bi-download me-1"></i>Export CSV
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($setoranList)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox" style="font-size:40px;"></i>
                        <p class="mt-2">Belum ada data setoran pada periode ini.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" style="width:50px;">No</th>
                                    <th>Tanggal &amp; Kode</th>
                                    <th>Nasabah</th>
                                    <th>Petugas</th>
                                    <th>Berat</th>
                                    <th>Nominal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($setoranList as $i => $r): ?>
                                    <tr>
                                        <td class="text-center"><?= $i + 1 ?></td>
                                        <td>
                                            <strong class="text-nowrap"><?= htmlspecialchars($r['kode_transaksi']) ?></strong>
                                            <div class="text-muted small text-nowrap"><?= fTgl($r['created_at']) ?></div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <?= renderAvatar($r['nama_nasabah'], $r['foto_nasabah'] ?? '', $baseUrl, 32) ?>
                                                <span><?= htmlspecialchars($r['nama_nasabah']) ?></span>
                                            </div>
                                        </td>
                                        <td class="text-muted small">
                                            <div class="d-flex align-items-center gap-2">
                                                <?= renderAvatar($r['nama_petugas'], $r['foto_petugas'] ?? '', $baseUrl, 32) ?>
                                                <span><?= htmlspecialchars($r['nama_petugas']) ?></span>
                                            </div>
                                        </td>
                                        <td class="text-nowrap"><?= kg($r['total_berat']) ?></td>
                                        <td class="fw-bold text-success text-nowrap"><?= rp($r['total_nominal']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="4" class="text-end">Total</th>
                                    <th class="text-nowrap"><?= kg($statSetoran['berat']) ?></th>
                                    <th class="text-nowrap"><?= rp($statSetoran['nominal']) ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <?= lapPagination('ps', $pageSetor, $totalPageSetor, $totalSetor) ?>
        </div>
    </div>

    <!-- ======================== TAB: REKAP KATEGORI ======================== -->
    <div class="tab-pane fade" id="tab-kategori" role="tabpanel">
        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h6 class="mb-0"><i class="bi bi-tags me-2 text-primary"></i>Rekapitulasi per Kategori Sampah</h6>
                <a href="<?= lapCsvLink('kategori') ?>" class="btn btn-sm btn-outline-success no-print">
                    <i class="bi bi-download me-1"></i>Export CSV
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($rekapKategori)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox" style="font-size:40px;"></i>
                        <p class="mt-2">Belum ada data setoran pada periode ini.</p>
                    </div>
                <?php else: ?>
                    <?php $maxBerat = max(array_column($rekapKategori, 'total_berat')) ?: 1; ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" style="width:50px;">No</th>
                                    <th>Kategori</th>
                                    <th style="width:30%;">Proporsi</th>
                                    <th class="text-end">Berat Total</th>
                                    <th class="text-center">Jumlah Item</th>
                                    <th class="text-end">Nilai Setoran</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rekapKategori as $i => $r): ?>
                                    <tr>
                                        <td class="text-center"><?= $i + 1 ?></td>
                                        <td><strong><?= htmlspecialchars($r['nama_kategori']) ?></strong></td>
                                        <td>
                                            <div class="progress" style="height:8px;">
                                                <div class="progress-bar bg-success" style="width: <?= ($r['total_berat'] / $maxBerat) * 100 ?>%"></div>
                                            </div>
                                        </td>
                                        <td class="text-end text-nowrap"><?= kg($r['total_berat']) ?></td>
                                        <td class="text-center"><?= number_format($r['jumlah_item']) ?> item</td>
                                        <td class="text-end fw-bold text-success text-nowrap"><?= rp($r['total_nilai']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ======================== TAB: PENARIKAN ======================== -->
    <div class="tab-pane fade" id="tab-penarikan" role="tabpanel">
        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h6 class="mb-0"><i class="bi bi-cash-stack me-2 text-danger"></i>Pengajuan Penarikan Saldo</h6>
                <a href="<?= lapCsvLink('penarikan') ?>" class="btn btn-sm btn-outline-success no-print">
                    <i class="bi bi-download me-1"></i>Export CSV
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($penarikanList)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox" style="font-size:40px;"></i>
                        <p class="mt-2">Belum ada data penarikan pada periode ini.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" style="width:50px;">No</th>
                                    <th>Tanggal</th>
                                    <th>Nasabah</th>
                                    <th>Tipe</th>
                                    <th>Tujuan</th>
                                    <th>Nominal</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($penarikanList as $i => $r): ?>
                                    <tr>
                                        <td class="text-center"><?= $i + 1 ?></td>
                                        <td class="text-nowrap small"><?= fTgl($r['created_at']) ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <?= renderAvatar($r['nama_nasabah'], $r['foto_nasabah'] ?? '', $baseUrl, 32) ?>
                                                <span><?= htmlspecialchars($r['nama_nasabah']) ?></span>
                                            </div>
                                        </td>
                                        <td><span class="badge rounded-pill text-bg-primary"><?= tipePenarikan($r['tipe_penarikan']) ?></span></td>
                                        <td class="small text-muted"><?= htmlspecialchars($r['detail_tujuan']) ?></td>
                                        <td class="fw-bold text-nowrap"><?= rp($r['nominal']) ?></td>
                                        <td class="text-center"><?= badgeStatus($r['status']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <?= lapPagination('pt', $pageTarik, $totalPageTarik, $totalTarik) ?>
        </div>
    </div>

    <!-- ======================== TAB: PENJUALAN PENGEpul ======================== -->
    <div class="tab-pane fade" id="tab-pengepul" role="tabpanel">
        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h6 class="mb-0"><i class="bi bi-truck me-2 text-warning"></i>Penjualan ke Pengepul</h6>
                <a href="<?= lapCsvLink('penjualan') ?>" class="btn btn-sm btn-outline-success no-print">
                    <i class="bi bi-download me-1"></i>Export CSV
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($penjualanList)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox" style="font-size:40px;"></i>
                        <p class="mt-2">Belum ada data penjualan ke pengepul pada periode ini.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" style="width:50px;">No</th>
                                    <th>Tanggal</th>
                                    <th>Pengepul</th>
                                    <th>Kategori</th>
                                    <th>Berat</th>
                                    <th>Harga / kg</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($penjualanList as $i => $r): ?>
                                    <tr>
                                        <td class="text-center"><?= $i + 1 ?></td>
                                        <td class="text-nowrap small"><?= fTgl($r['created_at']) ?></td>
                                        <td><strong><?= htmlspecialchars($r['nama_pengepul']) ?></strong></td>
                                        <td><?= htmlspecialchars($r['nama_kategori']) ?></td>
                                        <td class="text-nowrap"><?= kg($r['total_berat']) ?></td>
                                        <td class="text-nowrap"><?= rp($r['harga_jual_per_kg']) ?></td>
                                        <td class="fw-bold text-success text-nowrap"><?= rp($r['total_pendapatan']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="4" class="text-end">Total</th>
                                    <th class="text-nowrap"><?= kg($statPengepul['berat']) ?></th>
                                    <th></th>
                                    <th class="text-nowrap"><?= rp($statPengepul['pendapatan']) ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <?= lapPagination('pj', $pageJual, $totalPageJual, $totalJual) ?>
        </div>
    </div>

    <!-- ======================== TAB: JEMPUTAN SAMPAH ======================== -->
    <div class="tab-pane fade" id="tab-jemputan" role="tabpanel">
        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h6 class="mb-0"><i class="bi bi-calendar-check me-2 text-info"></i>Request Jemput Sampah</h6>
                <a href="<?= lapCsvLink('jemputan') ?>" class="btn btn-sm btn-outline-success no-print">
                    <i class="bi bi-download me-1"></i>Export CSV
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($jemputanList)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox" style="font-size:40px;"></i>
                        <p class="mt-2">Belum ada data jemputan pada periode ini.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" style="width:50px;">No</th>
                                    <th>Tanggal</th>
                                    <th>Nasabah</th>
                                    <th>Alamat Jemput</th>
                                    <th>Est. Berat</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($jemputanList as $i => $r): ?>
                                    <tr>
                                        <td class="text-center"><?= $i + 1 ?></td>
                                            <td class="text-nowrap small"><?= fTgl($r['created_at']) ?></td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <?= renderAvatar($r['nama_nasabah'], $r['foto_nasabah'] ?? '', $baseUrl, 32) ?>
                                                    <span><?= htmlspecialchars($r['nama_nasabah']) ?></span>
                                                </div>
                                            </td>
                                            <td class="text-muted small" style="max-width:260px;"><?= htmlspecialchars($r['alamat_jemput']) ?></td>
                                        <td class="text-nowrap"><?= $r['estimasi_berat'] !== null ? kg($r['estimasi_berat']) : '-' ?></td>
                                        <td class="text-center"><?= badgeStatus($r['status']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <?= lapPagination('pm', $pageJemput, $totalPageJemput, $totalJemput) ?>
        </div>
    </div>

</div><!-- /.tab-content -->

</div><!-- /.report-area -->

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
        width: 52px;
        height: 52px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        color: #fff;
        flex-shrink: 0;
    }
    .stat-info  { display: flex; flex-direction: column; }
.stat-label { font-size: 13px; color: var(--text-muted); font-weight: 500; }
        .stat-value { font-size: 20px; font-weight: 700; color: var(--text-strong); }
        .stat-sub   { font-size: 12px; color: var(--text-soft); }

    .filter-card {
        border: 1px dashed #cbd5e1;
        background: #f8fafc;
        box-shadow: none;
    }

    .report-tabs .nav-link {
        color: var(--text-body);
        font-weight: 500;
    }
    .report-tabs .nav-link.active {
        color: var(--eco-700);
        font-weight: 700;
        border-bottom-color: #2e7d32;
    }

    @media (max-width: 767px) {
        .stat-card { padding: 16px; gap: 12px; }
        .stat-icon { width: 44px; height: 44px; font-size: 18px; border-radius: 12px; }
        .stat-label { font-size: 12px; }
        .stat-value { font-size: 17px; }
    }

    /* ======================== CETAK ======================== */
    @media print {
        .sidebar, .top-navbar, .sidebar-overlay, .main-footer,
        .no-print, .report-tabs { display: none !important; }
        .main-content { margin-left: 0 !important; padding: 0 !important; }
        body { background: #fff !important; }
        .stat-card { box-shadow: none; border: 1px solid #e2e8f0; }
        .tab-pane { display: block !important; opacity: 1 !important; visibility: visible !important; }
        .tab-content > .tab-pane { display: block !important; }
        .card { border: 1px solid #d1d5db; break-inside: avoid; }
    }
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>