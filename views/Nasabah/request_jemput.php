<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<?php
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/pickup_helper.php';
require_once __DIR__ . '/../../includes/notifikasi.php';
require_once __DIR__ . '/../../includes/lokasi.php';

// Pastikan kolom opsional tersedia
pastikanKolomPickup($pdo, 'jadwal_jemput', 'DATETIME NULL');
pastikanKolomPickup($pdo, 'rating', 'TINYINT NULL');
pastikanKolomPickup($pdo, 'ulasan', 'TEXT NULL');
pastikanTabelLokasi($pdo);

// 1. Proteksi Akses Khusus Nasabah
if (($role ?? null) !== 'nasabah') {
    header('Location: ' . $baseUrl . '/auth/login.php');
    exit;
}

$nasabah_id = (int) ($_SESSION['id_user'] ?? 0);
$pesan = '';

// 2. Ambil Alamat Default Nasabah dari Database
$stmt_user = $pdo->prepare("SELECT alamat FROM users WHERE id = ?");
$stmt_user->execute([$nasabah_id]);
$user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

$alamat_default = $user_data['alamat'] ?? '';
$estimasi_lama  = $_POST['estimasi_berat'] ?? '';

// Pre-fill dari halaman "Cari Teller Terdekat" (?lat=&lng=&lokasi=&teller=)
$prefillLat    = isset($_GET['lat']) && is_numeric($_GET['lat']) ? (float) $_GET['lat'] : null;
$prefillLng    = isset($_GET['lng']) && is_numeric($_GET['lng']) ? (float) $_GET['lng'] : null;
$prefillLokasi = trim((string) ($_GET['lokasi'] ?? ''));
$prefillTeller = (int) ($_GET['teller'] ?? 0);

$latAwal    = $prefillLat !== null ? $prefillLat : (is_numeric($_POST['lat'] ?? null) ? (float) $_POST['lat'] : null);
$lngAwal    = $prefillLng !== null ? $prefillLng : (is_numeric($_POST['lng'] ?? null) ? (float) $_POST['lng'] : null);
$lokasiAwal = $prefillLokasi !== '' ? $prefillLokasi : trim((string) ($_POST['lokasi_label'] ?? ''));
$tellerAwal = $prefillTeller > 0 ? $prefillTeller : (int) ($_POST['teller_pilihan'] ?? 0);

// 3. Proses Form Submit Request Penjemputan
if (isset($_POST['kirim_request'])) {
    $alamat_jemput  = trim($_POST['alamat_jemput'] ?? '');
    $estimasi_berat = trim($_POST['estimasi_berat'] ?? '');
    $estimasi_berat = str_replace(',', '.', $estimasi_berat);
    $jadwal_jemput  = trim($_POST['jadwal_jemput'] ?? '');
    if ($jadwal_jemput !== '') {
        $jadwal_jemput = str_replace('T', ' ', $jadwal_jemput) . ':00';
    } else {
        $jadwal_jemput = null;
    }

    // Lokasi penjemputan (dipakai untuk mencari teller terdekat)
    $lat           = is_numeric($_POST['lat'] ?? null) ? (float) $_POST['lat'] : null;
    $lng           = is_numeric($_POST['lng'] ?? null) ? (float) $_POST['lng'] : null;
    $lokasiLabel   = trim($_POST['lokasi_label'] ?? '');
    $tellerPilihan = (int) ($_POST['teller_pilihan'] ?? 0);

    if ($alamat_jemput === '') {
        $pesan = "<div class='alert alert-danger'>Alamat penjemputan wajib diisi.</div>";
    } elseif (!is_numeric($estimasi_berat) || (float) $estimasi_berat <= 0) {
        $pesan = "<div class='alert alert-danger'>Estimasi berat harus berupa angka lebih dari 0.</div>";
    } else {
        // 3a. Prosessing Upload Foto Sampah (Opsional)
        $foto_sampah = NULL;
        if (isset($_FILES['foto_sampah']) && $_FILES['foto_sampah']['error'] === UPLOAD_ERR_OK) {
            $file_tmp  = $_FILES['foto_sampah']['tmp_name'];
            $file_name = basename($_FILES['foto_sampah']['name']);
            $ext       = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            $allowed_ext = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            $max_size    = 2 * 1024 * 1024; // 2MB

            if (!in_array($ext, $allowed_ext)) {
                $pesan = "<div class='alert alert-danger'>Foto sampah hanya boleh berformat JPG, PNG, WEBP, atau GIF.</div>";
            } elseif ($_FILES['foto_sampah']['size'] > $max_size) {
                $pesan = "<div class='alert alert-danger'>Ukuran foto maksimal 2MB.</div>";
            } else {
                $upload_dir = __DIR__ . '/../../uploads/pickup/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $foto_sampah  = "pickup_" . $nasabah_id . "_" . time() . "_" . bin2hex(random_bytes(3)) . "." . $ext;
                $tujuan_foto  = $upload_dir . $foto_sampah;

                if (!move_uploaded_file($file_tmp, $tujuan_foto)) {
                    $foto_sampah = NULL;
                    $pesan = "<div class='alert alert-danger'>Gagal mengunggah foto sampah.</div>";
                }
            }
        }

        // 3b. Simpan ke Database (hanya jika tidak ada error upload)
        if ($pesan === '') {
            try {
                $sql = "INSERT INTO pickup_requests (nasabah_id, alamat_jemput, estimasi_berat, foto_sampah, status, jadwal_jemput, latitude, longitude, lokasi_label)
                        VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $nasabah_id,
                    $alamat_jemput,
                    (float) $estimasi_berat,
                    $foto_sampah,
                    $jadwal_jemput,
                    $lat,
                    $lng,
                    $lokasiLabel !== '' ? $lokasiLabel : null,
                ]);

                $requestId = (int) $pdo->lastInsertId();

                // 3c. Auto-assign teller terdekat (ala ojek online)
                $teller = null;

                if ($lat !== null && $lng !== null) {
                    $daftarTeller = tellerTerdekat($pdo, $lat, $lng, 20);

                    if ($tellerPilihan > 0) {
                        foreach ($daftarTeller as $t) {
                            if ((int) $t['user_id'] === $tellerPilihan) {
                                $teller = $t;
                                break;
                            }
                        }
                    }

                    if ($teller === null && !empty($daftarTeller)) {
                        $teller = $daftarTeller[0]; // teller terdekat
                    }
                }

                if ($teller !== null) {
                    $stmtUpdate = $pdo->prepare(
                        "UPDATE pickup_requests SET petugas_id = :p, eta_menit = :eta, assign_at = NOW() WHERE id = :id"
                    );
                    $stmtUpdate->execute([
                        ':p'   => (int) $teller['user_id'],
                        ':eta' => (int) $teller['eta_menit'],
                        ':id'  => $requestId,
                    ]);

                    $namaNasabah = (string) ($_SESSION['nama'] ?? 'Nasabah');

                    // Kabari teller yang ditugaskan (in-app + email)
                    $pesanTeller = "Permintaan jemputan baru JMP-{$requestId} dari {$namaNasabah}, jarak " . fmtJarak($teller['jarak_km']) . ".";
                    $htmlTeller = '<div style="font-family:Arial,sans-serif;font-size:14px;line-height:1.6">'
                        . '<h3 style="color:#2e7d32;margin:0 0 8px">RecyCash</h3>'
                        . '<p>Ada permintaan jemputan baru untuk Anda.</p>'
                        . '<ul><li>Kode: JMP-' . $requestId . '</li>'
                        . '<li>Nasabah: ' . htmlspecialchars($namaNasabah) . '</li>'
                        . '<li>Jarak: ' . fmtJarak($teller['jarak_km']) . '</li>'
                        . '<li>Estimasi tiba: ' . (int) $teller['eta_menit'] . ' menit</li></ul>'
                        . '<p style="color:#64748b">Buka aplikasi RecyCash &gt; Jemputan untuk menerima permintaan.</p></div>';
                    notifKirim($pdo, (int) $teller['user_id'], $pesanTeller, 'kelola_jemput.php',
                        'RecyCash - Permintaan Jemputan JMP-' . $requestId, $htmlTeller);

                    // Kabari nasabah
                    $pesanNasabah = "Teller " . $teller['nama'] . " (" . $teller['nama_outlet'] . ") akan menjemput Anda. Jarak "
                        . fmtJarak($teller['jarak_km']) . ", estimasi " . (int) $teller['eta_menit'] . " menit.";
                    $htmlNasabah = '<div style="font-family:Arial,sans-serif;font-size:14px;line-height:1.6">'
                        . '<h3 style="color:#2e7d32;margin:0 0 8px">RecyCash</h3>'
                        . '<p>' . htmlspecialchars($pesanNasabah) . '</p>'
                        . '<p style="color:#64748b">Anda bisa chatting dan menelepon teller melalui aplikasi.</p></div>';
                    notifKirim($pdo, $nasabah_id, $pesanNasabah, 'request_jemput.php',
                        'RecyCash - Teller Terdekat untuk JMP-' . $requestId, $htmlNasabah);

                    auditCatat($pdo, $nasabah_id, 'AUTO_ASSIGN_TELLER', 'pickup_requests', $requestId,
                        'Teller ' . $teller['nama'] . ' (ID ' . (int) $teller['user_id'] . '), jarak '
                        . fmtJarak($teller['jarak_km']) . ', ETA ' . (int) $teller['eta_menit'] . ' menit');

                    $pesan = "<div class='alert alert-success'>Permintaan penjemputan berhasil dikirim! "
                        . "Teller terdekat: <strong>" . htmlspecialchars($teller['nama']) . "</strong> ("
                        . htmlspecialchars($teller['nama_outlet']) . ") · jarak "
                        . htmlspecialchars(fmtJarak($teller['jarak_km'])) . " · estimasi "
                        . (int) $teller['eta_menit'] . " menit.</div>";
                } else {
                    $pesan = "<div class='alert alert-success'>Permintaan penjemputan berhasil dikirim! "
                        . "Petugas akan segera memprosesnya.</div>";
                }

                $estimasi_lama = '';
            } catch (PDOException $e) {
                $pesan = "<div class='alert alert-danger'>Gagal mengirim permintaan: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    }
}

// 3c. Proses kirim rating/ulasan untuk penjemputan selesai
if (isset($_POST['kirim_rating'])) {
    $rid    = (int) ($_POST['rating_id'] ?? 0);
    $rating = (int) ($_POST['rating'] ?? 0);
    $ulasan = trim($_POST['ulasan'] ?? '');
    if ($rid > 0 && $rating >= 1 && $rating <= 5) {
        try {
            $stmt = $pdo->prepare("UPDATE pickup_requests SET rating = ?, ulasan = ? WHERE id = ? AND nasabah_id = ? AND status = 'selesai'");
            $stmt->execute([$rating, $ulasan !== '' ? $ulasan : null, $rid, $nasabah_id]);
            $pesan = "<div class='alert alert-success'>Terima kasih atas penilaian Anda!</div>";
        } catch (PDOException $e) {
            $pesan = "<div class='alert alert-danger'>Gagal menyimpan penilaian: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        $pesan = "<div class='alert alert-danger'>Penilaian tidak valid.</div>";
    }
}

// 4. Query Ambil Riwayat Penjemputan Milik Nasabah Ini
$query_riwayat = "SELECT p.*, u.nama AS nama_petugas, u.foto AS foto_petugas
                  FROM pickup_requests p 
                  LEFT JOIN users u ON p.petugas_id = u.id 
                  WHERE p.nasabah_id = ? 
                  ORDER BY p.created_at DESC";
$stmt_riwayat = $pdo->prepare($query_riwayat);
$stmt_riwayat->execute([$nasabah_id]);
$riwayat_list = $stmt_riwayat->fetchAll(PDO::FETCH_ASSOC);

// Request aktif (menunggu / diproses / disetujui) untuk kartu status langsung
$stmt_aktif = $pdo->prepare(
    "SELECT p.*, u.nama AS nama_petugas, u.nomor_hp AS hp_petugas, u.foto AS foto_petugas
     FROM pickup_requests p
     LEFT JOIN users u ON u.id = p.petugas_id
     WHERE p.nasabah_id = ? AND p.status IN ('pending','diproses','disetujui')
     ORDER BY p.created_at DESC LIMIT 1"
);
$stmt_aktif->execute([$nasabah_id]);
$request_aktif = $stmt_aktif->fetch(PDO::FETCH_ASSOC);

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

<?= $pesan ?>

<?php if ($request_aktif): ?>
    <?php
    $aktifTeller = (string) ($request_aktif['nama_petugas'] ?? '');
    $aktifHp     = (string) ($request_aktif['hp_petugas'] ?? '');
    $aktifId     = (int) $request_aktif['id'];
    $aktifLabel  = ['pending' => 'Menunggu teller', 'diproses' => 'Teller dalam perjalanan', 'disetujui' => 'Disetujui'][$request_aktif['status']] ?? ucfirst((string) $request_aktif['status']);
    $aktifWa     = waLink($aktifHp, "Halo " . $aktifTeller . ", saya " . (string) ($_SESSION['nama'] ?? 'Nasabah') . " dari RecyCash. Mohon info untuk request JMP-{$aktifId}.");
    ?>
    <div class="card shadow-sm mb-4" id="kartuAktif" data-aos="fade-up">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0"><i class="bi bi-broadcast-pin me-2"></i>Penjemputan Sedang Berlangsung</h5>
            <span class="badge bg-white text-success" id="badgeStatusAktif"><?= htmlspecialchars($aktifLabel) ?></span>
        </div>
        <div class="card-body">
            <div class="row g-3 align-items-center">
                <div class="col-md-5">
                    <div class="d-flex align-items-center gap-3">
                        <?= renderAvatar($aktifTeller !== '' ? $aktifTeller : 'Teller', (string) ($request_aktif['foto_petugas'] ?? ''), $baseUrl, 48) ?>
                        <div class="min-w-0">
                            <strong class="d-block" id="namaTellerAktif"><?= htmlspecialchars($aktifTeller !== '' ? $aktifTeller : 'Menunggu teller terdekat') ?></strong>
                            <span class="small text-muted" id="infoTellerAktif">
                                <?php if ($request_aktif['eta_menit']): ?>
                                    Estimasi tiba ±<?= (int) $request_aktif['eta_menit'] ?> menit
                                <?php else: ?>
                                    Sedang mencari teller terdekat
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-md-7">
                    <div class="d-flex flex-wrap gap-2 justify-content-md-end">
                        <a class="btn btn-success" id="btnChatAktif"
                           href="<?= $baseUrl ?>/views/Nasabah/chat.php?request_id=<?= $aktifId ?>">
                            <i class="bi bi-chat-dots-fill"></i> Chat Teller
                        </a>
                        <a class="btn btn-outline-success" id="btnTelAktif"
                           href="<?= $aktifHp !== '' ? 'tel:' . htmlspecialchars(preg_replace('/[^0-9+]/', '', $aktifHp)) : '#' ?>">
                            <i class="bi bi-telephone-fill"></i> Telepon
                        </a>
                        <a class="btn btn-outline-success" id="btnWaAktif" target="_blank" rel="noopener"
                           href="<?= htmlspecialchars($aktifWa !== '' ? $aktifWa : '#') ?>">
                            <i class="bi bi-whatsapp"></i> WhatsApp
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Form Request Penjemputan -->
    <div class="col-xl-5 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-truck me-2"></i>Form Jemput Sampah</h5>
            </div>
            <div class="card-body">
                <form action="" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="lat" id="latJemput" value="<?= $latAwal !== null ? htmlspecialchars((string) $latAwal) : '' ?>">
                    <input type="hidden" name="lng" id="lngJemput" value="<?= $lngAwal !== null ? htmlspecialchars((string) $lngAwal) : '' ?>">
                    <input type="hidden" name="lokasi_label" id="lokasiLabel" value="<?= htmlspecialchars($lokasiAwal) ?>">
                    <input type="hidden" name="teller_pilihan" id="tellerPilihan" value="<?= (int) $tellerAwal ?>">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Cari Lokasi Penjemputan</label>
                        <div class="lokasi-search">
                            <div class="input-group">
                                <span class="input-group-text bg-transparent"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control" id="cariLokasi" autocomplete="off"
                                       placeholder="Ketik alamat, mis. Jl. Merdeka, Bandung">
                                <button class="btn btn-outline-success" type="button" id="btnGps">
                                    <i class="bi bi-crosshair"></i> <span class="d-none d-md-inline">GPS</span>
                                </button>
                            </div>
                            <div class="lokasi-hasil" id="hasilLokasi"></div>
                        </div>
                        <div class="form-text" id="infoLokasi">
                            Lokasi dipakai untuk menentukan teller terdekat. Sumber: <?= htmlspecialchars(lokasiProviderLabel()) ?>.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Alamat Penjemputan</label>
                        <textarea name="alamat_jemput" id="alamat_jemput" class="form-control" rows="3" required><?= htmlspecialchars($_POST['alamat_jemput'] ?? ($prefillLokasi !== '' ? $prefillLokasi : $alamat_default)); ?></textarea>
                        <a class="btn btn-outline-success w-100 mt-2" id="btnCariMaps"
                           href="https://www.google.com/maps/search/?api=1&query=<?= rawurlencode((string) ($_POST['alamat_jemput'] ?? $alamat_default)) ?>"
                           target="_blank" rel="noopener">
                            <i class="bi bi-geo-alt me-1"></i>Lihat di Google Maps
                        </a>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Estimasi Berat (kg)</label>
                        <input type="number" step="0.1" min="0.1" name="estimasi_berat" class="form-control"
                               placeholder="Contoh: 5.5" required value="<?= htmlspecialchars($estimasi_lama) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Jadwal Jemput yang Diinginkan (opsional)</label>
                        <input type="datetime-local" name="jadwal_jemput" class="form-control"
                               value="<?= htmlspecialchars($_POST['jadwal_jemput'] ?? '') ?>">
                        <div class="form-text">Kosongkan jika fleksibel. Petugas akan menyesuaikan.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Foto Sampah (Opsional)</label>
                        <input type="file" name="foto_sampah" class="form-control" accept="image/*">
                        <div class="form-text">Format JPG, PNG, WEBP, GIF. Maksimal 2MB.</div>
                    </div>
                    <button type="submit" name="kirim_request" class="btn btn-success w-100">
                        <i class="bi bi-send me-1"></i>Kirim Permintaan
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Riwayat Request -->
    <div class="col-xl-7">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-clock-history me-2 text-success"></i>Status Penjemputan Saya</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($riwayat_list)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox" style="font-size:40px;"></i>
                        <p class="mt-2">Belum ada permintaan penjemputan.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Kode</th>
                                    <th>Tanggal</th>
                                    <th>Jadwal Jemput</th>
                                    <th>Est. Berat</th>
                                    <th>Foto</th>
                                    <th>Petugas</th>
                                    <th>Status</th>
                                    <th>Rating</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($riwayat_list as $row): ?>
                                    <tr>
                                        <td><span class="badge text-bg-success">JMP-<?= (int) $row['id'] ?></span></td>
                                        <td>
                                            <div><?= date('d M Y', strtotime($row['created_at'])) ?></div>
                                            <small class="text-muted"><?= date('H:i', strtotime($row['created_at'])) ?></small>
                                        </td>
                                        <td>
                                            <?php if (!empty($row['jadwal_jemput'])): ?>
                                                <div><?= date('d M Y', strtotime($row['jadwal_jemput'])) ?></div>
                                                <small class="text-muted"><?= date('H:i', strtotime($row['jadwal_jemput'])) ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">Fleksibel</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= number_format((float) $row['estimasi_berat'], 2) ?> kg</td>
                                        <td>
                                            <?php if (!empty($row['foto_sampah'])): ?>
                                                <a href="<?= $baseUrl ?>/uploads/pickup/<?= urlencode($row['foto_sampah']) ?>" target="_blank">
                                                    <i class="bi bi-image text-success"></i>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($row['nama_petugas'])): ?>
                                                <div class="d-flex align-items-center gap-2">
                                                    <?= renderAvatar($row['nama_petugas'], $row['foto_petugas'] ?? '', $baseUrl, 32) ?>
                                                    <span><?= htmlspecialchars($row['nama_petugas']) ?></span>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                    <?= badgeStatus($row['status']) ?>
                                    <?php if ($row['status'] === 'ditolak' && !empty($row['catatan_penolakan'] ?? null)): ?>
                                        <div class="small text-danger mt-1">
                                            <i class="bi bi-info-circle me-1"></i><?= htmlspecialchars($row['catatan_penolakan']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['status'] === 'selesai'): ?>
                                        <?php if (!empty($row['rating'])): ?>
                                            <span class="text-warning">
                                                <?php for ($i=1; $i<=5; $i++): ?>
                                                    <i class="bi bi-star<?= $i <= (int)$row['rating'] ? '-fill' : '' ?>"></i>
                                                <?php endfor; ?>
                                            </span>
                                            <?php if (!empty($row['ulasan'])): ?>
                                                <div class="small text-muted mt-1"><?= htmlspecialchars($row['ulasan']) ?></div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <form method="POST" class="d-flex flex-column gap-1">
                                                <input type="hidden" name="rating_id" value="<?= (int)$row['id'] ?>">
                                                <select name="rating" class="form-select form-select-sm" required>
                                                    <option value="">★</option>
                                                    <?php for ($i=1; $i<=5; $i++): ?>
                                                        <option value="<?= $i ?>"><?= $i ?> bintang</option>
                                                    <?php endfor; ?>
                                                </select>
                                                <input type="text" name="ulasan" class="form-control form-control-sm" placeholder="Ulasan (opsional)">
                                                <button type="submit" name="kirim_rating" class="btn btn-success btn-sm">Kirim</button>
                                            </form>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
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
    </div>
</div>

<script>
(function () {
    window.RECYCASH_BASE_URL = '<?= htmlspecialchars($baseUrl) ?>';

    document.addEventListener('DOMContentLoaded', function () {
        var inputCari  = document.getElementById('cariLokasi');
        var hasilCari  = document.getElementById('hasilLokasi');
        var infoLokasi = document.getElementById('infoLokasi');
        var btnGps     = document.getElementById('btnGps');
        var inputAlamat= document.getElementById('alamat_jemput');
        var latEl      = document.getElementById('latJemput');
        var lngEl      = document.getElementById('lngJemput');
        var labelEl    = document.getElementById('lokasiLabel');

        function setLokasi(l, g, label, isiAlamat) {
            latEl.value = l.toFixed(7);
            lngEl.value = g.toFixed(7);
            labelEl.value = label || '';
            infoLokasi.innerHTML = 'Lokasi dipilih: <strong>' + Lokasi.escapeHtml(label || (l.toFixed(5) + ', ' + g.toFixed(5))) + '</strong>';
            if (isiAlamat && inputAlamat && !inputAlamat.value.trim()) {
                inputAlamat.value = label || '';
            }
        }

        if (latEl.value && lngEl.value) {
            setLokasi(parseFloat(latEl.value), parseFloat(lngEl.value), labelEl.value, false);
        }

        Lokasi.pencarian({
            input: inputCari,
            container: hasilCari,
            onPilih: function (tempat) {
                setLokasi(tempat.lat, tempat.lng, tempat.label, true);
                inputCari.value = tempat.label;
            },
            onGagal: function (err) {
                infoLokasi.innerHTML = '<span class="text-danger">' + Lokasi.escapeHtml(err.message) + '</span>';
            }
        });

        btnGps.addEventListener('click', function () {
            var btn = this;
            var awal = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

            Lokasi.geoPosisi()
                .then(function (pos) {
                    setLokasi(pos.lat, pos.lng, 'Lokasi GPS saya', true);
                    inputCari.value = 'Lokasi GPS saya';
                })
                .catch(function (err) {
                    infoLokasi.innerHTML = '<span class="text-danger">' + Lokasi.escapeHtml(err.message) + '</span>';
                })
                .finally(function () {
                    btn.disabled = false;
                    btn.innerHTML = awal;
                });
        });

        /* ---- Polling status penjemputan aktif ---- */
        var kartuAktif = document.getElementById('kartuAktif');

        if (kartuAktif) {
            var requestId = <?= (int) ($request_aktif['id'] ?? 0) ?>;

            setInterval(function () {
                fetch(Lokasi.url('/api/pickup_status.php?id=' + requestId), { credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (!res || !res.ok) {
                            return;
                        }

                        var badge = document.getElementById('badgeStatusAktif');
                        var info  = document.getElementById('infoTellerAktif');
                        var nama  = document.getElementById('namaTellerAktif');

                        var label = res.status_teks + (res.tiba ? ' — teller sudah tiba' : '');
                        if (badge && badge.textContent !== label) {
                            badge.textContent = label;
                        }

                        if (nama && res.teller && res.teller.nama) {
                            nama.textContent = res.teller.nama;
                        }

                        if (info) {
                            var teks = '';
                            if (res.tiba) {
                                teks = 'Teller sudah tiba di lokasi. Silakan hubungi langsung.';
                            } else if (res.jarak_teks) {
                                teks = 'Jarak ' + res.jarak_teks + ' · estimasi ±' + res.eta_menit + ' menit';
                            } else {
                                teks = 'Sedang mencari teller terdekat';
                            }
                            info.textContent = teks;
                        }

                        if (res.teller && res.teller.telepon) {
                            var tel = document.getElementById('btnTelAktif');
                            if (tel) {
                                tel.href = 'tel:' + res.teller.telepon.replace(/[^0-9+]/g, '');
                            }
                        }
                    })
                    .catch(function () {});
            }, 10000);
        }
    });
})();
</script>

<?php
$extraJS = [
    $baseUrl . '/assets/js/lokasi.js',
];
require_once __DIR__ . '/../../includes/footer.php';
?>