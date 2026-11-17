<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<?php
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/lokasi.php';

pastikanTabelLokasi($pdo);

$jumlahTeller = (int) $pdo->query("SELECT COUNT(*) FROM teller_locations WHERE status_aktif = 1")->fetchColumn();
?>

<div class="dashboard-page">

    <div class="dashboard-intro" data-aos="fade-up">
        <div>
            <span class="section-eyebrow"><i class="bi bi-geo-alt-fill"></i> Cari Teller</span>
            <h1>Teller Terdekat</h1>
            <p>Cari outlet pengumpul sampah terdekat, seperti mencari driver di aplikasi ojek online.</p>
        </div>
        <span class="intro-badge">
            <i class="bi bi-map"></i>
            <?= htmlspecialchars(lokasiProviderLabel()) ?>
        </span>
    </div>

    <div class="row g-4">
        <!-- Pencarian -->
        <div class="col-xl-5">
            <div class="bento-card p-4 mb-4" data-aos="fade-up" data-aos-delay="60">
                <div class="card-heading">
                    <div>
                        <span class="section-eyebrow"><i class="bi bi-search"></i> Lokasi Anda</span>
                        <h2>Cari Alamat</h2>
                        <p>Ketik alamat atau pakai GPS untuk menemukan teller terdekat.</p>
                    </div>
                </div>

                <div class="lokasi-search mb-3">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" id="cariLokasi" autocomplete="off"
                               placeholder="Contoh: Jl. Merdeka, Bandung">
                        <button class="btn btn-outline-success" type="button" id="btnGps">
                            <i class="bi bi-crosshair"></i> <span class="d-none d-md-inline">GPS</span>
                        </button>
                    </div>
                    <div class="lokasi-hasil" id="hasilLokasi"></div>
                </div>

                <div class="small text-muted mb-3" id="infoLokasi">
                    Belum ada lokasi dipilih.
                </div>

                <div class="peta" id="petaTeller"></div>

                <div class="d-flex gap-2 mt-3">
                    <button type="button" class="btn btn-success flex-grow-1" id="btnCariTerdekat" disabled>
                        <i class="bi bi-arrow-repeat"></i> Cari Teller Terdekat
                    </button>
                </div>
            </div>

            <div class="bento-card p-4" data-aos="fade-up" data-aos-delay="120">
                <div class="card-heading">
                    <div>
                        <span class="section-eyebrow"><i class="bi bi-info-circle-fill"></i> Info</span>
                        <h2>Cara Kerja</h2>
                    </div>
                </div>
                <ol class="small mb-0 ps-3" style="line-height:1.9">
                    <li>Pilih lokasi rumah atau tempat sampah berada.</li>
                    <li>Sistem menampilkan teller terdekat beserta jarak & estimasi tiba.</li>
                    <li>Tekan <strong>Minta Jemput</strong> — teller terdekat otomatis ditugaskan.</li>
                    <li>Saat teller dalam perjalanan, Anda bisa chat dan meneleponnya.</li>
                </ol>
            </div>
        </div>

        <!-- Hasil -->
        <div class="col-xl-7">
            <div class="bento-card p-4" data-aos="fade-up" data-aos-delay="180">
                <div class="card-heading">
                    <div>
                        <span class="section-eyebrow"><i class="bi bi-list-ul"></i> Hasil</span>
                        <h2 id="judulDaftar">Teller Terdekat</h2>
                        <p id="subjudulDaftar">Belum ada outlet terdaftar untuk saat ini.</p>
                    </div>
                </div>

                <div id="daftarTeller">
                    <?php if ($jumlahTeller === 0): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon"><i class="bi bi-shop-window"></i></div>
                            <strong>Belum ada teller yang terdaftar</strong>
                            <p>Teller dapat mendaftarkan lokasi outletnya lewat halaman "Outlet Saya".
                               Setelah itu, Anda akan melihat outlet terdekat di sini.</p>
                        </div>
                    <?php endif; ?>
                </div>
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
        var peta = null;
        var markerSaya = null;
        var markerTeller = [];
        var lat = null;
        var lng = null;
        var labelLokasi = '';
        var tellerTerpilih = [];

        var inputCari   = document.getElementById('cariLokasi');
        var hasilCari   = document.getElementById('hasilLokasi');
        var infoLokasi  = document.getElementById('infoLokasi');
        var btnGps      = document.getElementById('btnGps');
        var btnCari     = document.getElementById('btnCariTerdekat');
        var daftarEl    = document.getElementById('daftarTeller');
        var judulEl     = document.getElementById('judulDaftar');
        var subjudulEl  = document.getElementById('subjudulDaftar');

        function gambarPeta() {
            if (!peta) {
                peta = Lokasi.peta({
                    el: document.getElementById('petaTeller'),
                    lat: lat || -6.9,
                    lng: lng || 107.6,
                    zoom: lat ? 14 : 11
                });
            }

            if (markerSaya) {
                peta.removeLayer(markerSaya);
            }

            markerSaya = L.marker([lat, lng], {
                icon: L.divIcon({
                    className: 'peta-pin',
                    html: '<span class="peta-pin-ikon pin-nasabah"><i class="bi bi-house-fill"></i></span>',
                    iconSize: [34, 34],
                    iconAnchor: [17, 34]
                })
            }).addTo(peta).bindPopup('Lokasi Anda');

            tellerTerpilih.forEach(function (t) {
                peta.removeLayer(t.marker);
            });

            tellerTerpilih = [];
        }

        function gambarMarkerTeller(daftar) {
            tellerTerpilih.forEach(function (t) {
                peta.removeLayer(t.marker);
            });
            tellerTerpilih = [];

            if (!peta) {
                return;
            }

            daftar.forEach(function (t) {
                var m = L.marker([t.lat, t.lng], {
                    icon: L.divIcon({
                        className: 'peta-pin',
                        html: '<span class="peta-pin-ikon pin-teller"><i class="bi bi-shop"></i></span>',
                        iconSize: [34, 34],
                        iconAnchor: [17, 34]
                    })
                }).addTo(peta).bindPopup(t.nama_outlet + ' — ' + t.jarak_teks);
                tellerTerpilih.push({ data: t, marker: m });
            });
        }

        function setLokasi(l, g, label) {
            lat = l;
            lng = g;
            labelLokasi = label || '';
            btnCari.disabled = false;
            infoLokasi.innerHTML = 'Lokasi dipilih: <strong>' + Lokasi.escapeHtml(labelLokasi || (l.toFixed(5) + ', ' + g.toFixed(5))) + '</strong>';
            gambarPeta();
        }

        Lokasi.pencarian({
            input: inputCari,
            container: hasilCari,
            onPilih: function (tempat) {
                setLokasi(tempat.lat, tempat.lng, tempat.label);
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
                    setLokasi(pos.lat, pos.lng, 'Lokasi GPS saya');
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

        btnCari.addEventListener('click', function () {
            if (lat === null) {
                return;
            }

            var btn = this;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Mencari...';

            fetch(Lokasi.url('/api/teller_terdekat.php?lat=' + lat + '&lng=' + lng), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Cari Teller Terdekat';

                    if (!res || !res.ok) {
                        judulEl.textContent = 'Gagal memuat data';
                        subjudulEl.textContent = (res && res.message) || 'Terjadi kesalahan.';
                        return;
                    }

                    var daftar = res.tellers || [];
                    judulEl.textContent = daftar.length ? 'Teller Terdekat' : 'Tidak ada teller di sekitar sini';
                    subjudulEl.textContent = daftar.length
                        ? daftar.length + ' outlet ditemukan, diurutkan dari yang terdekat.'
                        : 'Belum ada teller yang terdaftar atau masih di luar radius pencarian.';

                    if (!daftar.length) {
                        daftarEl.innerHTML = '<div class="empty-state">'
                            + '<div class="empty-state-icon"><i class="bi bi-shop-window"></i></div>'
                            + '<strong>Belum ada teller terdekat</strong>'
                            + '<p>Coba geser lokasi pencarian Anda atau perluas area.</p></div>';
                        return;
                    }

                    var html = '';

                    daftar.forEach(function (t, i) {
                        var tel = t.telepon ? 'tel:' + t.telepon.replace(/[^0-9+]/g, '') : '';
                        var params = new URLSearchParams({
                            lat: lat,
                            lng: lng,
                            lokasi: labelLokasi,
                            teller: t.user_id
                        });

                        html += '<div class="teller-card' + (i === 0 ? ' is-terdekat' : '') + '">'
                            + '<div class="teller-info">'
                            + '<strong>' + Lokasi.escapeHtml(t.nama_outlet) + '</strong>'
                            + '<small>' + Lokasi.escapeHtml(t.alamat || t.nama) + '</small>'
                            + '<small>Estimasi tiba ±' + t.eta_menit + ' menit</small>'
                            + '</div>'
                            + '<div class="teller-jarak">'
                            + '<strong>' + Lokasi.escapeHtml(t.jarak_teks) + '</strong>'
                            + '<small>' + (i === 0 ? 'TERDEKAT' : 'PILIHAN') + '</small>'
                            + '</div>'
                            + '<div class="d-flex flex-column gap-1">'
                            + (tel ? '<a class="btn btn-sm btn-outline-success" href="' + tel + '"><i class="bi bi-telephone-fill"></i></a>' : '')
                            + '<a class="btn btn-sm btn-success" href="' + Lokasi.url('/views/Nasabah/request_jemput.php?' + params.toString()) + '">'
                            + '<i class="bi bi-truck"></i> Minta Jemput</a>'
                            + '</div>'
                            + '</div>';
                    });

                    daftarEl.innerHTML = html;
                    gambarMarkerTeller(daftar);
                })
                .catch(function () {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Cari Teller Terdekat';
                    judulEl.textContent = 'Gagal memuat data';
                    subjudulEl.textContent = 'Tidak dapat terhubung ke server.';
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
