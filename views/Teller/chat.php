<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<?php
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/lokasi.php';
require_once __DIR__ . '/../../includes/notifikasi.php';

$petugasId = (int) ($_SESSION['id_user'] ?? 0);
$requestId = (int) ($_GET['request_id'] ?? 0);

pastikanTabelLokasi($pdo);

$req = null;

if ($requestId > 0) {
    $stmt = $pdo->prepare(
        "SELECT p.*, ns.nama AS nama_nasabah, ns.nomor_hp AS hp_nasabah, ns.foto AS foto_nasabah
         FROM pickup_requests p
         LEFT JOIN users ns ON ns.id = p.nasabah_id
         WHERE p.id = :id AND p.petugas_id = :uid LIMIT 1"
    );
    $stmt->execute([':id' => $requestId, ':uid' => $petugasId]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$req) {
    $_SESSION['error'] = "Percakapan tidak ditemukan.";
    header('Location: ' . $baseUrl . '/views/Teller/kelola_jemput.php');
    exit;
}

$nasabahNama = (string) ($req['nama_nasabah'] ?? 'Nasabah');
$nasabahHp   = (string) ($req['hp_nasabah'] ?? '');

$statusLabel = [
    'pending'   => 'Menunggu diproses',
    'diproses'  => 'Sedang diproses',
    'disetujui' => 'Disetujui',
    'selesai'   => 'Selesai',
    'ditolak'   => 'Ditolak',
][$req['status']] ?? ucfirst((string) $req['status']);

$pesanWa = waLink($nasabahHp, "Halo " . $nasabahNama . ", saya teller RecyCash yang menangani request JMP-" . $requestId . ". Saya sedang menuju lokasi Anda.");

$petaNavigasi = '';
if ($req['latitude'] !== null && $req['longitude'] !== null) {
    $petaNavigasi = linkNavigasi((float) $req['latitude'], (float) $req['longitude'], $req['alamat_jemput'] ?? '');
}
?>

<div class="dashboard-page">
    <div class="dashboard-intro" data-aos="fade-up">
        <div>
            <span class="section-eyebrow"><i class="bi bi-chat-dots-fill"></i> Chat Nasabah</span>
            <h1>Hubungi Nasabah</h1>
            <p>Komunikasi langsung dengan nasabah yang Anda tangani.</p>
        </div>
        <span class="intro-badge">
            <i class="bi bi-recycle"></i> JMP-<?= (int) $requestId ?>
        </span>
    </div>

    <div class="bento-card p-0 overflow-hidden" data-aos="fade-up" data-aos-delay="60">
        <div class="chat-room">
            <div class="chat-head">
                <?= renderAvatar($nasabahNama, (string) ($req['foto_nasabah'] ?? ''), $baseUrl, 42) ?>
                <div class="flex-grow-1 min-w-0">
                    <strong><?= htmlspecialchars($nasabahNama) ?></strong>
                    <small id="statusRequest"><?= htmlspecialchars($statusLabel) ?></small>
                </div>
                <div class="d-flex gap-2 flex-wrap justify-content-end">
                    <?php if ($nasabahHp !== ''): ?>
                        <a class="btn btn-sm btn-success" href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', $nasabahHp)) ?>">
                            <i class="bi bi-telephone-fill"></i> <span class="d-none d-md-inline">Telepon</span>
                        </a>
                    <?php endif; ?>
                    <?php if ($pesanWa !== ''): ?>
                        <a class="btn btn-sm btn-outline-success" href="<?= htmlspecialchars($pesanWa) ?>" target="_blank" rel="noopener">
                            <i class="bi bi-whatsapp"></i> <span class="d-none d-md-inline">WhatsApp</span>
                        </a>
                    <?php endif; ?>
                    <?php if ($petaNavigasi !== ''): ?>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars($petaNavigasi) ?>" target="_blank" rel="noopener">
                            <i class="bi bi-map"></i> <span class="d-none d-md-inline">Navigasi</span>
                        </a>
                    <?php endif; ?>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= $baseUrl ?>/views/Teller/kelola_jemput.php">
                        <i class="bi bi-arrow-left"></i> <span class="d-none d-md-inline">Kembali</span>
                    </a>
                </div>
            </div>

            <div class="chat-body" id="chatBody">
                <div class="chat-kosong" id="chatKosong">
                    <i class="bi bi-chat-dots" style="font-size:30px"></i>
                    <span>Belum ada pesan. Hubungi nasabah bila perlu konfirmasi lokasi.</span>
                </div>
            </div>

            <div class="chat-foot">
                <input type="text" class="form-control" id="inputPesan" maxlength="500" autocomplete="off"
                       placeholder="Tulis pesan untuk nasabah...">
                <button class="btn btn-success px-4" id="btnKirim">
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
                            body.innerHTML = '<div class="chat-kosong">'
                                + '<i class="bi bi-chat-dots" style="font-size:30px"></i>'
                                + '<span>Belum ada pesan. Hubungi nasabah bila perlu konfirmasi lokasi.</span></div>';
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

        setInterval(function () {
            muatPesan(false);
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
