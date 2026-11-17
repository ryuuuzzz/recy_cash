<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<?php
ob_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/notifikasi.php';

$adminId = (int) ($_SESSION['id_user'] ?? 0);

$flashError   = $_SESSION['error'] ?? '';
$flashSuccess = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);

// ==================== TAMBAH KATEGORI / HARGA ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_harga'])) {
    $nama      = trim($_POST['nama_kategori'] ?? '');
    $satuan    = trim($_POST['satuan'] ?? '');
    $hargaBeli = str_replace(',', '.', trim($_POST['harga_beli'] ?? ''));
    $hargaJual = str_replace(',', '.', trim($_POST['harga_jual'] ?? ''));

    if ($nama === '' || $satuan === '') {
        $_SESSION['error'] = "Nama kategori dan satuan wajib diisi.";
        header('Location: master_harga.php');
        exit;
    }

    if (!is_numeric($hargaBeli) || !is_numeric($hargaJual) || $hargaBeli < 0 || $hargaJual < 0) {
        $_SESSION['error'] = "Harga beli dan harga jual harus berupa angka yang lebih dari atau sama dengan 0.";
        header('Location: master_harga.php');
        exit;
    }

    $cek = $pdo->prepare("SELECT id FROM categories_sampah WHERE nama_kategori = :nama LIMIT 1");
    $cek->execute([':nama' => $nama]);
    if ($cek->fetch()) {
        $_SESSION['error'] = "Kategori \"$nama\" sudah terdaftar. Gunakan nama lain.";
        header('Location: master_harga.php');
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO categories_sampah (nama_kategori, satuan, harga_beli_per_kg, harga_jual_per_kg)
        VALUES (:nama, :satuan, :harga_beli, :harga_jual)
    ");
    $stmt->execute([
        ':nama'       => $nama,
        ':satuan'     => $satuan,
        ':harga_beli' => $hargaBeli,
        ':harga_jual' => $hargaJual,
    ]);
    $newKatId = (int) $pdo->lastInsertId();

    // Audit log aktivitas admin
    auditCatat($pdo, $adminId, 'INSERT_KATEGORI', 'categories_sampah', $newKatId,
        "Nama: $nama, satuan: $satuan, harga beli: $hargaBeli, harga jual: $hargaJual");

    $_SESSION['success'] = "Kategori \"$nama\" beserta harga berhasil ditambahkan.";
    header('Location: master_harga.php');
    exit;
}

// ==================== EDIT KATEGORI / HARGA ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_harga'])) {
    $id        = (int) ($_POST['id_kategori'] ?? 0);
    $nama      = trim($_POST['nama_kategori'] ?? '');
    $satuan    = trim($_POST['satuan'] ?? '');
    $hargaBeli = str_replace(',', '.', trim($_POST['harga_beli'] ?? ''));
    $hargaJual = str_replace(',', '.', trim($_POST['harga_jual'] ?? ''));

    if ($id <= 0 || $nama === '' || $satuan === '') {
        $_SESSION['error'] = "Data tidak lengkap.";
        header('Location: master_harga.php');
        exit;
    }

    if (!is_numeric($hargaBeli) || !is_numeric($hargaJual) || $hargaBeli < 0 || $hargaJual < 0) {
        $_SESSION['error'] = "Harga beli dan harga jual harus berupa angka yang lebih dari atau sama dengan 0.";
        header('Location: master_harga.php');
        exit;
    }

    $cek = $pdo->prepare("SELECT id FROM categories_sampah WHERE nama_kategori = :nama AND id <> :id LIMIT 1");
    $cek->execute([':nama' => $nama, ':id' => $id]);
    if ($cek->fetch()) {
        $_SESSION['error'] = "Kategori \"$nama\" sudah digunakan kategori lain.";
        header('Location: master_harga.php');
        exit;
    }

    // Ambil nilai lama untuk jejak audit
    $stmtOld = $pdo->prepare("SELECT nama_kategori, satuan, harga_beli_per_kg, harga_jual_per_kg FROM categories_sampah WHERE id = :id");
    $stmtOld->execute([':id' => $id]);
    $lama = $stmtOld->fetch();

    $stmt = $pdo->prepare("
        UPDATE categories_sampah
        SET nama_kategori = :nama, satuan = :satuan,
            harga_beli_per_kg = :harga_beli, harga_jual_per_kg = :harga_jual
        WHERE id = :id
    ");
    $stmt->execute([
        ':nama'       => $nama,
        ':satuan'     => $satuan,
        ':harga_beli' => $hargaBeli,
        ':harga_jual' => $hargaJual,
        ':id'         => $id,
    ]);

    // Audit log aktivitas admin (ubah harga)
    auditCatat($pdo, $adminId, 'UPDATE_HARGA', 'categories_sampah', $id,
        "Nama: " . ($lama['nama_kategori'] ?? '-') . " -> $nama | Beli: " . ($lama['harga_beli_per_kg'] ?? '-') . " -> $hargaBeli"
        . " | Jual: " . ($lama['harga_jual_per_kg'] ?? '-') . " -> $hargaJual");

    $_SESSION['success'] = "Data harga kategori \"$nama\" berhasil diperbarui.";
    header('Location: master_harga.php');
    exit;
}

// ==================== HAPUS KATEGORI / HARGA ====================
if (isset($_GET['hapus'])) {
    $id = (int) $_GET['hapus'];

    $cekPakai = $pdo->prepare("
        SELECT
            (SELECT COUNT(*) FROM deposit_details dd WHERE dd.category_id = :id) AS dipakai_setoran,
            (SELECT COUNT(*) FROM penjualan_pengepul pp WHERE pp.category_id = :id) AS dipakai_penjualan
    ");
    $cekPakai->execute([':id' => $id]);
    $status = $cekPakai->fetch();

    if ((int) $status['dipakai_setoran'] > 0 || (int) $status['dipakai_penjualan'] > 0) {
        $relasi = [];
        if ((int) $status['dipakai_setoran'] > 0)    $relasi[] = $status['dipakai_setoran'] . ' transaksi setoran';
        if ((int) $status['dipakai_penjualan'] > 0)  $relasi[] = $status['dipakai_penjualan'] . ' penjualan pengepul';
        $_SESSION['error'] = "Kategori tidak dapat dihapus karena masih digunakan oleh " . implode(' dan ', $relasi) . ".";
        header('Location: master_harga.php');
        exit;
    }

    $stmtNama = $pdo->prepare("SELECT nama_kategori FROM categories_sampah WHERE id = :id");
    $stmtNama->execute([':id' => $id]);
    $namaHapus = (string) ($stmtNama->fetchColumn() ?: ('#' . $id));

    $stmt = $pdo->prepare("DELETE FROM categories_sampah WHERE id = :id");
    $stmt->execute([':id' => $id]);

    // Audit log aktivitas admin
    auditCatat($pdo, $adminId, 'HAPUS_KATEGORI', 'categories_sampah', $id, "Nama: $namaHapus");

    $_SESSION['success'] = "Kategori harga berhasil dihapus.";
    header('Location: master_harga.php');
    exit;
}

// ==================== QUERY: DAFTAR KATEGORI & HARGA ====================
$stmt = $pdo->query("SELECT * FROM categories_sampah ORDER BY nama_kategori ASC");
$kategori = $stmt->fetchAll();

// ==================== QUERY: STATISTIK ====================
$stmt = $pdo->query("
    SELECT
        COUNT(*)                          AS jumlah_kategori,
        COALESCE(AVG(harga_beli_per_kg), 0) AS rata_beli,
        COALESCE(AVG(harga_jual_per_kg), 0) AS rata_jual,
        COALESCE(SUM(harga_beli_per_kg), 0) AS total_modal
    FROM categories_sampah
");
$stat = $stmt->fetch();

// Perbedaan harga jual - beli (margin) untuk tiap kategori
$jumlahPoin = 0;
foreach ($kategori as $ktg) {
    $jumlahPoin += max(0, $ktg['harga_jual_per_kg'] - $ktg['harga_beli_per_kg']);
}
?>

<?php if ($flashError): ?>
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-start gap-2" role="alert">
        <i class="bi bi-x-circle mt-1"></i>
        <div class="flex-grow-1"><?= nl2br(htmlspecialchars($flashError)) ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($flashSuccess): ?>
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-start gap-2" role="alert">
        <i class="bi bi-check-circle mt-1"></i>
        <div class="flex-grow-1"><?= nl2br(htmlspecialchars($flashSuccess)) ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- ======================== CARD STATISTIK ======================== -->
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card">
            <div class="stat-icon bg-success"><i class="bi bi-tags-fill"></i></div>
            <div class="stat-info">
                <span class="stat-label">Total Kategori</span>
                <span class="stat-value"><?= number_format($stat['jumlah_kategori']) ?></span>
                <span class="stat-sub">Jenis sampah terdaftar</span>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card">
            <div class="stat-icon bg-info"><i class="bi bi-bag-heart-fill"></i></div>
            <div class="stat-info">
                <span class="stat-label">Rata-rata Harga Beli</span>
                <span class="stat-value">Rp <?= number_format($stat['rata_beli'], 0, ',', '.') ?></span>
                <span class="stat-sub">per kg / satuan</span>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card">
            <div class="stat-icon bg-warning"><i class="bi bi-cash-coin"></i></div>
            <div class="stat-info">
                <span class="stat-label">Rata-rata Harga Jual</span>
                <span class="stat-value">Rp <?= number_format($stat['rata_jual'], 0, ',', '.') ?></span>
                <span class="stat-sub">per kg / satuan</span>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card">
            <div class="stat-icon bg-danger"><i class="bi bi-graph-up-arrow"></i></div>
            <div class="stat-info">
                <span class="stat-label">Potensi Keuntungan</span>
                <span class="stat-value">Rp <?= number_format($jumlahPoin, 0, ',', '.') ?></span>
                <span class="stat-sub">Jumlah selisih beli vs jual</span>
            </div>
        </div>
    </div>
</div>

<!-- ======================== TABEL MASTER HARGA ======================== -->
<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h6 class="mb-0"><i class="bi bi-tags me-2"></i>Daftar Harga Sampah</h6>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <input type="search" id="searchHarga" class="form-control form-control-sm" style="width: 220px;"
                   placeholder="Cari nama kategori...">
            <button class="btn btn-success btn-sm d-inline-flex align-items-center gap-1"
                    data-bs-toggle="modal" data-bs-target="#modalTambah">
                <i class="bi bi-plus-circle-fill"></i> Tambah Harga
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($kategori)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox" style="font-size:40px;"></i>
                <p class="mt-2">Belum ada data harga sampah.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle" id="tableHarga">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width:50px;">No</th>
                            <th>Nama Jenis / Kategori</th>
                            <th class="text-center" style="width:110px;">Satuan</th>
                            <th class="text-end">Harga Beli</th>
                            <th class="text-end">Harga Jual</th>
                            <th class="text-end" style="width:160px;">Keuntungan</th>
                            <th class="text-center" style="width:120px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($kategori as $i => $row): ?>
                            <tr>
                                <td class="text-center"><?= $i + 1 ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar-cell"><?= strtoupper(substr($row['nama_kategori'], 0, 1)) ?></div>
                                        <strong><?= htmlspecialchars($row['nama_kategori']) ?></strong>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge text-bg-light border"><?= htmlspecialchars($row['satuan']) ?></span>
                                </td>
                                <td class="text-end text-nowrap text-danger">
                                    Rp <?= number_format($row['harga_beli_per_kg'], 0, ',', '.') ?>
                                </td>
                                <td class="text-end text-nowrap text-success">
                                    Rp <?= number_format($row['harga_jual_per_kg'], 0, ',', '.') ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <?php
                                    $margin = $row['harga_jual_per_kg'] - $row['harga_beli_per_kg'];
                                    ?>
                                    <span class="badge <?= $margin >= 0 ? 'bg-primary' : 'bg-danger' ?>">
                                        Rp <?= number_format($margin, 0, ',', '.') ?>
                                    </span>
                                </td>
                                <td class="text-center text-nowrap">
                                    <button type="button" class="btn btn-sm btn-outline-primary" title="Edit"
                                            data-bs-toggle="modal" data-bs-target="#modalEdit"
                                            onclick="isiEdit(
                                                <?= (int) $row['id'] ?>,
                                                <?= json_encode($row['nama_kategori']) ?>,
                                                <?= json_encode($row['satuan']) ?>,
                                                <?= (float) $row['harga_beli_per_kg'] ?>,
                                                <?= (float) $row['harga_jual_per_kg'] ?>
                                            )">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <a href="?hapus=<?= (int) $row['id'] ?>" class="btn btn-sm btn-outline-danger" title="Hapus"
                                       onclick="return confirm('Yakin ingin menghapus kategori &quot;<?= htmlspecialchars($row['nama_kategori']) ?>&quot;?');">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ======================== MODAL TAMBAH HARGA ======================== -->
<div class="modal fade" id="modalTambah" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="master_harga.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle-fill me-2 text-success"></i>Tambah Harga Sampah</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="t_nama_kategori" class="form-label">Nama Jenis / Kategori</label>
                        <input type="text" class="form-control" id="t_nama_kategori" name="nama_kategori"
                               placeholder="cth: Botol Plastik, Kardus, Besi, Organik" required>
                    </div>
                    <div class="mb-3">
                        <label for="t_satuan" class="form-label">Unit / Satuan</label>
                        <select class="form-select" id="t_satuan" name="satuan" required>
                            <option value="kg">kg (Kilogram)</option>
                            <option value="gram">gram</option>
                            <option value="liter">liter</option>
                            <option value="unit">unit / buah</option>
                            <option value="botol">botol</option>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label for="t_harga_beli" class="form-label">Harga Beli (Rp)</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="t_harga_beli"
                                   name="harga_beli" placeholder="cth: 3000" required>
                        </div>
                        <div class="col-sm-6">
                            <label for="t_harga_jual" class="form-label">Harga Jual (Rp)</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="t_harga_jual"
                                   name="harga_jual" placeholder="cth: 4500" required>
                        </div>
                    </div>
                    <div class="form-text mt-2">
                        <i class="bi bi-info-circle me-1"></i>Harga beli dibayarkan kepada nasabah, harga jual berlaku untuk penjualan ke pengepul.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah_harga" class="btn btn-success btn-sm">
                        <i class="bi bi-check-lg me-1"></i>Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======================== MODAL EDIT HARGA ======================== -->
<div class="modal fade" id="modalEdit" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="master_harga.php" method="POST">
                <input type="hidden" name="id_kategori" id="e_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2 text-primary"></i>Edit Harga Sampah</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="e_nama_kategori" class="form-label">Nama Jenis / Kategori</label>
                        <input type="text" class="form-control" id="e_nama_kategori" name="nama_kategori" required>
                    </div>
                    <div class="mb-3">
                        <label for="e_satuan" class="form-label">Unit / Satuan</label>
                        <select class="form-select" id="e_satuan" name="satuan" required>
                            <option value="kg">kg (Kilogram)</option>
                            <option value="gram">gram</option>
                            <option value="liter">liter</option>
                            <option value="unit">unit / buah</option>
                            <option value="botol">botol</option>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label for="e_harga_beli" class="form-label">Harga Beli (Rp)</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="e_harga_beli"
                                   name="harga_beli" required>
                        </div>
                        <div class="col-sm-6">
                            <label for="e_harga_jual" class="form-label">Harga Jual (Rp)</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="e_harga_jual"
                                   name="harga_jual" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="edit_harga" class="btn btn-primary btn-sm">
                        <i class="bi bi-check-lg me-1"></i>Simpan Perubahan
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

    .avatar-cell {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 14px;
        color: #fff;
        flex-shrink: 0;
        background: #2e7d32;
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
    const pilihanSatuan = ['kg', 'gram', 'liter', 'unit', 'botol'];

    function setSatuan(selectEl, nilai) {
        nilai = (nilai || 'kg').toLowerCase();
        for (let i = 0; i < selectEl.options.length; i++) {
            if (selectEl.options[i].value.toLowerCase() === nilai) {
                selectEl.selectedIndex = i;
                return;
            }
        }
        const opt = document.createElement('option');
        opt.value = nilai;
        opt.textContent = nilai;
        opt.selected = true;
        selectEl.appendChild(opt);
    }

    // ====== Isi modal edit ======
    window.isiEdit = function (id, nama, satuan, hargaBeli, hargaJual) {
        document.getElementById('e_id').value         = id;
        document.getElementById('e_nama_kategori').value = nama;
        setSatuan(document.getElementById('e_satuan'), satuan);
        document.getElementById('e_harga_beli').value = hargaBeli;
        document.getElementById('e_harga_jual').value = hargaJual;
    };

    // ====== Pencarian nama kategori ======
    const searchInput = document.getElementById('searchHarga');
    const rows        = document.querySelectorAll('#tableHarga tbody tr');

    searchInput.addEventListener('input', function () {
        const q = (searchInput.value || '').toLowerCase().trim();
        rows.forEach(function (row) {
            const text = (row.textContent || '').toLowerCase();
            row.style.display = (q === '' || text.indexOf(q) !== -1) ? '' : 'none';
        });
    });

    // ====== Reset form modal tambah saat ditutup ======
    const modalTambah = document.getElementById('modalTambah');
    if (modalTambah) {
        modalTambah.addEventListener('hidden.bs.modal', function () {
            this.querySelector('form').reset();
        });
    }
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>