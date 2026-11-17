<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/pickup_helper.php';
require_once __DIR__ . '/../../includes/notifikasi.php';

// Migrasi otomatis tabel stok (harus SEBELUM transaksi; DDL memicu implicit commit)
pastikanTabelStok($pdo);

// ---------- Proteksi akses: hanya petugas (Teller) ----------
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'petugas') {
    $_SESSION['error'] = "Anda tidak memiliki akses ke halaman tersebut.";
    header('Location: ../../auth/login.php');
    exit;
}

$petugasId = (int) ($_SESSION['id_user'] ?? 0);

// Ambil daftar nasabah + saldo (dataset kecil, aman dimuat sekaligus untuk autocomplete)
$stmt = $pdo->query("
    SELECT u.id, u.nama, u.nomor_hp, u.alamat, u.foto, COALESCE(w.saldo_rupiah, 0) AS saldo
    FROM users u
    LEFT JOIN wallets w ON w.user_id = u.id
    WHERE u.role = 'nasabah'
    ORDER BY u.nama ASC
");
$nasabahList = $stmt->fetchAll();

// Ambil daftar kategori sampah beserta harga beli (default harga teller)
$stmt = $pdo->query("SELECT * FROM categories_sampah ORDER BY nama_kategori ASC");
$kategoriList = $stmt->fetchAll();

// Deteksi kolom catatan (fitur opsional; aman bila migrasi belum dijalankan)
function kolomAda(PDO $pdo, string $table, string $kolom): bool {
    $s = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE :k");
    $s->execute([':k' => $kolom]);
    return (bool) $s->fetch();
}
$adaCatatan = kolomAda($pdo, 'deposit_transactions', 'catatan');

// ==================================================================
// PROSES SIMPAN SETORAN (PDO Transaction / Atomic)
// ==================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_setoran'])) {
    $nasabahId   = (int) ($_POST['nasabah_id'] ?? 0);
    $catatan     = trim($_POST['catatan'] ?? '');
    $categoryIds = $_POST['category_id'] ?? [];
    $berats      = $_POST['berat'] ?? [];

    $errors = [];

    // 1) Validasi nasabah
    $nasabah = null;
    if ($nasabahId <= 0) {
        $errors[] = "Nasabah wajib dipilih.";
    } else {
        $cekN = $pdo->prepare("SELECT id, nama FROM users WHERE id = :id AND role = 'nasabah' LIMIT 1");
        $cekN->execute([':id' => $nasabahId]);
        $nasabah = $cekN->fetch();
        if (!$nasabah) {
            $errors[] = "ID nasabah tidak valid.";
        }
    }

    // 2) Susun & validasi item setoran
    $categoryMap = [];
    foreach ($kategoriList as $k) {
        $categoryMap[(int) $k['id']] = $k;
    }

    $items = [];
    foreach ($categoryIds as $i => $cid) {
        $cid      = (int) $cid;
        $beratRaw = str_replace(',', '.', trim((string) ($berats[$i] ?? '')));
        $berat    = is_numeric($beratRaw) ? (float) $beratRaw : 0;

        // Lewati baris kosong (tanpa kategori & tanpa berat)
        if ($cid <= 0 && $berat <= 0) {
            continue;
        }
        if ($cid <= 0) {
            $errors[] = "Ada baris yang belum memilih kategori sampah.";
            continue;
        }
        if (!isset($categoryMap[$cid])) {
            $errors[] = "Kategori sampah tidak ditemukan pada baris ke-" . ($i + 1) . ".";
            continue;
        }
        if (!is_numeric($beratRaw) || $berat <= 0) {
            $errors[] = "Berat pada baris ke-" . ($i + 1) . " harus lebih besar dari 0.";
            continue;
        }

        $kat    = $categoryMap[$cid];
        $satuan = strtolower($kat['satuan'] ?: 'kg');
        // Normalisasi ke kilogram: gram dibagi 1000, satuan lain dianggap setara kg
        $beratKg = $satuan === 'gram' ? $berat / 1000 : $berat;
        $harga   = (float) $kat['harga_beli_per_kg'];
        $subtotal = round($beratKg * $harga, 2);

        $items[] = [
            'category_id' => $cid,
            'berat_kg'    => round($beratKg, 2),
            'harga'       => $harga,
            'subtotal'    => $subtotal,
        ];
    }

    if (empty($items) && empty($errors)) {
        $errors[] = "Minimal satu jenis sampah harus diisi dengan berat lebih dari 0.";
    }

    if (empty($errors)) {
        $totalBerat   = 0;
        $totalNominal = 0;
        foreach ($items as $it) {
            $totalBerat   += $it['berat_kg'];
            $totalNominal += $it['subtotal'];
        }
        $totalBerat   = round($totalBerat, 2);
        $totalNominal = round($totalNominal, 2);

        // Kode transaksi unik: DEP-YYYYMMDD-HHMMSS-XXX
        $kode = 'DEP-' . date('Ymd-His') . '-' . random_int(100, 999);

        try {
            require_once __DIR__ . '/../../includes/gamifikasi.php';
            $streakSebelum = hitungStreakHarian(ambilTanggalSetoran($pdo, $nasabahId));
            $rankSebelum   = tentukanRank($streakSebelum);

            $pdo->beginTransaction();

            // a) Simpan transaksi utama
            $kolomCatatan = $adaCatatan ? ", catatan" : "";
            $valCatatan   = $adaCatatan ? ", :catatan" : "";
            $sqlMain = "INSERT INTO deposit_transactions
                            (kode_transaksi, nasabah_id, petugas_id, total_nominal, total_berat{$kolomCatatan})
                        VALUES (:kode, :nasabah, :petugas, :nominal, :berat{$valCatatan})";
            $ins = $pdo->prepare($sqlMain);
            $par = [
                ':kode'    => $kode,
                ':nasabah' => $nasabahId,
                ':petugas' => $petugasId,
                ':nominal' => $totalNominal,
                ':berat'   => $totalBerat,
            ];
            if ($adaCatatan) {
                $par[':catatan'] = $catatan !== '' ? $catatan : null;
            }
            $ins->execute($par);
            $trxId = (int) $pdo->lastInsertId();

            // b) Simpan rincian tiap jenis sampah
            $insDetail = $pdo->prepare("
                INSERT INTO deposit_details (transaction_id, category_id, berat_kg, harga_per_kg, subtotal)
                VALUES (:tid, :cid, :berat, :harga, :subtotal)
            ");
            foreach ($items as $it) {
                $insDetail->execute([
                    ':tid'      => $trxId,
                    ':cid'      => $it['category_id'],
                    ':berat'    => $it['berat_kg'],
                    ':harga'    => $it['harga'],
                    ':subtotal' => $it['subtotal'],
                ]);

                // Stok gudang bertambah otomatis sesuai kategori
                stokCatat($pdo, (int) $it['category_id'], 'masuk', (float) $it['berat_kg'],
                    "Setoran $kode", 'SETORAN', $petugasId);
            }

            // c) Auto-update saldo nasabah (ambil / buat baris wallet lebih dulu)
            $w = $pdo->prepare("SELECT id FROM wallets WHERE user_id = :uid LIMIT 1 FOR UPDATE");
            $w->execute([':uid' => $nasabahId]);
            $wallet = $w->fetch();
            if ($wallet) {
                $pdo->prepare("UPDATE wallets SET saldo_rupiah = saldo_rupiah + :n WHERE user_id = :uid")
                    ->execute([':n' => $totalNominal, ':uid' => $nasabahId]);
            } else {
                $pdo->prepare("INSERT INTO wallets (user_id, saldo_rupiah, poin) VALUES (:uid, :n, 0)")
                    ->execute([':uid' => $nasabahId, ':n' => $totalNominal]);
            }

            $pdo->commit();

            require_once __DIR__ . '/../../includes/gamifikasi.php';
            $streakSesudah = hitungStreakHarian(ambilTanggalSetoran($pdo, $nasabahId));
            $rankSesudah   = tentukanRank($streakSesudah);

            // Notifikasi ke nasabah (in-app + email best-effort)
            try {
                $pesanNotif = "Setoran sampah berhasil dicatat (Kode: $kode). Saldo bertambah Rp " . number_format($totalNominal, 0, ',', '.') . ".";
                $htmlEmail = '<div style="font-family:Arial,sans-serif;font-size:14px;line-height:1.6">'
                    . '<h3 style="color:#2e7d32;margin:0 0 8px">RecyCash</h3>'
                    . '<p>Setoran Anda berhasil dicatat petugas.</p>'
                    . '<table cellpadding="6" style="border-collapse:collapse">'
                    . '<tr><td style="color:#64748b">Kode</td><td><strong>' . htmlspecialchars($kode) . '</strong></td></tr>'
                    . '<tr><td style="color:#64748b">Total Berat</td><td>' . htmlspecialchars((string) $totalBerat) . ' kg</td></tr>'
                    . '<tr><td style="color:#64748b">Saldo Bertambah</td><td><strong>Rp ' . number_format($totalNominal, 0, ',', '.') . '</strong></td></tr>'
                    . '</table>'
                    . '<p style="color:#64748b">Buka aplikasi RecyCash untuk melihat riwayat dan nota digital.</p></div>';
                notifKirim($pdo, $nasabahId, $pesanNotif, 'riwayat.php',
                    'RecyCash - Bukti Setoran ' . $kode, $htmlEmail);
            } catch (PDOException $e) {
                // abaikan
            }

            // Audit log pencatatan berat
            try {
                pastikanTabelAudit($pdo);
                $stmtA = $pdo->prepare("INSERT INTO audit_log (user_id, aksi, tabel, record_id, detail) VALUES (?, 'INSERT_SETORAN', 'deposit_transactions', ?, ?)");
                $stmtA->execute([$petugasId, $trxId, "Nasabah ID $nasabahId, berat {$totalBerat} kg, nominal Rp " . number_format($totalNominal, 0, ',', '.')]);
            } catch (PDOException $e) {
                // abaikan
            }

            $_SESSION['success'] = "Setoran berhasil disimpan (Kode: <strong>$kode</strong>). "
                . "Saldo nasabah <strong>" . htmlspecialchars($nasabah['nama']) . "</strong> "
                . "ditambah <strong>Rp " . number_format($totalNominal, 0, ',', '.') . "</strong>.";

            if ($rankSesudah['nama'] !== $rankSebelum['nama']) {
                $_SESSION['success'] .= "<br><span class='badge text-bg-warning me-1'><i class='bi bi-trophy-fill'></i></span>"
                    . "Rank baru <strong>" . htmlspecialchars($nasabah['nama']) . "</strong>: "
                    . "<strong>" . htmlspecialchars($rankSesudah['nama']) . "</strong>"
                    . " (streak " . $streakSesudah . " hari beruntun)";
            }

            header('Location: input_setor.php');
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = "Gagal menyimpan transaksi: " . $e->getMessage();
        }
    }

    if (!empty($errors)) {
        $_SESSION['error'] = implode('<br>', array_map('htmlspecialchars', $errors));
        $_SESSION['old'] = [
            'nasabah_id'   => $nasabahId,
            'catatan'      => $catatan,
            'category_ids' => array_map('intval', $categoryIds),
            'berats'       => array_values($berats),
        ];
        header('Location: input_setor.php');
        exit;
    }
}

// Data isian lama (agar tidak hilang saat validasi gagal)
$old = $_SESSION['old'] ?? null;
unset($_SESSION['old']);

// Pre-select nasabah dari query string (mis. dari tombol "Setor" di Kelola Jemput)
$preselectNasabahId = isset($_GET['nasabah_id']) ? (int) $_GET['nasabah_id'] : 0;

$flashError   = $_SESSION['error'] ?? '';
$flashSuccess = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);

// Siapkan JSON untuk JavaScript
$kategoriJson = [];
foreach ($kategoriList as $k) {
    $kategoriJson[] = [
        'id'     => (int) $k['id'],
        'nama'   => $k['nama_kategori'],
        'harga'  => (float) $k['harga_beli_per_kg'],
        'satuan' => strtolower($k['satuan'] ?: 'kg'),
    ];
}
$nasabahJson = [];
foreach ($nasabahList as $n) {
    $nasabahJson[] = [
        'id'     => (int) $n['id'],
        'nama'   => $n['nama'],
        'hp'     => $n['nomor_hp'] ?? '',
        'alamat' => $n['alamat'] ?? '',
        'foto'   => $n['foto'] ?? '',
        'saldo'  => (float) $n['saldo'],
    ];
}
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

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

<form id="formSetor" action="input_setor.php" method="POST" novalidate>

    <!-- ======================== 1. PILIH NASABAH ======================== -->
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0"><i class="bi bi-person-badge me-2"></i>Pilih Nasabah</h6>
        </div>
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-lg-6">
                    <label for="cariNasabah" class="form-label">Nama / Nomor HP / ID Nasabah</label>
                    <input type="text" class="form-control" id="cariNasabah" list="listNasabah"
                           placeholder="Ketik untuk mencari nasabah..." autocomplete="off">
                    <datalist id="listNasabah">
                        <?php foreach ($nasabahList as $n): ?>
                            <option value="<?= htmlspecialchars($n['nama'] . ($n['nomor_hp'] ? ' — ' . $n['nomor_hp'] : '')) ?>"
                                    data-id="<?= (int) $n['id'] ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                    <input type="hidden" name="nasabah_id" id="nasabahId" value="">
                    <div class="form-text"><i class="bi bi-info-circle me-1"></i>Pilih dari daftar, atau ketik ID nasabah secara langsung.</div>
                </div>
            </div>

            <!-- Info nasabah terpilih -->
            <div id="infoNasabah" class="nasabah-info mt-3 d-none">
                <div class="d-flex align-items-center gap-3">
                    <div class="nasabah-avatar" id="nsbAvatar">?</div>
                    <div class="flex-grow-1">
                        <div class="fw-bold" id="nsbNama">-</div>
                        <div class="small text-muted">
                            <span id="nsbHp"><i class="bi bi-telephone me-1"></i>-</span>
                            <span class="mx-2">•</span>
                            <span id="nsbAlamat"><i class="bi bi-geo-alt me-1"></i>-</span>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="small text-muted">Saldo Saat Ini</div>
                        <div class="fw-bold text-success" id="nsbSaldo">Rp 0</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ======================== 2. ITEM SAMPAH ======================== -->
    <div class="card mb-4">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h6 class="mb-0"><i class="bi bi-recycle me-2"></i>Rincian Jenis Sampah</h6>
            <button type="button" class="btn btn-success btn-sm d-inline-flex align-items-center gap-1" id="btnTambahItem">
                <i class="bi bi-plus-circle-fill"></i> Tambah Item
            </button>
        </div>
        <div class="card-body p-0">
            <?php if (empty($kategoriList)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-exclamation-triangle" style="font-size:40px;"></i>
                    <p class="mt-2 mb-0">Belum ada kategori sampah. Hubungi admin untuk menambah data harga.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0" id="tableItem">
                        <thead class="table-light">
                            <tr>
                                <th style="min-width:200px;">Kategori Sampah</th>
                                <th class="text-end" style="min-width:130px;">Harga / kg</th>
                                <th style="min-width:170px;">Berat</th>
                                <th class="text-end" style="min-width:130px;">Subtotal</th>
                                <th class="text-center" style="width:54px;"></th>
                            </tr>
                        </thead>
                        <tbody id="itemBody"><!-- baris diisi via JavaScript --></tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ======================== 3. RINGKASAN & CATATAN ======================== -->
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-chat-left-text me-2"></i>Catatan / Keterangan (Opsional)</h6>
                </div>
                <div class="card-body">
                    <textarea name="catatan" class="form-control" rows="4"
                              placeholder="cth: Setoran kardus hasil bersih-bersih gudang..."><?= htmlspecialchars($old['catatan'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card h-100 summary-card">
                <div class="card-body d-flex flex-column">
                    <h6 class="mb-3"><i class="bi bi-calculator me-2"></i>Ringkasan Transaksi</h6>
                    <div class="summary-row">
                        <span>Total Berat</span>
                        <strong id="totalBerat">0.00 kg</strong>
                    </div>
                    <div class="summary-row">
                        <span>Jumlah Item</span>
                        <strong id="totalItem">0 item</strong>
                    </div>
                    <hr>
                    <div class="summary-total">
                        <span>Total Diterima</span>
                        <span class="value" id="totalNominal">Rp 0</span>
                    </div>
                    <div class="mt-auto pt-3 d-grid gap-2">
                        <button type="submit" name="simpan_setoran" class="btn btn-success btn-lg">
                            <i class="bi bi-save me-1"></i>Simpan Setoran
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="btnReset">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Reset Form
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<style>
    .nasabah-info {
        border: 1px solid #bbf7d0;
        background: #f0fdf4;
        border-radius: 12px;
        padding: 14px 16px;
    }
    .nasabah-avatar {
        width: 46px; height: 46px; border-radius: 50%;
        background: var(--eco-600); color: var(--eco-50);
        display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: 18px; flex-shrink: 0;
    }
    .summary-card { border: none; box-shadow: 0 2px 14px rgba(46,125,50,0.12); }
    .summary-row {
        display: flex; justify-content: space-between; align-items: center;
        font-size: 14px; color: var(--text-body); padding: 4px 0;
    }
    .summary-total {
        display: flex; justify-content: space-between; align-items: center;
        font-weight: 600; color: var(--text-strong);
    }
    .summary-total .value { font-size: 24px; font-weight: 800; color: var(--eco-700); }
    .item-harga { font-weight: 600; color: var(--danger); white-space: nowrap; }
    .item-subtotal { font-weight: 700; color: var(--eco-700); white-space: nowrap; }
    .unit-hint { font-size: 11px; color: var(--text-soft); }
    @media (max-width: 767px) {
        .nasabah-info .row-info { flex-direction: column; }
    }
</style>

<script>
(function () {
    const KATEGORI      = <?= json_encode($kategoriJson) ?>;
    const NASABAH       = <?= json_encode($nasabahJson) ?>;
    const PROFILE_BASE  = '<?= $baseUrl ?>/assets/img/profile/';
    const OLD           = <?= json_encode($old ?: null) ?>;
    const PRESELECT_ID  = <?= json_encode($preselectNasabahId) ?>;
    const tabelAda      = !!document.getElementById('itemBody');

    const katMap = {};
    KATEGORI.forEach(function (k) { katMap[k.id] = k; });

    // ================== Helper format ==================
    function rp(n) {
        return 'Rp ' + Math.round(n || 0).toLocaleString('id-ID');
    }
    function duaDesimal(n) {
        return (Math.round((n || 0) * 100) / 100).toFixed(2);
    }

    // ================== Autocomplete nasabah ==================
    const inputNasabah = document.getElementById('cariNasabah');
    const hiddenNasabah = document.getElementById('nasabahId');
    const infoBox = document.getElementById('infoNasabah');

    const nsbByLabel = {};
    const nsbById = {};
    NASABAH.forEach(function (n) {
        const label = n.nama + (n.hp ? ' — ' + n.hp : '');
        nsbByLabel[label.toLowerCase()] = n;
        nsbById[String(n.id)] = n;
    });

    function pilihNasabah(n) {
        if (!n) {
            hiddenNasabah.value = '';
            infoBox.classList.add('d-none');
            return;
        }
        hiddenNasabah.value = n.id;
        const avatarEl = document.getElementById('nsbAvatar');
        avatarEl.innerHTML = '';
        if (n.foto) {
            const img = document.createElement('img');
            img.src = PROFILE_BASE + encodeURIComponent(n.foto);
            img.alt = '';
            avatarEl.appendChild(img);
        } else {
            avatarEl.textContent = (n.nama || '?').charAt(0).toUpperCase();
        }
        document.getElementById('nsbNama').textContent = n.nama;
        document.getElementById('nsbHp').innerHTML = '<i class="bi bi-telephone me-1"></i>' + (n.hp || '-');
        document.getElementById('nsbAlamat').innerHTML = '<i class="bi bi-geo-alt me-1"></i>' + (n.alamat || '-');
        document.getElementById('nsbSaldo').textContent = rp(n.saldo);
        infoBox.classList.remove('d-none');
    }

    function cariNasabah(val) {
        val = (val || '').trim().toLowerCase();
        if (val === '') return null;
        if (nsbById[val]) return nsbById[val];
        return nsbByLabel[val] || null;
    }

    inputNasabah.addEventListener('input', function () {
        pilihNasabah(cariNasabah(this.value));
    });
    inputNasabah.addEventListener('change', function () {
        pilihNasabah(cariNasabah(this.value));
    });

    // ================== Baris item dinamis ==================
    const itemBody = document.getElementById('itemBody');

    function opsiKategori(selectedId) {
        let html = '<option value="">-- Pilih Kategori --</option>';
        KATEGORI.forEach(function (k) {
            html += '<option value="' + k.id + '"' + (String(selectedId) === String(k.id) ? ' selected' : '') + '>'
                + k.nama + '</option>';
        });
        return html;
    }

    function tambahBaris(selectedId, beratVal) {
        const tr = document.createElement('tr');
        tr.innerHTML =
            '<td data-label="Kategori Sampah"><select class="form-select form-select-sm input-kategori" name="category_id[]">' + opsiKategori(selectedId) + '</select></td>' +
            '<td data-label="Harga / kg" class="text-end"><span class="item-harga">Rp 0</span></td>' +
            '<td data-label="Berat">' +
                '<div class="input-group input-group-sm">' +
                    '<input type="number" step="0.01" min="0" class="form-control input-berat" name="berat[]" placeholder="0" value="' + (beratVal != null ? beratVal : '') + '">' +
                    '<span class="input-group-text unit-label">kg</span>' +
                '</div>' +
                '<div class="unit-hint"></div>' +
            '</td>' +
            '<td data-label="Subtotal" class="text-end"><span class="item-subtotal">Rp 0</span></td>' +
            '<td class="text-center">' +
                '<button type="button" class="btn btn-sm btn-outline-danger btn-hapus" title="Hapus item"><i class="bi bi-trash"></i></button>' +
            '</td>';
        itemBody.appendChild(tr);

        const sel = tr.querySelector('.input-kategori');
        sel.addEventListener('change', function () { updateBaris(tr); });
        tr.querySelector('.input-berat').addEventListener('input', function () { updateBaris(tr); });
        tr.querySelector('.btn-hapus').addEventListener('click', function () {
            if (itemBody.querySelectorAll('tr').length > 1) {
                tr.remove();
            } else {
                tr.querySelector('.input-kategori').value = '';
                tr.querySelector('.input-berat').value = '';
            }
            updateBaris(tr);
            hitungTotal();
        });

        updateBaris(tr);
        return tr;
    }

    function updateBaris(tr) {
        const sel   = tr.querySelector('.input-kategori');
        const berat = tr.querySelector('.input-berat');
        const unitEl = tr.querySelector('.unit-label');
        const hintEl = tr.querySelector('.unit-hint');
        const hargaEl = tr.querySelector('.item-harga');
        const subEl = tr.querySelector('.item-subtotal');

        const kat = katMap[sel.value];
        if (!kat) {
            hargaEl.textContent = 'Rp 0';
            subEl.textContent = 'Rp 0';
            unitEl.textContent = 'kg';
            hintEl.textContent = '';
            return;
        }

        unitEl.textContent = kat.satuan;
        hargaEl.textContent = rp(kat.harga);

        const val = parseFloat(berat.value) || 0;
        const rate = kat.satuan === 'gram' ? 0.001 : 1;
        const kg = val * rate;
        hintEl.textContent = kat.satuan === 'gram' && val > 0 ? ('= ' + duaDesimal(kg) + ' kg') : '';
        subEl.textContent = rp(kg * kat.harga);
    }

    function hitungTotal() {
        let berat = 0, nominal = 0, jml = 0;
        itemBody.querySelectorAll('tr').forEach(function (tr) {
            const kat = katMap[tr.querySelector('.input-kategori').value];
            const val = parseFloat(tr.querySelector('.input-berat').value) || 0;
            if (!kat || val <= 0) return;
            const rate = kat.satuan === 'gram' ? 0.001 : 1;
            const kg = val * rate;
            berat += kg;
            nominal += kg * kat.harga;
            jml++;
        });
        document.getElementById('totalBerat').textContent = duaDesimal(berat) + ' kg';
        document.getElementById('totalNominal').textContent = rp(nominal);
        document.getElementById('totalItem').textContent = jml + ' item';
    }

    // Rangkai event: setiap perubahan baris -> hitung total
    function pasangObserver() {
        itemBody.addEventListener('input', hitungTotal);
        itemBody.addEventListener('change', hitungTotal);
    }

    document.getElementById('btnTambahItem').addEventListener('click', function () {
        tambahBaris();
        hitungTotal();
    });

    // ================== Validasi sebelum submit ==================
    document.getElementById('formSetor').addEventListener('submit', function (e) {
        if (!hiddenNasabah.value) {
            e.preventDefault();
            alert('Silakan pilih nasabah terlebih dahulu.');
            inputNasabah.focus();
            return;
        }
        let adaItem = false;
        itemBody.querySelectorAll('tr').forEach(function (tr) {
            const kat = tr.querySelector('.input-kategori').value;
            const val = parseFloat(tr.querySelector('.input-berat').value) || 0;
            if (kat && val > 0) adaItem = true;
        });
        if (!adaItem) {
            e.preventDefault();
            alert('Minimal satu jenis sampah harus diisi dengan berat lebih dari 0.');
        }
    });

    // ================== Reset form ==================
    document.getElementById('btnReset').addEventListener('click', function () {
        if (!confirm('Reset seluruh isian form?')) return;
        this.form.reset();
        hiddenNasabah.value = '';
        infoBox.classList.add('d-none');
        itemBody.innerHTML = '';
        tambahBaris();
        hitungTotal();
    });

    // ================== Inisialisasi ==================
    if (tabelAda) {
        pasangObserver();

        // Pre-select nasabah jika datang dari tombol "Setor" (Kelola Jemput)
        if (!OLD && PRESELECT_ID) {
            const n = nsbById[String(PRESELECT_ID)];
            if (n) {
                inputNasabah.value = n.nama + (n.hp ? ' — ' + n.hp : '');
                pilihNasabah(n);
            }
        }

        if (OLD && OLD.category_ids && OLD.category_ids.length) {
            // Pulihkan isian saat validasi server gagal
            if (OLD.nasabah_id) {
                const n = nsbById[String(OLD.nasabah_id)];
                if (n) {
                    inputNasabah.value = n.nama + (n.hp ? ' — ' + n.hp : '');
                    pilihNasabah(n);
                }
            }
            OLD.category_ids.forEach(function (cid, idx) {
                tambahBaris(cid || '', OLD.berats && OLD.berats[idx] != null ? OLD.berats[idx] : '');
            });
        } else {
            tambahBaris();
        }
        hitungTotal();
    }
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
