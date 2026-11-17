<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<?php
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/lokasi.php';
require_once __DIR__ . '/../../includes/notifikasi.php';

$petugasId = (int) ($_SESSION['id_user'] ?? 0);

pastikanTabelLokasi($pdo);

$pesan = '';

// Nilai lama (untuk isi ulang form)
$lokasi = tellerLokasiAmbil($pdo, $petugasId);

$old = [
    'nama_outlet'  => (string) ($lokasi['nama_outlet'] ?? ('Outlet ' . ($_SESSION['nama'] ?? 'Teller'))),
    'alamat'       => (string) ($lokasi['alamat'] ?? ($_SESSION['alamat'] ?? '')),
    'lat'          => $lokasi !== null ? (float) $lokasi['latitude'] : '',
    'lng'          => $lokasi !== null ? (float) $lokasi['longitude'] : '',
    'radius_km'    => $lokasi !== null ? (float) $lokasi['radius_km'] : 5.0,
    'status_aktif' => $lokasi !== null ? (int) $lokasi['status_aktif'] === 1 : 1,
    'catatan'      => (string) ($lokasi['catatan'] ?? ''),
];

// Simpan lokasi outlet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_outlet'])) {
    $old['nama_outlet']  = trim($_POST['nama_outlet'] ?? '');
    $old['alamat']       = trim($_POST['alamat'] ?? '');
    $old['lat']          = $_POST['lat'] ?? '';
    $old['lng']          = $_POST['lng'] ?? '';
    $old['radius_km']    = (float) ($_POST['radius_km'] ?? 5);
    $old['status_aktif'] = isset($_POST['status_aktif']) ? 1 : 0;
    $old['catatan']      = trim($_POST['catatan'] ?? '');

    $lat = is_numeric($old['lat']) ? (float) $old['lat'] : null;
    $lng = is_numeric($old['lng']) ? (float) $old['lng'] : null;

    if ($old['nama_outlet'] === '') {
        $pesan = "<div class='alert alert-danger'>Nama outlet wajib diisi.</div>";
    } elseif ($lat === null || $lng === null) {
        $pesan = "<div class='alert alert-danger'>Lokasi outlet belum dipilih. Cari alamat pada kotak pencarian atau tekan <strong>Gunakan Lokasi Saya</strong>.</div>";
    } else {
        $berhasil = tellerLokasiSimpan($pdo, $petugasId, [
            'nama_outlet'  => $old['nama_outlet'],
            'alamat'       => $old['alamat'],
            'lat'          => $lat,
            'lng'          => $lng,
            'radius_km'    => $old['radius_km'],
            'status_aktif' => $old['status_aktif'],
            'catatan'      => $old['catatan'],
        ]);

        if ($berhasil) {
            auditCatat($pdo, $petugasId, 'UPDATE_LOKASI_OUTLET', 'teller_locations', $petugasId,
                'Outlet ' . $old['nama_outlet'] . ' @ ' . fmtKoordinat($lat, $lng)
                . ' | status ' . ($old['status_aktif'] ? 'aktif' : 'nonaktif'));

            $pesan = "<div class='alert alert-success'>Lokasi outlet tersimpan. Nasabah di sekitar Anda kini dapat menemukan Anda sebagai teller terdekat.</div>";
            $lokasi = tellerLokasiAmbil($pdo, $petugasId);
        } else {
            $pesan = "<div class='alert alert-danger'>Gagal menyimpan lokasi outlet.</div>";
        }
    }
}
?>

<div class="dashboard-page">

    <div class="dashboard-intro" data-aos="fade-up">
        <div>
            <span class="section-eyebrow"><i class="bi bi-geo-alt-fill"></i> Lokasi Outlet</span>
            <h1>Outlet Saya</h1>
            <p>Tentukan titik outlet Anda agar nasabah dapat mencari teller terdekat seperti di aplikasi ojek online.</p>
        </div>
        <span class="intro-badge">
            <i class="bi bi-map"></i>
            Sumber peta: <?= htmlspecialchars(lokasiProviderLabel()) ?>
        </span>
    </div>

    <?= $pesan ?>

    <div class="row g-4">
        <!-- Form -->
        <div class="col-xl-7">
            <div class="bento-card p-4" data-aos="fade-up" data-aos-delay="60">
                <div class="card-heading">
                    <div>
                        <span class="section-eyebrow"><i class="bi bi-pencil-square"></i> Pengaturan</span>
                        <h2>Titik Lokasi Outlet</h2>
                        <p>Minimal 3 karakter untuk mencari alamat.</p>
                    </div>
                </div>

                <form method="POST" action="outlet.php" id="formOutlet">
                    <input type="hidden" name="lat" id="latOutlet" value="<?= htmlspecialchars((string) $old['lat']) ?>">
                    <input type="hidden" name="lng" id="lngOutlet" value="<?= htmlspecialchars((string) $old['lng']) ?>">

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="namaOutlet">Nama Outlet</label>
                        <input type="text" class="form-control" id="namaOutlet" name="nama_outlet" maxlength="120" required
                               placeholder="Contoh: Outlet Cibodas" value="<?= htmlspecialchars($old['nama_outlet']) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="cariAlamat">Cari Alamat Outlet</label>
                        <div class="lokasi-search">
                            <div class="input-group">
                                <span class="input-group-text bg-transparent"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control" id="cariAlamat" autocomplete="off"
                                       placeholder="Ketik nama jalan / kota, mis. Jl. Merdeka, Bandung">
                                <button class="btn btn-outline-success" type="button" id="btnGps">
                                    <i class="bi bi-crosshair"></i> <span class="d-none d-md-inline">Gunakan Lokasi Saya</span>
                                </button>
                            </div>
                            <div class="lokasi-hasil" id="hasilAlamat"></div>
                        </div>
                        <div class="form-text" id="infoProvider">
                            Pencarian alamat memakai <?= htmlspecialchars(lokasiProviderLabel()) ?>.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="alamatOutlet">Alamat Lengkap</label>
                        <textarea class="form-control" id="alamatOutlet" name="alamat" rows="2" maxlength="255"
                                  placeholder="Contoh: Jl. Merdeka No. 10, Cibodas, Bandung"><?= htmlspecialchars($old['alamat']) ?></textarea>
                    </div>

                    <div class="mb-3">
                        <div class="peta mb-2" id="petaOutlet"></div>
                        <div class="small text-muted" id="infoKoordinat">
                            Koordinat: <?= fmtKoordinat(is_numeric($old['lat']) ? (float) $old['lat'] : null, is_numeric($old['lng']) ? (float) $old['lng'] : null) ?>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold" for="radiusOutlet">Radius Layanan (km)</label>
                            <input type="number" step="0.5" min="0.5" max="50" class="form-control" id="radiusOutlet"
                                   name="radius_km" value="<?= htmlspecialchars((string) $old['radius_km']) ?>">
                        </div>
                        <div class="col-sm-6 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="statusAktif" name="status_aktif" value="1"
                                       <?= $old['status_aktif'] ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold" for="statusAktif">Tersedia menerima jemputan</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="catatanOutlet">Catatan (opsional)</label>
                        <input type="text" class="form-control" id="catatanOutlet" name="catatan" maxlength="255"
                               placeholder="Contoh: Buka setiap hari 08.00 - 16.00" value="<?= htmlspecialchars($old['catatan']) ?>">
                    </div>

                    <button type="submit" name="simpan_outlet" class="btn btn-success w-100">
                        <i class="bi bi-save me-1"></i> Simpan Lokasi Outlet
                    </button>
                </form>
            </div>
        </div>

        <!-- Ringkasan -->
        <div class="col-xl-5">
            <div class="bento-card p-4 mb-4" data-aos="fade-up" data-aos-delay="120">
                <div class="card-heading">
                    <div>
                        <span class="section-eyebrow"><i class="bi bi-info-circle-fill"></i> Ringkasan</span>
                        <h2>Status Outlet</h2>
                    </div>
                </div>

                <?php if ($lokasi === null): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon"><i class="bi bi-geo-alt"></i></div>
                        <strong>Belum ada lokasi outlet</strong>
                        <p>Isi form di samping untuk mulai menerima jemputan dari nasabah terdekat.</p>
                    </div>
                <?php else: ?>
                    <div class="teller-card <?= (int) $lokasi['status_aktif'] === 1 ? 'is-terdekat' : '' ?> mb-3">
                        <div class="teller-info">
                            <strong><?= htmlspecialchars((string) $lokasi['nama_outlet']) ?></strong>
                            <small><?= htmlspecialchars((string) ($lokasi['alamat'] ?? 'Alamat belum diisi')) ?></small>
                        </div>
                        <div class="teller-jarak">
                            <strong><?= (int) $lokasi['status_aktif'] === 1 ? 'Aktif' : 'Nonaktif' ?></strong>
                            <small>Radius <?= number_format((float) $lokasi['radius_km'], 1, ',', '.') ?> km</small>
                        </div>
                    </div>

                    <ul class="list-unstyled small mb-3">
                        <li class="d-flex justify-content-between py-1">
                            <span class="text-muted">Koordinat</span>
                            <span><?= fmtKoordinat((float) $lokasi['latitude'], (float) $lokasi['longitude']) ?></span>
                        </li>
                        <li class="d-flex justify-content-between py-1">
                            <span class="text-muted">Terakhir diperbarui</span>
                            <span><?= date('d M Y H:i', strtotime((string) $lokasi['updated_at'])) ?></span>
                        </li>
                        <?php if (!empty($lokasi['catatan'])): ?>
                            <li class="d-flex justify-content-between py-1">
                                <span class="text-muted">Catatan</span>
                                <span><?= htmlspecialchars((string) $lokasi['catatan']) ?></span>
                            </li>
                        <?php endif; ?>
                    </ul>

                    <a class="btn btn-outline-success w-100"
                       href="<?= htmlspecialchars(linkNavigasi((float) $lokasi['latitude'], (float) $lokasi['longitude'])) ?>"
                       target="_blank" rel="noopener">
                        <i class="bi bi-map"></i> Lihat Outlet di Google Maps
                    </a>
                <?php endif; ?>
            </div>

            <div class="bento-card p-4" data-aos="fade-up" data-aos-delay="180">
                <div class="card-heading">
                    <div>
                        <span class="section-eyebrow"><i class="bi bi-lightbulb-fill"></i> Tips</span>
                        <h2>Cara Kerja</h2>
                    </div>
                </div>
                <ol class="small mb-0 ps-3" style="line-height:1.9">
                    <li>Pastikan GPS perangkat aktif, lalu simpan titik outlet Anda.</li>
                    <li>Sistem otomatis menugaskan Anda ke permintaan nasabah yang paling dekat.</li>
                    <li>Saat Anda tiba di lokasi, nasabah bisa menghubungi lewat chat atau telepon.</li>
                    <li>Nonaktifkan "Tersedia" bila Anda sedang tidak melayani.</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<style>
@import url('https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
</style>

<script>
(function () {
    window.RECYCASH_BASE_URL = '<?= htmlspecialchars($baseUrl) ?>';

    document.addEventListener('DOMContentLoaded', function () {
        var lat = parseFloat(document.getElementById('latOutlet').value);
        var lng = parseFloat(document.getElementById('lngOutlet').value);
        var adaLokasi = !isNaN(lat) && !isNaN(lng);

        var peta = null;
        var marker = null;

        function gambarPeta(l, g, zoom) {
            if (!peta) {
                peta = Lokasi.peta({
                    el: document.getElementById('petaOutlet'),
                    lat: l,
                    lng: g,
                    zoom: zoom || (adaLokasi ? 15 : 12),
                    titik: [{ lat: l, lng: g, title: 'Outlet Anda', ikon: 'pin-teller', ikonIkon: 'bi-shop' }]
                });
            } else {
                peta.setView([l, g], zoom || peta.getZoom());
            }

            if (marker) {
                peta.removeLayer(marker);
            }

            marker = L.marker([l, g], {
                icon: L.divIcon({
                    className: 'peta-pin',
                    html: '<span class="peta-pin-ikon pin-teller"><i class="bi bi-shop"></i></span>',
                    iconSize: [34, 34],
                    iconAnchor: [17, 34]
                })
            }).addTo(peta).bindPopup('Outlet Anda');
        }

        if (adaLokasi) {
            gambarPeta(lat, lng, 15);
        }

        function setLokasi(l, g, label) {
            document.getElementById('latOutlet').value = l.toFixed(7);
            document.getElementById('lngOutlet').value = g.toFixed(7);
            document.getElementById('infoKoordinat').textContent = 'Koordinat: ' + l.toFixed(5).replace('.', ',') + ', ' + g.toFixed(5).replace('.', ',');
            gambarPeta(l, g, 16);

            if (label) {
                var kolomAlamat = document.getElementById('alamatOutlet');
                if (!kolomAlamat.value.trim()) {
                    kolomAlamat.value = label;
                }
            }
        }

        Lokasi.pencarian({
            input: document.getElementById('cariAlamat'),
            container: document.getElementById('hasilAlamat'),
            onPilih: function (tempat) {
                setLokasi(tempat.lat, tempat.lng, tempat.label);
                document.getElementById('cariAlamat').value = tempat.label;
            },
            onGagal: function (err) {
                document.getElementById('infoProvider').innerHTML =
                    '<span class="text-danger">' + Lokasi.escapeHtml(err.message) + '</span>';
            }
        });

        document.getElementById('btnGps').addEventListener('click', function () {
            var btn = this;
            var teksAwal = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Mengambil GPS...';

            Lokasi.geoPosisi()
                .then(function (pos) {
                    setLokasi(pos.lat, pos.lng, '');
                    document.getElementById('infoProvider').innerHTML =
                        'Lokasi GPS berhasil taken (akurasi ±' + pos.akurasi + ' m).';
                })
                .catch(function (err) {
                    document.getElementById('infoProvider').innerHTML =
                        '<span class="text-danger">' + Lokasi.escapeHtml(err.message) + '</span>';
                })
                .finally(function () {
                    btn.disabled = false;
                    btn.innerHTML = teksAwal;
                });
        });
    });
})();
</script>

<?php
$extraJS = [
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
    $baseUrl . '/assets/js/lokasi.js',
];
require_once __DIR__ . '/../../includes/footer.php';
?>
