<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/koneksi.php';

// ---------- Proteksi akses: hanya petugas (Teller) ----------
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'petugas') {
    $_SESSION['error'] = "Anda tidak memiliki akses ke halaman tersebut.";
    header('Location: ../../auth/login.php');
    exit;
}

// ---------- Helper ----------
function rp($n): string {
    return 'Rp ' . number_format((float) $n, 0, ',', '.');
}
function kg($n): string {
    return number_format((float) $n, 2, ',', '.') . ' kg';
}
function fTanggal(?string $t, bool $withTime = true): string {
    if (!$t) return '-';
    return date($withTime ? 'd/m/Y H:i' : 'd/m/Y', strtotime($t));
}
function kolomAda(PDO $pdo, string $table, string $kolom): bool {
    $s = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE :k");
    $s->execute([':k' => $kolom]);
    return (bool) $s->fetch();
}

// Catatan bersifat opsional (migrasi belum tentu dijalankan)
$adaCatatan = kolomAda($pdo, 'deposit_transactions', 'catatan');

// ==================================================================
// Ambil parameter: id (utama) atau kode transaksi (alternatif)
// ==================================================================
$trxId  = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$kode   = trim($_GET['kode'] ?? '');
$nota   = null;
$detail = [];
$notFound = false;

if ($trxId > 0 || $kode !== '') {
    $kolomCatatan = $adaCatatan ? ", dt.catatan" : ", NULL AS catatan";
    $where  = $trxId > 0 ? "dt.id = :key" : "dt.kode_transaksi = :key";
    $key    = $trxId > 0 ? $trxId : $kode;

    $stmt = $pdo->prepare("
        SELECT dt.id, dt.kode_transaksi, dt.total_nominal, dt.total_berat, dt.created_at{$kolomCatatan},
               nsb.nama AS nama_nasabah, nsb.nomor_hp, nsb.alamat,
               ptg.nama AS nama_petugas
        FROM deposit_transactions dt
        INNER JOIN users nsb ON nsb.id = dt.nasabah_id
        INNER JOIN users ptg ON ptg.id = dt.petugas_id
        WHERE $where
        LIMIT 1
    ");
    $stmt->execute([':key' => $key]);
    $nota = $stmt->fetch();

    if ($nota) {
        // Rincian tiap jenis sampah yang disetor
        $stmt = $pdo->prepare("
            SELECT dd.berat_kg, dd.harga_per_kg, dd.subtotal, cs.nama_kategori, cs.satuan
            FROM deposit_details dd
            INNER JOIN categories_sampah cs ON cs.id = dd.category_id
            WHERE dd.transaction_id = :tid
            ORDER BY dd.id ASC
        ");
        $stmt->execute([':tid' => $nota['id']]);
        $detail = $stmt->fetchAll();

        // Siapkan tautan WhatsApp untuk bukti digital ke nasabah
        $waMsg  = "RecyCash - Nota Setoran Sampah\n";
        $waMsg .= "Kode: " . $nota['kode_transaksi'] . "\n";
        $waMsg .= "Nasabah: " . $nota['nama_nasabah'] . "\n";
        $waMsg .= "Tanggal: " . date('d/m/Y H:i', strtotime($nota['created_at'])) . "\n";
        $waMsg .= "Total Berat: " . number_format($nota['total_berat'], 2, ',', '.') . " kg\n";
        $waMsg .= "Total Diterima: Rp " . number_format($nota['total_nominal'], 0, ',', '.') . "\n";
        if (!empty($detail)) {
            $waMsg .= "Rincian:\n";
            foreach ($detail as $d) {
                $waMsg .= "- " . $d['nama_kategori'] . ": " . number_format($d['berat_kg'], 2, ',', '.') . " " . $d['satuan'] . " x Rp " . number_format($d['harga_per_kg'], 0, ',', '.') . " = Rp " . number_format($d['subtotal'], 0, ',', '.') . "\n";
            }
        }
        $waPhone = preg_replace('/[^0-9]/', '', (string) ($nota['nomor_hp'] ?? ''));
        if ($waPhone !== '' && str_starts_with($waPhone, '0')) {
            $waPhone = '62' . substr($waPhone, 1);
        }
        $waUrl = 'https://wa.me/' . ($waPhone !== '' ? $waPhone : '') . '?text=' . rawurlencode($waMsg);
    } else {
        $notFound = true;
    }
}

// ==================================================================
// Bila tidak ada parameter -> tampilkan daftar setoran terakhir
// ==================================================================
$setoranList = [];
if (!$nota) {
    $setoranList = $pdo->query("
        SELECT dt.id, dt.kode_transaksi, dt.created_at, dt.total_nominal, dt.total_berat,
               u.nama AS nama_nasabah
        FROM deposit_transactions dt
        INNER JOIN users u ON u.id = dt.nasabah_id
        ORDER BY dt.created_at DESC, dt.id DESC
        LIMIT 100
    ")->fetchAll();
}

// Auto print: aktif secara default saat nota tampil, dapat dimatikan dgn ?autoprint=0
$autoPrint = isset($_GET['autoprint']) ? ($_GET['autoprint'] !== '0') : true;
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<?php if ($notFound): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2 no-print" role="alert">
        <i class="bi bi-exclamation-triangle"></i>
        <div>Transaksi tidak ditemukan. Pastikan ID/kode transaksi benar.</div>
    </div>
<?php endif; ?>

<?php if (!$nota): ?>
    <!-- ======================== PILIH TRANSAKSI ======================== -->
    <div class="no-print">
        <div class="card mb-4">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h6 class="mb-0"><i class="bi bi-receipt me-2"></i>Cetak Nota Setoran</h6>
                <a href="cetak.nota.php" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-clockwise me-1"></i>Muat Ulang
                </a>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Pilih transaksi di bawah, atau buka langsung dengan parameter
                    <code>cetak.nota.php?id=12</code>.
                </p>
                <div class="row g-2 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label">Cari (Kode / Nama Nasabah)</label>
                        <input type="text" id="cariSetoran" class="form-control" placeholder="Ketik untuk menyaring...">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Buka lewat ID Transaksi</label>
                        <form class="input-group" method="GET" action="cetak.nota.php">
                            <input type="number" min="1" name="id" class="form-control" placeholder="cth: 12">
                            <button class="btn btn-success" type="submit"><i class="bi bi-box-arrow-up-right"></i></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <?php if (empty($setoranList)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox" style="font-size:40px;"></i>
                        <p class="mt-2 mb-0">Belum ada transaksi setoran.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="tabelSetoran">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" style="width:50px;">No</th>
                                    <th>Tanggal &amp; Kode</th>
                                    <th>Nasabah</th>
                                    <th class="text-end">Berat</th>
                                    <th class="text-end">Nominal</th>
                                    <th class="text-center" style="width:110px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($setoranList as $i => $r): ?>
                                    <tr>
                                        <td class="text-center"><?= $i + 1 ?></td>
                                        <td>
                                            <strong class="text-nowrap"><?= htmlspecialchars($r['kode_transaksi']) ?></strong>
                                            <div class="text-muted small text-nowrap"><?= fTanggal($r['created_at']) ?></div>
                                        </td>
                                        <td><?= htmlspecialchars($r['nama_nasabah']) ?></td>
                                        <td class="text-end text-nowrap"><?= kg($r['total_berat']) ?></td>
                                        <td class="text-end fw-bold text-success text-nowrap"><?= rp($r['total_nominal']) ?></td>
                                        <td class="text-center">
                                            <a href="cetak.nota.php?id=<?= (int) $r['id'] ?>"
                                               class="btn btn-sm btn-success text-nowrap">
                                                <i class="bi bi-printer me-1"></i>Cetak
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
    </div>

    <script>
    (function () {
        var input = document.getElementById('cariSetoran');
        var tabel = document.getElementById('tabelSetoran');
        if (!input || !tabel) return;
        input.addEventListener('input', function () {
            var q = this.value.toLowerCase();
            tabel.querySelectorAll('tbody tr').forEach(function (tr) {
                tr.style.display = tr.textContent.toLowerCase().indexOf(q) > -1 ? '' : 'none';
            });
        });
    })();
    </script>
<?php else: ?>
    <!-- ======================== NOTA ======================== -->
    <div class="toolbar-print no-print">
        <a href="cetak.nota.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
        <div class="d-flex gap-2">
            <label class="btn btn-outline-success mb-0" for="autoPrint">
                <input type="checkbox" id="autoPrint" <?= $autoPrint ? 'checked' : '' ?>>
                Auto Cetak
            </label>
            <button type="button" class="btn btn-success" onclick="window.print()">
                <i class="bi bi-printer me-1"></i>Cetak Nota
            </button>
            <?php if (!empty($waUrl)): ?>
            <a href="<?= htmlspecialchars($waUrl) ?>" target="_blank" rel="noopener" class="btn btn-outline-success">
                <i class="bi bi-whatsapp me-1"></i>Bagikan via WhatsApp
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="nota-wrapper">
        <div class="nota" id="nota">
            <div class="nota-head">
                <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 48 48'%3E%3Crect width='48' height='48' rx='14' fill='%2314532D'/%3E%3Cpath d='M32 12c-8 1-13 6-13 14 7 0 12-5 13-14Z' fill='%23fff'/%3E%3Ccircle cx='36' cy='36' r='8' fill='%23F2C55C'/%3E%3C/svg%3E"
                     alt="Logo" class="nota-logo">
                <div class="nota-brand">
                    <h1>RECYCASH</h1>
                    <p>Daur Ulang Jadi Cuan</p>
                </div>
            </div>
            <div class="nota-title">NOTA SETORAN SAMPAH</div>

            <table class="nota-meta">
                <tr>
                    <td class="label">No. Transaksi</td>
                    <td class="value">: <strong><?= htmlspecialchars($nota['kode_transaksi']) ?></strong></td>
                </tr>
                <tr>
                    <td class="label">Tanggal</td>
                    <td class="value">: <?= fTanggal($nota['created_at']) ?></td>
                </tr>
                <tr>
                    <td class="label">Nasabah</td>
                    <td class="value">: <?= htmlspecialchars($nota['nama_nasabah']) ?></td>
                </tr>
                <?php if (!empty($nota['nomor_hp'])): ?>
                <tr>
                    <td class="label">No. HP</td>
                    <td class="value">: <?= htmlspecialchars($nota['nomor_hp']) ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($nota['alamat'])): ?>
                <tr>
                    <td class="label">Alamat</td>
                    <td class="value">: <?= htmlspecialchars($nota['alamat']) ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td class="label">Petugas</td>
                    <td class="value">: <?= htmlspecialchars($nota['nama_petugas']) ?></td>
                </tr>
            </table>

            <table class="nota-detail">
                <thead>
                    <tr>
                        <th>Jenis Sampah</th>
                        <th class="text-end">Berat</th>
                        <th class="text-end">Harga/kg</th>
                        <th class="text-end">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($detail)): ?>
                        <tr><td colspan="4" class="text-center text-muted">Tidak ada rincian.</td></tr>
                    <?php else: ?>
                        <?php foreach ($detail as $d): ?>
                            <tr>
                                <td><?= htmlspecialchars($d['nama_kategori']) ?></td>
                                <td class="text-end text-nowrap"><?= kg($d['berat_kg']) ?></td>
                                <td class="text-end text-nowrap"><?= rp($d['harga_per_kg']) ?></td>
                                <td class="text-end text-nowrap"><?= rp($d['subtotal']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3" class="text-end">Total Berat</th>
                        <th class="text-end text-nowrap"><?= kg($nota['total_berat']) ?></th>
                    </tr>
                    <tr class="nota-total">
                        <th colspan="3" class="text-end">TOTAL DITERIMA</th>
                        <th class="text-end text-nowrap"><?= rp($nota['total_nominal']) ?></th>
                    </tr>
                </tfoot>
            </table>

            <?php if (!empty($nota['catatan'])): ?>
                <div class="nota-catatan">
                    <strong>Catatan:</strong> <?= nl2br(htmlspecialchars($nota['catatan'])) ?>
                </div>
            <?php endif; ?>

            <div class="nota-foot">
                <p>Terima kasih telah menyetorkan sampah Anda.</p>
                <p class="small text-muted">Nota ini merupakan bukti sah transaksi setoran.</p>
            </div>
        </div>
    </div>

    <style>
        .toolbar-print {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
        }

        .nota-wrapper {
            display: flex;
            justify-content: center;
        }

        .nota {
            width: 100%;
            max-width: 440px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 22px 24px;
            box-shadow: 0 4px 18px rgba(15,23,42,0.08);
            color: var(--text-strong);
            font-size: 13px;
        }

        .nota-head {
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 2px dashed #cbd5e1;
            padding-bottom: 12px;
        }

        .nota-logo { width: 46px; height: 46px; object-fit: contain; }

        .nota-brand h1 {
            font-size: 20px;
            font-weight: 800;
            color: #2e7d32;
            margin: 0;
            letter-spacing: 1px;
        }

        .nota-brand p {
            margin: 0;
            font-size: 11px;
            color: var(--text-muted);
            letter-spacing: 0.5px;
        }

        .nota-title {
            text-align: center;
            font-weight: 700;
            font-size: 14px;
            letter-spacing: 1px;
            margin: 14px 0;
            color: #166534;
        }

        .nota-meta { width: 100%; margin-bottom: 14px; }
        .nota-meta td { vertical-align: top; padding: 2px 0; }
        .nota-meta .label { width: 110px; color: var(--text-muted); }
        .nota-meta .value { color: var(--text-strong); }

        .nota-detail {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .nota-detail th,
        .nota-detail td {
            border-bottom: 1px solid #e2e8f0;
            padding: 6px 4px;
            font-size: 12.5px;
        }

        .nota-detail thead th {
            border-top: 1px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
            color: var(--text-body);
            font-size: 11.5px;
            text-transform: uppercase;
        }

        .nota-detail tfoot th {
            border-bottom: none;
            padding-top: 8px;
        }

        .nota-detail .nota-total th {
            font-size: 15px;
            color: #2e7d32;
            border-top: 2px solid #cbd5e1;
            padding-top: 10px;
        }

        .nota-catatan {
            background: #f8fafc;
            border-left: 3px solid #94a3b8;
            border-radius: 6px;
            padding: 8px 10px;
            font-size: 12px;
            color: var(--text-body);
            margin-bottom: 12px;
        }

        .nota-foot {
            text-align: center;
            border-top: 2px dashed #cbd5e1;
            padding-top: 12px;
        }

        .nota-foot p { margin: 0; font-size: 12px; }
        .nota-foot .small { font-size: 10.5px; margin-top: 4px; }

        /* ======================== LAYOUT KHUSUS CETAK ======================== */
        @page {
            size: auto;
            margin: 8mm;
        }

        @media print {
            /* Sembunyikan seluruh elemen navigasi & tombol web */
            .top-navbar,
            .sidebar,
            .sidebar-overlay,
            .main-footer,
            .no-print {
                display: none !important;
            }

            body {
                background: #fff !important;
            }

            .main-content {
                margin: 0 !important;
                padding: 0 !important;
            }

            .nota-wrapper { display: block; }

            .nota {
                max-width: 100%;
                width: 100%;
                border: none;
                border-radius: 0;
                box-shadow: none;
                padding: 0;
            }
        }
    </style>

    <script>
    (function () {
        var kotak = document.getElementById('autoPrint');
        var sudah = false;
        function cetak() {
            if (sudah) return;
            sudah = true;
            window.print();
        }
        if (kotak && kotak.checked) {
            // Auto print saat halaman terbuka
            window.addEventListener('load', function () {
                setTimeout(cetak, 400);
            });
        }
    })();
    </script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
