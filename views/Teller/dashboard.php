<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<?php
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/lokasi.php';
require_once __DIR__ . '/../../includes/notifikasi.php';

pastikanTabelLokasi($pdo);

$petugasId = (int) $_SESSION['id_user'];
$today = date('Y-m-d');

$stmt = $pdo->prepare("
    SELECT COUNT(*) AS transaksi,
           COALESCE(SUM(total_berat), 0) AS berat,
           COALESCE(SUM(total_nominal), 0) AS nominal,
           COUNT(DISTINCT nasabah_id) AS nasabah
    FROM deposit_transactions
    WHERE petugas_id = :pid AND DATE(created_at) = :today
");
$stmt->execute([':pid' => $petugasId, ':today' => $today]);
$ringkasanHari = $stmt->fetch();

$transaksiHari = (int) ($ringkasanHari['transaksi'] ?? 0);
$sampahHari = (float) ($ringkasanHari['berat'] ?? 0);
$nominalHari = (float) ($ringkasanHari['nominal'] ?? 0);
$nasabahHari = (int) ($ringkasanHari['nasabah'] ?? 0);

$stmt = $pdo->prepare("
    SELECT pr.*, u.nama AS nama_nasabah, u.foto AS foto_nasabah
    FROM pickup_requests pr
    JOIN users u ON u.id = pr.nasabah_id
    WHERE pr.status = 'pending'
    ORDER BY pr.created_at ASC
");
$stmt->execute();
$antrean = $stmt->fetchAll();
$jumlahAntrean = count($antrean);

// Request yang ditugaskan ke teller ini (untuk panel status langsung)
$stmtTugas = $pdo->prepare("
    SELECT pr.*, u.nama AS nama_nasabah, u.foto AS foto_nasabah, u.nomor_hp AS hp_nasabah
    FROM pickup_requests pr
    JOIN users u ON u.id = pr.nasabah_id
    WHERE pr.petugas_id = :pid AND pr.status IN ('pending', 'diproses', 'disetujui')
    ORDER BY pr.created_at ASC
    LIMIT 6
");
$stmtTugas->execute([':pid' => $petugasId]);
$tugasSaya = $stmtTugas->fetchAll();

// Lokasi outlet teller (untuk menghitung jarak)
$lokasiSaya = tellerLokasiAmbil($pdo, $petugasId);

$namaHari = [1 => 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
$mulaiMinggu = (new DateTimeImmutable('today'))->modify('-6 days');
$stmt = $pdo->prepare("
    SELECT DATE(created_at) AS tanggal,
           COALESCE(SUM(total_berat), 0) AS berat,
           COALESCE(SUM(total_nominal), 0) AS nominal,
           COUNT(*) AS transaksi
    FROM deposit_transactions
    WHERE petugas_id = :pid AND created_at >= :mulai
    GROUP BY DATE(created_at)
    ORDER BY tanggal ASC
");
$stmt->execute([
    ':pid' => $petugasId,
    ':mulai' => $mulaiMinggu->format('Y-m-d 00:00:00'),
]);

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
?>

<div class="dashboard-page">
    <div class="dashboard-intro" data-aos="fade-up">
        <div>
            <span class="section-eyebrow"><i class="bi bi-speedometer2"></i> Teller Workspace</span>
            <h1>Siap melayani, <?= htmlspecialchars($nama) ?>?</h1>
            <p>Catat setoran dengan cepat dan pantau antrean penjemputan hari ini.</p>
        </div>
        <span class="intro-badge">
            <i class="bi bi-calendar3"></i>
            <?= date('d M Y') ?>
        </span>
    </div>

    <div class="bento-grid">
        <div class="bento-card ops-hero" data-aos="fade-up" data-aos-delay="60">
            <div class="ops-hero-top">
                <span class="section-eyebrow"><i class="bi bi-clipboard2-check"></i> Performa Hari Ini</span>
                <h1>Setiap setoran yang tercatat membawa dampak nyata.</h1>
                <p>Layani nasabah, timbang sampah dengan teliti, dan pastikan saldo masuk ke akun mereka tanpa hambatan.</p>

                <div class="ops-hero-stats">
                    <div class="ops-hero-stat">
                        <span>Transaksi</span>
                        <strong><?= number_format($transaksiHari, 0, ',', '.') ?>x</strong>
                    </div>
                    <div class="ops-hero-stat">
                        <span>Sampah Tercatat</span>
                        <strong><?= number_format($sampahHari, 1, ',', '.') ?> kg</strong>
                    </div>
                    <div class="ops-hero-stat">
                        <span>Saldo Disalurkan</span>
                        <strong>Rp <?= number_format($nominalHari, 0, ',', '.') ?></strong>
                    </div>
                </div>
            </div>

            <div class="ops-hero-bottom">
                <div class="ops-hero-actions">
                    <a href="<?= $baseUrl ?>/views/Teller/input_setor.php" class="btn-wallet btn-wallet-primary">
                        <i class="bi bi-plus-circle"></i> Input Setoran
                    </a>
                    <a href="<?= $baseUrl ?>/views/Teller/kelola_jemput.php" class="btn-wallet btn-wallet-ghost">
                        <i class="bi bi-truck"></i> Kelola Jemputan
                    </a>
                </div>
            </div>
        </div>

        <div class="bento-card ops-side" data-aos="fade-up" data-aos-delay="120">
            <div class="ops-side-head">
                <span class="section-eyebrow"><i class="bi bi-lightning-charge-fill"></i> Aksi Cepat</span>
                <h2>Menu operasional</h2>
                <p>Akses fitur yang paling sering dipakai.</p>
            </div>

            <div class="quick-action-list">
                <a href="<?= $baseUrl ?>/views/Teller/input_setor.php" class="quick-action">
                    <span class="quick-action-icon"><i class="bi bi-inbox-fill"></i></span>
                    <span class="quick-action-body">
                        <strong>Input Setoran</strong>
                        <span>Timbang dan catat sampah</span>
                    </span>
                    <span class="quick-action-count"><i class="bi bi-arrow-right"></i></span>
                </a>
                <a href="<?= $baseUrl ?>/views/Teller/kelola_jemput.php" class="quick-action">
                    <span class="quick-action-icon"><i class="bi bi-truck"></i></span>
                    <span class="quick-action-body">
                        <strong>Jemputan Sampah</strong>
                        <span>Proses request nasabah</span>
                    </span>
                    <span class="quick-action-count"><?= $jumlahAntrean ?></span>
                </a>
                <a href="<?= $baseUrl ?>/views/Teller/cetak.nota.php" class="quick-action">
                    <span class="quick-action-icon"><i class="bi bi-receipt"></i></span>
                    <span class="quick-action-body">
                        <strong>Cetak Nota</strong>
                        <span>Bukti setoran nasabah</span>
                    </span>
                    <span class="quick-action-count"><i class="bi bi-arrow-right"></i></span>
                </a>
                <a href="<?= $baseUrl ?>/views/Teller/penarikan.php" class="quick-action">
                    <span class="quick-action-icon"><i class="bi bi-wallet2"></i></span>
                    <span class="quick-action-body">
                        <strong>Verifikasi Penarikan</strong>
                        <span>Setujui pengajuan saldo</span>
                    </span>
                    <span class="quick-action-count"><i class="bi bi-arrow-right"></i></span>
                </a>
            </div>
        </div>

        <div class="bento-card ops-kpi" data-aos="fade-up" data-aos-delay="60">
            <div class="ops-kpi-top">
                <span class="ops-kpi-icon"><i class="bi bi-clipboard-check"></i></span>
                <span class="ops-kpi-label">Transaksi Hari Ini</span>
            </div>
            <div>
                <span class="ops-kpi-value"><?= number_format($transaksiHari, 0, ',', '.') ?> transaksi</span>
                <span class="ops-kpi-meta"><i class="bi bi-clock"></i> Diperbarui hari ini</span>
            </div>
        </div>

        <div class="bento-card ops-kpi" data-aos="fade-up" data-aos-delay="105">
            <div class="ops-kpi-top">
                <span class="ops-kpi-icon is-blue"><i class="bi bi-recycle"></i></span>
                <span class="ops-kpi-label">Sampah Hari Ini</span>
            </div>
            <div>
                <span class="ops-kpi-value"><?= number_format($sampahHari, 1, ',', '.') ?> kg</span>
                <span class="ops-kpi-meta"><i class="bi bi-box-seam"></i> Total berat timbangan</span>
            </div>
        </div>

        <div class="bento-card ops-kpi" data-aos="fade-up" data-aos-delay="150">
            <div class="ops-kpi-top">
                <span class="ops-kpi-icon is-amber"><i class="bi bi-people"></i></span>
                <span class="ops-kpi-label">Nasabah Terlayani</span>
            </div>
            <div>
                <span class="ops-kpi-value"><?= number_format($nasabahHari, 0, ',', '.') ?> orang</span>
                <span class="ops-kpi-meta"><i class="bi bi-check2-circle"></i> Nasabah unik hari ini</span>
            </div>
        </div>

        <div class="bento-card ops-kpi" data-aos="fade-up" data-aos-delay="195">
            <div class="ops-kpi-top">
                <span class="ops-kpi-icon is-slate"><i class="bi bi-hourglass-split"></i></span>
                <span class="ops-kpi-label">Antrean Penjemputan</span>
            </div>
            <div>
                <span class="ops-kpi-value"><?= number_format($jumlahAntrean, 0, ',', '.') ?> request</span>
                <span class="ops-kpi-meta"><i class="bi bi-truck"></i> Menunggu diproses</span>
            </div>
        </div>

        <div class="bento-card ops-chart chart-card" data-aos="fade-up" data-aos-delay="60">
            <div class="card-heading">
                <div>
                    <span class="section-eyebrow"><i class="bi bi-graph-up-arrow"></i> Analitik</span>
                    <h2>Tren Setoran Saya</h2>
                    <p>7 hari terakhir, dalam kilogram</p>
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
                        <p class="mb-0">Grafik muncul setelah kamu mencatat setoran.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bento-card queue-card" data-aos="fade-up" data-aos-delay="120">
            <div class="card-heading">
                <div>
                    <span class="section-eyebrow"><i class="bi bi-truck"></i> Antrean</span>
                    <h2>Jemputan Pending</h2>
                    <p>Request yang menunggu diproses</p>
                </div>
                <a href="<?= $baseUrl ?>/views/Teller/kelola_jemput.php" class="history-link">
                    Semua <i class="bi bi-arrow-right"></i>
                </a>
            </div>

            <?php if (empty($antrean)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="bi bi-check2-circle"></i></div>
                    <strong>Tidak ada antrean</strong>
                    <p>Semua request penjemputan sudah ditangani.</p>
                </div>
            <?php else: ?>
                <div class="queue-list">
                    <?php foreach ($antrean as $row): ?>
                        <div class="queue-item">
                            <span class="queue-avatar"><?= htmlspecialchars(strtoupper(substr(trim((string) $row['nama_nasabah']), 0, 1)) ?: 'N') ?></span>
                            <div class="queue-body">
                                <strong><?= htmlspecialchars($row['nama_nasabah']) ?></strong>
                                <span><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($row['alamat_jemput']) ?></span>
                            </div>
                            <div class="queue-meta">
                                <strong><?= $row['estimasi_berat'] ? number_format((float) $row['estimasi_berat'], 1, ',', '.') . ' kg' : '-' ?></strong>
                                <span><?= date('d M', strtotime($row['created_at'])) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="ops-list-note">
                    <i class="bi bi-info-circle"></i> Buka menu Jemputan untuk memproses request.
                </div>
            <?php endif; ?>
        </div>

        <!-- ======================== TUGAS PENJEMPUTAN SAYA ======================== -->
        <div class="bento-card tugascard" data-aos="fade-up" data-aos-delay="120">
            <div class="card-heading">
                <div>
                    <span class="section-eyebrow"><i class="bi bi-geo-alt-fill"></i> Tugas Anda</span>
                    <h2>Penjemputan Ditugaskan ke Anda</h2>
                    <p>Permintaan nasabah yang otomatis ditugaskan ke outlet terdekat Anda.</p>
                </div>
                <a href="<?= $baseUrl ?>/views/Teller/outlet.php" class="history-link">
                    Outlet Saya <i class="bi bi-arrow-right"></i>
                </a>
            </div>

            <?php if ($lokasiSaya === null || (int) $lokasiSaya['status_aktif'] !== 1): ?>
                <div class="alert alert-warning d-flex align-items-center gap-2 py-2 small">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span class="flex-grow-1">Outlet Anda belum aktif. Nasabah tidak dapat menemukan Anda sebagai teller terdekat.</span>
                    <a href="<?= $baseUrl ?>/views/Teller/outlet.php" class="btn btn-sm btn-warning">Aktifkan</a>
                </div>
            <?php endif; ?>

            <?php if (empty($tugasSaya)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="bi bi-check2-circle"></i></div>
                    <strong>Belum ada tugas</strong>
                    <p>Ketika ada 요청 jemputan di dekat outlet Anda, akan muncul di sini secara otomatis.</p>
                </div>
            <?php else: ?>
                <div class="queue-list">
                    <?php foreach ($tugasSaya as $row): ?>
                        <?php
                        $jarak = null;
                        if ($lokasiSaya && $row['latitude'] !== null && $row['longitude'] !== null) {
                            $jarak = haversineKm(
                                (float) $row['latitude'],
                                (float) $row['longitude'],
                                (float) $lokasiSaya['latitude'],
                                (float) $lokasiSaya['longitude']
                            );
                        }
                        $labelStatus = [
                            'pending'   => 'Menunggu Anda',
                            'diproses'  => 'Dalam perjalanan',
                            'disetujui' => 'Disetujui',
                        ][$row['status']] ?? ucfirst((string) $row['status']);
                        ?>
                        <div class="queue-item">
                            <span class="queue-avatar"><?= htmlspecialchars(strtoupper(substr(trim((string) $row['nama_nasabah']), 0, 1)) ?: 'N') ?></span>
                            <div class="queue-body">
                                <strong>JMP-<?= (int) $row['id'] ?> · <?= htmlspecialchars($row['nama_nasabah']) ?></strong>
                                <span><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($row['alamat_jemput']) ?></span>
                                <span>
                                    <i class="bi bi-info-circle"></i> <?= htmlspecialchars($labelStatus) ?>
                                    <?php if ($jarak !== null): ?>
                                        · <?= htmlspecialchars(fmtJarak($jarak)) ?>
                                        · ±<?= (int) ($row['eta_menit'] ?? 0) ?> menit
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="queue-meta">
                                <strong><?= $row['estimasi_berat'] ? number_format((float) $row['estimasi_berat'], 1, ',', '.') . ' kg' : '-' ?></strong>
                                <span class="d-flex gap-1 mt-1">
                                    <a class="btn btn-sm btn-outline-success" title="Chat nasabah"
                                       href="<?= $baseUrl ?>/views/Teller/chat.php?request_id=<?= (int) $row['id'] ?>">
                                        <i class="bi bi-chat-dots-fill"></i>
                                    </a>
                                    <?php if (!empty($row['hp_nasabah'])): ?>
                                        <a class="btn btn-sm btn-outline-success" title="Telepon nasabah"
                                           href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', (string) $row['hp_nasabah'])) ?>">
                                            <i class="bi bi-telephone-fill"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($row['latitude'] !== null && $row['longitude'] !== null): ?>
                                        <a class="btn btn-sm btn-outline-success" title="Navigasi ke lokasi"
                                           target="_blank" rel="noopener"
                                           href="<?= htmlspecialchars(linkNavigasi((float) $row['latitude'], (float) $row['longitude'], (string) $row['alamat_jemput'])) ?>">
                                            <i class="bi bi-map"></i>
                                        </a>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
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
</script>

<?php
$extraJS = [
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js',
    $baseUrl . '/assets/js/dashboard.js',
];
require_once __DIR__ . '/../../includes/footer.php';
?>
