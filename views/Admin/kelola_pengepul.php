<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<?php
ob_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/notifikasi.php';

// Migrasi otomatis: role pengepul, kolom pengepul_id, tabel stok
pastikanRolePengepul($pdo);
pastikanKolomPengepulId($pdo);
pastikanTabelStok($pdo);

$flashError   = $_SESSION['error'] ?? '';
$flashSuccess = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);

$adminId = (int) ($_SESSION['id_user'] ?? 0);

// Daftar akun pengepul (mitra terdaftar)
$pengepulAkun = $pdo->query("
    SELECT id, nama, email, nomor_hp
    FROM users
    WHERE role = 'pengepul'
    ORDER BY nama ASC
")->fetchAll();

// ==================== INSERT TRANSAKSI PENJUALAN ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_penjualan'])) {
    $modeAkun     = ($_POST['mode_pengepul'] ?? 'manual') === 'akun';
    $pengepulId   = $modeAkun ? (int) ($_POST['pengepul_id'] ?? 0) : 0;
    $namaPengepul = $modeAkun ? '' : trim($_POST['nama_pengepul'] ?? '');
    $categoryId   = (int) ($_POST['category_id'] ?? 0);
    $totalBerat   = str_replace(',', '.', trim($_POST['total_berat'] ?? ''));
    $hargaPerKg   = str_replace(',', '.', trim($_POST['harga_jual_per_kg'] ?? ''));
    $catatan      = trim($_POST['catatan'] ?? '');

    if ($modeAkun && $pengepulId > 0) {
        $stmtP = $pdo->prepare("SELECT nama FROM users WHERE id = :id AND role = 'pengepul' LIMIT 1");
        $stmtP->execute([':id' => $pengepulId]);
        $u = $stmtP->fetch();
        if (!$u) {
            $_SESSION['error'] = "Akun pengepul tidak ditemukan.";
            header('Location: kelola_pengepul.php');
            exit;
        }
        $namaPengepul = $u['nama'];
    }

    if ($namaPengepul === '' || $categoryId <= 0 || $totalBerat === '' || $hargaPerKg === '') {
        $_SESSION['error'] = "Semua field wajib diisi.";
        header('Location: kelola_pengepul.php');
        exit;
    }

    if ($totalBerat <= 0 || $hargaPerKg <= 0) {
        $_SESSION['error'] = "Berat dan harga harus lebih dari 0.";
        header('Location: kelola_pengepul.php');
        exit;
    }

    $totalPendapatan = $totalBerat * $hargaPerKg;

    try {
        $pdo->beginTransaction();

        // Stok gudang harus cukup sebelum penjualan dicatat
        [$stokOk, $stokSisa] = stokCatat($pdo, $categoryId, 'keluar', (float) $totalBerat,
            "Penjualan ke $namaPengepul", 'PENJUALAN', $adminId);
        if (!$stokOk) {
            $pdo->rollBack();
            throw new RuntimeException("Stok kategori hanya tersedia " . number_format($stokSisa, 2, ',', '.') . " kg. Catat mutasi masuk di halaman Gudang terlebih dahulu.");
        }

        $stmt = $pdo->prepare("
            INSERT INTO penjualan_pengepul
                (admin_id, nama_pengepul, pengepul_id, category_id, total_berat, harga_jual_per_kg, total_pendapatan, catatan)
            VALUES
                (:admin_id, :nama_pengepul, :pengepul_id, :category_id, :total_berat, :harga_jual_per_kg, :total_pendapatan, :catatan)
        ");
        $stmt->execute([
            ':admin_id'           => $adminId,
            ':nama_pengepul'      => $namaPengepul,
            ':pengepul_id'        => $pengepulId > 0 ? $pengepulId : null,
            ':category_id'        => $categoryId,
            ':total_berat'        => $totalBerat,
            ':harga_jual_per_kg'  => $hargaPerKg,
            ':total_pendapatan'   => $totalPendapatan,
            ':catatan'            => $catatan !== '' ? $catatan : null,
        ]);
        $trxPenjualanId = (int) $pdo->lastInsertId();

        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error'] = "Gagal menyimpan penjualan: " . htmlspecialchars($e->getMessage());
        header('Location: kelola_pengepul.php');
        exit;
    } catch (RuntimeException $e) {
        $_SESSION['error'] = htmlspecialchars($e->getMessage());
        header('Location: kelola_pengepul.php');
        exit;
    }

    // Audit log aktivitas admin
    auditCatat($pdo, $adminId, 'INSERT_PENJUALAN', 'penjualan_pengepul', $trxPenjualanId,
        "Pengepul: $namaPengepul" . ($pengepulId > 0 ? " (akun #$pengepulId)" : " (manual)")
        . ", kategori #$categoryId, berat {$totalBerat} kg, total Rp " . number_format($totalPendapatan, 0, ',', '.'));

    // Notifikasi email/in-app ke pengepul (jika akun terdaftar)
    if ($pengepulId > 0) {
        notifKirim($pdo, $pengepulId,
            "Penjualan baru tercatat: {$totalBerat} kg senilai Rp " . number_format($totalPendapatan, 0, ',', '.') . ".",
            '../Pengepul/dashboard.php',
            'RecyCash - Penjualan Baru Tercatat',
            '<p>Penjualan baru telah dicatat atas nama <strong>' . htmlspecialchars($namaPengepul) . '</strong>.</p>'
            . '<ul><li>Berat: ' . htmlspecialchars($totalBerat) . ' kg</li>'
            . '<li>Harga: Rp ' . number_format($hargaPerKg, 0, ',', '.') . '/kg</li>'
            . '<li>Total: <strong>Rp ' . number_format($totalPendapatan, 0, ',', '.') . '</strong></li></ul>');
    }

    $_SESSION['success'] = "Penjualan ke \"$namaPengepul\" berhasil disimpan.";
    header('Location: kelola_pengepul.php');
    exit;
}

// ==================== HAPUS TRANSAKSI PENJUALAN ====================
if (isset($_GET['hapus'])) {
    $id = (int) $_GET['hapus'];

    $stmtRow = $pdo->prepare("SELECT * FROM penjualan_pengepul WHERE id = :id LIMIT 1");
    $stmtRow->execute([':id' => $id]);
    $rowHapus = $stmtRow->fetch();

    if ($rowHapus) {
        try {
            $pdo->beginTransaction();
            // Kembalikan stok karena penjualan dibatalkan
            stokCatat($pdo, (int) $rowHapus['category_id'], 'masuk', (float) $rowHapus['total_berat'],
                "Pembatalan penjualan #{$id} (" . $rowHapus['nama_pengepul'] . ")", 'BATAL_JUAL', $adminId);
            $pdo->prepare("DELETE FROM penjualan_pengepul WHERE id = :id")->execute([':id' => $id]);
            $pdo->commit();

            auditCatat($pdo, $adminId, 'HAPUS_PENJUALAN', 'penjualan_pengepul', $id,
                "Pengepul: " . $rowHapus['nama_pengepul'] . ", berat {$rowHapus['total_berat']} kg, total Rp "
                . number_format((float) $rowHapus['total_pendapatan'], 0, ',', '.'));
            $_SESSION['success'] = "Data penjualan berhasil dihapus dan stok dikembalikan.";
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['error'] = "Gagal menghapus data penjualan.";
        }
    } else {
        $_SESSION['error'] = "Data penjualan tidak ditemukan.";
    }
    header('Location: kelola_pengepul.php');
    exit;
}

// ==================== QUERY: MASTER KATEGORI SAMPAH ====================
$stmt = $pdo->query("SELECT id, nama_kategori, harga_jual_per_kg, satuan FROM categories_sampah ORDER BY nama_kategori ASC");
$kategoriList = $stmt->fetchAll();

// ==================== QUERY: RINGKASAN STATISTIK ====================
$stmt = $pdo->query("
    SELECT
        COALESCE(SUM(total_pendapatan), 0) AS total_pendapatan,
        COALESCE(SUM(total_berat), 0)      AS total_berat,
        COUNT(*)                           AS jumlah_transaksi
    FROM penjualan_pengepul
");
$ringkasan = $stmt->fetch();

// ==================== QUERY: HISTORI PENJUALAN (PAGINATION) ====================
$perPageJual = (int) ($_GET['per_page'] ?? 15);
if (!in_array($perPageJual, [15, 30, 50], true)) $perPageJual = 15;
$pageJual = max(1, (int) ($_GET['page'] ?? 1));

$totalJual = (int) $pdo->query("SELECT COUNT(*) FROM penjualan_pengepul")->fetchColumn();
$totalPageJual = max(1, (int) ceil($totalJual / $perPageJual));
if ($pageJual > $totalPageJual) $pageJual = $totalPageJual;
$offJual = ($pageJual - 1) * $perPageJual;

$stmt = $pdo->query("
    SELECT
        pp.id,
        pp.nama_pengepul,
        pp.pengepul_id,
        pp.total_berat,
        pp.harga_jual_per_kg,
        pp.total_pendapatan,
        pp.catatan,
        pp.created_at,
        cs.nama_kategori,
        cs.satuan
    FROM penjualan_pengepul pp
    INNER JOIN categories_sampah cs ON cs.id = pp.category_id
    ORDER BY pp.created_at DESC, pp.id DESC
    LIMIT $perPageJual OFFSET $offJual
");
$histori = $stmt->fetchAll();

function qsPengepul(array $ganti = []): string {
    $q = array_merge($_GET, $ganti);
    unset($q['page']);
    $q = array_filter($q, static fn($v) => $v !== '' && $v !== null);
    return 'kelola_pengepul.php' . (count($q) ? '?' . http_build_query($q) : '');
}
?>

<?php
$pesanFlash = [];
if ($flashError !== '')   $pesanFlash[] = ['danger',  'bi-x-circle',     $flashError];
if ($flashSuccess !== '') $pesanFlash[] = ['success', 'bi-check-circle', $flashSuccess];
?>

<?php if ($pesanFlash): ?>
    <div class="d-flex flex-column gap-2 mb-4">
        <?php foreach ($pesanFlash as $p): ?>
            <div class="alert alert-<?= $p[0] ?> alert-dismissible fade show d-flex align-items-start gap-2 mb-0" role="alert">
                <i class="bi <?= $p[1] ?> mt-1"></i>
                <div class="flex-grow-1"><?= nl2br(htmlspecialchars($p[2])) ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- ======================== CARD STATISTIK ======================== -->
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-4">
        <div class="card stat-card">
            <div class="stat-icon bg-success"><i class="bi bi-cash-coin"></i></div>
            <div class="stat-info">
                <span class="stat-label">Total Pendapatan Pengepul</span>
                <span class="stat-value">Rp <?= number_format($ringkasan['total_pendapatan'], 0, ',', '.') ?></span>
                <span class="stat-sub">Dari <?= number_format($ringkasan['jumlah_transaksi']) ?> transaksi</span>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="card stat-card">
            <div class="stat-icon bg-info"><i class="bi bi-box-seam"></i></div>
            <div class="stat-info">
                <span class="stat-label">Total Sampah Terjual</span>
                <span class="stat-value"><?= number_format($ringkasan['total_berat'], 2, ',', '.') ?> kg</span>
                <span class="stat-sub">Rata-rata <?= number_format($ringkasan['total_berat'] / max($ringkasan['jumlah_transaksi'], 1), 2, ',', '.') ?> kg / transaksi</span>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="card stat-card">
            <div class="stat-icon bg-warning"><i class="bi bi-receipt"></i></div>
            <div class="stat-info">
                <span class="stat-label">Jumlah Transaksi</span>
                <span class="stat-value"><?= number_format($ringkasan['jumlah_transaksi']) ?>x</span>
                <span class="stat-sub">Seluruh riwayat penjualan tercatat</span>
            </div>
        </div>
    </div>
</div>

<!-- ======================== TABEL HISTORI PENJUALAN ======================== -->
<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h6 class="mb-0"><i class="bi bi-truck me-2"></i>Histori Penjualan Pengepul</h6>
        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalPengepul">
            <i class="bi bi-plus-circle me-1"></i>Tambah Penjualan
        </button>
    </div>
    <div class="card-body p-0">
        <?php if (empty($histori)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox" style="font-size:40px;"></i>
                <p class="mt-2">Belum ada data penjualan ke pengepul.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive table-report">
                <table class="table table-hover mb-0 align-middle">                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width:50px;">No</th>
                            <th>Tanggal</th>
                            <th>Nama Pengepul</th>
                            <th>Jenis Sampah</th>
                            <th class="text-end">Berat (kg)</th>
                            <th class="text-end">Harga Jual / kg</th>
                            <th class="text-end">Total Pendapatan</th>
                            <th class="text-center" style="width:110px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($histori as $i => $row): ?>
                            <tr>
                                <td class="text-center"><?= $i + 1 ?></td>
                                <td class="text-nowrap"><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($row['nama_pengepul']) ?></strong>
                                    <br>
                                    <?php if (!empty($row['pengepul_id'])): ?>
                                        <small class="text-muted"><i class="bi bi-person-check me-1"></i>akun mitra #<?= (int) $row['pengepul_id'] ?></small>
                                    <?php else: ?>
                                        <small class="text-muted">tanpa akun</small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($row['nama_kategori']) ?></td>
                                <td class="text-end text-nowrap"><?= number_format($row['total_berat'], 2, ',', '.') ?> kg</td>
                                <td class="text-end text-nowrap">Rp <?= number_format($row['harga_jual_per_kg'], 0, ',', '.') ?></td>
                                <td class="text-end text-nowrap">
                                    <span class="badge text-bg-success">Rp <?= number_format($row['total_pendapatan'], 0, ',', '.') ?></span>
                                </td>
                                <td class="text-center text-nowrap">
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                            data-bs-toggle="modal" data-bs-target="#modalDetail<?= $row['id'] ?>"
                                            title="Detail">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <a href="?hapus=<?= $row['id'] ?>"
                                       class="btn btn-sm btn-outline-danger"
                                       title="Hapus"
                                       onclick="return confirm('Hapus transaksi penjualan ini?');">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light fw-semibold">
                        <tr>
                            <td colspan="4" class="text-end">Total</td>
                            <td class="text-end text-nowrap"><?= number_format($ringkasan['total_berat'], 2, ',', '.') ?> kg</td>
                            <td></td>
                            <td class="text-end text-nowrap">Rp <?= number_format($ringkasan['total_pendapatan'], 0, ',', '.') ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

        <?php endif; ?>
    </div>
</div>

<!-- ======================== PAGINATION ======================== -->
<?php if ($totalPageJual > 1): ?>
    <nav class="mt-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="text-muted small">Halaman <strong><?= $pageJual ?></strong> dari <strong><?= $totalPageJual ?></strong> · <?= number_format($totalJual) ?> data</div>
        <div class="d-flex gap-1 align-items-center">
            <select class="form-select form-select-sm w-auto" onchange="window.location.href=this.value">
                <?php foreach ([15, 30, 50] as $n): ?>
                    <option value="<?= qsPengepul(['per_page' => $n]) ?>" <?= $perPageJual === $n ? 'selected' : '' ?>><?= $n ?> / halaman</option>
                <?php endforeach; ?>
            </select>
            <a href="<?= qsPengepul(['page' => max(1, $pageJual - 1)]) ?>" class="btn btn-outline-success btn-sm <?= $pageJual <= 1 ? 'disabled' : '' ?>"><i class="bi bi-chevron-left"></i></a>
            <span class="btn btn-sm btn-success disabled"><?= $pageJual ?> / <?= $totalPageJual ?></span>
            <a href="<?= qsPengepul(['page' => min($totalPageJual, $pageJual + 1)]) ?>" class="btn btn-outline-success btn-sm <?= $pageJual >= $totalPageJual ? 'disabled' : '' ?>"><i class="bi bi-chevron-right"></i></a>
        </div>
    </nav>
<?php endif; ?>

<?php if (!empty($histori)): ?>
    <?php foreach ($histori as $row): ?>
        <!-- MODAL DETAIL -->
        <div class="modal fade" id="modalDetail<?= $row['id'] ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Detail Penjualan</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <th class="text-muted w-50">Tanggal</th>
                                <td><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Nama Pengepul</th>
                                <td><?= htmlspecialchars($row['nama_pengepul']) ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Jenis Sampah</th>
                                <td><?= htmlspecialchars($row['nama_kategori']) ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Berat</th>
                                <td><?= number_format($row['total_berat'], 2, ',', '.') ?> kg</td>
                            </tr>
                            <tr>
                                <th class="text-muted">Harga Jual / kg</th>
                                <td>Rp <?= number_format($row['harga_jual_per_kg'], 0, ',', '.') ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Total Pendapatan</th>
                                <td><strong>Rp <?= number_format($row['total_pendapatan'], 0, ',', '.') ?></strong></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Catatan</th>
                                <td class="text-break"><?= nl2br(htmlspecialchars($row['catatan'] ?? '-')) ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- ======================== MODAL TAMBAH PENJUALAN ======================== -->
<div class="modal fade" id="modalPengepul" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="kelola_pengepul.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-truck me-2"></i>Catat Penjualan Sampah</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="mode_pengepul" class="form-label">Pengepul</label>
                        <select class="form-select" id="mode_pengepul" name="mode_pengepul">
                            <option value="akun" <?= !empty($pengepulAkun) ? 'selected' : '' ?>>Akun mitra terdaftar</option>
                            <option value="manual">Tanpa akun (nama manual)</option>
                        </select>
                    </div>
                    <div class="mb-3" id="wrapAkun" <?= empty($pengepulAkun) ? 'style="display:none"' : '' ?>>
                        <label for="pengepul_id" class="form-label">Akun Pengepul</label>
                        <select class="form-select" id="pengepul_id" name="pengepul_id">
                            <?php foreach ($pengepulAkun as $pa): ?>
                                <option value="<?= (int) $pa['id'] ?>">
                                    <?= htmlspecialchars($pa['nama']) ?><?= $pa['email'] !== '' ? ' (' . htmlspecialchars($pa['email']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Transaksi otomatis muncul di dashboard akun pengepul terkait & dapat mengirim notifikasi.</div>
                    </div>
                    <div class="mb-3" id="wrapManual" <?= empty($pengepulAkun) ? '' : 'style="display:none"' ?>>
                        <label for="nama_pengepul" class="form-label">Nama Pengepul / Pabrik</label>
                        <input type="text" class="form-control" id="nama_pengepul" name="nama_pengepul"
                               placeholder="cth: PT Daur Ulang Mandiri / Pengepul Pak Eko">
                    </div>

                    <div class="mb-3">
                        <label for="category_id" class="form-label">Kategori Sampah</label>
                        <select class="form-select" id="category_id" name="category_id" required>
                            <option value="">-- Pilih Jenis Sampah --</option>
                            <?php foreach ($kategoriList as $ktg): ?>
                                <option value="<?= $ktg['id'] ?>"
                                        data-harga="<?= $ktg['harga_jual_per_kg'] ?>">
                                    <?= htmlspecialchars($ktg['nama_kategori']) ?>
                                    (Rp <?= number_format($ktg['harga_jual_per_kg'], 0, ',', '.') ?>/<?= htmlspecialchars($ktg['satuan']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label for="total_berat" class="form-label">Total Berat (kg)</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="total_berat"
                                   name="total_berat" placeholder="cth: 25.5" required>
                        </div>
                        <div class="col-sm-6">
                            <label for="harga_jual_per_kg" class="form-label">Harga Jual / kg (Rp)</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="harga_jual_per_kg"
                                   name="harga_jual_per_kg" placeholder="Otomatis / edit manual" required>
                        </div>
                    </div>

                    <div class="alert alert-success mt-3 mb-3 d-flex justify-content-between align-items-center">
                        <span class="fw-semibold">Total Pendapatan</span>
                        <span class="fw-bold fs-5" id="total_pendapatan_text">Rp 0</span>
                    </div>

                    <div class="mb-1">
                        <label for="catatan" class="form-label">Catatan <span class="text-muted">(opsional)</span></label>
                        <textarea class="form-control" id="catatan" name="catatan" rows="2"
                                  placeholder="cth: Sampah diambil langsung oleh pengepul"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="simpan_penjualan" class="btn btn-success btn-sm">
                        <i class="bi bi-check-lg me-1"></i>Simpan Penjualan
                    </button>
                </div>
            </form>
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
        .stat-sub   { font-size: 12px; color: var(--text-soft); }

    .table-report tfoot td {
        border-top: 2px solid #2e7d32;
        background: #f8fafc;
    }

    @media (max-width: 767px) {
        .stat-card { padding: 16px; gap: 12px; }
        .stat-icon { width: 44px; height: 44px; font-size: 18px; border-radius: 12px; }
        .stat-label { font-size: 12px; }
        .stat-value { font-size: 17px; }
    }
</style>

<script>
(function () {
    const selectKategori = document.getElementById('category_id');
    const inputBerat     = document.getElementById('total_berat');
    const inputHarga     = document.getElementById('harga_jual_per_kg');
    const textTotal      = document.getElementById('total_pendapatan_text');

    function formatRupiah(angka) {
        return 'Rp ' + Number(angka || 0).toLocaleString('id-ID');
    }

    function hitungTotal() {
        const berat = parseFloat(inputBerat.value) || 0;
        const harga = parseFloat(inputHarga.value) || 0;
        textTotal.textContent = formatRupiah(berat * harga);
    }

    // Otomatis isi harga jual dari kategori yang dipilih & buat editable
    selectKategori.addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        if (opt && opt.dataset.harga) {
            inputHarga.value = opt.dataset.harga;
            hitungTotal();
        }
    });

    inputBerat.addEventListener('input', hitungTotal);
    inputHarga.addEventListener('input', hitungTotal);

    // Reset form saat modal ditutup agar data lama tidak menumpuk
    document.getElementById('modalPengepul').addEventListener('hidden.bs.modal', function () {
        this.querySelector('form').reset();
        textTotal.textContent = formatRupiah(0);
        updateMode();
    });

    // Toggle mode pengepul: akun mitra vs nama manual
    const modePengepul = document.getElementById('mode_pengepul');
    const wrapAkun     = document.getElementById('wrapAkun');
    const wrapManual   = document.getElementById('wrapManual');
    const inputNamaManual = document.getElementById('nama_pengepul');

    function updateMode() {
        const pakaiAkun = modePengepul.value === 'akun';
        wrapAkun.style.display   = pakaiAkun ? '' : 'none';
        wrapManual.style.display = pakaiAkun ? 'none' : '';
        inputNamaManual.required = !pakaiAkun;
    }
    modePengepul.addEventListener('change', updateMode);
    updateMode();
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>