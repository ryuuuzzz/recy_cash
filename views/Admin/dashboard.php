<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<?php
require_once __DIR__ . '/../../config/koneksi.php';

$totalNasabah = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'nasabah'")->fetchColumn();
$totalSampah = (float) $pdo->query("SELECT COALESCE(SUM(total_berat), 0) FROM deposit_transactions")->fetchColumn();
$totalSaldoKeluar = (float) $pdo->query("SELECT COALESCE(SUM(nominal), 0) FROM withdrawals WHERE status IN ('disetujui', 'selesai')")->fetchColumn();
$totalTransaksi = (int) $pdo->query("SELECT COUNT(*) FROM deposit_transactions")->fetchColumn();
$pendingPenarikan = (int) $pdo->query("SELECT COUNT(*) FROM withdrawals WHERE status = 'pending'")->fetchColumn();
$pendingAkun = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status_akun = 'pending'")->fetchColumn();
$pendingJemput = (int) $pdo->query("SELECT COUNT(*) FROM pickup_requests WHERE status = 'pending'")->fetchColumn();

$stmt = $pdo->query("
    SELECT
        cs.nama_kategori,
        COALESCE(SUM(dd.berat_kg), 0) AS total_berat,
        COUNT(dd.id) AS jumlah_transaksi
    FROM categories_sampah cs
    LEFT JOIN deposit_details dd ON dd.category_id = cs.id
    GROUP BY cs.id, cs.nama_kategori
    ORDER BY total_berat DESC
");
$stokGudang = $stmt->fetchAll();
$totalKategori = count($stokGudang);
$totalStok = 0.0;
foreach ($stokGudang as $row) {
    $totalStok += (float) $row['total_berat'];
}

$namaHari = [1 => 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
$mulaiMinggu = (new DateTimeImmutable('today'))->modify('-6 days');
$stmt = $pdo->prepare("
    SELECT DATE(created_at) AS tanggal,
           COALESCE(SUM(total_berat), 0) AS berat,
           COALESCE(SUM(total_nominal), 0) AS nominal,
           COUNT(*) AS transaksi
    FROM deposit_transactions
    WHERE created_at >= :mulai
    GROUP BY DATE(created_at)
    ORDER BY tanggal ASC
");
$stmt->execute([':mulai' => $mulaiMinggu->format('Y-m-d 00:00:00')]);

$trenHarian = [];
foreach ($stmt->fetchAll() as $row) {
    $trenHarian[$row['tanggal']] = $row;
}

$chartLabels = [];
$chartBerat = [];
$chartNominal = [];
$chartTransaksi = [];
for ($i = 0; $i < 7; $i++) {
    $tanggal = $mulaiMinggu->modify("+$i day");
    $key = $tanggal->format('Y-m-d');
    $row = $trenHarian[$key] ?? null;

    $chartLabels[] = $namaHari[(int) $tanggal->format('N')] . ' ' . $tanggal->format('d/m');
    $chartBerat[] = (float) ($row['berat'] ?? 0);
    $chartNominal[] = (float) ($row['nominal'] ?? 0);
    $chartTransaksi[] = (int) ($row['transaksi'] ?? 0);
}
$totalBeratMinggu = array_sum($chartBerat);

$stokChart = array_values(array_filter($stokGudang, static function (array $row): bool {
    return (float) $row['total_berat'] > 0;
}));
$stokLabels = array_map(static fn(array $row): string => $row['nama_kategori'], $stokChart);
$stokValues = array_map(static fn(array $row): float => (float) $row['total_berat'], $stokChart);
$stokColors = ['#059669', '#34d399', '#a3e635', '#fbbf24', '#38bdf8', '#fb7185', '#94a3b8', '#8b5cf6'];
?>

<div class="dashboard-page">
    <div class="dashboard-intro" data-aos="fade-up">
        <div>
            <span class="section-eyebrow"><i class="bi bi-grid-1x2-fill"></i> Command Center</span>
            <h1>Selamat datang, <?= htmlspecialchars($nama) ?></h1>
            <p>Satu tempat untuk memantau aliran sampah, saldo, dan antrean verifikasi.</p>
        </div>
        <span class="intro-badge">
            <i class="bi bi-calendar3"></i>
            <?= date('d M Y') ?>
        </span>
    </div>

    <div class="bento-grid">
        <div class="bento-card ops-hero" data-aos="fade-up" data-aos-delay="60">
            <div class="ops-hero-top">
                <span class="section-eyebrow"><i class="bi bi-shield-check"></i> Operasional RecyCash</span>
                <h1>Kelola ekosistem sampah dengan data yang rapi.</h1>
                <p>Pantau performa bank sampah, tindak lanjuti pengajuan, dan pastikan setiap transaksi tercatat dengan baik.</p>

                <div class="ops-hero-stats">
                    <div class="ops-hero-stat">
                        <span>Total Transaksi</span>
                        <strong><?= number_format($totalTransaksi, 0, ',', '.') ?>x</strong>
                    </div>
                    <div class="ops-hero-stat">
                        <span>Stok Gudang</span>
                        <strong><?= number_format($totalStok, 1, ',', '.') ?> kg</strong>
                    </div>
                    <div class="ops-hero-stat">
                        <span>Antrean Jemput</span>
                        <strong><?= number_format($pendingJemput, 0, ',', '.') ?> request</strong>
                    </div>
                </div>
            </div>

            <div class="ops-hero-bottom">
                <div class="ops-hero-actions">
                    <a href="<?= $baseUrl ?>/views/Admin/penarikan.php" class="btn-wallet btn-wallet-primary">
                        <i class="bi bi-wallet2"></i> Verifikasi Penarikan
                        <?php if ($pendingPenarikan > 0): ?>
                            <span class="badge text-bg-warning ms-1"><?= $pendingPenarikan ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="<?= $baseUrl ?>/views/Admin/laporan.php" class="btn-wallet btn-wallet-ghost">
                        <i class="bi bi-file-earmark-bar-graph"></i> Lihat Laporan
                    </a>
                </div>
            </div>
        </div>

        <div class="bento-card ops-side" data-aos="fade-up" data-aos-delay="120">
            <div class="ops-side-head">
                <span class="section-eyebrow"><i class="bi bi-lightning-charge-fill"></i> Aksi Cepat</span>
                <h2>Perlu ditindaklanjuti</h2>
                <p>Antrean yang menunggu keputusan admin.</p>
            </div>

            <div class="quick-action-list">
                <a href="<?= $baseUrl ?>/views/Admin/penarikan.php?status=pending" class="quick-action">
                    <span class="quick-action-icon"><i class="bi bi-wallet2"></i></span>
                    <span class="quick-action-body">
                        <strong>Verifikasi Penarikan</strong>
                        <span>Setujui atau tolak pengajuan</span>
                    </span>
                    <span class="quick-action-count"><?= $pendingPenarikan ?></span>
                </a>
                <a href="<?= $baseUrl ?>/views/Admin/verifikasi_akun.php" class="quick-action">
                    <span class="quick-action-icon"><i class="bi bi-person-check-fill"></i></span>
                    <span class="quick-action-body">
                        <strong>Verifikasi Akun</strong>
                        <span>Admin dan petugas baru</span>
                    </span>
                    <span class="quick-action-count"><?= $pendingAkun ?></span>
                </a>
                <a href="<?= $baseUrl ?>/views/Admin/kelola_user.php" class="quick-action">
                    <span class="quick-action-icon"><i class="bi bi-people-fill"></i></span>
                    <span class="quick-action-body">
                        <strong>Kelola Pengguna</strong>
                        <span>Data seluruh akun</span>
                    </span>
                    <span class="quick-action-count"><?= number_format($totalNasabah, 0, ',', '.') ?></span>
                </a>
                <a href="<?= $baseUrl ?>/views/Admin/master_harga.php" class="quick-action">
                    <span class="quick-action-icon"><i class="bi bi-tags-fill"></i></span>
                    <span class="quick-action-body">
                        <strong>Atur Harga Sampah</strong>
                        <span>Harga beli dan harga jual</span>
                    </span>
                    <span class="quick-action-count"><?= $totalKategori ?></span>
                </a>
            </div>
        </div>

        <div class="bento-card ops-kpi" data-aos="fade-up" data-aos-delay="60">
            <div class="ops-kpi-top">
                <span class="ops-kpi-icon"><i class="bi bi-people-fill"></i></span>
                <span class="ops-kpi-label">Total Nasabah</span>
            </div>
            <div>
                <span class="ops-kpi-value"><?= number_format($totalNasabah, 0, ',', '.') ?> orang</span>
                <span class="ops-kpi-meta"><i class="bi bi-arrow-up-right"></i> Terdaftar di RecyCash</span>
            </div>
        </div>

        <div class="bento-card ops-kpi" data-aos="fade-up" data-aos-delay="105">
            <div class="ops-kpi-top">
                <span class="ops-kpi-icon is-blue"><i class="bi bi-recycle"></i></span>
                <span class="ops-kpi-label">Total Sampah</span>
            </div>
            <div>
                <span class="ops-kpi-value"><?= number_format($totalSampah, 1, ',', '.') ?> kg</span>
                <span class="ops-kpi-meta"><i class="bi bi-clock-history"></i> <?= number_format($totalTransaksi, 0, ',', '.') ?> transaksi</span>
            </div>
        </div>

        <div class="bento-card ops-kpi" data-aos="fade-up" data-aos-delay="150">
            <div class="ops-kpi-top">
                <span class="ops-kpi-icon is-rose"><i class="bi bi-cash-stack"></i></span>
                <span class="ops-kpi-label">Saldo Keluar</span>
            </div>
            <div>
                <span class="ops-kpi-value">Rp <?= number_format($totalSaldoKeluar, 0, ',', '.') ?></span>
                <span class="ops-kpi-meta"><i class="bi bi-check2-circle"></i> Penarikan disetujui</span>
            </div>
        </div>

        <div class="bento-card ops-kpi" data-aos="fade-up" data-aos-delay="195">
            <div class="ops-kpi-top">
                <span class="ops-kpi-icon is-amber"><i class="bi bi-box-seam-fill"></i></span>
                <span class="ops-kpi-label">Kategori Sampah</span>
            </div>
            <div>
                <span class="ops-kpi-value"><?= number_format($totalKategori, 0, ',', '.') ?> jenis</span>
                <span class="ops-kpi-meta"><i class="bi bi-layers"></i> Aktif di master harga</span>
            </div>
        </div>

        <div class="bento-card ops-chart chart-card" data-aos="fade-up" data-aos-delay="60">
            <div class="card-heading">
                <div>
                    <span class="section-eyebrow"><i class="bi bi-graph-up-arrow"></i> Analitik</span>
                    <h2>Tren Setoran 7 Hari</h2>
                    <p>Kilogram sampah yang masuk ke gudang</p>
                </div>
                <div class="chart-highlight">
                    <strong><?= number_format($totalBeratMinggu, 1, ',', '.') ?> kg</strong>
                    <span>Total 7 hari</span>
                </div>
            </div>
            <div class="chart-wrap">
                <canvas id="chartMingguan" aria-label="Grafik tren setoran sampah 7 hari" role="img"></canvas>
                <div class="chart-empty d-none" id="weeklyChartEmpty">
                    <i class="bi bi-bar-chart"></i>
                    <div>
                        <strong>Belum ada setoran</strong>
                        <p class="mb-0">Data akan muncul setelah petugas mencatat setoran.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bento-card ops-stock" data-aos="fade-up" data-aos-delay="120">
            <div class="card-heading">
                <div>
                    <span class="section-eyebrow"><i class="bi bi-pie-chart-fill"></i> Gudang</span>
                    <h2>Komposisi Stok</h2>
                    <p>Distribusi berat per kategori</p>
                </div>
            </div>
            <div class="ops-donut">
                <canvas id="chartStok" aria-label="Grafik komposisi stok sampah" role="img"></canvas>
                <div class="stock-total">
                    <strong><?= number_format($totalStok, 1, ',', '.') ?></strong>
                    <span>Total kg</span>
                </div>
                <div class="chart-empty d-none" id="stockChartEmpty">
                    <i class="bi bi-box-seam"></i>
                    <div>
                        <strong>Stok masih kosong</strong>
                        <p class="mb-0">Belum ada sampah yang tercatat.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bento-card ops-table" data-aos="fade-up" data-aos-delay="60">
            <div class="card-heading">
                <div>
                    <span class="section-eyebrow"><i class="bi bi-box-seam"></i> Inventaris</span>
                    <h2>Rincian Stok Gudang</h2>
                    <p>Akumulasi berat dan porsi tiap kategori</p>
                </div>
                <a href="<?= $baseUrl ?>/views/Admin/master_harga.php" class="history-link">
                    Kelola Harga <i class="bi bi-arrow-right"></i>
                </a>
            </div>

            <?php if (empty($stokGudang)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="bi bi-inbox"></i></div>
                    <strong>Belum ada data stok</strong>
                    <p>Stok gudang akan tampil setelah ada setoran.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Kategori Sampah</th>
                                <th class="text-end">Total Berat</th>
                                <th class="text-end">Transaksi</th>
                                <th style="min-width: 180px;">Porsi Stok</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stokGudang as $row): ?>
                                <?php $porsi = $totalStok > 0 ? ((float) $row['total_berat'] / $totalStok) * 100 : 0; ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($row['nama_kategori']) ?></strong></td>
                                    <td class="text-end fw-semibold"><?= number_format((float) $row['total_berat'], 2, ',', '.') ?> kg</td>
                                    <td class="text-end text-muted"><?= number_format((int) $row['jumlah_transaksi'], 0, ',', '.') ?> item</td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress flex-grow-1" role="progressbar" aria-label="Porsi <?= htmlspecialchars($row['nama_kategori']) ?>" aria-valuenow="<?= round($porsi) ?>" aria-valuemin="0" aria-valuemax="100">
                                                <div class="progress-bar" style="width: <?= min(100, round($porsi, 1)) ?>%"></div>
                                            </div>
                                            <span class="small fw-semibold text-muted" style="min-width: 42px; text-align: right;"><?= number_format($porsi, 1, ',', '.') ?>%</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
window.RecyCashWeeklyChart = {
    labels: <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>,
    berat: <?= json_encode($chartBerat) ?>,
    nominal: <?= json_encode($chartNominal) ?>,
    transaksi: <?= json_encode($chartTransaksi) ?>
};
window.RecyCashStockChart = {
    labels: <?= json_encode($stokLabels, JSON_UNESCAPED_UNICODE) ?>,
    values: <?= json_encode($stokValues) ?>,
    colors: <?= json_encode($stokColors) ?>
};
</script>

<?php
$extraJS = [
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js',
    $baseUrl . '/assets/js/dashboard.js',
];
require_once __DIR__ . '/../../includes/footer.php';
?>
