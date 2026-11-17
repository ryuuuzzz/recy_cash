<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<?php
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/gamifikasi.php';
require_once __DIR__ . '/../../includes/pickup_helper.php';
require_once __DIR__ . '/../../includes/notifikasi.php';
require_once __DIR__ . '/../../includes/lokasi.php';

$userId = (int) $_SESSION['id_user'];
$namaNasabah = $nama ?? $_SESSION['nama'] ?? 'Nasabah';

pastikanTabelNotifikasi($pdo);
pastikanTabelLokasi($pdo);

// Notifikasi belum dibaca
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifikasi WHERE user_id = ? AND dibaca = 0");
$stmt->execute([$userId]);
$notifUnread = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT id, pesan, link, created_at FROM notifikasi WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$userId]);
$notifList = $stmt->fetchAll();

// Tandai semua sebagai dibaca
$pdo->prepare("UPDATE notifikasi SET dibaca = 1 WHERE user_id = ? AND dibaca = 0")->execute([$userId]);

// Status penjemputan aktif (kalau sedang berjalan)
$stmtAktif = $pdo->prepare("
    SELECT p.*, u.nama AS nama_petugas, u.nomor_hp AS hp_petugas, u.foto AS foto_petugas
    FROM pickup_requests p
    LEFT JOIN users u ON u.id = p.petugas_id
    WHERE p.nasabah_id = :uid AND p.status IN ('pending', 'diproses', 'disetujui')
    ORDER BY p.created_at DESC
    LIMIT 1
");
$stmtAktif->execute([':uid' => $userId]);
$requestAktif = $stmtAktif->fetch();

$aktifTeller = (string) ($requestAktif['nama_petugas'] ?? '');
$aktifHp     = (string) ($requestAktif['hp_petugas'] ?? '');
$aktifWa     = $requestAktif && $aktifHp !== ''
    ? waLink($aktifHp, 'Halo ' . $aktifTeller . ', saya ' . $namaNasabah . ' dari RecyCash. Mohon info untuk request JMP-' . (int) $requestAktif['id'] . '.')
    : '';
$aktifLabel  = ['pending' => 'Menunggu teller', 'diproses' => 'Teller dalam perjalanan', 'disetujui' => 'Disetujui'][$requestAktif['status'] ?? ''] ?? '';

$stmt = $pdo->prepare("SELECT saldo_rupiah, poin FROM wallets WHERE user_id = :uid LIMIT 1");
$stmt->execute([':uid' => $userId]);
$wallet = $stmt->fetch();

$saldo = (float) ($wallet['saldo_rupiah'] ?? 0);
$poin  = (int) ($wallet['poin'] ?? 0);

$stmt = $pdo->prepare("
    SELECT COUNT(*) AS total_transaksi, COALESCE(SUM(total_berat), 0) AS total_berat
    FROM deposit_transactions
    WHERE nasabah_id = :uid
");
$stmt->execute([':uid' => $userId]);
$statistikSetoran = $stmt->fetch();
$totalTransaksi = (int) ($statistikSetoran['total_transaksi'] ?? 0);
$totalBeratAll = (float) ($statistikSetoran['total_berat'] ?? 0);

$stmt = $pdo->prepare("
    SELECT id, kode_transaksi, total_nominal, total_berat, created_at
    FROM deposit_transactions
    WHERE nasabah_id = :uid
    ORDER BY created_at DESC, id DESC
    LIMIT 5
");
$stmt->execute([':uid' => $userId]);
$riwayat = $stmt->fetchAll();

$detailMap = [];
if (!empty($riwayat)) {
    $ids = array_column($riwayat, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT dd.transaction_id, dd.berat_kg, dd.harga_per_kg, dd.subtotal, cs.nama_kategori
        FROM deposit_details dd
        JOIN categories_sampah cs ON cs.id = dd.category_id
        WHERE dd.transaction_id IN ($placeholders)
        ORDER BY dd.id ASC
    ");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $detail) {
        $detailMap[$detail['transaction_id']][] = $detail;
    }
}

$bulanLabel = [
    1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
    'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des',
];
$bulanPanjang = [
    1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
];

$awalBulan = (new DateTimeImmutable('first day of this month'))->modify('-11 months');
$stmt = $pdo->prepare("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS bulan,
           COALESCE(SUM(total_berat), 0) AS berat,
           COALESCE(SUM(total_nominal), 0) AS nominal,
           COUNT(*) AS transaksi
    FROM deposit_transactions
    WHERE nasabah_id = :uid AND created_at >= :mulai
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY bulan ASC
");
$stmt->execute([
    ':uid'   => $userId,
    ':mulai' => $awalBulan->format('Y-m-d 00:00:00'),
]);

$dataBulanan = [];
foreach ($stmt->fetchAll() as $row) {
    $dataBulanan[$row['bulan']] = $row;
}

$chartLabels = [];
$chartBerat = [];
$chartNominal = [];
$chartTransaksi = [];
for ($i = 0; $i < 12; $i++) {
    $bulan = $awalBulan->modify("+$i months");
    $key = $bulan->format('Y-m');
    $row = $dataBulanan[$key] ?? null;

    $chartLabels[] = $bulanLabel[(int) $bulan->format('n')] . ' ' . $bulan->format('y');
    $chartBerat[] = (float) ($row['berat'] ?? 0);
    $chartNominal[] = (float) ($row['nominal'] ?? 0);
    $chartTransaksi[] = (int) ($row['transaksi'] ?? 0);
}
$chartTotal = array_sum($chartBerat);

$gami = dataGamifikasi($pdo, $userId);
$rank = $gami['rank'];

$hariIni = date('Y-m-d');
$hariMap = array_fill_keys($gami['tanggal_setoran'], true);
$seninTs = strtotime('monday this week');
$namaHari = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
$pekan = [];

for ($i = 0; $i < 7; $i++) {
    $ts = strtotime("+$i day", $seninTs);
    $tgl = date('Y-m-d', $ts);
    $ada = isset($hariMap[$tgl]);
    $masaDepan = $tgl > $hariIni;

    $pekan[] = [
        'tgl'       => $tgl,
        'label'     => $namaHari[$i],
        'tanggal'   => (int) date('j', $ts),
        'ada'       => $ada,
        'hari_ini'  => $tgl === $hariIni,
        'masa_depan' => $masaDepan,
    ];
}

$hariMingguIni = count(array_filter($pekan, static function (array $hari): bool {
    return $hari['ada'];
}));
$progressMinggu = (int) round(($hariMingguIni / 7) * 100);

$nextRankMin = (int) ($rank['selanjutnya']['min'] ?? 0);
$progressRank = $nextRankMin > 0
    ? min(100, max(0, (int) round(((int) $gami['streak_hari'] / $nextRankMin) * 100)))
    : 100;

function tanggalIndo(string $tanggal, array $namaBulan, bool $denganJam = true): string
{
    $ts = strtotime($tanggal);
    $hasil = date('d', $ts) . ' ' . $namaBulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);
    return $denganJam ? $hasil . ', ' . date('H:i', $ts) : $hasil;
}
?>

<div class="dashboard-page">
    <div class="dashboard-intro" data-aos="fade-up">
        <div>
            <span class="section-eyebrow"><i class="bi bi-leaf-fill"></i> Eco-Fintech Dashboard</span>
            <h1>Halo, <?= htmlspecialchars($namaNasabah) ?></h1>
            <p>Pantau saldo, poin, dan perjalanan hijau mu dalam satu tampilan.</p>
        </div>
        <span class="intro-badge">
            <i class="bi bi-calendar3"></i>
            <?= tanggalIndo(date('Y-m-d'), $bulanPanjang, false) ?>
        </span>
    </div>

    <div class="bento-grid">
        <div class="bento-card bento-wallet wallet-card" data-aos="fade-up" data-aos-delay="60">
            <div class="wallet-top">
                <div class="wallet-brand">
                    <div class="wallet-brand-icon"><i class="bi bi-recycle"></i></div>
                    <div class="wallet-brand-text">
                        <strong>RecyCash Wallet</strong>
                        <span>Digital Eco Card</span>
                    </div>
                </div>
                <span class="wallet-chip"><span class="chip-dot"></span> Nasabah</span>
            </div>

            <div class="wallet-balance">
                <span class="wallet-balance-label">Saldo Rupiah</span>
                <div class="wallet-balance-value">
                    <span class="currency">Rp</span>
                    <span data-countup="<?= (int) round($saldo) ?>" data-duration="1500"><?= number_format($saldo, 0, ',', '.') ?></span>
                </div>
            </div>

            <div class="wallet-bottom">
                <div class="wallet-meta">
                    <div class="wallet-meta-item">
                        <span>Pemilik</span>
                        <strong><?= htmlspecialchars($namaNasabah) ?></strong>
                    </div>
                    <div class="wallet-meta-item">
                        <span>ID Nasabah</span>
                        <strong>NSB-<?= str_pad((string) $userId, 4, '0', STR_PAD_LEFT) ?></strong>
                    </div>
                </div>
                <div class="wallet-actions">
                    <a href="<?= $baseUrl ?>/views/Nasabah/penarikan.php" class="btn-wallet btn-wallet-primary">
                        <i class="bi bi-cash-coin"></i> Tarik Saldo
                    </a>
                    <button type="button" class="btn-wallet btn-wallet-ghost" data-bs-toggle="modal" data-bs-target="#modalQris">
                        <i class="bi bi-qr-code-scan"></i> QRIS
                    </button>
                </div>
            </div>
        </div>

        <div class="bento-card bento-points points-card" data-aos="fade-up" data-aos-delay="120">
            <div class="points-head">
                <div class="points-icon"><i class="bi bi-stars"></i></div>
                <span class="points-pill">Loyalty</span>
            </div>

            <div class="points-title">Kartu Poin Loyalitas</div>
            <div class="points-value">
                <span data-countup="<?= $poin ?>" data-duration="1500"><?= number_format($poin, 0, ',', '.') ?></span>
                <small>Poin</small>
            </div>

            <?php if (!empty($rank['selanjutnya'])): ?>
                <div class="points-track">
                    <span style="width: <?= $progressRank ?>%"></span>
                </div>
                <div class="points-caption">
                    <span>Menuju <?= htmlspecialchars($rank['selanjutnya']['nama']) ?></span>
                    <strong><?= (int) $gami['streak_hari'] ?>/<?= (int) $rank['selanjutnya']['min'] ?> hari</strong>
                </div>
            <?php else: ?>
                <div class="points-track">
                    <span style="width: 100%"></span>
                </div>
                <div class="points-caption">
                    <span>Rank tertinggi sudah tercapai</span>
                    <strong>Maksimal</strong>
                </div>
            <?php endif; ?>

            <div class="points-stats">
                <div class="point-stat">
                    <span>Total Setoran</span>
                    <strong><?= number_format($totalTransaksi, 0, ',', '.') ?>x</strong>
                </div>
                <div class="point-stat">
                    <span>Total Sampah</span>
                    <strong><?= number_format($totalBeratAll, 1, ',', '.') ?> kg</strong>
                </div>
            </div>
        </div>

        <div class="bento-card bento-streak streak-card" data-aos="fade-up" data-aos-delay="180">
            <div class="streak-head">
                <div>
                    <span class="section-eyebrow"><i class="bi bi-trophy-fill"></i> Gamifikasi</span>
                    <h2>Streak Mingguan</h2>
                    <p>Setor sampah setiap hari untuk menjaga api hijau mu tetap menyala.</p>
                </div>
                <span class="rank-chip" style="--rank-color: <?= htmlspecialchars($rank['warna']) ?>;">
                    <i class="bi <?= htmlspecialchars($rank['ikon']) ?>"></i>
                    <?= htmlspecialchars($rank['nama']) ?>
                </span>
            </div>

            <div class="streak-progress-head">
                <span>Progress minggu ini</span>
                <strong><?= $hariMingguIni ?>/7 hari</strong>
            </div>
            <div class="progress streak-progress" role="progressbar" aria-label="Progress streak mingguan" aria-valuenow="<?= $progressMinggu ?>" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar" style="width: <?= $progressMinggu ?>%"></div>
            </div>

            <div class="streak-days">
                <?php foreach ($pekan as $hari): ?>
                    <?php
                    $kelas = [];
                    if ($hari['ada']) {
                        $kelas[] = 'is-done';
                    }
                    if ($hari['hari_ini']) {
                        $kelas[] = 'is-today';
                    } elseif ($hari['masa_depan']) {
                        $kelas[] = 'is-locked';
                    } elseif (!$hari['ada']) {
                        $kelas[] = 'is-missed';
                    }

                    if ($hari['ada']) {
                        $ikon = 'bi-check-lg';
                        $judul = 'Setor pada ' . tanggalIndo($hari['tgl'], $bulanPanjang, false);
                    } elseif ($hari['masa_depan']) {
                        $ikon = 'bi-lock-fill';
                        $judul = 'Terkunci, belum waktunya';
                    } elseif ($hari['hari_ini']) {
                        $ikon = 'bi-brightness-high-fill';
                        $judul = 'Hari ini, belum ada setoran';
                    } else {
                        $ikon = 'bi-dot';
                        $judul = 'Belum setor';
                    }
                    ?>
                    <div class="streak-day <?= implode(' ', $kelas) ?>" title="<?= htmlspecialchars($judul) ?>">
                        <div class="streak-circle"><i class="bi <?= $ikon ?>"></i></div>
                        <span class="streak-day-label"><?= $hari['label'] ?></span>
                        <span class="streak-date"><?= $hari['tanggal'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="streak-foot">
                <span class="streak-fire"><i class="bi bi-fire"></i> <?= (int) $gami['streak_hari'] ?> hari beruntun</span>
                <span>
                    <?php if (!empty($rank['selanjutnya'])): ?>
                        <strong><?= max(0, (int) $rank['selanjutnya']['min'] - (int) $gami['streak_hari']) ?> hari lagi</strong> menuju <?= htmlspecialchars($rank['selanjutnya']['nama']) ?>
                    <?php else: ?>
                        Kamu sudah berada di rank tertinggi. Pertahankan konsistensinya!
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <!-- ======================== NOTIFIKASI ======================== -->
        <?php if (!empty($notifList)): ?>
        <div class="bento-card bento-notif" data-aos="fade-up" data-aos-delay="220">
            <div class="card-heading">
                <div>
                    <span class="section-eyebrow"><i class="bi bi-bell"></i> Notifikasi</span>
                    <h2>Pemberitahuan Terbaru
                        <?php if ($notifUnread > 0): ?>
                            <span class="badge text-bg-danger ms-2"><?= $notifUnread ?></span>
                        <?php endif; ?>
                    </h2>
                </div>
            </div>
            <div class="list-group list-group-flush notif-list">
                <?php foreach ($notifList as $n): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <div class="small"><?= htmlspecialchars($n['pesan']) ?></div>
                            <small class="text-muted"><?= date('d M Y H:i', strtotime($n['created_at'])) ?></small>
                        </div>
                        <?php if (!empty($n['link'])): ?>
                            <a href="<?= $baseUrl ?>/views/Nasabah/<?= htmlspecialchars($n['link']) ?>" class="btn btn-sm btn-outline-success flex-shrink-0">Lihat</a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ======================== PENJEMPUTAN AKTIF ======================== -->
        <?php if ($requestAktif): ?>
        <div class="bento-card bento-notif" data-aos="fade-up" data-aos-delay="180">
            <div class="card-heading">
                <div>
                    <span class="section-eyebrow"><i class="bi bi-broadcast-pin"></i> Penjemputan</span>
                    <h2>Status Penjemputan Aktif</h2>
                </div>
                <span class="badge text-bg-success" id="badgeJemputAktif"><?= htmlspecialchars($aktifLabel) ?></span>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-3 justify-content-between">
                <div class="d-flex align-items-center gap-3 min-w-0">
                    <?= renderAvatar($aktifTeller !== '' ? $aktifTeller : 'Teller', (string) ($requestAktif['foto_petugas'] ?? ''), $baseUrl, 46) ?>
                    <div class="min-w-0">
                        <strong class="d-block"><?= htmlspecialchars($aktifTeller !== '' ? $aktifTeller : 'Mencari teller terdekat...') ?></strong>
                        <small class="text-muted" id="infoJemputAktif">
                            Kode JMP-<?= (int) $requestAktif['id'] ?>
                            <?php if (!empty($requestAktif['eta_menit'])): ?>
                                · estimasi tiba ±<?= (int) $requestAktif['eta_menit'] ?> menit
                            <?php endif; ?>
                        </small>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-sm btn-success" href="<?= $baseUrl ?>/views/Nasabah/chat.php?request_id=<?= (int) $requestAktif['id'] ?>">
                        <i class="bi bi-chat-dots-fill"></i> Chat
                    </a>
                    <a class="btn btn-sm btn-outline-success" id="telJemputAktif"
                       href="<?= $aktifHp !== '' ? 'tel:' . htmlspecialchars(preg_replace('/[^0-9+]/', '', $aktifHp)) : '#' ?>">
                        <i class="bi bi-telephone-fill"></i> Telepon
                    </a>
                    <a class="btn btn-sm btn-outline-success" target="_blank" rel="noopener"
                       href="<?= htmlspecialchars($aktifWa !== '' ? $aktifWa : '#') ?>">
                        <i class="bi bi-whatsapp"></i> WhatsApp
                    </a>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= $baseUrl ?>/views/Nasabah/request_jemput.php">
                        <i class="bi bi-arrow-right"></i> Detail
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="bento-card bento-chart chart-card" data-aos="fade-up" data-aos-delay="240">
            <div class="card-heading">
                <div>
                    <span class="section-eyebrow"><i class="bi bi-graph-up-arrow"></i> Analitik</span>
                    <h2>Tren Setoran Bulanan</h2>
                    <p>12 bulan terakhir, dalam kilogram</p>
                </div>
                <div class="chart-highlight">
                    <strong><?= number_format($chartTotal, 1, ',', '.') ?> kg</strong>
                    <span>Total 12 bulan</span>
                </div>
            </div>
            <div class="chart-wrap">
                <canvas id="chartBulanan" aria-label="Grafik tren setoran sampah bulanan" role="img"></canvas>
                <div class="chart-empty d-none" id="chartEmpty">
                    <i class="bi bi-bar-chart"></i>
                    <div>
                        <strong>Belum ada data setoran</strong>
                        <p class="mb-0">Grafik akan muncul setelah kamu melakukan setoran pertama.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bento-card bento-history history-card" data-aos="fade-up" data-aos-delay="300">
            <div class="card-heading">
                <div>
                    <span class="section-eyebrow"><i class="bi bi-clock-history"></i> Aktivitas</span>
                    <h2>Transaksi Terakhir</h2>
                    <p>Klik baris untuk melihat rincian setoran</p>
                </div>
                <a href="<?= $baseUrl ?>/views/Nasabah/riwayat.php" class="history-link">
                    Semua <i class="bi bi-arrow-right"></i>
                </a>
            </div>

            <?php if (empty($riwayat)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="bi bi-inbox"></i></div>
                    <strong>Belum ada transaksi</strong>
                    <p>Transaksi setoran pertamamu akan tampil di sini.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table history-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Kode / Tanggal</th>
                                <th class="text-end">Berat</th>
                                <th class="text-end">Nominal</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($riwayat as $row): ?>
                                <?php
                                $detailItems = $detailMap[$row['id']] ?? [];
                                $detailJson = htmlspecialchars(
                                    json_encode($detailItems, JSON_UNESCAPED_UNICODE),
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>
                                <tr class="history-row"
                                    tabindex="0"
                                    role="button"
                                    aria-label="Lihat detail transaksi <?= htmlspecialchars($row['kode_transaksi']) ?>"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalDetailSetoran"
                                    data-kode="<?= htmlspecialchars($row['kode_transaksi']) ?>"
                                    data-tanggal="<?= htmlspecialchars(tanggalIndo($row['created_at'], $bulanPanjang)) ?>"
                                    data-berat="<?= (float) $row['total_berat'] ?>"
                                    data-nominal="<?= (float) $row['total_nominal'] ?>"
                                    data-detail="<?= $detailJson ?>">
                                    <td>
                                        <span class="trx-code"><?= htmlspecialchars($row['kode_transaksi']) ?></span>
                                        <span class="trx-date"><?= tanggalIndo($row['created_at'], $bulanLabel, false) ?></span>
                                    </td>
                                    <td class="text-end fw-semibold"><?= number_format((float) $row['total_berat'], 2, ',', '.') ?> kg</td>
                                    <td class="text-end trx-amount">Rp <?= number_format((float) $row['total_nominal'], 0, ',', '.') ?></td>
                                    <td class="text-end"><i class="bi bi-chevron-right trx-chevron"></i></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="modalDetailSetoran" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <span class="section-eyebrow">Detail Setoran</span>
                    <h5 class="modal-title mb-0" id="detailModalLabel">
                        <span id="detailKode">-</span>
                    </h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="detail-summary">
                    <div class="detail-summary-item">
                        <span>Tanggal</span>
                        <strong id="detailTanggal">-</strong>
                    </div>
                    <div class="detail-summary-item">
                        <span>Total Berat</span>
                        <strong id="detailBerat">-</strong>
                    </div>
                    <div class="detail-summary-item">
                        <span>Total Nominal</span>
                        <strong id="detailNominal" class="text-success">-</strong>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table detail-table mb-0">
                        <thead>
                            <tr>
                                <th>Jenis Sampah</th>
                                <th class="text-end">Berat</th>
                                <th class="text-end">Harga/kg</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody id="detailItems"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <a href="<?= $baseUrl ?>/views/Nasabah/riwayat.php" class="btn-eco btn-eco-soft">
                    <i class="bi bi-clock-history"></i> Riwayat Lengkap
                </a>
                <button type="button" class="btn-eco" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalQris" tabindex="-1" aria-labelledby="qrisModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <span class="section-eyebrow">Pembayaran Digital</span>
                    <h5 class="modal-title mb-0" id="qrisModalLabel">QRIS RecyCash</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="qris-box">
                    <div class="qris-icon"><i class="bi bi-qr-code-scan"></i></div>
                    <h6>QRIS segera hadir</h6>
                    <p>Pembayaran dan penarikan via QRIS sedang disiapkan. Sementara ini, kamu bisa menarik saldo ke rekening bank, e-wallet, atau tunai di lokasi.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-eco btn-eco-soft" data-bs-dismiss="modal">Nanti Saja</button>
                <a href="<?= $baseUrl ?>/views/Nasabah/penarikan.php" class="btn-eco">
                    <i class="bi bi-cash-coin"></i> Tarik Saldo
                </a>
            </div>
        </div>
    </div>
</div>

<script>
window.RecyCashMonthlyChart = {
    labels: <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>,
    berat: <?= json_encode($chartBerat) ?>,
    nominal: <?= json_encode($chartNominal) ?>,
    transaksi: <?= json_encode($chartTransaksi) ?>
};
</script>

<?php
if (!empty($requestAktif)): ?>
<script>
(function () {
    var requestId = <?= (int) $requestAktif['id'] ?>;
    var baseUrl = '<?= htmlspecialchars($baseUrl) ?>';

    setInterval(function () {
        fetch(baseUrl + '/api/pickup_status.php?id=' + requestId, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || !res.ok) {
                    return;
                }

                var badge = document.getElementById('badgeJemputAktif');
                var info  = document.getElementById('infoJemputAktif');

                var label = res.status_teks + (res.tiba ? ' — teller sudah tiba' : '');
                if (badge && badge.textContent !== label) {
                    badge.textContent = label;
                }

                if (info) {
                    var teks = 'Kode JMP-' + requestId;
                    if (res.tiba) {
                        teks += ' · teller sudah tiba di lokasi';
                    } else if (res.jarak_teks) {
                        teks += ' · ' + res.jarak_teks + ' · ±' + res.eta_menit + ' menit';
                    }
                    info.textContent = teks;
                }

                if (res.teller && res.teller.telepon) {
                    var tel = document.getElementById('telJemputAktif');
                    if (tel) {
                        tel.href = 'tel:' + res.teller.telepon.replace(/[^0-9+]/g, '');
                    }
                }
            })
            .catch(function () {});
    }, 10000);
})();
</script>
<?php endif; ?>

<?php
$extraJS = [
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js',
    $baseUrl . '/assets/js/dashboard.js',
];
require_once __DIR__ . '/../../includes/footer.php';
?>
