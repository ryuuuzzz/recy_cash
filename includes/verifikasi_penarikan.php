<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/avatar_helper.php';
require_once __DIR__ . '/notifikasi.php';
require_once __DIR__ . '/payout.php';

$M = [
    'e-wallet'      => 'E-Wallet',
    'bank_transfer' => 'Transfer Bank',
    'voucher'       => 'Tukar Poin / Voucher',
    'tunai'         => 'Cair Tunai',
];
$badge = [
    'pending'   => 'bg-warning text-warning-emphasis',
    'disetujui' => 'bg-success text-white',
    'ditolak'   => 'bg-danger text-white',
    'selesai'   => 'bg-success text-white',
];
$label = [
    'pending'   => 'Menunggu',
    'disetujui' => 'Disetujui',
    'ditolak'   => 'Ditolak',
    'selesai'   => 'Selesai',
];

$pesan = '';

// ---------- HELPER: NOTIFIKASI STATUS PENARIKAN ----------
function notifStatusPenarikan(PDO $pdo, array $w, string $statusBaru, string $alasan = ''): void {
    $idLabel = 'PNK-' . str_pad((string) $w['id'], 4, '0', STR_PAD_LEFT);
    $nominal = 'Rp ' . number_format((float) $w['nominal'], 0, ',', '.');
    $userId  = (int) $w['nasabah_id'];
    $link    = 'penarikan.php';

    switch ($statusBaru) {
        case 'disetujui':
            $pesanApp = "Pengajuan penarikan {$idLabel} senilai {$nominal} telah disetujui. Dana sedang diproses.";
            $subjek   = 'RecyCash - Penarikan Disetujui';
            break;
        case 'ditolak':
            $pesanApp = "Pengajuan penarikan {$idLabel} senilai {$nominal} ditolak." . ($alasan !== '' ? ' Alasan: ' . $alasan : '');
            $subjek   = 'RecyCash - Penarikan Ditolak';
            break;
        case 'selesai':
            $pesanApp = "Penarikan {$idLabel} senilai {$nominal} telah dibayar. Terima kasih!";
            $subjek   = 'RecyCash - Penarikan Selesai Dibayar';
            break;
        default:
            return;
    }

    $htmlEmail = '<div style="font-family:Arial,sans-serif;font-size:14px;line-height:1.6">'
        . '<h3 style="color:#2e7d32;margin:0 0 8px">RecyCash</h3>'
        . '<p>' . htmlspecialchars($pesanApp) . '</p>'
        . '<table cellpadding="6" style="border-collapse:collapse">'
        . '<tr><td style="color:#64748b">Kode</td><td><strong>' . $idLabel . '</strong></td></tr>'
        . '<tr><td style="color:#64748b">Metode</td><td>' . htmlspecialchars((string) $w['tipe_penarikan']) . '</td></tr>'
        . '<tr><td style="color:#64748b">Tujuan</td><td>' . htmlspecialchars((string) $w['detail_tujuan']) . '</td></tr>'
        . '<tr><td style="color:#64748b">Nominal</td><td><strong>' . $nominal . '</strong></td></tr>'
        . '</table>'
        . '<p style="color:#64748b">Buka aplikasi RecyCash untuk melihat status terbaru.</p>'
        . '</div>';

    notifKirim($pdo, $userId, $pesanApp, $link, $subjek, $htmlEmail);
}

// ---------- AKSI: SETUJUI / TOLAK / SELESAI / PAYOUT OTOMATIS ----------
$actorId = (int) ($_SESSION['id_user'] ?? 0);

if (isset($_POST['aksi']) && $_POST['aksi'] === 'payout' && isset($_POST['id_penarikan'])) {
    // ---------- PAYOUT OTOMATIS VIA PAYMENT GATEWAY ----------
    $wid = (int) $_POST['id_penarikan'];
    $stmt = $pdo->prepare("SELECT w.* FROM withdrawals w WHERE w.id = :id");
    $stmt->execute([':id' => $wid]);
    $w = $stmt->fetch();

    if (!$w) {
        $pesan = warn('Data penarikan tidak ditemukan.');
    } elseif ($w['status'] !== 'disetujui') {
        $pesan = warn('Hanya penarikan berstatus <strong>Disetujui</strong> yang dapat dibayar otomatis.');
    } elseif (!payoutTipeDidukung((string) $w['tipe_penarikan'])) {
        $pesan = warn('Metode penarikan ini tidak didukung payment gateway. Tandai selesai manual.');
    } elseif (!payoutGatewayAktif()) {
        $pesan = warn('Payment gateway belum dikonfigurasi (config/payment_config.php). Tandai selesai manual setelah dana dikirim.');
    } else {
        $res = payoutKirim($w);
        if ($res['ok']) {
            $pdo->prepare("UPDATE withdrawals SET status='selesai', payout_ref=:ref, payout_status=:st, payout_provider=:pv, payout_updated_at=NOW(), payout_response_json=:raw WHERE id=:id")
                ->execute([
                    ':ref' => $res['ref'],
                    ':st'  => $res['status'],
                    ':pv'  => PAYOUT_PROVIDER,
                    ':raw' => mb_substr($res['raw'], 0, 5000),
                    ':id'  => $wid,
                ]);
            $w2 = $pdo->prepare("SELECT * FROM withdrawals WHERE id = :id");
            $w2->execute([':id' => $wid]);
            notifStatusPenarikan($pdo, $w2->fetch() ?: $w, 'selesai');
            auditCatat($pdo, $actorId, 'PAYOUT_OTOMATIS', 'withdrawals', $wid,
                'Ref: ' . $res['ref'] . ', nominal Rp ' . number_format((float) $w['nominal'], 0, ',', '.'));
            $pesan = sukses('Pembayaran otomatis berhasil diproses. Referensi: <strong>' . htmlspecialchars($res['ref']) . '</strong>');
        } else {
            $pdo->prepare("UPDATE withdrawals SET payout_status=:st, payout_provider=:pv, payout_updated_at=NOW(), payout_error_code=:ec, payout_error_msg=:em WHERE id=:id")
                ->execute([
                    ':st' => $res['status'] !== '' ? $res['status'] : 'gagal',
                    ':pv' => defined('PAYOUT_PROVIDER') ? PAYOUT_PROVIDER : '',
                    ':ec' => mb_substr($res['status'], 0, 10),
                    ':em' => mb_substr($res['message'], 0, 255),
                    ':id' => $wid,
                ]);
            auditCatat($pdo, $actorId, 'PAYOUT_GAGAL', 'withdrawals', $wid, $res['message']);
            $pesan = err('Pembayaran otomatis gagal: ' . htmlspecialchars($res['message']));
        }
    }
} elseif (isset($_POST['aksi']) && isset($_POST['id_penarikan'])) {
    $wid    = (int) $_POST['id_penarikan'];
    $aksi   = $_POST['aksi'];
    $reason = trim($_POST['alasan_tolak'] ?? '');

    try {
        $pdo->beginTransaction();

        $q = "SELECT * FROM withdrawals WHERE id = :id FOR UPDATE";
        $s = $pdo->prepare($q);
        $s->execute([':id' => $wid]);
        $w = $s->fetch();

        if (!$w) {
            $pesan = warn('Data penarikan tidak ditemukan.');
        } elseif ($w['status'] !== 'pending' && $w['status'] !== 'disetujui') {
            $pesan = warn('Status penarikan sudah tidak dapat diubah.');
        } else {
            $statusLama = $w['status'];
            $statusBaru = '';
            if ($aksi === 'setujui' && $w['status'] === 'pending') {
                $pdo->prepare("UPDATE withdrawals SET status='disetujui', alasan_penolakan=NULL WHERE id=:id")
                    ->execute([':id' => $wid]);
                potongSaldo($pdo, (int) $w['nasabah_id'], $w['tipe_penarikan'], (float) $w['nominal']);
                $pesan = sukses('Penarikan disetujui. Saldo/poin nasabah telah dipotong.');
                $statusBaru = 'disetujui';
            } elseif ($aksi === 'tolak' && $w['status'] === 'pending') {
                if ($reason === '') {
                    $pesan = warn('Alasan penolakan wajib diisi.');
                } else {
                    $pdo->prepare("UPDATE withdrawals SET status='ditolak', alasan_penolakan=:r WHERE id=:id")
                        ->execute([':id' => $wid, ':r' => $reason]);
                    $pesan = sukses('Pengajuan penarikan ditolak.');
                    $statusBaru = 'ditolak';
                }
            } elseif ($aksi === 'selesai' && $w['status'] === 'disetujui') {
                $pdo->prepare("UPDATE withdrawals SET status='selesai', payout_status='manual', payout_updated_at=NOW() WHERE id=:id")
                    ->execute([':id' => $wid]);
                $pesan = sukses('Penarikan ditandai selesai.');
                $statusBaru = 'selesai';
            } else {
                $pesan = warn('Tindakan tidak valid untuk status saat ini.');
            }

            // Audit hanya jika status benar-benar berubah
            if ($statusBaru !== '' && $statusBaru !== $statusLama) {
                auditCatat($pdo, $actorId, 'UPDATE_STATUS_PENARIKAN', 'withdrawals', $wid,
                    "Status: $statusLama -> $statusBaru" . ($statusBaru === 'ditolak' && $reason !== '' ? ', alasan: ' . $reason : ''));
            }
            $notifPending = $statusBaru !== '' && $statusBaru !== $statusLama ? [$statusBaru, $statusBaru === 'ditolak' ? $reason : ''] : null;
        }
        $pdo->commit();

        // Kirim notifikasi (in-app + email) SETELAH commit agar konsisten
        if (!empty($notifPending) && isset($w)) {
            $w2 = $pdo->prepare("SELECT * FROM withdrawals WHERE id = :id");
            $w2->execute([':id' => $wid]);
            notifStatusPenarikan($pdo, $w2->fetch() ?: $w, $notifPending[0], $notifPending[1]);
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $pesan = err('Terjadi kesalahan: ' . htmlspecialchars($e->getMessage()));
    }
}

// ---------- AKSI: SIMPAN TUJUAN PAYOUT ----------
if (isset($_POST['aksi']) && $_POST['aksi'] === 'simpan_payout' && isset($_POST['id_penarikan'])) {
    $wid       = (int) $_POST['id_penarikan'];
    $bankCode  = strtoupper(trim($_POST['payout_bank_code'] ?? ''));
    $account   = trim($_POST['payout_account_number'] ?? '');
    $name      = trim($_POST['payout_beneficiary_name'] ?? '');

    if ($bankCode === '' || $account === '' || $name === '') {
        $pesan = warn('Semua field tujuan payout wajib diisi (kode bank, nomor rekening, nama penerima).');
    } else {
        try {
            $pdo->prepare("UPDATE withdrawals SET payout_bank_code = :bc, payout_account_number = :an, payout_beneficiary_name = :bn, payout_provider = :pv WHERE id = :id")
                ->execute([
                    ':bc' => $bankCode,
                    ':an' => $account,
                    ':bn' => $name,
                    ':pv' => defined('PAYOUT_PROVIDER') ? PAYOUT_PROVIDER : '',
                    ':id' => $wid,
                ]);
            auditCatat($pdo, $actorId, 'UPDATE_PAYOUT_TUJUAN', 'withdrawals', $wid,
                "Bank: $bankCode, Rek: $account, a.n. $name");
            $pesan = sukses('Tujuan payout berhasil disimpan. Silakan klik "Bayar Otomatis".');
        } catch (PDOException $e) {
            $pesan = err('Gagal menyimpan tujuan payout: ' . htmlspecialchars($e->getMessage()));
        }
    }
}

// ---------- DATA ----------
$filter = $_GET['status'] ?? 'semua';
$where  = '';
$param  = [];
if ($filter !== 'semua') {
    $where  = ' AND w.status = :status';
    $param  = ['status' => $filter];
}
$sql = "SELECT w.*, u.nama AS nama_nasabah, u.nomor_hp, u.foto
        FROM withdrawals w
        JOIN users u ON u.id = w.nasabah_id
        WHERE 1=1 $where
        ORDER BY CASE w.status WHEN 'pending' THEN 0 WHEN 'disetujui' THEN 1 ELSE 2 END, w.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($param);
$list = $stmt->fetchAll();

$jumlah = ['pending' => 0, 'disetujui' => 0, 'ditolak' => 0, 'selesai' => 0];
foreach ($list as $r) {
    $jumlah[$r['status']] = ($jumlah[$r['status']] ?? 0) + 1;
}

function potongSaldo($pdo, $nasabahId, $tipe, $nominal) {
    if ($tipe === 'voucher') {
        $pdo->prepare("UPDATE wallets SET poin = GREATEST(0, poin - :n) WHERE user_id = :uid")
            ->execute([':n' => (int) $nominal, ':uid' => $nasabahId]);
    } else {
        $pdo->prepare("UPDATE wallets SET saldo_rupiah = GREATEST(0, saldo_rupiah - :n) WHERE user_id = :uid")
            ->execute([':n' => $nominal, ':uid' => $nasabahId]);
    }
}

function sukses($m) { return '<div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-1"></i>' . $m . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>'; }
function warn($m)   { return '<div class="alert alert-warning alert-dismissible fade show">' . $m . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>'; }
function err($m)    { return '<div class="alert alert-danger alert-dismissible fade show">' . $m . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>'; }

function tampilTipe($t) { global $M; return '<span class="badge rounded-pill text-bg-primary">' . ($M[$t] ?? ucfirst($t)) . '</span>'; }
function tampilStatus($s) { global $badge, $label; return '<span class="badge rounded-pill ' . ($badge[$s] ?? 'bg-secondary') . '">' . ($label[$s] ?? ucfirst($s)) . '</span>'; }
?>

<?= $pesan ?>

<div class="page-heading">
    <div>
        <h5><i class="bi bi-wallet2"></i>Verifikasi Penarikan</h5>
        <small>Setuju, tolak (dengan alasan), atau tandai selesai pengajuan penarikan nasabah.
            <?php if (payoutGatewayAktif()): ?>
                Mode pembayaran: <span class="text-success fw-semibold">otomatis via <?= htmlspecialchars(PAYOUT_PROVIDER) ?></span>.
            <?php else: ?>
                Mode pembayaran: <span class="text-warning fw-semibold">manual</span> (gateway belum dikonfigurasi di <code>config/payment_config.php</code>).
            <?php endif; ?>
        </small>
    </div>
    <div class="btn-group btn-group-sm filter-pills" role="group">
        <a href="?status=semua"   class="btn btn-outline-success <?= $filter === 'semua' ? 'active' : '' ?>">Semua</a>
        <a href="?status=pending" class="btn btn-outline-warning <?= $filter === 'pending' ? 'active' : '' ?>">Menunggu (<?= $jumlah['pending'] ?>)</a>
        <a href="?status=disetujui" class="btn btn-outline-primary <?= $filter === 'disetujui' ? 'active' : '' ?>">Disetujui (<?= $jumlah['disetujui'] ?>)</a>
        <a href="?status=ditolak" class="btn btn-outline-danger <?= $filter === 'ditolak' ? 'active' : '' ?>">Ditolak (<?= $jumlah['ditolak'] ?>)</a>
        <a href="?status=selesai" class="btn btn-outline-secondary <?= $filter === 'selesai' ? 'active' : '' ?>">Selesai (<?= $jumlah['selesai'] ?>)</a>
    </div>
</div>

<?php if (empty($list)): ?>
    <div class="card shadow-sm">
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-inbox" style="font-size:40px;"></i>
            <p class="mt-2 mb-0">Belum ada data penarikan.</p>
        </div>
    </div>
<?php else: ?>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Kode</th>
                        <th>Nasabah</th>
                        <th>Tanggal</th>
                        <th>Metode</th>
                        <th>Tujuan</th>
                        <th>Nominal</th>
                        <th>Status</th>
                        <th class="text-center" style="min-width:210px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($list as $r): ?>
                        <tr>
                            <td><span class="badge bg-success">PNK-<?= str_pad((int) $r['id'], 4, '0', STR_PAD_LEFT) ?></span></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <?= renderAvatar($r['nama_nasabah'], $r['foto'] ?? '', $baseUrl ?? '', 34) ?>
                                    <div>
                                        <div class="fw-semibold"><?= htmlspecialchars($r['nama_nasabah']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($r['nomor_hp'] ?? '-') ?></small>
                                    </div>
                                </div>
                            </td>
                            <td class="text-nowrap small"><div><?= date('d M Y', strtotime($r['created_at'])) ?></div><small class="text-muted"><?= date('H:i', strtotime($r['created_at'])) ?></small></td>
                            <td><?= tampilTipe($r['tipe_penarikan']) ?></td>
                            <td class="small text-muted" style="max-width:200px;">
                                <?= htmlspecialchars($r['detail_tujuan']) ?>
                                <?php if (!empty($r['payout_bank_code'])): ?>
                                    <div class="mt-1">
                                        <span class="badge text-bg-info" style="font-size:10px;">
                                            <i class="bi bi-credit-card-2-front me-1"></i>Payout: <?= htmlspecialchars($r['payout_bank_code']) ?> · <?= htmlspecialchars($r['payout_account_number'] ?? '') ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="fw-bold text-nowrap">
                                <?php if ($r['tipe_penarikan'] === 'voucher'): ?>
                                    <?= number_format((int) $r['nominal']) ?> poin
                                <?php else: ?>
                                    Rp <?= number_format((float) $r['nominal'], 0, ',', '.') ?>
                                <?php endif; ?>
                            </td>
                            <td><?= tampilStatus($r['status']) ?>
                                <?php if ($r['status'] === 'ditolak' && !empty($r['alasan_penolakan'])): ?>
                                    <div class="text-danger small mt-1" style="max-width:150px;"><i class="bi bi-x-circle me-1"></i><?= htmlspecialchars($r['alasan_penolakan']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($r['payout_ref'])): ?>
                                    <div class="small text-muted mt-1" style="max-width:160px;"><i class="bi bi-receipt-cutoff me-1"></i>Ref: <?= htmlspecialchars($r['payout_ref']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php
                                // Pesan WhatsApp berdasarkan status penarikan
                                $idLabelWa   = 'PNK-' . str_pad((string) $r['id'], 4, '0', STR_PAD_LEFT);
                                $nominalWa   = $r['tipe_penarikan'] === 'voucher'
                                    ? number_format((int) $r['nominal']) . ' poin'
                                    : 'Rp ' . number_format((float) $r['nominal'], 0, ',', '.');
                                $waStatusTxt = [
                                    'pending'   => 'Status: masih menunggu verifikasi petugas.',
                                    'disetujui' => 'Status: disetujui, dana sedang diproses.',
                                    'ditolak'   => 'Status: ditolak.' . (!empty($r['alasan_penolakan']) ? "\nAlasan: " . $r['alasan_penolakan'] : ''),
                                    'selesai'   => 'Status: telah dibayar. Terima kasih!',
                                ][$r['status']] ?? '';
                                $waUrl = waLink((string) ($r['nomor_hp'] ?? ''),
                                    "Halo {$r['nama_nasabah']},\nInfo penarikan RecyCash {$idLabelWa} ({$nominalWa}):\n{$waStatusTxt}\n\nSalam, RecyCash");
                                ?>
                                <?php if ($waUrl !== ''): ?>
                                    <a href="<?= htmlspecialchars($waUrl) ?>" target="_blank" rel="noopener"
                                       class="btn btn-sm btn-outline-success" title="Kirim info status via WhatsApp">
                                        <i class="bi bi-whatsapp"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if ($r['status'] === 'pending'): ?>
                                    <form action="" method="POST" class="d-inline">
                                        <input type="hidden" name="id_penarikan" value="<?= (int) $r['id'] ?>">
                                        <input type="hidden" name="aksi" value="setujui">
                                        <button class="btn btn-sm btn-success" onclick="return confirm('Setujui penarikan ini?')" title="Setujui">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                    </form>
                                    <button class="btn btn-sm btn-danger" data-bs-toggle="modal"
                                            data-bs-target="#modalTolak"
                                            data-id="<?= (int) $r['id'] ?>"
                                            data-nasabah="<?= htmlspecialchars($r['nama_nasabah']) ?>"
                                            title="Tolak"><i class="bi bi-x-lg"></i></button>
                                <?php elseif ($r['status'] === 'disetujui'): ?>
                                    <?php
                                    $payoutLengkap = !empty($r['payout_bank_code']) && !empty($r['payout_account_number']) && !empty($r['payout_beneficiary_name']);
                                    $payoutDidukung = payoutGatewayAktif() && payoutTipeDidukung((string) $r['tipe_penarikan']);
                                    ?>
                                    <?php if ($payoutDidukung): ?>
                                        <?php if ($payoutLengkap): ?>
                                            <form action="" method="POST" class="d-inline" onsubmit="return confirm('Kirim pembayaran otomatis via payment gateway?');">
                                                <input type="hidden" name="id_penarikan" value="<?= (int) $r['id'] ?>">
                                                <input type="hidden" name="aksi" value="payout">
                                                <button class="btn btn-sm btn-primary" title="Bayar otomatis via gateway">
                                                    <i class="bi bi-lightning-charge me-1"></i>Bayar Otomatis
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#modalPayout"
                                                    data-id="<?= (int) $r['id'] ?>"
                                                    data-nasabah="<?= htmlspecialchars($r['nama_nasabah']) ?>"
                                                    data-detail="<?= htmlspecialchars($r['detail_tujuan']) ?>"
                                                    data-bank="<?= htmlspecialchars($r['payout_bank_code'] ?? '') ?>"
                                                    data-rek="<?= htmlspecialchars($r['payout_account_number'] ?? '') ?>"
                                                    data-nama="<?= htmlspecialchars($r['payout_beneficiary_name'] ?? '') ?>"
                                                    title="Atur tujuan payout">
                                                <i class="bi bi-pencil me-1"></i>Atur Tujuan
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalPayout"
                                            data-id="<?= (int) $r['id'] ?>"
                                            data-nasabah="<?= htmlspecialchars($r['nama_nasabah']) ?>"
                                            data-detail="<?= htmlspecialchars($r['detail_tujuan']) ?>"
                                            data-bank="<?= htmlspecialchars($r['payout_bank_code'] ?? '') ?>"
                                            data-rek="<?= htmlspecialchars($r['payout_account_number'] ?? '') ?>"
                                            data-nama="<?= htmlspecialchars($r['payout_beneficiary_name'] ?? '') ?>"
                                            title="Edit tujuan payout">
                                        <i class="bi bi-gear"></i>
                                    </button>
                                    <form action="" method="POST" class="d-inline">
                                        <input type="hidden" name="id_penarikan" value="<?= (int) $r['id'] ?>">
                                        <input type="hidden" name="aksi" value="selesai">
                                        <button class="btn btn-sm btn-outline-success" onclick="return confirm('Tandai penarikan ini selesai?')" title="Tandai selesai">
                                            <i class="bi bi-check2-circle"></i> Selesai
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- ====================== MODAL ATUR TUJUAN PAYOUT ====================== -->
<div class="modal fade" id="modalPayout" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" action="" method="POST">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-credit-card-2-front text-primary me-1"></i>Atur Tujuan Payout (Otomatis)</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2 small" id="infoPayoutNasabah"></div>
                <input type="hidden" name="id_penarikan" id="idPayout" value="">
                <input type="hidden" name="aksi" value="simpan_payout">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Kode Bank / E-Wallet <span class="text-danger">*</span></label>
                    <select name="payout_bank_code" id="payoutBankCode" class="form-select" required>
                        <option value="">-- Pilih tujuan payout --</option>
                        <optgroup label="Transfer Bank">
                            <option value="BCA">BCA</option>
                            <option value="MANDIRI">Bank Mandiri</option>
                            <option value="BRI">BRI</option>
                            <option value="BNI">BNI</option>
                            <option value="CIMB">CIMB Niaga</option>
                            <option value="PERMATA">Bank Permata</option>
                            <option value="DANAMON">Bank Danamon</option>
                            <option value="PANIN">Panin Bank</option>
                            <option value="MAYBANK">Maybank</option>
                            <option value="BSI">Bank Syariah Indonesia (BSI)</option>
                        </optgroup>
                        <optgroup label="E-Wallet">
                            <option value="GOPAY">GoPay</option>
                            <option value="OVO">OVO</option>
                            <option value="DANA">DANA</option>
                            <option value="SHOPEEPAY">ShopeePay</option>
                            <option value="LINKAJA">LinkAja</option>
                        </optgroup>
                    </select>
                    <div class="form-text">Kode harus sesuai daftar bank/e-wallet yang didukung payment gateway.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Nomor Rekening / Nomor HP <span class="text-danger">*</span></label>
                    <input type="text" name="payout_account_number" id="payoutAccountNumber" class="form-control" maxlength="30" placeholder="Contoh: 1234567890 / 081234567890" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Nama Pemilik Rekening / Akun <span class="text-danger">*</span></label>
                    <input type="text" name="payout_beneficiary_name" id="payoutBeneficiaryName" class="form-control" maxlength="100" placeholder="Sesuai nama pada rekening/akun" required>
                </div>
                <div class="alert alert-light border py-2 small" id="detailTujuanInfo">
                    <i class="bi bi-info-circle me-1"></i>Tujuan dari pengajuan nasabah: <span id="detailTujuanText">-</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan Tujuan Payout</button>
            </div>
        </form>
    </div>
</div>

<!-- ====================== MODAL TOLAK ====================== -->
<div class="modal fade" id="modalTolak" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" action="" method="POST">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-x-octagon text-danger me-1"></i>Tolak Pengajuan Penarikan</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning py-2 small" id="infoNasabah"></div>
                <input type="hidden" name="id_penarikan" id="idTolak" value="">
                <input type="hidden" name="aksi" value="tolak">
                <div class="mb-1">
                    <label class="form-label fw-semibold">Alasan Penolakan <span class="text-danger">*</span></label>
                    <select name="alasan_tolak" class="form-select" id="alasanTolak">
                        <option value="">-- Pilih alasan --</option>
                        <option>Nomor rekening / tujuan tidak valid</option>
                        <option>Nama pemilik tidak sesuai</option>
                        <option>Saldo / poin tidak mencukupi</option>
                        <option>Jumlah melebihi batas maksimal</option>
                        <option value="__lainnya">Lainnya...</option>
                    </select>
                    <textarea name="alasan_tolak_kustom" id="alasanTolakKustom" class="form-control mt-2 d-none" placeholder="Tulis alasan lain..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger"><i class="bi bi-x-lg me-1"></i>Tolak Pengajuan</button>
            </div>
        </form>
    </div>
</div>

<script>
// Isi modal tolak + alasan switch
document.addEventListener('DOMContentLoaded', function () {
    var modalTolak = document.getElementById('modalTolak');
    if (modalTolak) {
        modalTolak.addEventListener('show.bs.modal', function (ev) {
            var btn = ev.relatedTarget;
            document.getElementById('idTolak').value = btn.dataset.id;
            document.getElementById('infoNasabah').innerHTML =
                'Yakin menolak pengajuan <strong>' + (btn.dataset.nasabah || '') + '</strong>?';
            document.getElementById('alasanTolak').value = '';
            document.getElementById('alasanTolakKustom').classList.add('d-none');
        });
    }

    var alasan = document.getElementById('alasanTolak');
    var kustom = document.getElementById('alasanTolakKustom');
    if (alasan && kustom) {
        alasan.addEventListener('change', function () {
            kustom.classList.toggle('d-none', alasan.value !== '__lainnya');
        });
    }

    // Isi modal atur tujuan payout
    var modalPayout = document.getElementById('modalPayout');
    if (modalPayout) {
        modalPayout.addEventListener('show.bs.modal', function (ev) {
            var btn = ev.relatedTarget;
            if (!btn) return;
            document.getElementById('idPayout').value = btn.dataset.id || '';
            document.getElementById('infoPayoutNasabah').innerHTML =
                'Atur tujuan payout untuk penarikan <strong>' + (btn.dataset.nasabah || '') + '</strong>.';
            document.getElementById('detailTujuanText').textContent = btn.dataset.detail || '-';

            var bank = btn.dataset.bank || '';
            var sel = document.getElementById('payoutBankCode');
            if (bank) {
                sel.value = bank;
            } else {
                // Coba deteksi dari detail_tujuan: "BCA - 123456 (a.n. Nama)"
                var m = (btn.dataset.detail || '').match(/^([A-Za-z]+)\s*-/);
                if (m) {
                    var code = m[1].toUpperCase();
                    var opt = sel.querySelector('option[value="' + code + '"]');
                    if (opt) sel.value = code;
                }
            }

            document.getElementById('payoutAccountNumber').value = btn.dataset.rek || '';
            document.getElementById('payoutBeneficiaryName').value = btn.dataset.nama || '';

            // Pre-fill nama pemilik dari detail_tujuan bila kosong
            var namaInput = document.getElementById('payoutBeneficiaryName');
            if (!namaInput.value) {
                var m2 = (btn.dataset.detail || '').match(/\(a\.n\.\s*(.+?)\s*\)/i);
                if (m2) namaInput.value = m2[1].trim();
            }
            var rekInput = document.getElementById('payoutAccountNumber');
            if (!rekInput.value) {
                var m3 = (btn.dataset.detail || '').match(/-\s*(.+?)\s*\(a\.n\./i);
                if (m3) rekInput.value = m3[1].trim();
            }
        });
    }
});
</script>
