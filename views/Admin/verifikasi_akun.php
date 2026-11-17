<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<?php
ob_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/notifikasi.php';

$adminId      = $_SESSION['id_user'] ?? null;
$isSuperAdmin = !empty($_SESSION['is_super_admin']);

// Halaman ini khusus Super Admin
if (!$isSuperAdmin) {
    $_SESSION['error'] = "Halaman Verifikasi Akun khusus untuk Super Admin.";
    header('Location: dashboard.php');
    exit;
}

$flashError   = $_SESSION['error'] ?? '';
$flashSuccess = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);

// ==================== AKSI VERIFIKASI ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi_verifikasi'])) {
    $id      = (int) ($_POST['user_id'] ?? 0);
    $aksi    = $_POST['aksi_verifikasi'];
    $catatan = trim($_POST['catatan'] ?? '');

    $stmt = $pdo->prepare("SELECT id, nama, role FROM users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $target = $stmt->fetch();

    if (!$target || !in_array($target['role'], ['admin', 'petugas'], true)) {
        $_SESSION['error'] = "Akun tidak ditemukan atau bukan akun admin/petugas.";
        header('Location: verifikasi_akun.php');
        exit;
    }

    if ($aksi === 'setujui') {
        $upd = $pdo->prepare("UPDATE users SET status_akun = 'approved', catatan_verifikasi = NULL, verified_by = :by, verified_at = NOW() WHERE id = :id");
        $upd->execute([':by' => $adminId, ':id' => $id]);
        auditCatat($pdo, (int) $adminId, 'VERIFIKASI_SETUJUI', 'users', $id, "Akun: {$target['nama']}, role: {$target['role']}");
        $_SESSION['success'] = "Akun \"{$target['nama']}\" berhasil disetujui dan sudah dapat login.";
    } elseif ($aksi === 'tolak') {
        if ($catatan === '') {
            $_SESSION['error'] = "Alasan penolakan wajib diisi.";
            header('Location: verifikasi_akun.php');
            exit;
        }
        $upd = $pdo->prepare("UPDATE users SET status_akun = 'rejected', catatan_verifikasi = :catatan, verified_by = :by, verified_at = NOW() WHERE id = :id");
        $upd->execute([':catatan' => $catatan, ':by' => $adminId, ':id' => $id]);
        auditCatat($pdo, (int) $adminId, 'VERIFIKASI_TOLAK', 'users', $id, "Akun: {$target['nama']}, role: {$target['role']}, alasan: $catatan");
        $_SESSION['success'] = "Akun \"{$target['nama']}\" ditolak.";
    } else {
        $_SESSION['error'] = "Aksi tidak valid.";
    }

    header('Location: verifikasi_akun.php');
    exit;
}

// ==================== STATISTIK ====================
$stat = $pdo->query("
    SELECT status_akun, COUNT(*) AS jml
    FROM users
    WHERE role IN ('admin', 'petugas')
    GROUP BY status_akun
")->fetchAll();

$counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
foreach ($stat as $r) {
    if (isset($counts[$r['status_akun']])) $counts[$r['status_akun']] = (int) $r['jml'];
}

// ==================== DAFTAR MENUNGGU ====================
$pending = $pdo->query("
    SELECT id, nama, email, nomor_hp, foto, role, created_at
    FROM users
    WHERE role IN ('admin', 'petugas') AND status_akun = 'pending'
    ORDER BY created_at ASC
")->fetchAll();

// ==================== RIWAYAT ====================
$riwayat = $pdo->query("
    SELECT u.id, u.nama, u.email, u.foto, u.role, u.status_akun, u.catatan_verifikasi, u.verified_at,
           v.nama AS verifikator
    FROM users u
    LEFT JOIN users v ON v.id = u.verified_by
    WHERE u.role IN ('admin', 'petugas') AND u.status_akun IN ('approved', 'rejected')
    ORDER BY u.verified_at DESC
")->fetchAll();

function badgeRoleVerif($role) {
    return $role === 'admin'
        ? '<span class="badge rounded-pill text-bg-danger">Admin</span>'
        : '<span class="badge rounded-pill text-bg-warning">Petugas / Teller</span>';
}
?>

<?php if ($flashError): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($flashError) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($flashSuccess): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($flashSuccess) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- ======================== CARD STATISTIK ======================== -->
<div class="row g-4 mb-4">
    <div class="col-sm-4">
        <div class="card stat-card">
            <div class="stat-icon bg-warning"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-info">
                <span class="stat-label">Menunggu Verifikasi</span>
                <span class="stat-value"><?= number_format($counts['pending']) ?></span>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card stat-card">
            <div class="stat-icon bg-success"><i class="bi bi-patch-check-fill"></i></div>
            <div class="stat-info">
                <span class="stat-label">Disetujui</span>
                <span class="stat-value"><?= number_format($counts['approved']) ?></span>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card stat-card">
            <div class="stat-icon bg-danger"><i class="bi bi-x-octagon-fill"></i></div>
            <div class="stat-info">
                <span class="stat-label">Ditolak</span>
                <span class="stat-value"><?= number_format($counts['rejected']) ?></span>
            </div>
        </div>
    </div>
</div>

<!-- ======================== MENUNGGU VERIFIKASI ======================== -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-person-check me-2"></i>Akun Menunggu Verifikasi</h6>
    </div>
    <div class="card-body p-0">
        <?php if (empty($pending)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-check2-circle" style="font-size:40px;"></i>
                <p class="mt-2 mb-0">Tidak ada akun yang menunggu verifikasi.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width:50px;">No</th>
                            <th>Nama & Kontak</th>
                            <th>Peran</th>
                            <th>Terdaftar</th>
                            <th class="text-center" style="width:200px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending as $i => $row): ?>
                            <tr>
                                <td class="text-center"><?= $i + 1 ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <?= renderAvatar($row['nama'], $row['foto'] ?? '', $baseUrl, 36) ?>
                                        <div>
                                            <strong><?= htmlspecialchars($row['nama']) ?></strong>
                                            <div class="text-muted small">
                                                <?= htmlspecialchars($row['email']) ?>
                                                <?php if (!empty($row['nomor_hp'])): ?>
                                                    <br><i class="bi bi-telephone-fill"></i> <?= htmlspecialchars($row['nomor_hp']) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td><?= badgeRoleVerif($row['role']) ?></td>
                                <td class="text-nowrap small text-muted">
                                    <?= date('d/m/Y H:i', strtotime($row['created_at'])) ?>
                                </td>
                                <td class="text-center text-nowrap">
                                    <form action="verifikasi_akun.php" method="POST" class="d-inline">
                                        <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                                        <button type="submit" name="aksi_verifikasi" value="setujui" class="btn btn-sm btn-success">
                                            <i class="bi bi-check-lg"></i> Setujui
                                        </button>
                                    </form>
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                            data-bs-toggle="modal" data-bs-target="#modalTolak"
                                            onclick="isiTolak(<?= (int) $row['id'] ?>, <?= json_encode($row['nama']) ?>)">
                                        <i class="bi bi-x-lg"></i> Tolak
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ======================== RIWAYAT VERIFIKASI ======================== -->
<div class="card">
    <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Riwayat Verifikasi</h6>
    </div>
    <div class="card-body p-0">
        <?php if (empty($riwayat)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox" style="font-size:40px;"></i>
                <p class="mt-2 mb-0">Belum ada riwayat verifikasi.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width:50px;">No</th>
                            <th>Nama</th>
                            <th>Peran</th>
                            <th>Status</th>
                            <th>Diverifikasi Oleh</th>
                            <th>Waktu</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($riwayat as $i => $row): ?>
                            <tr>
                                <td class="text-center"><?= $i + 1 ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <?= renderAvatar($row['nama'], $row['foto'] ?? '', $baseUrl, 36) ?>
                                        <div>
                                            <strong><?= htmlspecialchars($row['nama']) ?></strong>
                                            <div class="text-muted small"><?= htmlspecialchars($row['email']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?= badgeRoleVerif($row['role']) ?></td>
                                <td>
                                    <?php if ($row['status_akun'] === 'approved'): ?>
                                        <span class="badge rounded-pill text-bg-success">Disetujui</span>
                                    <?php else: ?>
                                        <span class="badge rounded-pill text-bg-danger">Ditolak</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small"><?= htmlspecialchars($row['verifikator'] ?? '-') ?></td>
                                <td class="text-nowrap small text-muted">
                                    <?= $row['verified_at'] ? date('d/m/Y H:i', strtotime($row['verified_at'])) : '-' ?>
                                </td>
                                <td class="small text-muted" style="max-width:240px;">
                                    <?= htmlspecialchars($row['catatan_verifikasi'] ?? '-') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ======================== MODAL TOLAK ======================== -->
<div class="modal fade" id="modalTolak" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="verifikasi_akun.php" method="POST">
                <input type="hidden" name="user_id" id="tolak_id">
                <input type="hidden" name="aksi_verifikasi" value="tolak">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-x-octagon me-2 text-danger"></i>Tolak Akun</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">Tolak akun <strong id="tolak_nama">-</strong>?</p>
                    <label for="tolak_catatan" class="form-label">Alasan Penolakan</label>
                    <textarea class="form-control" id="tolak_catatan" name="catatan" rows="3"
                              placeholder="Contoh: Data pengurus tidak sesuai / SK belum diterima." required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="bi bi-x-lg me-1"></i>Tolak Akun
                    </button>
                </div>
            </form>
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

    @media (max-width: 767px) {
        .stat-card { padding: 16px; gap: 12px; }
        .stat-icon { width: 44px; height: 44px; font-size: 18px; border-radius: 12px; }
        .stat-label { font-size: 12px; }
        .stat-value { font-size: 17px; }
    }
</style>

<script>
    window.isiTolak = function (id, nama) {
        document.getElementById('tolak_id').value = id;
        document.getElementById('tolak_nama').textContent = nama;
        document.getElementById('tolak_catatan').value = '';
    };
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
