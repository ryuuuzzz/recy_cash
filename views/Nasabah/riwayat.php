<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<?php
require_once __DIR__ . '/../../config/koneksi.php';

// Proteksi session: pastikan user login sebagai Nasabah
$role = $_SESSION['role'] ?? null;
if ($role === null) {
    header('Location: ' . $baseUrl . '/auth/login.php');
    exit;
}
if ($role !== 'nasabah') {
    $_SESSION['error'] = "Anda tidak memiliki akses ke halaman tersebut.";
    header('Location: ' . $baseUrl . '/auth/login.php');
    exit;
}

$userId = (int) $_SESSION['id_user'];

// ===================== FILTER TANGGAL =====================
$tglAwal  = trim($_GET['tgl_awal'] ?? '');
$tglAkhir = trim($_GET['tgl_akhir'] ?? '');
$adaFilter = ($tglAwal !== '' && $tglAkhir !== '');

$filterSql = '';
$params = [':uid' => $userId];
if ($adaFilter) {
    $filterSql = " AND DATE(dt.created_at) BETWEEN :tgla AND :tglb ";
    $params[':tgla'] = $tglAwal;
    $params[':tglb'] = $tglAkhir;
}

// ===================== DATA SETORAN =====================
$stmt = $pdo->prepare("
    SELECT dt.id, dt.kode_transaksi, dt.total_nominal, dt.total_berat, dt.created_at
    FROM deposit_transactions dt
    WHERE dt.nasabah_id = :uid " . $filterSql . "
    ORDER BY dt.created_at DESC, dt.id DESC
");
$stmt->execute($params);
$setoranList = $stmt->fetchAll();

// Detail item setoran (jenis sampah, berat, harga per kg saat transaksi)
$detailMap = [];
if (!empty($setoranList)) {
    $ids = array_column($setoranList, 'id');
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT dd.transaction_id, dd.berat_kg, dd.harga_per_kg, dd.subtotal, cs.nama_kategori
        FROM deposit_details dd
        JOIN categories_sampah cs ON cs.id = dd.category_id
        WHERE dd.transaction_id IN ($ph)
        ORDER BY dd.id ASC
    ");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $d) {
        $detailMap[$d['transaction_id']][] = $d;
    }
}

// ===================== DATA PENARIKAN =====================
$filterSqlW = '';
$paramsW = [':uid' => $userId];
if ($adaFilter) {
    $filterSqlW = " AND DATE(w.created_at) BETWEEN :tgla AND :tglb ";
    $paramsW[':tgla'] = $tglAwal;
    $paramsW[':tglb'] = $tglAkhir;
}

$stmt = $pdo->prepare("
    SELECT w.id, w.tipe_penarikan, w.nominal, w.detail_tujuan, w.status,
           w.alasan_penolakan, w.created_at
    FROM withdrawals w
    WHERE w.nasabah_id = :uid " . $filterSqlW . "
    ORDER BY w.created_at DESC, w.id DESC
");
$stmt->execute($paramsW);
$tarikList = $stmt->fetchAll();

// ===================== RINGKASAN (METRIC CARDS) =====================
$totalBerat = 0;
$totalMasuk = 0;
foreach ($setoranList as $s) {
    $totalBerat += (float) $s['total_berat'];
    $totalMasuk += (float) $s['total_nominal'];
}

$totalDitarik = 0;
foreach ($tarikList as $w) {
    if (in_array($w['status'], ['disetujui', 'selesai'], true)) {
        $totalDitarik += (float) $w['nominal'];
    }
}

// ===================== HELPER =====================
function badgeStatusRiwayat(string $status): string
{
    switch ($status) {
        case 'pending':
            return '<span class="badge rounded-pill text-bg-warning"><i class="bi bi-clock me-1"></i>Pending</span>';
        case 'disetujui':
        case 'selesai':
            return '<span class="badge rounded-pill text-bg-success"><i class="bi bi-check-circle me-1"></i>' .
                ($status === 'selesai' ? 'Selesai' : 'Disetujui') . '</span>';
        case 'ditolak':
            return '<span class="badge rounded-pill text-bg-danger"><i class="bi bi-x-circle me-1"></i>Ditolak</span>';
        default:
            return '<span class="badge rounded-pill bg-secondary">' . htmlspecialchars($status) . '</span>';
    }
}

function labelTipePenarikan(string $tipe): string
{
    switch ($tipe) {
        case 'e-wallet':       return 'E-Wallet';
        case 'bank_transfer':  return 'Transfer Bank';
        case 'voucher':        return 'Voucher';
        case 'tunai':          return 'Tunai';
        default:               return ucfirst($tipe);
    }
}

function iconTipePenarikan(string $tipe): string
{
    switch ($tipe) {
        case 'e-wallet':      return 'bi-wallet2';
        case 'bank_transfer': return 'bi-bank';
        case 'voucher':       return 'bi-ticket-perforated';
        case 'tunai':         return 'bi-cash';
        default:              return 'bi-arrow-up-right';
    }
}
?>

<?php if (!empty($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($_SESSION['error']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<?php if (!empty($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($_SESSION['success']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<!-- ======================== JUDUL ======================== -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h4 class="mb-1 fw-bold"><i class="bi bi-clock-history me-2"></i>Riwayat Transaksi</h4>
        <small class="text-muted">Rekam jejak digital setoran sampah & penarikan saldo Anda.</small>
    </div>
    <button type="button" class="btn btn-outline-primary btn-sm no-print" onclick="window.print()">
        <i class="bi bi-printer me-1"></i>Cetak / Download PDF
    </button>
</div>

<!-- ======================== METRIC CARDS ======================== -->
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-4">
        <div class="card stat-card">
            <div class="stat-icon bg-info"><i class="bi bi-recycle"></i></div>
            <div class="stat-info">
                <span class="stat-label">Total Berat Sampah Disetor</span>
                <span class="stat-value"><?= number_format($totalBerat, 2) ?> Kg</span>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="card stat-card">
            <div class="stat-icon bg-success"><i class="bi bi-arrow-down-circle"></i></div>
            <div class="stat-info">
                <span class="stat-label">Total Pendapatan Masuk</span>
                <span class="stat-value">Rp <?= number_format($totalMasuk, 0, ',', '.') ?></span>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="card stat-card">
            <div class="stat-icon bg-warning"><i class="bi bi-arrow-up-circle"></i></div>
            <div class="stat-info">
                <span class="stat-label">Total Saldo Ditarik</span>
                <span class="stat-value">Rp <?= number_format($totalDitarik, 0, ',', '.') ?></span>
            </div>
        </div>
    </div>
</div>

<!-- ======================== FILTER TANGGAL ======================== -->
<div class="card mb-4 no-print">
    <div class="card-body py-3">
        <form method="get" action="<?= htmlspecialchars(basename($_SERVER['SCRIPT_NAME'])) ?>" class="row g-2 align-items-end">
            <div class="col-sm-4 col-lg-3">
                <label class="form-label small fw-semibold">Dari Tanggal</label>
                <input type="date" name="tgl_awal" class="form-control form-control-sm" value="<?= htmlspecialchars($tglAwal) ?>">
            </div>
            <div class="col-sm-4 col-lg-3">
                <label class="form-label small fw-semibold">Sampai Tanggal</label>
                <input type="date" name="tgl_akhir" class="form-control form-control-sm" value="<?= htmlspecialchars($tglAkhir) ?>">
            </div>
            <div class="col-sm-4 col-lg-3 d-flex gap-2">
                <button type="submit" class="btn btn-success btn-sm">
                    <i class="bi bi-funnel me-1"></i>Terapkan Filter
                </button>
                <a href="<?= htmlspecialchars(basename($_SERVER['SCRIPT_NAME'])) ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                </a>
            </div>
        </form>
        <?php if ($adaFilter): ?>
            <div class="small text-muted mt-2">
                <i class="bi bi-calendar-event me-1"></i>
                Menampilkan riwayat
                <strong><?= date('d/m/Y', strtotime($tglAwal)) ?></strong> —
                <strong><?= date('d/m/Y', strtotime($tglAkhir)) ?></strong>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ======================== TABS ======================== -->
<ul class="nav nav-tabs report-tabs mb-0" id="riwayatTab" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="tab-setor" data-bs-toggle="tab"
                data-bs-target="#pane-setor" type="button" role="tab">
            <i class="bi bi-box-seam me-1"></i>Setor Sampah
            <span class="badge text-bg-success rounded-pill ms-1"><?= count($setoranList) ?></span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-tarik" data-bs-toggle="tab"
                data-bs-target="#pane-tarik" type="button" role="tab">
            <i class="bi bi-wallet2 me-1"></i>Penarikan Saldo
            <span class="badge text-bg-primary rounded-pill ms-1"><?= count($tarikList) ?></span>
        </button>
    </li>
</ul>

<div class="tab-content border border-top-0 rounded-bottom mb-4">
    <!-- ================= TAB: SETOR SAMPAH ================= -->
    <div class="tab-pane fade show active" id="pane-setor" role="tabpanel">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Kode</th>
                        <th>Tanggal</th>
                        <th>Jenis / Rincian Sampah</th>
                        <th class="text-end">Berat (kg)</th>
                        <th class="text-end">Nominal</th>
                        <th class="text-center no-print">Detail</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($setoranList)): ?>
                        <tr>
                            <td colspan="6">
                                <div class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox" style="font-size:40px;"></i>
                                    <p class="mt-2">Belum ada riwayat penyetoran sampah.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($setoranList as $row): ?>
                            <?php $details = $detailMap[$row['id']] ?? []; ?>
                            <tr>
                                <td>
                                    <span class="badge text-bg-success">
                                        <?= htmlspecialchars($row['kode_transaksi']) ?>
                                    </span>
                                </td>
                                <td class="text-nowrap">
                                    <?= date('d M Y', strtotime($row['created_at'])) ?><br>
                                    <small class="text-muted"><?= date('H:i', strtotime($row['created_at'])) ?> WIB</small>
                                </td>
                                <td>
                                    <?php if (empty($details)): ?>
                                        <span class="text-muted">-</span>
                                    <?php else: ?>
                                        <?php foreach ($details as $d): ?>
                                            <div class="small">
                                                <?= htmlspecialchars($d['nama_kategori']) ?>
                                                <span class="text-muted">
                                                    (<?= number_format($d['berat_kg'], 2) ?> kg ×
                                                    Rp <?= number_format($d['harga_per_kg'], 0, ',', '.') ?>)
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-nowrap"><?= number_format($row['total_berat'], 2) ?></td>
                                <td class="text-end text-nowrap fw-semibold">
                                    Rp <?= number_format($row['total_nominal'], 0, ',', '.') ?>
                                </td>
                                <td class="text-center no-print">
                                    <button type="button" class="btn btn-outline-success btn-sm"
                                            data-bs-toggle="modal" data-bs-target="#modalDetail"
                                            data-id="<?= (int) $row['id'] ?>"
                                            data-kode="<?= htmlspecialchars($row['kode_transaksi']) ?>"
                                            data-tanggal="<?= date('d/m/Y H:i', strtotime($row['created_at'])) ?>"
                                            data-berat="<?= number_format($row['total_berat'], 2) ?>"
                                            data-nominal="<?= number_format($row['total_nominal'], 0, ',', '.') ?>">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($setoranList)): ?>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="3">Total (<?= count($setoranList) ?> transaksi)</td>
                            <td class="text-end"><?= number_format($totalBerat, 2) ?> kg</td>
                            <td class="text-end">Rp <?= number_format($totalMasuk, 0, ',', '.') ?></td>
                            <td class="no-print"></td>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <!-- ================= TAB: PENARIKAN SALDO ================= -->
    <div class="tab-pane fade" id="pane-tarik" role="tabpanel">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Kode</th>
                        <th>Tanggal</th>
                        <th>Metode</th>
                        <th>Tujuan</th>
                        <th class="text-end">Nominal</th>
                        <th class="text-center">Status</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tarikList)): ?>
                        <tr>
                            <td colspan="7">
                                <div class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox" style="font-size:40px;"></i>
                                    <p class="mt-2">Belum ada riwayat penarikan saldo.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tarikList as $row): ?>
                            <tr>
                                <td>
                                    <span class="badge text-bg-primary">TNK-<?= str_pad((string) $row['id'], 4, '0', STR_PAD_LEFT) ?></span>
                                </td>
                                <td class="text-nowrap">
                                    <?= date('d M Y', strtotime($row['created_at'])) ?><br>
                                    <small class="text-muted"><?= date('H:i', strtotime($row['created_at'])) ?> WIB</small>
                                </td>
                                <td class="text-nowrap">
                                    <i class="bi <?= iconTipePenarikan($row['tipe_penarikan']) ?> me-1"></i>
                                    <?= labelTipePenarikan($row['tipe_penarikan']) ?>
                                </td>
                                <td><?= htmlspecialchars($row['detail_tujuan']) ?></td>
                                <td class="text-end text-nowrap fw-semibold">
                                    <?php if ($row['tipe_penarikan'] === 'voucher'): ?>
                                        <?= number_format($row['nominal'], 0, ',', '.') ?> Poin
                                    <?php else: ?>
                                        Rp <?= number_format($row['nominal'], 0, ',', '.') ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center text-nowrap"><?= badgeStatusRiwayat($row['status']) ?></td>
                                <td>
                                    <?php if ($row['status'] === 'ditolak' && !empty($row['alasan_penolakan'])): ?>
                                        <span class="small text-danger">
                                            <i class="bi bi-exclamation-circle me-1"></i>
                                            <?= htmlspecialchars($row['alasan_penolakan']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ======================== MODAL DETAIL TRANSAKSI ======================== -->
<div class="modal fade" id="modalDetail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h6 class="modal-title">
                    <i class="bi bi-receipt me-2"></i>Detail Transaksi Setoran
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3 g-2">
                    <div class="col-6">
                        <div class="small text-muted">Kode Transaksi</div>
                        <div class="fw-semibold" id="mdKode">-</div>
                    </div>
                    <div class="col-6">
                        <div class="small text-muted">Tanggal</div>
                        <div class="fw-semibold" id="mdTanggal">-</div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Jenis Sampah</th>
                                <th class="text-end">Berat</th>
                                <th class="text-end">Harga/kg</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody id="mdDetailBody">
                            <tr><td colspan="4" class="text-center text-muted">Memuat detail...</td></tr>
                        </tbody>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td colspan="1">Total</td>
                                <td class="text-end" id="mdTotalBerat">-</td>
                                <td></td>
                                <td class="text-end" id="mdTotalNominal">-</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="alert alert-light border small mt-3 mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    Harga per kg dicatat sesuai harga berlaku pada saat transaksi terjadi.
                </div>
            </div>
        </div>
    </div>
</div>

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
    .stat-info { display: flex; flex-direction: column; }
    .stat-label { font-size: 13px; color: var(--text-muted); font-weight: 500; }
    .stat-value { font-size: 20px; font-weight: 700; color: var(--text-strong); }

    .report-tabs .nav-link {
        color: var(--text-body);
        font-weight: 600;
        border: none;
        padding: 10px 18px;
        border-bottom: 2px solid transparent;
        border-radius: 0;
    }
    .report-tabs .nav-link.active {
        color: var(--eco-700);
        background: transparent;
        border-bottom: 2px solid #2e7d32;
    }
    .tab-content { box-shadow: 0 2px 10px rgba(0,0,0,0.05); }

    @media (max-width: 767px) {
        .stat-card { padding: 16px; gap: 12px; }
        .stat-icon { width: 44px; height: 44px; font-size: 18px; border-radius: 12px; }
        .stat-label { font-size: 12px; }
        .stat-value { font-size: 17px; }
    }

    /* ==== CETAK / PDF ==== */
    @media print {
        .sidebar, .top-navbar, .sidebar-overlay, .main-footer, .no-print, .report-tabs { display: none !important; }
        .main-content { margin: 0 !important; padding: 0 !important; }
        .tab-content { box-shadow: none !important; border: none !important; }
        .tab-pane { display: block !important; }
        .tab-pane:not(.active) { display: none !important; }
        body { background: #fff !important; }
        .card, .stat-card { box-shadow: none !important; border: 1px solid #dee2e6 !important; }
    }
</style>

<script>
(function () {
    // Data detail transaksi per id (di-embed dari PHP)
    var DETAIL_MAP = <?= json_encode($detailMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    var modal = document.getElementById('modalDetail');
    if (!modal) return;

    modal.addEventListener('show.bs.modal', function (event) {
        var btn = event.relatedTarget;
        if (!btn) return;

        var id = btn.getAttribute('data-id');

        document.getElementById('mdKode').textContent = btn.getAttribute('data-kode') || '-';
        document.getElementById('mdTanggal').textContent = btn.getAttribute('data-tanggal') || '-';
        document.getElementById('mdTotalBerat').textContent = (btn.getAttribute('data-berat') || '0') + ' kg';
        document.getElementById('mdTotalNominal').textContent = 'Rp ' + (btn.getAttribute('data-nominal') || '0');

        var body = document.getElementById('mdDetailBody');
        var rows = DETAIL_MAP[id] || [];

        if (!rows.length) {
            body.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Detail tidak tersedia.</td></tr>';
            return;
        }

        var html = '';
        rows.forEach(function (d) {
            html += '<tr>' +
                '<td>' + d.nama_kategori + '</td>' +
                '<td class="text-end">' + Number(d.berat_kg).toLocaleString('id-ID', {minimumFractionDigits: 2}) + ' kg</td>' +
                '<td class="text-end">Rp ' + Number(d.harga_per_kg).toLocaleString('id-ID') + '</td>' +
                '<td class="text-end">Rp ' + Number(d.subtotal).toLocaleString('id-ID') + '</td>' +
                '</tr>';
        });
        body.innerHTML = html;
    });
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
