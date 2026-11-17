<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<?php
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/lokasi.php';
require_once __DIR__ . '/../../includes/notifikasi.php';

$nasabahId = (int) ($_SESSION['id_user'] ?? 0);
$requestId = (int) ($_GET['request_id'] ?? 0);

pastikanTabelLokasi($pdo);

$req = null;

if ($requestId > 0) {
    $stmt = $pdo->prepare(
        "SELECT p.*, pt.nama AS nama_petugas, pt.nomor_hp AS hp_petugas, pt.foto AS foto_petugas
         FROM pickup_requests p
         LEFT JOIN users pt ON pt.id = p.petugas_id
         WHERE p.id = :id AND p.nasabah_id = :uid LIMIT 1"
    );
    $stmt->execute([':id' => $requestId, ':uid' => $nasabahId]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$req) {
    $_SESSION['error'] = "Percakapan tidak ditemukan.";
    header('Location: ' . $baseUrl . '/views/Nasabah/request_jemput.php');
    exit;
}

$tellerAda = !empty($req['petugas_id']);
$tellerNama = (string) ($req['nama_petugas'] ?? 'Petugas Teller');
$tellerHp   = (string) ($req['hp_petugas'] ?? '');

$statusLabel = [
    'pending'   => 'Menunggu teller',
    'diproses'  => 'Teller dalam perjalanan',
    'disetujui' => 'Disetujui',
    'selesai'   => 'Selesai',
    'ditolak'   => 'Ditolak',
][$req['status']] ?? ucfirst((string) $req['status']);

$namaNasabah = (string) ($_SESSION['nama'] ?? 'Nasabah');
$pesanWa = waLink($tellerHp, "Halo " . $tellerNama . ", saya " . $namaNasabah . " dari RecyCash. Mohon info untuk request JMP-" . $requestId . ".");

$lokasiTeller = $tellerAda ? tellerLokasiAmbil($pdo, (int) $req['petugas_id']) : null;
?>

<div class="dashboard-page">
    <div class="dashboard-intro" data-aos="fade-up">
        <div>
            <span class="section-eyebrow"><i class="bi bi-chat-dots-fill"></i> Chat Teller</span>
            <h1>Hubungi Teller</h1>
            <p>Percakapan langsung dengan teller yang menangani penjemputan Anda.</p>
        </div>
        <span class="intro-badge">
            <i class="bi bi-recycle"></i> JMP-<?= (int) $requestId ?>
        </span>
    </div>

    <div class="bento-card p-0 overflow-hidden" data-aos="fade-up" data-aos-delay="60">
        <div class="chat-room">
            <!-- Kepala percakapan -->
            <div class="chat-head">
                <?= renderAvatar($tellerNama, (string) ($req['foto_petugas'] ?? ''), $baseUrl, 42) ?>
                <div class="flex-grow-1 min-w-0">
                    <strong><?= htmlspecialchars($tellerNama) ?></strong>
                    <small id="statusTeller"><?= htmlspecialchars($statusLabel) ?></small>
                </div>
                <div class="d-flex gap-2 flex-wrap justify-content-end">
                    <?php if ($tellerHp !== ''): ?>
                        <a class="btn btn-sm btn-success" href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', $tellerHp)) ?>">
                            <i class="bi bi-telephone-fill"></i> <span class="d-none d-md-inline">Telepon</span>
                        </a>
                    <?php endif; ?>
                    <?php if ($pesanWa !== ''): ?>
                        <a class="btn btn-sm btn-outline-success" href="<?= htmlspecialchars($pesanWa) ?>" target="_blank" rel="noopener">
                            <i class="bi bi-whatsapp"></i> <span class="d-none d-md-inline">WhatsApp</span>
                        </a>
                    <?php endif; ?>
                    <?php if ($lokasiTeller): ?>
                        <a class="btn btn-sm btn-outline-secondary"
                           href="<?= htmlspecialchars(linkNavigasi((float) $lokasiTeller['latitude'], (float) $lokasiTeller['longitude'], $tellerNama)) ?>"
                           target="_blank" rel="noopener">
                            <i class="bi bi-map"></i> <span class="d-none d-md-inline">Lokasi</span>
                        </a>
                    <?php endif; ?>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= $baseUrl ?>/views/Nasabah/request_jemput.php">
                        <i class="bi bi-arrow-left"></i> <span class="d-none d-md-inline">Kembali</span>
                    </a>
                </div>
            </div>

            <?php if (!$tellerAda): ?>
                <div class="px-3 pt-3">
                    <div class="alert alert-warning mb-0 py-2 small">
                        <i class="bi bi-hourglass-split me-1"></i>
                        Teller belum ditugaskan untuk request ini. Chat akan aktif otomatis setelah teller terdekat menerima permintaan.
                    </div>
                </div>
            <?php endif; ?>

            <!-- Pesan -->
            <div class="chat-body" id="chatBody">
                <div class="chat-kosong" id="chatKosong">
                    <i class="bi bi-chat-dots" style="font-size:30px"></i>
                    <span>Belum ada pesan. Sapa teller Anda, misalnya: "Halo, saya sudah siap di depan rumah."</span>
                </div>
            </div>

            <!-- Input -->
            <div class="chat-foot">
                <input type="text" class="form-control" id="inputPesan" maxlength="500"
                       placeholder="Tulis pesan untuk teller..." autocomplete="off"
                       <?= $tellerAda ? '' : 'disabled' ?>>
                <button class="btn btn-success px-4" id="btnKirim" <?= $tellerAda ? '' : 'disabled' ?>>
                    <i class="bi bi-send-fill"></i> <span class="d-none d-md-inline">Kirim</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    window.RECYCASH_BASE_URL = '<?= htmlspecialchars($baseUrl) ?>';
    window.RECYCASH_REQUEST_ID = <?= (int) $requestId ?>;

    document.addEventListener('DOMContentLoaded', function () {
        var body = document.getElementById('chatBody');
        var kosong = document.getElementById('chatKosong');
        var input = document.getElementById('inputPesan');
        var btn = document.getElementById('btnKirim');
        var requestId = window.RECYCASH_REQUEST_ID;
        var terakhirId = 0;
        var memuat = false;

        function waktuPendek(waktu) {
            if (!waktu) {
                return '';
            }
            var d = new Date(waktu.replace(' ', 'T'));
            if (isNaN(d.getTime())) {
                return '';
            }
            return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        }

        function tambahPesan(p) {
            if (kosong && kosong.parentNode) {
                kosong.parentNode.removeChild(kosong);
            }

            var el = document.createElement('div');
            el.className = 'chat-pesan' + (p.saya ? ' saya' : '');
            el.innerHTML = Lokasi.escapeHtml(p.pesan)
                + '<span class="chat-waktu">' + Lokasi.escapeHtml(waktuPendek(p.waktu)) + '</span>';
            body.appendChild(el);
            body.scrollTop = body.scrollHeight;
        }

        function muatPesan(awal) {
            if (memuat) {
                return;
            }
            memuat = true;

            fetch(Lokasi.url('/api/chat_pesan.php?request_id=' + requestId), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    memuat = false;

                    if (!res || !res.ok) {
                        return;
                    }

                    var pesan = res.messages || [];

                    if (awal) {
                        body.innerHTML = '';
                        pesan.forEach(tambahPesan);
                        if (!pesan.length) {
                            body.innerHTML = '<div class="chat-kosong" id="chatKosong">'
                                + '<i class="bi bi-chat-dots" style="font-size:30px"></i>'
                                + '<span>Belum ada pesan. Sapa teller Anda, misalnya: "Halo, saya sudah siap di depan rumah."</span></div>';
                        }
                    } else {
                        pesan.forEach(function (p) {
                            if (p.id > terakhirId) {
                                tambahPesan(p);
                            }
                        });
                    }

                    if (pesan.length) {
                        terakhirId = pesan[pesan.length - 1].id;
                    }
                })
                .catch(function () {
                    memuat = false;
                });
        }

        function kirimPesan() {
            var teks = input.value.trim();

            if (!teks) {
                return;
            }

            input.value = '';
            btn.disabled = true;

            var form = new URLSearchParams();
            form.append('pesan', teks);

            fetch(Lokasi.url('/api/chat_pesan.php?request_id=' + requestId), {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: form.toString()
            })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    btn.disabled = false;
                    if (!res || !res.ok) {
                        input.value = teks;
                        alert((res && res.message) || 'Pesan gagal dikirim.');
                        return;
                    }
                    muatPesan(false);
                })
                .catch(function () {
                    btn.disabled = false;
                    input.value = teks;
                    alert('Tidak dapat terhubung ke server.');
                });
        }

        btn.addEventListener('click', kirimPesan);

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                kirimPesan();
            }
        });

        // Polling status teller + pesan baru
        setInterval(function () {
            muatPesan(false);

            fetch(Lokasi.url('/api/pickup_status.php?id=' + requestId), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res && res.ok) {
                        var el = document.getElementById('statusTeller');
                        var teks = res.status_teks + (res.tiba ? ' — sudah tiba' : '');
                        if (res.jarak_teks) {
                            teks += ' • ' + res.jarak_teks + ' • ±' + res.eta_menit + ' menit';
                        }
                        if (el && el.textContent !== teks) {
                            el.textContent = teks;
                        }
                    }
                })
                .catch(function () {});
        }, 5000);

        muatPesan(true);
    });
})();
</script>

<?php
$extraJS = [
    $baseUrl . '/assets/js/lokasi.js',
];
require_once __DIR__ . '/../../includes/footer.php';
?>
