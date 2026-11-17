<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<?php
if (($role ?? null) !== 'nasabah') {
    header('Location: ' . $baseUrl . '/auth/login.php');
    exit;
}

require_once __DIR__ . '/../../config/koneksi.php';

// ==================== KATALOG HARGA (READ-ONLY) ====================
$stmt = $pdo->query("
    SELECT id, nama_kategori, satuan, harga_beli_per_kg
    FROM categories_sampah
    ORDER BY nama_kategori ASC
");
$kategori = $stmt->fetchAll();

// ==================== DETEKSI GOLONGAN / WARNA ====================
function deteksiTipeSampah($nama) {
    $nama = strtolower(trim($nama));
    $map  = [
        'kaca'    => ['botol kaca', 'gelas kaca', 'beling', 'kaca'],
        'plastik' => ['plastik', 'botol', 'hdpe', 'pp ', 'lampion'],
        'kertas'  => ['kertas', 'kardus', 'koran', 'buku', 'duplek', 'majalah'],
        'logam'   => ['logam', 'besi', 'aluminium', 'alumunium', 'kaleng', 'tembaga', 'kuningan', 'stainless'],
        'organik' => ['organik', 'makanan', 'dapur', 'daun', 'sayur'],
    ];
    foreach ($map as $tipe => $kata) {
        foreach ($kata as $k) {
            if (strpos($nama, $k) !== false) {
                return $tipe;
            }
        }
    }
    return 'lainnya';
}

$tipeConfig = [
    'plastik'  => ['label' => 'Plastik',  'icon' => 'bi-cup-straw',      'warna' => '#0ea5e9'],
    'kertas'   => ['label' => 'Kertas',   'icon' => 'bi-journal-text',   'warna' => '#d97706'],
    'logam'    => ['label' => 'Logam',    'icon' => 'bi-tools',          'warna' => '#dc2626'],
    'kaca'     => ['label' => 'Kaca',     'icon' => 'bi-gem',            'warna' => '#16a34a'],
    'organik'  => ['label' => 'Organik',  'icon' => 'bi-tree',           'warna' => '#059669'],
    'lainnya'  => ['label' => 'Lainnya',  'icon' => 'bi-box2',           'warna' => '#64748b'],
];

foreach ($kategori as &$row) {
    $row['tipe'] = deteksiTipeSampah($row['nama_kategori']);
}
unset($row);

$tipeHadir = [];
foreach ($kategori as $row) {
    $tipeHadir[$row['tipe']] = true;
}

// ==================== STATISTIK ====================
$tampilHarga = array_filter($kategori, function ($k) {
    return (float) $k['harga_beli_per_kg'] > 0;
});

$jumlah       = count($kategori);
$hargaTertinggi = $tampilHarga ? max(array_map(function ($k) {
    return (float) $k['harga_beli_per_kg'];
}, $tampilHarga)) : 0;
$hargaTerendah  = $tampilHarga ? min(array_map(function ($k) {
    return (float) $k['harga_beli_per_kg'];
}, $tampilHarga)) : 0;
$rataBeli     = $tampilHarga ? array_sum(array_map(function ($k) {
    return (float) $k['harga_beli_per_kg'];
}, $tampilHarga)) / count($tampilHarga) : 0;
?>

<!-- ======================== STATISTIK ======================== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="card stat-card">
            <div class="stat-icon bg-success"><i class="bi bi-tags-fill"></i></div>
            <div class="stat-info">
                <span class="stat-label">Total Kategori</span>
                <span class="stat-value"><?= number_format($jumlah) ?></span>
                <span class="stat-sub">Jenis sampah terdaftar</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card stat-card">
            <div class="stat-icon bg-info"><i class="bi bi-info-circle-fill"></i></div>
            <div class="stat-info">
                <span class="stat-label">Harga Termurah</span>
                <span class="stat-value">Rp <?= number_format($hargaTerendah, 0, ',', '.') ?></span>
                <span class="stat-sub">per satuan</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card stat-card">
            <div class="stat-icon bg-warning"><i class="bi bi-gem"></i></div>
            <div class="stat-info">
                <span class="stat-label">Harga Termahal</span>
                <span class="stat-value">Rp <?= number_format($hargaTertinggi, 0, ',', '.') ?></span>
                <span class="stat-sub">per satuan</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card stat-card">
            <div class="stat-icon bg-danger"><i class="bi bi-cash-coin"></i></div>
            <div class="stat-info">
                <span class="stat-label">Rata-rata Harga</span>
                <span class="stat-value">Rp <?= number_format($rataBeli, 0, ',', '.') ?></span>
                <span class="stat-sub">per satuan</span>
            </div>
        </div>
    </div>
</div>

<!-- ======================== PENCARIAN & FILTER ======================== -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="search" id="searchHarga" class="form-control" placeholder="Cari jenis sampah...">
                </div>
            </div>
            <div class="col-md-7">
                <div class="d-flex flex-wrap gap-2" id="filterChips">
                    <button type="button" class="btn btn-sm chip-tipe active" data-tipe="all">
                        <i class="bi bi-grid-fill me-1"></i>Semua
                    </button>
                    <?php foreach ($tipeConfig as $tipe => $cfg): ?>
                        <?php if (isset($tipeHadir[$tipe])): ?>
                            <button type="button" class="btn btn-sm chip-tipe btn-outline-secondary" data-tipe="<?= $tipe ?>"
                                    style="--chip: <?= $cfg['warna'] ?>;">
                                <i class="bi <?= $cfg['icon'] ?> me-1"></i><?= $cfg['label'] ?>
                            </button>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ======================== KATALOG HARGA (CARD GRID) ======================== -->
<?php if (empty($kategori)): ?>
    <div class="card">
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-inbox" style="font-size:40px;"></i>
            <p class="mt-2">Belum ada data harga sampah.</p>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3" id="katalogGrid">
        <?php foreach ($kategori as $row): ?>
            <?php $cfg = $tipeConfig[$row['tipe']]; ?>
            <div class="col-6 col-md-4 col-lg-3 produk-wrap" data-tipe="<?= $row['tipe'] ?>">
                <div class="card produk-card h-100" data-id="<?= (int) $row['id'] ?>">
                    <div class="produk-avatar" style="background: <?= $cfg['warna'] ?>;">
                        <i class="bi <?= $cfg['icon'] ?>"></i>
                    </div>
                    <div class="card-body p-3 d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <h6 class="produk-nama mb-0"><?= htmlspecialchars($row['nama_kategori']) ?></h6>
                        </div>
                        <small class="text-muted fw-medium"><?= $cfg['label'] ?> &bull; <?= htmlspecialchars($row['satuan']) ?></small>

                        <div class="harga-badge" style="--harga: <?= $cfg['warna'] ?>;">
                            <span class="harga-angka">Rp <?= number_format($row['harga_beli_per_kg'], 0, ',', '.') ?></span>
                            <span class="harga-satuan">/<?= htmlspecialchars($row['satuan']) ?></span>
                        </div>

                        <button type="button" class="btn btn-sm btn-success mt-auto btn-hit mt-2"
                                data-hit="<?= (int) $row['id'] ?>">
                            <i class="bi bi-calculator me-1"></i>Hitung Saldo
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="col-12 d-none" id="produkKosong">
            <div class="card">
                <div class="card-body text-center py-5 text-muted">
                    <i class="bi bi-search" style="font-size:40px;"></i>
                    <p class="mt-2">Jenis sampah tidak ditemukan.</p>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ======================== KALKULATOR SIMULASI ======================== -->
<div class="card mt-4" id="kalkulator">
    <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-calculator me-2 text-success"></i>Kalkulator Tabungan Sampah</h6>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-5">
                <label for="kategoriKalkulator" class="form-label fw-semibold">Jenis Sampah</label>
                <select class="form-select" id="kategoriKalkulator">
                    <option value="" selected disabled>-- Pilih jenis sampah --</option>
                    <?php foreach ($tipeConfig as $tipe => $cfg): ?>
                        <?php if (!isset($tipeHadir[$tipe])) continue; ?>
                        <optgroup label="<?= $cfg['label'] ?>">
                            <?php foreach ($kategori as $row): ?>
                                <?php if ($row['tipe'] !== $tipe) continue; ?>
                                <option value="<?= (int) $row['id'] ?>"
                                        data-price="<?= (float) $row['harga_beli_per_kg'] ?>"
                                        data-satuan="<?= htmlspecialchars($row['satuan']) ?>">
                                    <?= htmlspecialchars($row['nama_kategori']) ?>
                                    (Rp <?= number_format($row['harga_beli_per_kg'], 0, ',', '.') ?>/<?= htmlspecialchars($row['satuan']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="beratKalkulator" class="form-label fw-semibold">Estimasi Berat</label>
                <div class="input-group">
                    <input type="number" id="beratKalkulator" class="form-control" step="0.01" min="0"
                           placeholder="cth: 2.5">
                    <span class="input-group-text" id="satuanLabel">kg</span>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Perkiraan Saldo</label>
                <div class="kalkulasi-hasil p-3 text-center rounded-3 bg-success-subtle">
                    <div class="text-muted small" id="hasilDetail">Pilih jenis & masukkan berat</div>
                    <div class="kalkulasi-nominal mt-1" id="hasilNominal">Rp 0</div>
                </div>
            </div>
        </div>
        <div class="form-text mt-3">
            <i class="bi bi-info-circle me-1"></i>Simulasi ini hanya perkiraan. Nilai akhir ditentukan petugas setelah penimbangan & pemilahan di lokasi.
        </div>
    </div>
</div>

<!-- ======================== KETENTUAN & TIPS ======================== -->
<div class="card mt-4">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-success"><i class="bi bi-lightbulb-fill"></i></div>
                <div>
                    <h6 class="mb-1">Ketentuan & Tips Pemilahan Sampah</h6>
                    <small class="text-muted">Pastikan sampah memenuhi syarat agar layak ditimbang.</small>
                </div>
            </div>
            <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalAturan">
                <i class="bi bi-info-circle me-1"></i>Lihat Ketentuan
            </button>
        </div>
    </div>
</div>

<!-- MODAL KETENTUAN -->
<div class="modal fade" id="modalAturan" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-lightbulb-fill me-2 text-success"></i>Ketentuan & Tips Pemilahan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ul class="list-unstyled aturan-list mb-0">
                    <li class="aturan-item" style="--dot:#0ea5e9">
                        <span class="aturan-icon"><i class="bi bi-cup-straw"></i></span>
                        <div>
                            <strong>Plastik</strong>
                            <span>Bersihkan dan keringkan sebelum disetor. Pisahkan dari tutup & label bila memungkinkan.</span>
                        </div>
                    </li>
                    <li class="aturan-item" style="--dot:#d97706">
                        <span class="aturan-icon"><i class="bi bi-journal-text"></i></span>
                        <div>
                            <strong>Kertas / Kardus</strong>
                            <span>Tidak boleh basah, kotor, atau berlumut. Ikat & rapikan per jenisnya.</span>
                        </div>
                    </li>
                    <li class="aturan-item" style="--dot:#dc2626">
                        <span class="aturan-icon"><i class="bi bi-tools"></i></span>
                        <div>
                            <strong>Logam</strong>
                            <span>Pisahkan logam ringan (kaleng) dan berat (besi). Bersihkan dari karat & karat berat.</span>
                        </div>
                    </li>
                    <li class="aturan-item" style="--dot:#16a34a">
                        <span class="aturan-icon"><i class="bi bi-gem"></i></span>
                        <div>
                            <strong>Kaca</strong>
                            <span>Kosongkan isinya dan bungkus pecahan kaca agar aman saat dibawa.</span>
                        </div>
                    </li>
                    <li class="aturan-item" style="--dot:#059669">
                        <span class="aturan-icon"><i class="bi bi-tree"></i></span>
                        <div>
                            <strong>Organik</strong>
                            <span>Pisahkan sisa makanan/daun dari sampah plastik. Sampah harus layak setor.</span>
                        </div>
                    </li>
                    <li class="aturan-item" style="--dot:#64748b">
                        <span class="aturan-icon"><i class="bi bi-info-circle"></i></span>
                        <div>
                            <strong>Umum</strong>
                            <span>Harga sewaktu-waktu dapat berubah sesuai tarif resmi. Saldo masuk ke dompet nasabah setelah setoran diverifikasi.</span>
                        </div>
                    </li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
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
        transition: transform 0.2s;
        height: 100%;
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
.stat-label { font-size: 12.5px; color: var(--text-muted); font-weight: 500; }
        .stat-value { font-size: 19px; font-weight: 700; color: var(--text-strong); }
        .stat-sub   { font-size: 11.5px; color: var(--text-soft); }

    .chip-tipe { border-radius: 50rem; transition: all 0.15s; }
    .chip-tipe.active {
        background: var(--chip, #2e7d32) !important;
        border-color: var(--chip, #2e7d32) !important;
        color: #fff !important;
        font-weight: 600;
    }

    .produk-card {
        border: none;
        border-radius: 14px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        transition: transform 0.2s, box-shadow 0.2s;
        overflow: hidden;
        height: 100%;
    }
    .produk-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 22px rgba(0,0,0,0.10);
    }
    .produk-avatar {
        height: 64px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 30px;
        color: #fff;
    }
    .produk-nama { font-size: 14.5px; font-weight: 700; color: var(--text-strong); line-height: 1.3; }
    .harga-badge {
        margin: 12px 0;
        padding: 8px 12px;
        border-radius: 10px;
        background: color-mix(in srgb, var(--harga) 12%, #fff);
        border-left: 4px solid var(--harga);
    }
    .harga-angka { font-size: 17px; font-weight: 800; color: var(--harga); }
    .harga-satuan { font-size: 12px; color: var(--text-muted); font-weight: 500; }

    .kalkulasi-nominal { font-size: 24px; font-weight: 800; color: var(--eco-700); }

    .aturan-item {
        display: flex;
        gap: 12px;
        padding: 12px 0;
        border-bottom: 1px dashed #e2e8f0;
    }
    .aturan-item:last-child { border-bottom: none; }
    .aturan-icon {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 17px;
        color: #fff;
        background: var(--dot);
        flex-shrink: 0;
    }
    .aturan-item strong  { display: block; font-size: 14px; }
    .aturan-item span    { color: var(--text-muted); font-size: 13px; }

    @media (max-width: 767px) {
        .stat-card { padding: 14px; gap: 10px; }
        .stat-icon { width: 42px; height: 42px; font-size: 18px; border-radius: 12px; }
        .stat-label { font-size: 11.5px; }
        .stat-value { font-size: 16px; }
        .produk-nama { font-size: 13px; }
        .harga-angka { font-size: 15px; }
    }
</style>

<script>
(function () {
    function formatRp(n) {
        n = Math.round(Number(n) || 0);
        return 'Rp ' + n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    // ====== Pencarian + filter kategori ======
    var searchInput = document.getElementById('searchHarga');
    var cards       = document.querySelectorAll('.produk-wrap');
    var kotaKosong  = document.getElementById('produkKosong');
    var filterAktif = 'all';

    function terapkanFilter() {
        var q = (searchInput.value || '').toLowerCase().trim();
        var terlihat = 0;
        cards.forEach(function (card) {
            var tipe = card.getAttribute('data-tipe');
            var cocokTipe = (filterAktif === 'all' || tipe === filterAktif);
            var cocokCari = (q === '' || (card.textContent || '').toLowerCase().indexOf(q) !== -1);
            var tampil = cocokTipe && cocokCari;
            card.style.display = tampil ? '' : 'none';
            if (tampil) terlihat++;
        });
        if (kotaKosong) {
            kotaKosong.classList.toggle('d-none', terlihat > 0);
        }
    }

    searchInput.addEventListener('input', terapkanFilter);

    document.querySelectorAll('.chip-tipe').forEach(function (chip) {
        chip.addEventListener('click', function () {
            filterAktif = this.getAttribute('data-tipe');
            document.querySelectorAll('.chip-tipe').forEach(function (c) { c.classList.remove('active'); });
            this.classList.add('active');
            terapkanFilter();
        });
    });

    // ====== Kalkulator simulasi ======
    var selKategori = document.getElementById('kategoriKalkulator');
    var beratInput  = document.getElementById('beratKalkulator');
    var satuanLabel = document.getElementById('satuanLabel');
    var hasilNominal = document.getElementById('hasilNominal');
    var hasilDetail  = document.getElementById('hasilDetail');

    function hitungSimulasi() {
        var opt = selKategori.options[selKategori.selectedIndex];
        if (!opt || opt.value === '') {
            hasilNominal.textContent = 'Rp 0';
            hasilDetail.textContent  = 'Pilih jenis & masukkan berat';
            satuanLabel.textContent  = 'kg';
            return;
        }
        var harga  = parseFloat(opt.getAttribute('data-price')) || 0;
        var satuan = opt.getAttribute('data-satuan') || 'kg';
        var berat  = parseFloat(String(beratInput.value).replace(',', '.')) || 0;
        satuanLabel.textContent = satuan;
        hasilNominal.textContent = formatRp(harga * berat);
        if (berat > 0) {
            hasilDetail.textContent = harga.toString().replace('.', ',') + ' / ' + satuan +
                ' x ' + berat.toString().replace('.', ',') + ' ' + satuan;
        } else {
            hasilDetail.textContent = 'Masukkan estimasi berat';
        }
    }

    selKategori.addEventListener('change', hitungSimulasi);
    beratInput.addEventListener('input', hitungSimulasi);

    // ====== Tombol "Hitung Saldo" pada kartu ======
    document.querySelectorAll('.btn-hit').forEach(function (btn) {
        btn.addEventListener('click', function () {
            selKategori.value = this.getAttribute('data-hit');
            hitungSimulasi();
            beratInput.focus();
            document.getElementById('kalkulator').scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>