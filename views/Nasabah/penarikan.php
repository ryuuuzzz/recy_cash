<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<?php
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../config/payment_config.php';

$userId = (int) ($_SESSION['id_user'] ?? 0);

$MIN_WITHDRAW = 10000;
$MIN_POIN     = 10;

$bankProviders = [
    'bca'     => 'BCA',
    'mandiri' => 'Bank Mandiri',
    'bri'     => 'BRI',
    'bni'     => 'BNI',
];
$ewalletProviders = [
    'gopay'     => 'GoPay',
    'ovo'       => 'OVO',
    'dana'      => 'DANA',
    'shopeepay' => 'ShopeePay',
];
$providerLabels = $bankProviders + $ewalletProviders;

$voucherOptions = [
    'Paket Sembako'         => 'Paket Sembako',
    'Voucher Pulsa'         => 'Voucher Pulsa',
    'Voucher Token Listrik' => 'Voucher Token Listrik',
    'Barang Rumah Tangga'   => 'Barang Rumah Tangga',
];

$methodLabels = [
    'bank_transfer' => 'Transfer Bank',
    'e-wallet'      => 'E-Wallet',
    'voucher'       => 'Tukar Poin / Voucher',
    'tunai'         => 'Tunai di Lokasi',
];

$stmt = $pdo->prepare("SELECT saldo_rupiah, poin FROM wallets WHERE user_id = :uid LIMIT 1");
$stmt->execute([':uid' => $userId]);
$wallet = $stmt->fetch();
$saldo  = (float) ($wallet['saldo_rupiah'] ?? 0);
$poin   = (int) ($wallet['poin'] ?? 0);

$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN tipe_penarikan IN ('bank_transfer','e-wallet','tunai') THEN nominal ELSE 0 END), 0) AS pending_saldo,
        COALESCE(SUM(CASE WHEN tipe_penarikan = 'voucher' THEN nominal ELSE 0 END), 0) AS pending_poin
    FROM withdrawals
    WHERE nasabah_id = :uid AND status = 'pending'
");
$stmt->execute([':uid' => $userId]);
$pending      = $stmt->fetch();
$pendingSaldo = (float) $pending['pending_saldo'];
$pendingPoin  = (float) $pending['pending_poin'];

$saldoTersedia = max(0, $saldo - $pendingSaldo);
$poinTersedia  = max(0, $poin - (int) $pendingPoin);

$pesan     = '';
$oldMethod = 'bank';

if (isset($_POST['ajukan_penarikan'])) {
    $metode       = $_POST['metode'] ?? '';
    $oldMethod    = $metode;
    $nominalInput = str_replace('.', '', trim($_POST['nominal'] ?? ''));
    $nominalInput = str_replace(',', '.', $nominalInput);
    $errors       = [];

    if ($metode === 'bank') {
        $penyedia    = $_POST['penyedia'] ?? '';
        $noTujuan    = trim($_POST['no_tujuan'] ?? '');
        $namaPemilik = trim($_POST['nama_pemilik'] ?? '');
        if (!isset($providerLabels[$penyedia])) {
            $errors[] = 'Silakan pilih bank atau e-wallet tujuan transfer.';
        }
        if ($noTujuan === '') {
            $errors[] = 'Nomor rekening / nomor HP e-wallet wajib diisi.';
        }
        if ($namaPemilik === '') {
            $errors[] = 'Nama pemilik rekening/akun wajib diisi.';
        }

        $tipePenarikan = isset($bankProviders[$penyedia]) ? 'bank_transfer' : 'e-wallet';
    } elseif ($metode === 'voucher') {
        $voucher       = $_POST['voucher'] ?? '';
        $tipePenarikan = 'voucher';
        if (!isset($voucherOptions[$voucher])) {
            $errors[] = 'Silakan pilih voucher / barang yang diinginkan.';
        }
    } elseif ($metode === 'tunai') {
        $tipePenarikan = 'tunai';
    } else {
        $tipePenarikan = '';
        $errors[]      = 'Silakan pilih metode penarikan terlebih dahulu.';
    }

    if ($nominalInput === '' || !is_numeric($nominalInput) || (float) $nominalInput <= 0) {
        $errors[] = 'Nominal penarikan harus berupa angka lebih dari 0.';
    } else {
        $nominal = (float) $nominalInput;
        if ($tipePenarikan === 'voucher') {
            if ($nominal < $MIN_POIN) {
                $errors[] = 'Minimal penukaran adalah ' . number_format($MIN_POIN) . ' poin.';
            }
            if ($nominal > $poinTersedia) {
                $errors[] = 'Jumlah poin melebihi sisa poin yang dapat ditukarkan (' . number_format($poinTersedia, 0, ',', '.') . ' poin).';
            }
        } elseif ($tipePenarikan !== '') {
            if ($nominal < $MIN_WITHDRAW) {
                $errors[] = 'Minimal penarikan adalah Rp ' . number_format($MIN_WITHDRAW, 0, ',', '.') . '.';
            }
            if ($nominal > $saldoTersedia) {
                $errors[] = 'Nominal melebihi sisa saldo aktif yang tersedia (Rp ' . number_format($saldoTersedia, 0, ',', '.') . ').';
            }
        }
    }

    if (empty($errors)) {
        if ($tipePenarikan === 'bank_transfer' || $tipePenarikan === 'e-wallet') {
            $detail = $providerLabels[$penyedia] . ' - ' . $noTujuan . ' (a.n. ' . $namaPemilik . ')';
        } elseif ($tipePenarikan === 'voucher') {
            $detail = 'Tukar poin - ' . $voucher;
        } else {
            $detail = 'Tunai di lokasi RecyCash';
        }
    }

    if (empty($errors)) {
        try {
            $sql = "INSERT INTO withdrawals
                        (nasabah_id, tipe_penarikan, nominal, detail_tujuan, status,
                         payout_bank_code, payout_account_number, payout_beneficiary_name, payout_provider)
                    VALUES
                        (:uid, :tipe, :nominal, :detail, 'pending',
                         :pbc, :pan, :pbn, :ppv)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':uid'     => $userId,
                ':tipe'    => $tipePenarikan,
                ':nominal' => $nominal,
                ':detail'  => $detail,
                ':pbc'     => ($tipePenarikan === 'bank_transfer' || $tipePenarikan === 'e-wallet') ? strtoupper($penyedia) : null,
                ':pan'     => ($tipePenarikan === 'bank_transfer' || $tipePenarikan === 'e-wallet') ? $noTujuan : null,
                ':pbn'     => ($tipePenarikan === 'bank_transfer' || $tipePenarikan === 'e-wallet') ? $namaPemilik : null,
                ':ppv'     => defined('PAYOUT_PROVIDER') ? PAYOUT_PROVIDER : null,
            ]);
            $_SESSION['success'] = 'Pengajuan penarikan berhasil dikirim dan menunggu verifikasi admin/petugas.';
            header('Location: ' . $baseUrl . '/views/Nasabah/penarikan.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Gagal menyimpan pengajuan: ' . $e->getMessage();
        }
    }

    if (!empty($errors)) {
        $listErr = implode('</li><li>', array_map('htmlspecialchars', $errors));
        $pesan = "<div class='alert alert-danger alert-dismissible fade show'>
                    <i class='bi bi-exclamation-triangle me-2'></i>
                    <ul class='mb-0 ps-3'>" . $listErr . "</ul>
                    <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                  </div>";
    }
}

$stmt = $pdo->prepare("
    SELECT w.*
    FROM withdrawals w
    WHERE w.nasabah_id = :uid
    ORDER BY w.created_at DESC, w.id DESC
");
$stmt->execute([':uid' => $userId]);
$riwayatList = $stmt->fetchAll();

function badgeStatusPenarikan($status) {
    $map = [
        'pending'   => 'text-bg-warning',
        'disetujui' => 'bg-success',
        'selesai'   => 'bg-success',
        'ditolak'   => 'bg-danger',
    ];
    $label = [
        'pending'   => 'Pending',
        'disetujui' => 'Disetujui',
        'selesai'   => 'Selesai',
        'ditolak'   => 'Ditolak',
    ];
    $cls = $map[$status] ?? 'bg-secondary';
    return '<span class="badge rounded-pill ' . $cls . '">' . ($label[$status] ?? ucfirst($status)) . '</span>';
}
?>

<?php if (!empty($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($_SESSION['success']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (!empty($pesan)) echo $pesan; ?>

<!-- ======================== RINGKASAN SALDO & POIN ======================== -->
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-4">
        <div class="card stat-card border-start-0">
            <div class="stat-icon bg-success"><i class="bi bi-wallet2"></i></div>
            <div class="stat-info">
                <span class="stat-label">Saldo Rupiah Aktif</span>
                <span class="stat-value">Rp <?= number_format($saldo, 0, ',', '.') ?></span>
                <small class="text-muted">Sisa yang bisa ditarik: Rp <?= number_format($saldoTersedia, 0, ',', '.') ?></small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="card stat-card border-start-0">
            <div class="stat-icon bg-warning"><i class="bi bi-star-fill"></i></div>
            <div class="stat-info">
                <span class="stat-label">Total Poin</span>
                <span class="stat-value"><?= number_format($poin) ?> Poin</span>
                <small class="text-muted">Bisa ditukar: <?= number_format($poinTersedia) ?> poin</small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="card stat-card border-start-0">
            <div class="stat-icon bg-info"><i class="bi bi-clock-history"></i></div>
            <div class="stat-info">
                <span class="stat-label">Total Pengajuan</span>
                <span class="stat-value"><?= count($riwayatList) ?></span>
                <small class="text-muted"><i class="bi bi-hourglass-split me-1"></i><?= number_format($pendingSaldo, 0, ',', '.') ?> rupiah / <?= number_format($pendingPoin) ?> poin pending</small>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- ======================== FORM PENGAJUAN ======================== -->
    <div class="col-xl-5 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-cash-coin me-2 text-success"></i>Ajukan Penarikan / Tukar Poin</h5>
            </div>
            <div class="card-body">
                <form action="" method="POST" id="formPenarikan">
                    <!-- Pilihan Metode -->
                    <label class="form-label fw-semibold">Pilih Metode Penarikan</label>
                    <div class="row g-2 mb-3">
                        <div class="col-6 col-lg-4">
                            <label class="method-card <?= $oldMethod === 'bank' ? 'selected' : '' ?>" id="opt-bank">
                                <input type="radio" name="metode" value="bank" <?= $oldMethod === 'bank' ? 'checked' : '' ?> onchange="pilihMetode('bank')">
                                <i class="bi bi-bank2"></i>
                                <span>Bank / E-Wallet</span>
                            </label>
                        </div>
                        <div class="col-6 col-lg-4">
                            <label class="method-card <?= $oldMethod === 'voucher' ? 'selected' : '' ?>" id="opt-voucher">
                                <input type="radio" name="metode" value="voucher" <?= $oldMethod === 'voucher' ? 'checked' : '' ?> onchange="pilihMetode('voucher')">
                                <i class="bi bi-gift"></i>
                                <span>Tukar Poin / Voucher</span>
                            </label>
                        </div>
                        <div class="col-6 col-lg-4">
                            <label class="method-card <?= $oldMethod === 'tunai' ? 'selected' : '' ?>" id="opt-tunai">
                                <input type="radio" name="metode" value="tunai" <?= $oldMethod === 'tunai' ? 'checked' : '' ?> onchange="pilihMetode('tunai')">
                                <i class="bi bi-cash-stack"></i>
                                <span>Cair Tunai di Lokasi</span>
                            </label>
                        </div>
                    </div>

                    <!-- Nominal / Poin -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold" id="label-nominal">Nominal Penarikan</label>
                        <div class="input-group">
                            <span class="input-group-text" id="prefix-nominal">Rp</span>
                            <input type="number" class="form-control" name="nominal" id="input-nominal"
                                   min="<?= $MIN_WITHDRAW ?>" step="1000"
                                   placeholder="Minimum Rp 10.000" required
                                   value="<?= htmlspecialchars($_POST['nominal'] ?? '') ?>">
                        </div>
                        <div class="form-text" id="hint-nominal">
                            Maksimum: <strong class="text-success">Rp <?= number_format($saldoTersedia, 0, ',', '.') ?></strong> (sisa saldo aktif).
                            Minimal penarikan Rp <?= number_format($MIN_WITHDRAW, 0, ',', '.') ?>.
                        </div>
                        <div class="d-flex flex-wrap gap-2 mt-2" id="quick-amount">
                            <button type="button" class="btn btn-sm btn-outline-success" onclick="setNominal(10000)">Rp 10.000</button>
                            <button type="button" class="btn btn-sm btn-outline-success" onclick="setNominal(25000)">Rp 25.000</button>
                            <button type="button" class="btn btn-sm btn-outline-success" onclick="setNominal(50000)">Rp 50.000</button>
                            <button type="button" class="btn btn-sm btn-outline-success" onclick="setNominal(100000)">Rp 100.000</button>
                        </div>
                    </div>

                    <!-- Field Khusus Transfer Bank / E-Wallet -->
                    <div id="field-bank" <?= $oldMethod !== 'bank' ? 'class="d-none"' : '' ?>>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Bank / E-Wallet Tujuan</label>
                            <select name="penyedia" class="form-select">
                                <optgroup label="Transfer Bank">
                                    <?php foreach ($bankProviders as $key => $label): ?>
                                        <option value="<?= $key ?>" <?= (($_POST['penyedia'] ?? '') === $key) ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <optgroup label="E-Wallet">
                                    <?php foreach ($ewalletProviders as $key => $label): ?>
                                        <option value="<?= $key ?>" <?= (($_POST['penyedia'] ?? '') === $key) ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            </select>
                            <div class="form-text">
                                Pastikan nama dan nomor rekening/e-wallet sesuai dengan data pemilik.
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nomor Rekening / Nomor HP E-Wallet</label>
                            <input type="text" name="no_tujuan" class="form-control" maxlength="30" placeholder="Contoh: 1234567890 / 081234567890" value="<?= htmlspecialchars($_POST['no_tujuan'] ?? '') ?>">
                            <div class="form-text">Untuk GoPay/OVO gunakan nomor HP yang terdaftar (08...).</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nama Pemilik Rekening / Akun</label>
                            <input type="text" name="nama_pemilik" class="form-control" maxlength="100" placeholder="Sesuai nama pada rekening/akun" value="<?= htmlspecialchars($_POST['nama_pemilik'] ?? '') ?>">
                        </div>
                    </div>

                    <!-- Field Khusus Tukar Poin / Voucher -->
                    <div id="field-voucher" <?= $oldMethod !== 'voucher' ? 'class="d-none"' : '' ?>>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Pilih Voucher / Barang</label>
                            <select name="voucher" class="form-select">
                                <?php foreach ($voucherOptions as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= (($_POST['voucher'] ?? '') === $key) ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">1 poin setara dengan nilai tukar yang berlaku di RecyCash.</div>
                        </div>
                    </div>

                    <button type="submit" name="ajukan_penarikan" class="btn btn-success w-100">
                        <i class="bi bi-send me-1"></i>Ajukan Penarikan
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- ======================== RIWAYAT PENARIKAN ======================== -->
    <div class="col-xl-7">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-clock-history me-2 text-success"></i>Riwayat &amp; Status Pengajuan</h5>
                <div class="d-flex gap-2 flex-wrap">
                    <span class="badge rounded-pill text-bg-warning">Pending</span>
                    <span class="badge rounded-pill text-bg-success">Disetujui / Selesai</span>
                    <span class="badge rounded-pill text-bg-danger">Ditolak</span>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($riwayatList)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox" style="font-size:40px;"></i>
                        <p class="mt-2">Belum ada pengajuan penarikan.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Kode</th>
                                    <th>Tanggal</th>
                                    <th>Metode</th>
                                    <th>Tujuan</th>
                                    <th>Nominal</th>
                                    <th class="text-center">Status</th>
                                    <th>Alasan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($riwayatList as $row): ?>
                                    <tr>
                                        <td><span class="badge text-bg-success">TNK-<?= (int) $row['id'] ?></span></td>
                                        <td class="text-nowrap small">
                                            <div><?= date('d M Y', strtotime($row['created_at'])) ?></div>
                                            <small class="text-muted"><?= date('H:i', strtotime($row['created_at'])) ?></small>
                                        </td>
                                        <td><span class="badge rounded-pill text-bg-primary"><?= htmlspecialchars($methodLabels[$row['tipe_penarikan']] ?? $row['tipe_penarikan']) ?></span></td>
                                        <td class="small text-muted" style="max-width:180px;"><?= htmlspecialchars($row['detail_tujuan']) ?></td>
                                        <td class="fw-bold text-nowrap">
                                            <?php if ($row['tipe_penarikan'] === 'voucher'): ?>
                                                <?= number_format((float) $row['nominal'], 0, ',', '.') ?> Poin
                                            <?php else: ?>
                                                Rp <?= number_format((float) $row['nominal'], 0, ',', '.') ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center"><?= badgeStatusPenarikan($row['status']) ?></td>
                                        <td class="small">
                                            <?php if ($row['status'] === 'ditolak'): ?>
                                                <span class="text-danger"><i class="bi bi-x-circle me-1"></i><?= htmlspecialchars($row['alasan_penolakan'] ?: 'Pengajuan ditolak.') ?></span>
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

<style>
    .stat-card {
        border: none;
        border-radius: 14px;
        padding: 22px;
        display: flex;
        align-items: center;
        gap: 16px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        transition: transform 0.2s;
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
    .stat-label { font-size: 13px; color: var(--text-muted); font-weight: 500; }
    .stat-value { font-size: 20px; font-weight: 700; color: var(--text-strong); }

    .method-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
        padding: 14px 8px;
        border: 2px solid #e2e8f0;
        border-radius: 14px;
        cursor: pointer;
        text-align: center;
        font-size: 12px;
        font-weight: 600;
        color: var(--text-body);
        transition: all 0.2s;
        height: 100%;
        background: #fff;
    }
    .method-card input { display: none; }
    .method-card i { font-size: 22px; color: var(--text-soft); }
    .method-card.selected {
        border-color: var(--primary, #2e7d32);
        background: #eaf7ec;
        color: var(--primary-dark, #1b5e20);
    }
    .method-card.selected i { color: var(--primary, #2e7d32); }

    @media (max-width: 767px) {
        .stat-card { padding: 16px; gap: 12px; }
        .stat-icon { width: 44px; height: 44px; font-size: 18px; border-radius: 12px; }
        .stat-label { font-size: 12px; }
        .stat-value { font-size: 17px; }
        .method-card { font-size: 11px; padding: 10px 6px; }
    }
</style>

<script>
    var MIN_WITHDRAW = <?= $MIN_WITHDRAW ?>;
    var MIN_POIN     = <?= $MIN_POIN ?>;
    var saldoTersedia = <?= $saldoTersedia ?>;
    var poinTersedia  = <?= $poinTersedia ?>;

    function fmtRp(n) { return 'Rp ' + Number(n).toLocaleString('id-ID'); }

    function pilihMetode(metode) {
        ['bank', 'voucher', 'tunai'].forEach(function (k) {
            var opt = document.getElementById('opt-' + k);
            if (opt) opt.classList.toggle('selected', k === metode);
        });
        document.getElementById('field-bank').classList.toggle('d-none', metode !== 'bank');
        document.getElementById('field-voucher').classList.toggle('d-none', metode !== 'voucher');

        var inp  = document.getElementById('input-nominal');
        var pre  = document.getElementById('prefix-nominal');
        var lbl  = document.getElementById('label-nominal');
        var hin  = document.getElementById('hint-nominal');
        var quick = document.getElementById('quick-amount');

        if (metode === 'voucher') {
            lbl.textContent = 'Jumlah Poin yang Ditukarkan';
            pre.textContent = 'Poin';
            inp.min = MIN_POIN;
            inp.step = 1;
            inp.placeholder = 'Minimum ' + MIN_POIN + ' poin';
            hin.innerHTML = 'Maksimum: <strong class="text-success">' + Number(poinTersedia).toLocaleString('id-ID') + '</strong> poin yang bisa ditukar.';
            quick.style.display = 'none';
        } else {
            lbl.textContent = 'Nominal Penarikan';
            pre.textContent = 'Rp';
            inp.min = MIN_WITHDRAW;
            inp.step = 1000;
            inp.placeholder = 'Minimum Rp 10.000';
            hin.innerHTML = 'Maksimum: <strong class="text-success">' + fmtRp(saldoTersedia) + '</strong> (sisa saldo aktif). Minimal penarikan ' + fmtRp(MIN_WITHDRAW) + '.';
            quick.style.display = '';
        }
    }

    function setNominal(v) {
        var metode = document.querySelector('input[name="metode"]:checked');
        if (metode && metode.value !== 'voucher') {
            document.getElementById('input-nominal').value = v;
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var checked = document.querySelector('input[name="metode"]:checked');
        if (checked) pilihMetode(checked.value);
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>