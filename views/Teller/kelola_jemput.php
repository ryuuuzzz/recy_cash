<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<?php
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/pickup_helper.php';
require_once __DIR__ . '/../../includes/lokasi.php';

// Pastikan kolom opsional tersedia
pastikanKolomPickup($pdo, 'jadwal_jemput', 'DATETIME NULL');
pastikanKolomPickup($pdo, 'rating', 'TINYINT NULL');
pastikanKolomPickup($pdo, 'ulasan', 'TEXT NULL');
pastikanKolomPickup($pdo, 'catatan_penolakan', 'VARCHAR(255) NULL');
pastikanTabelLokasi($pdo);
require_once __DIR__ . '/../../includes/notifikasi.php';

// 1. Proteksi akses khusus petugas (Teller)
if (($role ?? null) !== 'petugas') {
    header('Location: ' . $baseUrl . '/auth/login.php');
    exit;
}

$petugasId = (int) ($_SESSION['id_user'] ?? 0);

// Deteksi kolom catatan_penolakan (fitur opsional; aman bila migrasi belum dijalankan)
function kolomAda(PDO $pdo, string $table, string $kolom): bool {
    $s = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE :k");
    $s->execute([':k' => $kolom]);
    return (bool) $s->fetch();
}
$adaCatatanTolak = kolomAda($pdo, 'pickup_requests', 'catatan_penolakan');

// 2. Flash message
$flashError   = $_SESSION['error'] ?? '';
$flashSuccess = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);

// 3. Proses aksi status (PRG pattern)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id      = (int) ($_POST['id_request'] ?? 0);
    $aksi    = $_POST['aksi'] ?? '';
    $catatan = trim($_POST['catatan_penolakan'] ?? '');

    if ($id <= 0) {
        $_SESSION['error'] = "Request tidak valid.";
    } else {
        // Pastikan request ada
        $cek = $pdo->prepare("SELECT * FROM pickup_requests WHERE id = ?");
        $cek->execute([$id]);
        $req = $cek->fetch(PDO::FETCH_ASSOC);

        if (!$req) {
            $_SESSION['error'] = "Request penjemputan tidak ditemukan.";
        } else {
            $statusSekarang = (string) $req['status'];
            $nasabahId      = (int) ($req['nasabah_id'] ?? 0);
            $ditugaskan     = (int) ($req['petugas_id'] ?? 0);
            $isSuper        = !empty($_SESSION['is_super_admin']);

            $transisi = [
                'proses'  => ['from' => ['pending'],             'ke' => 'diproses'],
                'setujui' => ['from' => ['pending', 'diproses'], 'ke' => 'disetujui'],
                'selesai' => ['from' => ['disetujui'],           'ke' => 'selesai'],
                'tolak'   => ['from' => ['pending', 'diproses'], 'ke' => 'ditolak'],
            ];

            // Satu request hanya boleh ditangani satu teller (selama belum 'pending')
            $dikunciOlehLain = !$isSuper
                && $ditugaskan > 0
                && $ditugaskan !== $petugasId
                && $statusSekarang !== 'pending';

            if ($dikunciOlehLain) {
                $_SESSION['error'] = "Request JMP-{$id} sedang ditangani teller lain. Hubungi admin bila perlu.";
            } elseif ($aksi === 'tiba') {
                // Teller menyatakan sudah tiba di lokasi (status tetap berjalan)
                if (!in_array($statusSekarang, ['pending', 'diproses'], true)) {
                    $_SESSION['error'] = "Status request saat ini <strong>" . htmlspecialchars($statusSekarang)
                        . "</strong> tidak memungkinkan aksi <strong>tiba</strong>.";
                } else {
                    $pdo->prepare("UPDATE pickup_requests SET tiba_at = NOW(), status = 'diproses' WHERE id = :id")
                        ->execute([':id' => $id]);

                    $_SESSION['success'] = "Teller telah tiba di lokasi untuk request <strong>JMP-{$id}</strong>.";

                    auditCatat($pdo, $petugasId, 'TIBA_LOKASI', 'pickup_requests', $id,
                        'Teller menyatakan sudah tiba di lokasi');

                    if ($nasabahId > 0) {
                        $pesanNotif = "Teller Anda sudah tiba di lokasi untuk request JMP-{$id}. Silakan siapkan sampah Anda.";
                        $htmlEmail = '<div style="font-family:Arial,sans-serif;font-size:14px;line-height:1.6">'
                            . '<h3 style="color:#2e7d32;margin:0 0 8px">RecyCash</h3>'
                            . '<p>' . htmlspecialchars($pesanNotif) . '</p>'
                            . '<p style="color:#64748b">Buka aplikasi RecyCash untuk chatting atau menelepon teller.</p></div>';
                        notifKirim($pdo, $nasabahId, $pesanNotif, 'request_jemput.php',
                            'RecyCash - Teller Telah Tiba JMP-' . $id, $htmlEmail);
                    }
                }
            } elseif (!isset($transisi[$aksi])) {
                $_SESSION['error'] = "Aksi tidak dikenal.";
            } elseif (!in_array($statusSekarang, $transisi[$aksi]['from'], true)) {
                $_SESSION['error'] = "Status request saat ini <strong>" . htmlspecialchars($statusSekarang)
                    . "</strong> tidak memungkinkan aksi <strong>" . htmlspecialchars($aksi) . "</strong>.";
            } elseif ($aksi === 'tolak' && $adaCatatanTolak && $catatan === '') {
                $_SESSION['error'] = "Alasan penolakan wajib diisi.";
                $_SESSION['old_tolak'] = $id;
            } else {
                $newStatus = $transisi[$aksi]['ke'];
                try {
                    if ($aksi === 'tolak') {
                        // Coba alihkan ke teller terdekat berikutnya (ala ojek online)
                        $tellerBerikut = null;

                        if ($req['latitude'] !== null && $req['longitude'] !== null) {
                            $tellerBerikut = tellerPilihTerdekat(
                                $pdo,
                                (float) $req['latitude'],
                                (float) $req['longitude'],
                                $ditugaskan > 0 ? $ditugaskan : $petugasId
                            );
                        }

                        if ($tellerBerikut !== null) {
                            $pdo->prepare(
                                "UPDATE pickup_requests
                                 SET status = 'pending', petugas_id = :p, eta_menit = :eta, assign_at = NOW(), tiba_at = NULL, catatan_penolakan = :c
                                 WHERE id = :id"
                            )->execute([
                                ':p'   => (int) $tellerBerikut['user_id'],
                                ':eta' => (int) $tellerBerikut['eta_menit'],
                                ':c'   => $catatan !== '' ? $catatan : null,
                                ':id'  => $id,
                            ]);

                            $_SESSION['success'] = "Request <strong>JMP-{$id}</strong> dialihkan ke teller terdekat berikutnya: "
                                . htmlspecialchars($tellerBerikut['nama']) . " (" . htmlspecialchars(fmtJarak($tellerBerikut['jarak_km'])) . ").";

                            $pesanTeller = "Permintaan jemputan JMP-{$id} dialihkan ke Anda, jarak " . fmtJarak($tellerBerikut['jarak_km']) . ".";
                            $htmlTeller = '<div style="font-family:Arial,sans-serif;font-size:14px;line-height:1.6">'
                                . '<h3 style="color:#2e7d32;margin:0 0 8px">RecyCash</h3>'
                                . '<p>Ada permintaan jemputan yang perlu Anda layani.</p>'
                                . '<ul><li>Kode: JMP-' . $id . '</li>'
                                . '<li>Jarak: ' . fmtJarak($tellerBerikut['jarak_km']) . '</li></ul></div>';
                            notifKirim($pdo, (int) $tellerBerikut['user_id'], $pesanTeller, 'kelola_jemput.php',
                                'RecyCash - Permintaan Jemputan JMP-' . $id, $htmlTeller);

                            if ($nasabahId > 0) {
                                $pesanNasabah = "Permintaan JMP-{$id} diteruskan ke teller terdekat berikutnya: "
                                    . $tellerBerikut['nama'] . " (" . fmtJarak($tellerBerikut['jarak_km']) . ").";
                                notifKirim($pdo, $nasabahId, $pesanNasabah, 'request_jemput.php',
                                    'RecyCash - Update Penjemputan JMP-' . $id);
                            }

                            auditCatat($pdo, $petugasId, 'REASSIGN_TELLER', 'pickup_requests', $id,
                                'Dialihkan ke teller ID ' . (int) $tellerBerikut['user_id'] . ', jarak ' . fmtJarak($tellerBerikut['jarak_km']));
                        } else {
                            // Tidak ada teller lain -> request benar-benar ditolak
                            if ($adaCatatanTolak) {
                                $pdo->prepare("UPDATE pickup_requests SET status = :s, catatan_penolakan = :c WHERE id = :id")
                                    ->execute([':s' => 'ditolak', ':c' => $catatan !== '' ? $catatan : null, ':id' => $id]);
                            } else {
                                $pdo->prepare("UPDATE pickup_requests SET status = :s WHERE id = :id")
                                    ->execute([':s' => 'ditolak', ':id' => $id]);
                            }

                            $_SESSION['success'] = "Request <strong>JMP-{$id}</strong> berhasil ditolak.";

                            if ($nasabahId > 0) {
                                $pesanNotif = "Permintaan jemputan JMP-{$id} ditolak"
                                    . ($catatan !== '' ? ": " . $catatan : "") . ".";
                                notifKirim($pdo, $nasabahId, $pesanNotif, 'request_jemput.php',
                                    'RecyCash - Permintaan Ditolak JMP-' . $id);
                            }

                            auditCatat($pdo, $petugasId, 'UPDATE_STATUS_TOLAK', 'pickup_requests', $id,
                                'Status: ' . $statusSekarang . ' -> ditolak');
                        }
                    } elseif ($aksi === 'proses') {
                        // Petugas yang memproses menjadi penanggung jawab;
                        // bila teller terdekat sudah ditugaskan, kepemilikan tetap pada teller tersebut.
                        $sql = "UPDATE pickup_requests SET status = :s, petugas_id = :p WHERE id = :id";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([
                            ':s'  => $newStatus,
                            ':p'  => $ditugaskan > 0 ? $ditugaskan : $petugasId,
                            ':id' => $id,
                        ]);

                        $labelAksi = 'sedang diproses';
                        $_SESSION['success'] = "Request <strong>JMP-" . $id . "</strong> berhasil " . $labelAksi . ".";

                        auditCatat($pdo, $petugasId, 'UPDATE_STATUS_PROSES', 'pickup_requests', $id,
                            "Status: " . $statusSekarang . " -> " . $newStatus);

                        // Kabari nasabah bahwa teller sedang menuju lokasi
                        if ($nasabahId > 0) {
                            $eta = (int) ($req['eta_menit'] ?? 0);
                            $pesanNotif = "Teller sedang menuju lokasi Anda untuk request JMP-{$id}."
                                . ($eta > 0 ? " Estimasi tiba " . $eta . " menit." : "");
                            $htmlEmail = '<div style="font-family:Arial,sans-serif;font-size:14px;line-height:1.6">'
                                . '<h3 style="color:#2e7d32;margin:0 0 8px">RecyCash</h3>'
                                . '<p>' . htmlspecialchars($pesanNotif) . '</p>'
                                . '<p style="color:#64748b">Anda bisa chatting atau menelepon teller melalui aplikasi.</p></div>';
                            notifKirim($pdo, $nasabahId, $pesanNotif, 'request_jemput.php',
                                'RecyCash - Teller Sedang Berjalan JMP-' . $id, $htmlEmail);
                        }
                    } elseif ($aksi === 'tolak') {
                        // (tidak tercapai, sudah handled di atas)
                        $sql = "UPDATE pickup_requests SET status = :s WHERE id = :id";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([':s' => $newStatus, ':id' => $id]);
                    } else {
                        $sql = "UPDATE pickup_requests SET status = :s WHERE id = :id";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([':s' => $newStatus, ':id' => $id]);

                        $labelAksi = [
                            'proses'  => 'sedang diproses',
                            'setujui' => 'disetujui',
                            'selesai' => 'diselesaikan',
                            'tolak'   => 'ditolak',
                        ][$aksi];
                        $_SESSION['success'] = "Request <strong>JMP-" . $id . "</strong> berhasil " . $labelAksi . ".";

                        auditCatat($pdo, $petugasId, 'UPDATE_STATUS_' . strtoupper($aksi), 'pickup_requests', $id,
                            "Status: " . $statusSekarang . " -> " . $newStatus);

                        if ($nasabahId > 0) {
                            $labelStatus = ['pending' => 'Pending', 'diproses' => 'Diproses', 'disetujui' => 'Disetujui', 'selesai' => 'Selesai', 'ditolak' => 'Ditolak'][$newStatus] ?? ucfirst($newStatus);
                            $pesanNotif = "Status penjemputan JMP-{$id} berubah menjadi {$labelStatus}.";
                            $htmlEmail = '<div style="font-family:Arial,sans-serif;font-size:14px;line-height:1.6">'
                                . '<h3 style="color:#2e7d32;margin:0 0 8px">RecyCash</h3>'
                                . '<p>' . htmlspecialchars($pesanNotif) . '</p>'
                                . '<p style="color:#64748b">Buka aplikasi RecyCash untuk melihat detail penjemputan.</p></div>';
                            notifKirim($pdo, $nasabahId, $pesanNotif, 'request_jemput.php',
                                'RecyCash - Status Penjemputan JMP-' . $id, $htmlEmail);
                        }
                    }
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Gagal memperbarui status: " . htmlspecialchars($e->getMessage());
                }
            }
        }
    }

    // Jika aksi "selesai", langsung arahkan ke halaman setor dengan identitas nasabah
    if (isset($_POST['aksi']) && $_POST['aksi'] === 'selesai' && !empty($_SESSION['success'])) {
        $nasabahId = (int) ($req['nasabah_id'] ?? 0);
        if ($nasabahId > 0) {
            header('Location: input_setor.php?nasabah_id=' . $nasabahId);
            exit;
        }
    }
    header('Location: kelola_jemput.php');
    exit;
}

// 4. Filter status + pencarian
$filterStatus = $_GET['status'] ?? 'semua';
$allowedFilter = ['semua', 'pending', 'diproses', 'disetujui', 'ditolak', 'selesai'];
if (!in_array($filterStatus, $allowedFilter, true)) {
    $filterStatus = 'semua';
}
$cari = trim($_GET['cari'] ?? '');

// 5. Statistik ringkas
$stmt = $pdo->query("
    SELECT
        COUNT(*) AS total,
        COUNT(CASE WHEN status = 'pending'   THEN 1 END) AS pending,
        COUNT(CASE WHEN status = 'diproses'  THEN 1 END) AS diproses,
        COUNT(CASE WHEN status = 'disetujui' THEN 1 END) AS disetujui,
        COUNT(CASE WHEN status = 'selesai'   THEN 1 END) AS selesai,
        COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) AS hari_ini
    FROM pickup_requests
");
$stat = $stmt->fetch(PDO::FETCH_ASSOC);

// 6. Daftar request (filter + search)
$where  = [];
$params = [];
if ($filterStatus !== 'semua') {
    $where[]  = "p.status = :status";
    $params[':status'] = $filterStatus;
}
if ($cari !== '') {
    $where[] = "(u.nama LIKE :cari OR p.alamat_jemput LIKE :cari OR CONCAT('JMP-', p.id) LIKE :cari)";
    $params[':cari'] = '%' . $cari . '%';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$kolomExtra = $adaCatatanTolak ? ', p.catatan_penolakan' : '';
$sql = "SELECT p.*, u.nama AS nama_nasabah, u.nomor_hp AS hp_nasabah, u.alamat AS alamat_nasabah, u.foto AS foto_nasabah,
               pg.nama AS nama_petugas {$kolomExtra}
        FROM pickup_requests p
        INNER JOIN users u ON u.id = p.nasabah_id
        LEFT JOIN users pg ON pg.id = p.petugas_id
        {$whereSql}
        ORDER BY FIELD(p.status, 'pending', 'diproses', 'disetujui', 'ditolak', 'selesai'), p.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Peta lokasi outlet teller (untuk menghitung jarak ke lokasi penjemputan)
$lokasiTeller = [];
try {
    foreach ($pdo->query("SELECT user_id, latitude, longitude, nama_outlet FROM teller_locations")->fetchAll(PDO::FETCH_ASSOC) as $t) {
        $lokasiTeller[(int) $t['user_id']] = $t;
    }
} catch (PDOException $e) {
    // abaikan
}

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
?>

<?php if ($flashError): ?>
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-start gap-2" role="alert">
        <i class="bi bi-x-circle mt-1"></i>
        <div class="flex-grow-1"><?= $flashError ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($flashSuccess): ?>
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-start gap-2" role="alert">
        <i class="bi bi-check-circle mt-1"></i>
        <div class="flex-grow-1"><?= $flashSuccess ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- ======================== STATISTIK ======================== -->
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-2">
        <div class="card stat-card">
            <div class="stat-icon bg-secondary"><i class="bi bi-inbox"></i></div>
            <div class="stat-info">
                <span class="stat-label">Total</span>
                <span class="stat-value"><?= (int) $stat['total'] ?></span>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="card stat-card">
            <div class="stat-icon bg-warning"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-info">
                <span class="stat-label">Pending</span>
                <span class="stat-value"><?= (int) $stat['pending'] ?></span>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="card stat-card">
            <div class="stat-icon bg-info"><i class="bi bi-gear"></i></div>
            <div class="stat-info">
                <span class="stat-label">Diproses</span>
                <span class="stat-value"><?= (int) $stat['diproses'] ?></span>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="card stat-card">
            <div class="stat-icon bg-primary"><i class="bi bi-check-circle"></i></div>
            <div class="stat-info">
                <span class="stat-label">Disetujui</span>
                <span class="stat-value"><?= (int) $stat['disetujui'] ?></span>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="card stat-card">
            <div class="stat-icon bg-success"><i class="bi bi-flag"></i></div>
            <div class="stat-info">
                <span class="stat-label">Selesai</span>
                <span class="stat-value"><?= (int) $stat['selesai'] ?></span>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="card stat-card">
            <div class="stat-icon bg-dark"><i class="bi bi-calendar-event"></i></div>
            <div class="stat-info">
                <span class="stat-label">Hari Ini</span>
                <span class="stat-value"><?= (int) $stat['hari_ini'] ?></span>
            </div>
        </div>
    </div>
</div>

<!-- ======================== FILTER + PENCARIAN ======================== -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form action="kelola_jemput.php" method="GET" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="btn-group flex-wrap w-100" role="group" aria-label="Filter status">
                    <?php
                    $tabs = [
                        'semua'    => 'Semua',
                        'pending'  => 'Pending',
                        'diproses' => 'Diproses',
                        'disetujui'=> 'Disetujui',
                        'ditolak'  => 'Ditolak',
                        'selesai'  => 'Selesai',
                    ];
                    foreach ($tabs as $val => $lbl):
                        $active = $filterStatus === $val ? 'btn-success' : 'btn-outline-success';
                    ?>
                        <a href="kelola_jemput.php?status=<?= $val ?>&cari=<?= urlencode($cari) ?>"
                           class="btn btn-sm <?= $active ?>"><?= $lbl ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-md-5">
                <input type="text" name="cari" class="form-control form-control-sm"
                       placeholder="Cari nama nasabah, alamat, atau JMP-id..."
                       value="<?= htmlspecialchars($cari) ?>">
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-success btn-sm">
                    <i class="bi bi-search me-1"></i>Cari
                </button>
            </div>
            <?php if ($filterStatus !== 'semua' || $cari !== ''): ?>
                <div class="col-12">
                    <a href="kelola_jemput.php" class="btn btn-link btn-sm p-0">
                        <i class="bi bi-x-circle me-1"></i>Reset filter
                    </a>
                </div>
            <?php endif; ?>
            <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>">
        </form>
    </div>
</div>

<!-- ======================== TABEL REQUEST ======================== -->
<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h6 class="mb-0"><i class="bi bi-truck me-2 text-success"></i>Daftar Request Penjemputan</h6>
        <span class="badge bg-light text-body border"><?= count($list) ?> data</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($list)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox" style="font-size:40px;"></i>
                <p class="mt-2">Tidak ada request penjemputan<?php if ($filterStatus !== 'semua' || $cari !== ''): ?> pada filter ini<?php endif; ?>.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Kode</th>
                            <th>Nasabah</th>
                            <th>Alamat Jemput</th>
                            <th>Jarak</th>
                            <th>Est. Berat</th>
                            <th>Jadwal Jemput</th>
                            <th>Foto</th>
                            <th>Petugas</th>
                            <th>Tanggal</th>
                            <th>Status</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($list as $row): ?>
                            <?php
                            $rid        = (int) $row['id'];
                            $st         = $row['status'];

                            // Jarak teller -> lokasi penjemputan
                            $jarakTeller = null;
                            $koordinat   = null;
                            if (!empty($row['petugas_id']) && isset($lokasiTeller[(int) $row['petugas_id']])
                                && $row['latitude'] !== null && $row['longitude'] !== null) {
                                $tl = $lokasiTeller[(int) $row['petugas_id']];
                                $jarakTeller = haversineKm(
                                    (float) $row['latitude'],
                                    (float) $row['longitude'],
                                    (float) $tl['latitude'],
                                    (float) $tl['longitude']
                                );
                            }
                            if ($row['latitude'] !== null && $row['longitude'] !== null) {
                                $koordinat = ['lat' => (float) $row['latitude'], 'lng' => (float) $row['longitude']];
                            }
                            $fotoUrl    = !empty($row['foto_sampah'])
                                ? $baseUrl . '/uploads/pickup/' . urlencode($row['foto_sampah'])
                                : '';
                            ?>
                            <tr>
                                <td><span class="badge text-bg-success">JMP-<?= $rid ?></span></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <?= renderAvatar($row['nama_nasabah'], $row['foto_nasabah'] ?? '', $baseUrl, 34) ?>
                                        <div>
                                            <div class="fw-semibold"><?= htmlspecialchars($row['nama_nasabah']) ?></div>
                                            <?php if (!empty($row['hp_nasabah'])): ?>
                                                <small class="text-muted"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($row['hp_nasabah']) ?></small>
                                            <?php endif; ?>
                                            <?php
                                            $waJemputUrl = waLink((string) ($row['hp_nasabah'] ?? ''),
                                                "Halo {$row['nama_nasabah']},\nInfo penjemputan sampah RecyCash:\n"
                                                . "Kode: JMP-" . (int) $row['id'] . "\n"
                                                . "Status: " . ucfirst((string) $row['status']) . "\n"
                                                . "Alamat: " . preg_replace('/\s+/', ' ', (string) $row['alamat_jemput']) . "\n\n"
                                                . "Salam, RecyCash");
                                            if ($waJemputUrl !== ''): ?>
                                                <a href="<?= htmlspecialchars($waJemputUrl) ?>" target="_blank" rel="noopener"
                                                   class="small" title="Kirim info penjemputan via WhatsApp">
                                                    <i class="bi bi-whatsapp text-success me-1"></i>WA
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td style="max-width:220px;">
                                    <?= nl2br(htmlspecialchars($row['alamat_jemput'])) ?>
                                </td>
                                <td>
                                    <?php if ($jarakTeller !== null): ?>
                                        <strong class="d-block text-success"><?= htmlspecialchars(fmtJarak($jarakTeller)) ?></strong>
                                        <small class="text-muted"><?= (int) ($row['eta_menit'] ?? 0) ?> menit</small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $row['estimasi_berat'] !== null ? number_format((float) $row['estimasi_berat'], 2) . ' kg' : '-' ?></td>
                                <td>
                                    <?php if (!empty($row['jadwal_jemput'])): ?>
                                        <div><?= date('d M Y', strtotime($row['jadwal_jemput'])) ?></div>
                                        <small class="text-muted"><?= date('H:i', strtotime($row['jadwal_jemput'])) ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">Fleksibel</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($fotoUrl): ?>
                                        <a href="<?= $fotoUrl ?>" target="_blank" title="Lihat foto sampah">
                                            <i class="bi bi-image text-success fs-5"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($row['nama_petugas'] ?? '-') ?></td>
                                <td>
                                    <div><?= date('d M Y', strtotime($row['created_at'])) ?></div>
                                    <small class="text-muted"><?= date('H:i', strtotime($row['created_at'])) ?></small>
                                </td>
                                <td>
                                    <?= badgeStatus($st) ?>
                                    <?php if ($st === 'ditolak' && $adaCatatanTolak && !empty($row['catatan_penolakan'])): ?>
                                        <div class="small text-danger mt-1" title="<?= htmlspecialchars($row['catatan_penolakan']) ?>">
                                            <i class="bi bi-info-circle me-1"></i><?= htmlspecialchars($row['catatan_penolakan']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center text-nowrap">
                                    <!-- Tombol detail selalu tampil -->
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                            data-bs-toggle="modal" data-bs-target="#detailModal"
                                            data-id="<?= $rid ?>"
                                            data-kode="JMP-<?= $rid ?>"
                                            data-nasabah="<?= htmlspecialchars($row['nama_nasabah'], ENT_QUOTES) ?>"
                                            data-hp="<?= htmlspecialchars($row['hp_nasabah'] ?? '', ENT_QUOTES) ?>"
                                            data-alamat-nasabah="<?= htmlspecialchars($row['alamat_nasabah'] ?? '', ENT_QUOTES) ?>"
                                            data-alamat="<?= htmlspecialchars($row['alamat_jemput'], ENT_QUOTES) ?>"
                                            data-berat="<?= $row['estimasi_berat'] !== null ? number_format((float) $row['estimasi_berat'], 2) . ' kg' : '-' ?>"
                                            data-jadwal="<?= !empty($row['jadwal_jemput']) ? date('d M Y H:i', strtotime($row['jadwal_jemput'])) : 'Fleksibel' ?>"
                                            data-foto="<?= $fotoUrl ?>"
                                            data-petugas="<?= htmlspecialchars($row['nama_petugas'] ?? '-', ENT_QUOTES) ?>"
                                            data-tanggal="<?= date('d M Y H:i', strtotime($row['created_at'])) ?>"
                                            data-status="<?= htmlspecialchars($st) ?>"
                                            data-status-label="<?= htmlspecialchars(ucfirst($st)) ?>"
                                            data-catatan="<?= htmlspecialchars($row['catatan_penolakan'] ?? '', ENT_QUOTES) ?>"
                                            title="Detail">
                                        <i class="bi bi-eye"></i>
                                    </button>

                                    <?php $bolehChat = empty($row['petugas_id']) || (int) $row['petugas_id'] === $petugasId; ?>

                                    <?php if ($bolehChat): ?>
                                        <a class="btn btn-sm btn-outline-success"
                                           href="<?= $baseUrl ?>/views/Teller/chat.php?request_id=<?= $rid ?>"
                                           title="Chat nasabah">
                                            <i class="bi bi-chat-dots-fill"></i>
                                        </a>
                                    <?php endif; ?>

                                    <?php if (!empty($row['hp_nasabah'])): ?>
                                        <a class="btn btn-sm btn-outline-success"
                                           href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', (string) $row['hp_nasabah'])) ?>"
                                           title="Telepon nasabah">
                                            <i class="bi bi-telephone-fill"></i>
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($koordinat !== null): ?>
                                        <a class="btn btn-sm btn-outline-success"
                                           href="<?= htmlspecialchars(linkNavigasi($koordinat['lat'], $koordinat['lng'], (string) $row['alamat_jemput'])) ?>"
                                           target="_blank" rel="noopener" title="Navigasi ke lokasi penjemputan">
                                            <i class="bi bi-map"></i>
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($bolehChat && in_array($st, ['pending', 'diproses'], true)): ?>
                                        <form action="kelola_jemput.php" method="POST" class="d-inline">
                                            <input type="hidden" name="id_request" value="<?= $rid ?>">
                                            <input type="hidden" name="aksi" value="tiba">
                                            <button type="submit" class="btn btn-sm btn-warning"
                                                    onclick="return confirm('Tandai sudah tiba di lokasi JMP-<?= $rid ?>?')">
                                                <i class="bi bi-geo-alt-fill"></i> Tiba
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($st === 'pending'): ?>
                                        <form action="kelola_jemput.php" method="POST" class="d-inline">
                                            <input type="hidden" name="id_request" value="<?= $rid ?>">
                                            <input type="hidden" name="aksi" value="proses">
                                            <button type="submit" class="btn btn-sm btn-info"
                                                    onclick="return confirm('Proses request JMP-<?= $rid ?>?')">
                                                <i class="bi bi-gear"></i> Proses
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if (in_array($st, ['pending', 'diproses'], true)): ?>
                                        <button type="button" class="btn btn-sm btn-success"
                                                data-bs-toggle="modal" data-bs-target="#setujuiModal"
                                                data-id="<?= $rid ?>" data-kode="JMP-<?= $rid ?>"
                                                title="Setujui">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger"
                                                data-bs-toggle="modal" data-bs-target="#tolakModal"
                                                data-id="<?= $rid ?>" data-kode="JMP-<?= $rid ?>"
                                                title="Tolak">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($st === 'disetujui'): ?>
                                        <form action="kelola_jemput.php" method="POST" class="d-inline">
                                            <input type="hidden" name="id_request" value="<?= $rid ?>">
                                            <input type="hidden" name="aksi" value="selesai">
                                            <button type="submit" class="btn btn-sm btn-primary"
                                                    onclick="return confirm('Tandai JMP-<?= $rid ?> sebagai selesai?')">
                                                <i class="bi bi-flag"></i> Selesai
                                            </button>
                                        </form>
                                        <a href="input_setor.php?nasabah_id=<?= (int) $row['nasabah_id'] ?>" class="btn btn-sm btn-outline-success" title="Input Setor">
                                            <i class="bi bi-box-arrow-in-right"></i> Setor
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ======================== MODAL DETAIL ======================== -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-truck me-2 text-success"></i><span id="dKode">JMP-</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small mb-1">Nasabah</div>
                            <div class="fw-bold" id="dNasabah">-</div>
                            <div class="small text-muted" id="dHp">-</div>
                            <div class="small text-muted mt-1"><i class="bi bi-house me-1"></i><span id="dAlamatNasabah">-</span></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small mb-1">Informasi Request</div>
                            <div><span class="text-muted">Status:</span> <span id="dStatus">-</span></div>
                            <div><span class="text-muted">Estimasi Berat:</span> <strong id="dBerat">-</strong></div>
                            <div><span class="text-muted">Jadwal Jemput:</span> <strong id="dJadwal">-</strong></div>
                            <div><span class="text-muted">Petugas:</span> <span id="dPetugas">-</span></div>
                            <div><span class="text-muted">Tanggal:</span> <span id="dTanggal">-</span></div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="border rounded p-3">
                            <div class="text-muted small mb-1">Alamat Penjemputan</div>
                            <div id="dAlamat">-</div>
                            <a id="btnMaps" class="btn btn-outline-success btn-sm mt-2" href="https://www.google.com/maps/search/?api=1&query=" target="_blank" rel="noopener">
                                <i class="bi bi-geo-alt me-1"></i>Buka di Google Maps
                            </a>
                        </div>
                    </div>
                    <div class="col-12" id="dFotoWrap">
                        <div class="text-muted small mb-1">Foto Sampah</div>
                        <img id="dFoto" src="" alt="Foto Sampah" class="img-fluid rounded border" style="max-height:320px;">
                        <div id="dTidakAdaFoto" class="text-muted d-none">Tidak ada foto.</div>
                    </div>
                    <div class="col-12 d-none" id="dCatatanWrap">
                        <div class="alert alert-danger mb-0">
                            <i class="bi bi-info-circle me-1"></i><strong>Alasan Penolakan:</strong>
                            <span id="dCatatan">-</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- ======================== MODAL SETUJUI ======================== -->
<div class="modal fade" id="setujuiModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="kelola_jemput.php" method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-success"><i class="bi bi-check-circle me-2"></i>Setujui Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Setujui request penjemputan <strong id="sKode">-</strong>?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <input type="hidden" name="id_request" id="sId">
                <input type="hidden" name="aksi" value="setujui">
                <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Setujui</button>
            </div>
        </form>
    </div>
</div>

<!-- ======================== MODAL TOLAK ======================== -->
<div class="modal fade" id="tolakModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="kelola_jemput.php" method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="bi bi-x-circle me-2"></i>Tolak Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Tolak request penjemputan <strong id="tKode">-</strong>?</p>
                <?php if ($adaCatatanTolak): ?>
                    <label for="tCatatan" class="form-label">Alasan Penolakan <span class="text-danger">*</span></label>
                    <textarea name="catatan_penolakan" id="tCatatan" class="form-control" rows="2" required
                              placeholder="Contoh: Area di luar jangkauan..."></textarea>
                <?php else: ?>
                    <div class="form-text">Kolom alasan belum tersedia di database (jalankan migrasi config/tambah_catatan_penolakan_jemput.sql).</div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <input type="hidden" name="id_request" id="tId">
                <input type="hidden" name="aksi" value="tolak">
                <button type="submit" class="btn btn-danger"><i class="bi bi-x-lg me-1"></i>Tolak</button>
            </div>
        </form>
    </div>
</div>

<style>
    .stat-card {
        border: none;
        border-radius: 14px;
        padding: 18px;
        display: flex;
        align-items: center;
        gap: 14px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    }
    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        color: #fff;
        flex-shrink: 0;
    }
    .stat-info { display: flex; flex-direction: column; }
.stat-label { font-size: 12px; color: var(--text-muted); font-weight: 500; }
        .stat-value { font-size: 18px; font-weight: 700; color: var(--text-strong); }

    @media (max-width: 767px) {
        .stat-card { padding: 14px; gap: 10px; }
        .stat-icon { width: 40px; height: 40px; font-size: 16px; }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Modal Detail
    var detailModal = document.getElementById('detailModal');
    if (detailModal) {
        detailModal.addEventListener('show.bs.modal', function (event) {
            var btn = event.relatedTarget;
            if (!btn) return;
            document.getElementById('dKode').textContent = btn.getAttribute('data-kode');
            document.getElementById('dNasabah').textContent = btn.getAttribute('data-nasabah') || '-';
            document.getElementById('dHp').textContent = btn.getAttribute('data-hp')
                ? 'HP: ' + btn.getAttribute('data-hp') : 'HP: -';
            document.getElementById('dAlamatNasabah').textContent = btn.getAttribute('data-alamat-nasabah') || '-';
            document.getElementById('dAlamat').textContent = btn.getAttribute('data-alamat') || '-';
            document.getElementById('btnMaps').setAttribute('href', 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(btn.getAttribute('data-alamat') || ''));
            document.getElementById('dBerat').textContent = btn.getAttribute('data-berat') || '-';
            document.getElementById('dJadwal').textContent = btn.getAttribute('data-jadwal') || 'Fleksibel';
            document.getElementById('dPetugas').textContent = btn.getAttribute('data-petugas') || '-';
            document.getElementById('dTanggal').textContent = btn.getAttribute('data-tanggal') || '-';

            var st = btn.getAttribute('data-status');
            var stLabel = btn.getAttribute('data-status-label') || st;
            var badgeMap = {
                pending: 'text-bg-warning',
                diproses: 'text-bg-info',
                disetujui: 'bg-success',
                ditolak: 'bg-danger',
                selesai: 'bg-success'
            };
            document.getElementById('dStatus').innerHTML =
                '<span class="badge rounded-pill ' + (badgeMap[st] || 'bg-secondary') + '">' + stLabel + '</span>';

            var foto = btn.getAttribute('data-foto');
            var img = document.getElementById('dFoto');
            var noFoto = document.getElementById('dTidakAdaFoto');
            if (foto) {
                img.src = foto;
                img.classList.remove('d-none');
                noFoto.classList.add('d-none');
            } else {
                img.src = '';
                img.classList.add('d-none');
                noFoto.classList.remove('d-none');
            }

            var catatan = btn.getAttribute('data-catatan');
            var wrap = document.getElementById('dCatatanWrap');
            if (st === 'ditolak' && catatan) {
                document.getElementById('dCatatan').textContent = catatan;
                wrap.classList.remove('d-none');
            } else {
                wrap.classList.add('d-none');
            }
        });
    }

    // Modal Setujui
    var setujuiModal = document.getElementById('setujuiModal');
    if (setujuiModal) {
        setujuiModal.addEventListener('show.bs.modal', function (event) {
            var btn = event.relatedTarget;
            if (!btn) return;
            document.getElementById('sId').value = btn.getAttribute('data-id');
            document.getElementById('sKode').textContent = btn.getAttribute('data-kode');
        });
    }

    // Modal Tolak
    var tolakModal = document.getElementById('tolakModal');
    if (tolakModal) {
        tolakModal.addEventListener('show.bs.modal', function (event) {
            var btn = event.relatedTarget;
            if (!btn) return;
            document.getElementById('tId').value = btn.getAttribute('data-id');
            document.getElementById('tKode').textContent = btn.getAttribute('data-kode');
            var ta = document.getElementById('tCatatan');
            if (ta) ta.value = '';
        });
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
