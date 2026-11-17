<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<?php
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/pickup_helper.php';

pastikanTabelAudit($pdo);

/* ==================== FILTER ==================== */
$cariAksi = trim($_GET['aksi'] ?? '');
$cariTabel = trim($_GET['tabel'] ?? '');
$cariUser = (int) ($_GET['user'] ?? 0);
$tglAwal  = trim($_GET['tgl_awal'] ?? '');
$tglAkhir = trim($_GET['tgl_akhir'] ?? '');

function validTglAudit($d) {
    if (!$d) return '';
    $o = DateTime::createFromFormat('Y-m-d', $d);
    return ($o && $o->format('Y-m-d') === $d) ? $d : '';
}
$tglAwal  = validTglAudit($tglAwal);
$tglAkhir = validTglAudit($tglAkhir);
if ($tglAwal !== '' && $tglAkhir !== '' && $tglAwal > $tglAkhir) {
    [$tglAwal, $tglAkhir] = [$tglAkhir, $tglAwal];
}

$where = [];
$params = [];
if ($cariAksi !== '') {
    $where[] = "a.aksi LIKE :aksi";
    $params[':aksi'] = '%' . $cariAksi . '%';
}
if ($cariTabel !== '') {
    $where[] = "a.tabel = :tabel";
    $params[':tabel'] = $cariTabel;
}
if ($cariUser > 0) {
    $where[] = "a.user_id = :uid";
    $params[':uid'] = $cariUser;
}
if ($tglAwal !== '') {
    $where[] = "a.created_at >= :tgla";
    $params[':tgla'] = $tglAwal . ' 00:00:00';
}
if ($tglAkhir !== '') {
    $where[] = "a.created_at <= :tglb";
    $params[':tglb'] = $tglAkhir . ' 23:59:59';
}
$whereSql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

/* ==================== PAGINATION ==================== */
$perPage = (int) ($_GET['per_page'] ?? 20);
if (!in_array($perPage, [20, 50, 100], true)) $perPage = 20;
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM audit_log a $whereSql");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();
$totalPage = max(1, (int) ceil($total / $perPage));
if ($page > $totalPage) $page = $totalPage;
$offset = ($page - 1) * $perPage;

/* ==================== DAFTAR LOG ==================== */
$stmt = $pdo->prepare("
    SELECT a.*, u.nama AS nama_user, u.role AS role_user
    FROM audit_log a
    LEFT JOIN users u ON u.id = a.user_id
    $whereSql
    ORDER BY a.created_at DESC, a.id DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$logList = $stmt->fetchAll();

/* Daftar user pelaku untuk dropdown filter */
$userOptions = $pdo->query("
    SELECT DISTINCT a.user_id, u.nama, u.role
    FROM audit_log a LEFT JOIN users u ON u.id = a.user_id
    WHERE a.user_id IS NOT NULL
    ORDER BY u.nama
")->fetchAll();

/* Daftar tabel yang pernah tercatat */
$tabelOptions = $pdo->query("SELECT DISTINCT tabel FROM audit_log ORDER BY tabel")->fetchAll();

/* Query string builder */
function qsAudit(array $ganti = []): string {
    $q = array_merge($_GET, $ganti);
    unset($q['page']);
    $q = array_filter($q, static fn($v) => $v !== '' && $v !== null);
    return 'audit_log.php' . (count($q) ? '?' . http_build_query($q) : '');
}

function warnaAksi(string $a): string {
    if (str_starts_with($a, 'INSERT')) return 'text-bg-success';
    if (str_starts_with($a, 'UPDATE')) return 'text-bg-primary';
    if (str_starts_with($a, 'HAPUS') || str_starts_with($a, 'DELETE')) return 'text-bg-danger';
    if (str_starts_with($a, 'PAYOUT')) return 'text-bg-warning';
    return 'text-bg-secondary';
}
?>

<!-- ======================== HEADER ======================== -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <div class="text-muted small text-uppercase fw-semibold">Jejak Aktivitas</div>
        <h4 class="mb-0 fw-bold">Audit Log Sistem</h4>
    </div>
    <a href="../api/export_audit.php?<?= http_build_query(array_filter($_GET, static fn($v) => $v !== '')) ?>"
       class="btn btn-outline-success btn-sm">
        <i class="bi bi-download me-1"></i>Export CSV
    </a>
</div>

<!-- ======================== FILTER ======================== -->
<div class="card mb-4">
    <div class="card-body">
        <form class="row g-3 align-items-end" method="get" action="audit_log.php">
            <div class="col-6 col-lg-3">
                <label class="form-label small text-muted mb-1">Aksi (mis. HAPUS)</label>
                <input type="text" class="form-control form-control-sm" name="aksi" value="<?= htmlspecialchars($cariAksi) ?>" placeholder="INSERT / UPDATE / HAPUS...">
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label small text-muted mb-1">Tabel</label>
                <select class="form-select form-select-sm" name="tabel">
                    <option value="">Semua tabel</option>
                    <?php foreach ($tabelOptions as $t): ?>
                        <option value="<?= htmlspecialchars($t['tabel']) ?>" <?= $cariTabel === $t['tabel'] ? 'selected' : '' ?>><?= htmlspecialchars($t['tabel']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label small text-muted mb-1">Pelaku</label>
                <select class="form-select form-select-sm" name="user">
                    <option value="0">Semua user</option>
                    <?php foreach ($userOptions as $u): ?>
                        <option value="<?= (int) $u['user_id'] ?>" <?= $cariUser === (int) $u['user_id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['nama'] ?? '#' . $u['user_id']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label small text-muted mb-1">Dari Tanggal</label>
                <input type="date" class="form-control form-control-sm" name="tgl_awal" value="<?= htmlspecialchars($tglAwal) ?>">
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label small text-muted mb-1">Sampai Tanggal</label>
                <input type="date" class="form-control form-control-sm" name="tgl_akhir" value="<?= htmlspecialchars($tglAkhir) ?>">
            </div>
            <div class="col-12 col-lg-1 d-flex gap-2">
                <button type="submit" class="btn btn-success btn-sm flex-grow-1"><i class="bi bi-funnel"></i></button>
                <a href="audit_log.php" class="btn btn-outline-secondary btn-sm" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- ======================== TABEL LOG ======================== -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-journal-text me-2 text-success"></i>Riwayat Aktivitas</h6>
        <small class="text-muted">Total <?= number_format($total) ?> entri</small>
    </div>
    <div class="card-body p-0">
        <?php if (empty($logList)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox" style="font-size:40px;"></i>
                <p class="mt-2">Belum ada aktivitas tercatat.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width:50px;">No</th>
                            <th>Waktu</th>
                            <th>Pelaku</th>
                            <th>Aksi</th>
                            <th>Objek</th>
                            <th style="min-width:260px;">Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logList as $i => $r): ?>
                            <tr>
                                <td class="text-center"><?= $offset + $i + 1 ?></td>
                                <td class="text-nowrap small">
                                    <div><?= date('d/m/Y', strtotime($r['created_at'])) ?></div>
                                    <small class="text-muted"><?= date('H:i:s', strtotime($r['created_at'])) ?></small>
                                </td>
                                <td>
                                    <?php if ($r['nama_user']): ?>
                                        <div class="fw-semibold"><?= htmlspecialchars($r['nama_user']) ?></div>
                                        <small class="text-muted text-capitalize"><?= htmlspecialchars($r['role_user']) ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">Sistem (#<?= (int) $r['user_id'] ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge rounded-pill <?= warnaAksi($r['aksi']) ?>"><?= htmlspecialchars($r['aksi']) ?></span></td>
                                <td>
                                    <div class="small"><?= htmlspecialchars($r['tabel']) ?></div>
                                    <small class="text-muted"><?= $r['record_id'] !== null ? '#' . (int) $r['record_id'] : '-' ?></small>
                                </td>
                                <td class="small" style="max-width:360px; white-space:pre-wrap;"><?= htmlspecialchars($r['detail'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ======================== PAGINATION ======================== -->
<?php if ($totalPage > 1): ?>
    <nav class="mt-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="text-muted small">
            Halaman <strong><?= $page ?></strong> dari <strong><?= $totalPage ?></strong>
        </div>
        <div class="d-flex gap-1 align-items-center">
            <select class="form-select form-select-sm w-auto" onchange="window.location.href=this.value">
                <?php foreach ([20, 50, 100] as $n): ?>
                    <option value="<?= qsAudit(['per_page' => $n]) ?>" <?= $perPage === $n ? 'selected' : '' ?>><?= $n ?> / halaman</option>
                <?php endforeach; ?>
            </select>
            <a href="<?= qsAudit(['page' => max(1, $page - 1)]) ?>" class="btn btn-outline-success btn-sm <?= $page <= 1 ? 'disabled' : '' ?>"><i class="bi bi-chevron-left"></i></a>
            <span class="btn btn-sm btn-success disabled"><?= $page ?> / <?= $totalPage ?></span>
            <a href="<?= qsAudit(['page' => min($totalPage, $page + 1)]) ?>" class="btn btn-outline-success btn-sm <?= $page >= $totalPage ? 'disabled' : '' ?>"><i class="bi bi-chevron-right"></i></a>
        </div>
    </nav>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
