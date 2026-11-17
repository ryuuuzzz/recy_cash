<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<?php
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/pickup_helper.php';
require_once __DIR__ . '/../../includes/notifikasi.php';

$adminId = (int) ($_SESSION['id_user'] ?? 0);

pastikanTabelStok($pdo);

/* ==================== AKSI: CATAT MUTASI MANUAL ==================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_mutasi'])) {
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $tipe       = $_POST['tipe'] ?? '';
    $berat      = (float) str_replace(',', '.', trim($_POST['berat'] ?? ''));
    $ket        = trim($_POST['keterangan'] ?? '');

    if ($categoryId <= 0 || !in_array($tipe, ['masuk', 'keluar'], true) || $berat <= 0) {
        $_SESSION['error'] = 'Kategori, tipe mutasi, dan berat (> 0) wajib diisi dengan benar.';
    } else {
        $ok = stokCatat($pdo, $categoryId, $tipe, $berat, $ket !== '' ? $ket : 'Penyesuaian manual', 'MANUAL', $adminId);
        if ($ok[0]) {
            auditCatat($pdo, $adminId, $tipe === 'masuk' ? 'INSERT_MUTASI_MASUK' : 'INSERT_MUTASI_KELUAR', 'stok_mutasi', $categoryId,
                "Berat {$berat} kg. Keterangan: " . ($ket !== '' ? $ket : '(manual)'));
            $_SESSION['success'] = 'Mutasi stok berhasil dicatat. Stok kini ' . number_format($ok[1], 2, ',', '.') . ' kg.';
        } else {
            $_SESSION['error'] = 'Gagal mencatat mutasi. Untuk keluar: pastikan berat tidak melebihi stok tersedia (' . number_format($ok[1], 2, ',', '.') . ' kg).';
        }
    }
    header('Location: gudang.php');
    exit;
}

/* ==================== DATA STOK ==================== */
$stokList = $pdo->query("
    SELECT sg.stok_kg, sg.updated_at, cs.id, cs.nama_kategori, cs.satuan
    FROM categories_sampah cs
    LEFT JOIN stok_gudang sg ON sg.category_id = cs.id
    ORDER BY cs.nama_kategori ASC
")->fetchAll();

/* ==================== FILTER & PAGINATION MUTASI ==================== */
$fKategori = (int) ($_GET['kategori'] ?? 0);
$fTipe     = $_GET['tipe'] ?? '';

$where = [];
$params = [];
if ($fKategori > 0) { $where[] = 'm.category_id = :cid'; $params[':cid'] = $fKategori; }
if (in_array($fTipe, ['masuk', 'keluar'], true)) { $where[] = 'm.tipe = :tipe'; $params[':tipe'] = $fTipe; }
$whereSql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$perPage = (int) ($_GET['per_page'] ?? 15);
if (!in_array($perPage, [15, 30, 50], true)) $perPage = 15;
$page = max(1, (int) ($_GET['page'] ?? 1));

$stmt = $pdo->prepare("SELECT COUNT(*) FROM stok_mutasi m $whereSql");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();
$totalPage = max(1, (int) ceil($total / $perPage));
if ($page > $totalPage) $page = $totalPage;
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("
    SELECT m.*, cs.nama_kategori, u.nama AS nama_user
    FROM stok_mutasi m
    INNER JOIN categories_sampah cs ON cs.id = m.category_id
    LEFT JOIN users u ON u.id = m.user_id
    $whereSql
    ORDER BY m.created_at DESC, m.id DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$mutasiList = $stmt->fetchAll();

function qsGudang(array $ganti = []): string {
    $q = array_merge($_GET, $ganti);
    unset($q['page']);
    $q = array_filter($q, static fn($v) => $v !== '' && $v !== null);
    return 'gudang.php' . (count($q) ? '?' . http_build_query($q) : '');
}

$totalMasuk = 0.0;
$totalKeluar = 0.0;
$stmt = $pdo->query("SELECT
    COALESCE(SUM(CASE WHEN tipe='masuk' THEN berat_kg END),0) AS masuk,
    COALESCE(SUM(CASE WHEN tipe='keluar' THEN berat_kg END),0) AS keluar
    FROM stok_mutasi");
$rekap = $stmt->fetch();
$totalMasuk = (float) $rekap['masuk'];
$totalKeluar = (float) $rekap['keluar'];
?>

<!-- ======================== HEADER ======================== -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <div class="text-muted small text-uppercase fw-semibold">Manajemen Gudang</div>
        <h4 class="mb-0 fw-bold">Stok Sampah per Kategori</h4>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalMutasi">
            <i class="bi bi-plus-slash-minus me-1"></i>Catat Mutasi
        </button>
        <a href="../api/export_gudang.php?<?= http_build_query(array_filter($_GET, static fn($v) => $v !== '')) ?>" class="btn btn-outline-success btn-sm">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
    </div>
</div>

<!-- ======================== STATISTIK ======================== -->
<div class="row g-4 mb-4">
    <div class="col-sm-4">
        <div class="card stat-card">
            <div class="stat-icon bg-success"><i class="bi bi-boxes"></i></div>
            <div class="stat-info">
                <span class="stat-label">Total Stok Tersedia</span>
                <span class="stat-value"><?= number_format(array_sum(array_column($stokList, 'stok_kg')), 2, ',', '.') ?> kg</span>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card stat-card">
            <div class="stat-icon bg-info"><i class="bi bi-box-arrow-in-down"></i></div>
            <div class="stat-info">
                <span class="stat-label">Total Masuk (mutasi)</span>
                <span class="stat-value"><?= number_format($totalMasuk, 2, ',', '.') ?> kg</span>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card stat-card">
            <div class="stat-icon bg-warning"><i class="bi bi-box-arrow-up"></i></div>
            <div class="stat-info">
                <span class="stat-label">Total Keluar (mutasi)</span>
                <span class="stat-value"><?= number_format($totalKeluar, 2, ',', '.') ?> kg</span>
            </div>
        </div>
    </div>
</div>

<!-- ======================== KARTU STOK ======================== -->
<div class="row g-3 mb-4">
    <?php foreach ($stokList as $s): ?>
        <div class="col-6 col-md-4 col-xl-3">
            <div class="card h-100">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="fw-semibold"><?= htmlspecialchars($s['nama_kategori']) ?></div>
                            <small class="text-muted">per <?= htmlspecialchars($s['satuan'] ?? 'kg') ?></small>
                        </div>
                        <span class="badge rounded-pill text-bg-<?= ($s['stok_kg'] ?? 0) > 0 ? 'success' : 'secondary' ?>">
                            <?= number_format((float) ($s['stok_kg'] ?? 0), 2, ',', '.') ?> kg
                        </span>
                    </div>
                    <?php if (!empty($s['updated_at'])): ?>
                        <small class="text-muted">Diperbarui <?= date('d/m H:i', strtotime($s['updated_at'])) ?></small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- ======================== FILTER MUTASI ======================== -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form class="row g-2 align-items-center" method="get" action="gudang.php">
            <div class="col-6 col-md-4">
                <select class="form-select form-select-sm" name="kategori" onchange="this.form.submit()">
                    <option value="0">Semua kategori</option>
                    <?php foreach ($stokList as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= $fKategori === (int) $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['nama_kategori']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <select class="form-select form-select-sm" name="tipe" onchange="this.form.submit()">
                    <option value="">Semua tipe</option>
                    <option value="masuk" <?= $fTipe === 'masuk' ? 'selected' : '' ?>>Masuk</option>
                    <option value="keluar" <?= $fTipe === 'keluar' ? 'selected' : '' ?>>Keluar</option>
                </select>
            </div>
        </form>
    </div>
</div>

<!-- ======================== TABEL MUTASI ======================== -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-arrow-left-right me-2 text-success"></i>Riwayat Mutasi Stok</h6>
        <small class="text-muted">Total <?= number_format($total) ?> mutasi</small>
    </div>
    <div class="card-body p-0">
        <?php if (empty($mutasiList)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox" style="font-size:40px;"></i>
                <p class="mt-2">Belum ada mutasi stok.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width:50px;">No</th>
                            <th>Waktu</th>
                            <th>Kategori</th>
                            <th class="text-center">Tipe</th>
                            <th class="text-end">Berat</th>
                            <th>Keterangan</th>
                            <th>Referensi</th>
                            <th>Petugas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mutasiList as $i => $m): ?>
                            <tr>
                                <td class="text-center"><?= $offset + $i + 1 ?></td>
                                <td class="text-nowrap small">
                                    <div><?= date('d/m/Y', strtotime($m['created_at'])) ?></div>
                                    <small class="text-muted"><?= date('H:i', strtotime($m['created_at'])) ?></small>
                                </td>
                                <td><?= htmlspecialchars($m['nama_kategori']) ?></td>
                                <td class="text-center">
                                    <span class="badge rounded-pill text-bg-<?= $m['tipe'] === 'masuk' ? 'success' : 'warning' ?>">
                                        <?= ucfirst($m['tipe']) ?>
                                    </span>
                                </td>
                                <td class="text-end text-nowrap fw-semibold"><?= number_format((float) $m['berat_kg'], 2, ',', '.') ?> kg</td>
                                <td class="small"><?= htmlspecialchars($m['keterangan'] ?? '-') ?></td>
                                <td class="small text-muted"><?= htmlspecialchars($m['referensi'] ?? '-') ?></td>
                                <td class="small"><?= htmlspecialchars($m['nama_user'] ?? ($m['user_id'] ? '#' . $m['user_id'] : 'Sistem')) ?></td>
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
        <div class="text-muted small">Halaman <strong><?= $page ?></strong> dari <strong><?= $totalPage ?></strong></div>
        <div class="d-flex gap-1 align-items-center">
            <select class="form-select form-select-sm w-auto" onchange="window.location.href=this.value">
                <?php foreach ([15, 30, 50] as $n): ?>
                    <option value="<?= qsGudang(['per_page' => $n]) ?>" <?= $perPage === $n ? 'selected' : '' ?>><?= $n ?> / halaman</option>
                <?php endforeach; ?>
            </select>
            <a href="<?= qsGudang(['page' => max(1, $page - 1)]) ?>" class="btn btn-outline-success btn-sm <?= $page <= 1 ? 'disabled' : '' ?>"><i class="bi bi-chevron-left"></i></a>
            <span class="btn btn-sm btn-success disabled"><?= $page ?> / <?= $totalPage ?></span>
            <a href="<?= qsGudang(['page' => min($totalPage, $page + 1)]) ?>" class="btn btn-outline-success btn-sm <?= $page >= $totalPage ? 'disabled' : '' ?>"><i class="bi bi-chevron-right"></i></a>
        </div>
    </nav>
<?php endif; ?>

<!-- ======================== MODAL CATAT MUTASI ======================== -->
<div class="modal fade" id="modalMutasi" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" action="gudang.php" method="POST">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-plus-slash-minus me-2"></i>Catat Mutasi Stok Manual</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Kategori</label>
                    <select class="form-select" name="category_id" required>
                        <option value="">-- Pilih Kategori --</option>
                        <?php foreach ($stokList as $s): ?>
                            <option value="<?= (int) $s['id'] ?>"><?= htmlspecialchars($s['nama_kategori']) ?> (stok <?= number_format((float) ($s['stok_kg'] ?? 0), 2, ',', '.') ?> kg)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Tipe Mutasi</label>
                        <select class="form-select" name="tipe" required>
                            <option value="masuk">Masuk (+)</option>
                            <option value="keluar">Keluar (−)</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Berat (kg)</label>
                        <input type="number" step="0.01" min="0.01" class="form-control" name="berat" required placeholder="cth: 12.5">
                    </div>
                </div>
                <div class="mb-1">
                    <label class="form-label fw-semibold">Keterangan</label>
                    <textarea class="form-control" name="keterangan" rows="2" placeholder="cth: koreksi hasil rapat timbangan rusak"></textarea>
                </div>
                <div class="form-text">Setoran teller otomatis menambah stok "masuk"; penjualan ke pengepul otomatis "keluar". Form ini untuk penyesuaian manual.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="submit" name="simpan_mutasi" class="btn btn-success btn-sm"><i class="bi bi-check-lg me-1"></i>Simpan Mutasi</button>
            </div>
        </form>
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
        width: 52px; height: 52px; border-radius: 14px;
        display: flex; align-items: center; justify-content: center;
        font-size: 22px; color: #fff; flex-shrink: 0;
    }
    .stat-info { display: flex; flex-direction: column; }
    .stat-label { font-size: 13px; color: var(--text-muted); font-weight: 500; }
    .stat-value { font-size: 20px; font-weight: 700; color: var(--text-strong); }
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
