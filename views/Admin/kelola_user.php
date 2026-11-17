<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<?php
ob_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/notifikasi.php';

$flashError   = $_SESSION['error'] ?? '';
$flashSuccess = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);

$adminId      = $_SESSION['id_user'] ?? null;
$isSuperAdmin = !empty($_SESSION['is_super_admin']);

// Role yang boleh dibuat/diatur oleh admin yang sedang login
$allowedRoles = $isSuperAdmin ? ['admin', 'petugas', 'nasabah', 'pengepul'] : ['petugas', 'nasabah', 'pengepul'];

// ==================== TAMBAH USER ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_user'])) {
    $nama    = trim($_POST['nama'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $nomorHp = trim($_POST['nomor_hp'] ?? '');
    $alamat  = trim($_POST['alamat'] ?? '');
    $role    = trim($_POST['role'] ?? '');
    $pw      = $_POST['password'] ?? '';
    $mintaSuper = !empty($_POST['is_super_admin']);

    if ($nama === '' || $email === '' || $pw === '' || !in_array($role, $allowedRoles, true)) {
        $_SESSION['error'] = $isSuperAdmin
            ? "Nama, email, password, dan role wajib diisi."
            : "Nama, email, password, dan role wajib diisi. Hanya Super Admin yang dapat membuat akun admin.";
        header('Location: kelola_user.php');
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Format email tidak valid.";
        header('Location: kelola_user.php');
        exit;
    }

    if (strlen($pw) < 6) {
        $_SESSION['error'] = "Password minimal 6 karakter.";
        header('Location: kelola_user.php');
        exit;
    }

    $cek = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $cek->execute([':email' => $email]);
    if ($cek->fetch()) {
        $_SESSION['error'] = "Email \"$email\" sudah terdaftar. Gunakan email lain.";
        header('Location: kelola_user.php');
        exit;
    }

    if ($nomorHp !== '' && !preg_match('/^[0-9+\-\s]{8,15}$/', $nomorHp)) {
        $_SESSION['error'] = "Format nomor telepon tidak valid (8-15 digit).";
        header('Location: kelola_user.php');
        exit;
    }

    // Akun admin/petugas yang dibuat Super Admin langsung disetujui (approved);
    // petugas yang dibuat admin biasa tetap menunggu verifikasi.
    $statusAkun = 'approved';
    $verifiedBy = null;
    $verifiedAt = null;
    if (in_array($role, ['admin', 'petugas'], true) && !$isSuperAdmin) {
        $statusAkun = 'pending';
    } elseif (in_array($role, ['admin', 'petugas'], true)) {
        $verifiedBy = $adminId;
        $verifiedAt = date('Y-m-d H:i:s');
    }

    // Tentukan is_super_admin untuk akun baru
    $isSuperBaru = 0;
    if ($mintaSuper && $isSuperAdmin && $role === 'admin') {
        $isSuperBaru = 1;
    }

    $stmt = $pdo->prepare("INSERT INTO users (nama, email, nomor_hp, password, alamat, role, status_akun, is_super_admin, verified_by, verified_at) VALUES (:nama, :email, :nomor_hp, :password, :alamat, :role, :status_akun, :is_super_admin, :verified_by, :verified_at)");
    $stmt->execute([
        ':nama'          => $nama,
        ':email'         => $email,
        ':nomor_hp'      => $nomorHp !== '' ? $nomorHp : null,
        ':password'      => password_hash($pw, PASSWORD_DEFAULT),
        ':alamat'        => $alamat !== '' ? $alamat : null,
        ':role'          => $role,
        ':status_akun'   => $statusAkun,
        ':is_super_admin'=> $isSuperBaru,
        ':verified_by'   => $verifiedBy,
        ':verified_at'   => $verifiedAt,
    ]);
    $newUserId = (int) $pdo->lastInsertId();

    // Nasabah otomatis dapat dompet saldo
    if ($role === 'nasabah') {
        $uid = $pdo->lastInsertId();
        $cekWallet = $pdo->prepare("SELECT id FROM wallets WHERE user_id = :uid LIMIT 1");
        $cekWallet->execute([':uid' => $uid]);
        if (!$cekWallet->fetch()) {
            $pdo->prepare("INSERT INTO wallets (user_id, saldo_rupiah, poin) VALUES (:uid, 0.00, 0)")
                ->execute([':uid' => $uid]);
        }
    }

    // Audit log aktivitas admin
    auditCatat($pdo, (int) $adminId, 'INSERT_USER', 'users', $newUserId,
        "Nama: $nama, email: $email, role: $role, status: $statusAkun");

    $_SESSION['success'] = $statusAkun === 'pending'
        ? "Akun \"$nama\" ($role) berhasil ditambahkan dan menunggu verifikasi Super Admin."
        : "Akun \"$nama\" ($role) berhasil ditambahkan.";
    header('Location: kelola_user.php');
    exit;
}

// ==================== EDIT USER ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $id      = (int) ($_POST['user_id'] ?? 0);
    $nama    = trim($_POST['nama'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $nomorHp = trim($_POST['nomor_hp'] ?? '');
    $alamat  = trim($_POST['alamat'] ?? '');
    $role    = trim($_POST['role'] ?? '');
    $pw      = $_POST['password'] ?? '';
    $mintaSuper = !empty($_POST['is_super_admin']);

    if ($id <= 0 || $nama === '' || $email === '' || !in_array($role, $allowedRoles, true)) {
        $_SESSION['error'] = $isSuperAdmin
            ? "Data tidak lengkap."
            : "Data tidak lengkap. Hanya Super Admin yang dapat mengatur akun admin.";
        header('Location: kelola_user.php');
        exit;
    }

    if ($id === (int) $adminId && $role !== 'admin') {
        $_SESSION['error'] = "Anda tidak dapat mengubah role akun Anda sendiri.";
        header('Location: kelola_user.php');
        exit;
    }

    $stmtTarget = $pdo->prepare("SELECT role, status_akun, is_super_admin FROM users WHERE id = :id LIMIT 1");
    $stmtTarget->execute([':id' => $id]);
    $target = $stmtTarget->fetch();
    if (!$target) {
        $_SESSION['error'] = "Akun tidak ditemukan.";
        header('Location: kelola_user.php');
        exit;
    }
    if (!$isSuperAdmin && ($target['role'] === 'admin' || !empty($target['is_super_admin']))) {
        $_SESSION['error'] = "Hanya Super Admin yang dapat mengelola akun admin.";
        header('Location: kelola_user.php');
        exit;
    }

    // Tentukan status verifikasi setelah diedit
    if ($role === 'nasabah') {
        $statusAkun = 'approved';
        $verifiedBy = null;
        $verifiedAt = null;
    } elseif ($isSuperAdmin) {
        $statusAkun = 'approved';
        $verifiedBy = $adminId;
        $verifiedAt = date('Y-m-d H:i:s');
    } else {
        // Admin biasa mengatur petugas: pertahankan status yang sudah disetujui
        $statusAkun = ($target['status_akun'] === 'approved') ? 'approved' : 'pending';
        $verifiedBy = null;
        $verifiedAt = null;
    }

    // Tentukan is_super_admin setelah edit
    $superBaru = 0;
    if ($isSuperAdmin && $role === 'admin' && $mintaSuper) {
        $superBaru = 1;
    }
    $targetIsSuper = !empty($target['is_super_admin']);
    $jmlSuper = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE is_super_admin = 1")->fetchColumn();
    if ($id === (int) $adminId) {
        if (!$mintaSuper) {
            $_SESSION['error'] = "Anda tidak dapat mencabut status Super Admin dari akun sendiri.";
            header('Location: kelola_user.php');
            exit;
        }
        $superBaru = 1;
    } else {
        if ($targetIsSuper && !$mintaSuper) {
            if ($jmlSuper <= 1) {
                $_SESSION['error'] = "Minimal harus ada satu Super Admin yang tersisa.";
                header('Location: kelola_user.php');
                exit;
            }
        }
        if (!$mintaSuper) {
            $superBaru = 0;
        }
    }
    if ($role !== 'admin') {
        $superBaru = 0;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Format email tidak valid.";
        header('Location: kelola_user.php');
        exit;
    }

    if ($pw !== '' && strlen($pw) < 6) {
        $_SESSION['error'] = "Password baru minimal 6 karakter.";
        header('Location: kelola_user.php');
        exit;
    }

    // Email tidak boleh dipakai user lain
    $cek = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1");
    $cek->execute([':email' => $email, ':id' => $id]);
    if ($cek->fetch()) {
        $_SESSION['error'] = "Email \"$email\" sudah digunakan akun lain.";
        header('Location: kelola_user.php');
        exit;
    }

    if ($nomorHp !== '' && !preg_match('/^[0-9+\-\s]{8,15}$/', $nomorHp)) {
        $_SESSION['error'] = "Format nomor telepon tidak valid (8-15 digit).";
        header('Location: kelola_user.php');
        exit;
    }

    if ($pw === '') {
        $stmt = $pdo->prepare("UPDATE users SET nama = :nama, email = :email, nomor_hp = :nomor_hp, alamat = :alamat, role = :role, status_akun = :status_akun, is_super_admin = :is_super_admin, verified_by = :verified_by, verified_at = :verified_at WHERE id = :id");
        $stmt->execute([':nama' => $nama, ':email' => $email, ':nomor_hp' => $nomorHp !== '' ? $nomorHp : null, ':alamat' => $alamat !== '' ? $alamat : null, ':role' => $role, ':status_akun' => $statusAkun, ':is_super_admin' => $superBaru, ':verified_by' => $verifiedBy, ':verified_at' => $verifiedAt, ':id' => $id]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET nama = :nama, email = :email, nomor_hp = :nomor_hp, alamat = :alamat, role = :role, password = :password, status_akun = :status_akun, is_super_admin = :is_super_admin, verified_by = :verified_by, verified_at = :verified_at WHERE id = :id");
        $stmt->execute([':nama' => $nama, ':email' => $email, ':nomor_hp' => $nomorHp !== '' ? $nomorHp : null, ':alamat' => $alamat !== '' ? $alamat : null, ':role' => $role, ':password' => password_hash($pw, PASSWORD_DEFAULT), ':status_akun' => $statusAkun, ':is_super_admin' => $superBaru, ':verified_by' => $verifiedBy, ':verified_at' => $verifiedAt, ':id' => $id]);
    }

    // Pastikan nasabah punya dompet saldo setelah diedit
    if ($role === 'nasabah') {
        $cekWallet = $pdo->prepare("SELECT id FROM wallets WHERE user_id = :uid LIMIT 1");
        $cekWallet->execute([':uid' => $id]);
        if (!$cekWallet->fetch()) {
            $pdo->prepare("INSERT INTO wallets (user_id, saldo_rupiah, poin) VALUES (:uid, 0.00, 0)")
                ->execute([':uid' => $id]);
        }
    }

    // Audit log aktivitas admin
    auditCatat($pdo, (int) $adminId, 'UPDATE_USER', 'users', $id,
        "Nama: $nama, role: {$target['role']} -> $role" . ($pw !== '' ? ', password diganti' : ''));

    $_SESSION['success'] = "Data akun \"$nama\" berhasil diperbarui.";
    header('Location: kelola_user.php');
    exit;
}

// ==================== HAPUS USER ====================
if (isset($_GET['hapus'])) {
    $id = (int) $_GET['hapus'];

    if ($id === (int) $adminId) {
        $_SESSION['error'] = "Anda tidak dapat menghapus akun Anda sendiri.";
        header('Location: kelola_user.php');
        exit;
    }

    $stmtTarget = $pdo->prepare("SELECT role, is_super_admin, nama, email FROM users WHERE id = :id LIMIT 1");
    $stmtTarget->execute([':id' => $id]);
    $targetHapus = $stmtTarget->fetch();

    if (!$isSuperAdmin && $targetHapus && ($targetHapus['role'] === 'admin' || !empty($targetHapus['is_super_admin']))) {
        $_SESSION['error'] = "Hanya Super Admin yang dapat menghapus akun admin.";
        header('Location: kelola_user.php');
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
    $stmt->execute([':id' => $id]);

    // Audit log aktivitas admin
    auditCatat($pdo, (int) $adminId, 'HAPUS_USER', 'users', $id,
        "Nama: " . ($targetHapus['nama'] ?? '-') . ", email: " . ($targetHapus['email'] ?? '-') . ", role: " . ($targetHapus['role'] ?? '-'));

    $_SESSION['success'] = "Akun pengguna berhasil dihapus.";
    header('Location: kelola_user.php');
    exit;
}

// ==================== STATISTIK ====================
$stmt = $pdo->query("SELECT role, COUNT(*) AS jml FROM users GROUP BY role");
$counts = ['admin' => 0, 'petugas' => 0, 'nasabah' => 0, 'pengepul' => 0];
foreach ($stmt->fetchAll() as $r) {
    if (isset($counts[$r['role']])) $counts[$r['role']] = (int) $r['jml'];
}
$totalUsers   = array_sum($counts);

$stmt = $pdo->query("SELECT COALESCE(SUM(saldo_rupiah), 0) AS total FROM wallets");
$totalSaldo   = $stmt->fetch()['total'];

// ==================== DAFTAR USER ====================
$stmt = $pdo->query("
    SELECT u.id, u.nama, u.email, u.nomor_hp, u.alamat, u.foto, u.role, u.created_at,
           u.status_akun, u.is_super_admin,
           w.saldo_rupiah, w.poin
    FROM users u
    LEFT JOIN wallets w ON w.user_id = u.id
    ORDER BY u.role ASC, u.nama ASC
");
$users = $stmt->fetchAll();

function badgeStatus($row) {
    if (!in_array($row['role'], ['admin', 'petugas'], true)) {
        return '<span class="badge rounded-pill text-bg-success">Aktif</span>';
    }
    switch ($row['status_akun'] ?? 'approved') {
        case 'pending':  return '<span class="badge rounded-pill text-bg-warning">Menunggu</span>';
        case 'rejected': return '<span class="badge rounded-pill text-bg-danger">Ditolak</span>';
        default:         return '<span class="badge rounded-pill text-bg-success">Disetujui</span>';
    }
}

// Perankan badge role yang lebih halus / filter nasabah bisa dihapus di sini
function badgeRole($role) {
    switch ($role) {
        case 'admin':    return '<span class="badge rounded-pill text-bg-danger">Admin</span>';
        case 'petugas':  return '<span class="badge rounded-pill text-bg-warning">Petugas / Teller</span>';
        case 'pengepul': return '<span class="badge rounded-pill text-bg-info">Pengepul Mitra</span>';
        default:         return '<span class="badge rounded-pill text-bg-success">Nasabah</span>';
    }
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
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card">
            <div class="stat-icon bg-success"><i class="bi bi-people-fill"></i></div>
            <div class="stat-info">
                <span class="stat-label">Total Pengguna</span>
                <span class="stat-value"><?= number_format($totalUsers) ?></span>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card">
            <div class="stat-icon bg-danger"><i class="bi bi-shield-lock-fill"></i></div>
            <div class="stat-info">
                <span class="stat-label">Admin</span>
                <span class="stat-value"><?= number_format($counts['admin']) ?></span>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card">
            <div class="stat-icon bg-warning"><i class="bi bi-person-gear"></i></div>
            <div class="stat-info">
                <span class="stat-label">Petugas / Teller</span>
                <span class="stat-value"><?= number_format($counts['petugas']) ?></span>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card">
            <div class="stat-icon bg-info"><i class="bi bi-cash-coin"></i></div>
            <div class="stat-info">
                <span class="stat-label">Nasabah & Saldo</span>
                <span class="stat-value"><?= number_format($counts['nasabah']) ?></span>
                <span class="stat-sub">Total saldo: Rp <?= number_format($totalSaldo, 0, ',', '.') ?></span>
            </div>
        </div>
    </div>
</div>

<!-- ======================== TABEL PENGGUNA ======================== -->
<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h6 class="mb-0"><i class="bi bi-people me-2"></i>Daftar Akun Pengguna</h6>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <input type="search" id="searchUser" class="form-control form-control-sm" style="width: 240px;"
                   placeholder="Cari nama / email / no. HP...">
            <select id="filterRole" class="form-select form-select-sm" style="width: auto;">
                <option value="">Semua Role</option>
                <option value="admin">Admin</option>
                <option value="petugas">Petugas / Teller</option>
                <option value="nasabah">Nasabah</option>
                <option value="pengepul">Pengepul Mitra</option>
            </select>
            <button class="btn btn-success btn-sm d-inline-flex align-items-center gap-1"
                    data-bs-toggle="modal" data-bs-target="#modalTambah">
                <i class="bi bi-person-plus-fill"></i> Tambah User
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($users)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox" style="font-size:40px;"></i>
                <p class="mt-2">Belum ada data pengguna.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle" id="tableUsers">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width:50px;">No</th>
                            <th>Nama & Kontak</th>
                            <th>Peran</th>
                            <th>Status</th>
                            <th>Saldo / Poin</th>
                            <th>Alamat</th>
                            <th>Terdaftar</th>
                            <th class="text-center" style="width:120px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $i => $row): ?>
                            <tr data-role="<?= $row['role'] ?>">
                                <td class="text-center"><?= $i + 1 ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar-cell" data-role="<?= $row['role'] ?>">
                                            <?= renderAvatarContent($row['nama'], $row['foto'] ?? '', $baseUrl) ?>
                                        </div>
                                        <div>
                                            <strong><?= htmlspecialchars($row['nama']) ?></strong>
                                            <?php if ($row['id'] == $adminId): ?>
                                                <span class="badge text-bg-secondary ms-1">Anda</span>
                                            <?php endif; ?>
                                            <div class="text-muted small">
                                                <?= htmlspecialchars($row['email']) ?>
                                                <?php if (!empty($row['nomor_hp'])): ?>
                                                    <br><i class="bi bi-telephone-fill"></i> <?= htmlspecialchars($row['nomor_hp']) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?= badgeRole($row['role']) ?>
                                    <?php if (!empty($row['is_super_admin'])): ?>
                                        <br><span class="badge rounded-pill text-bg-dark mt-1"><i class="bi bi-star-fill"></i> Super Admin</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= badgeStatus($row) ?></td>
                                <td>
                                    <?php if ($row['role'] === 'nasabah'): ?>
                                        <span class="fw-bold text-success">Rp <?= number_format($row['saldo_rupiah'] ?? 0, 0, ',', '.') ?></span>
                                        <br>
                                        <span class="badge text-bg-primary"><?= number_format($row['poin'] ?? 0) ?> Poin</span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small" style="max-width:220px;">
                                    <?= htmlspecialchars($row['alamat'] ?? '-') ?>
                                </td>
                                <td class="text-nowrap small text-muted">
                                    <?= date('d/m/Y', strtotime($row['created_at'])) ?>
                                </td>
                                <td class="text-center text-nowrap">
                                     <button type="button" class="btn btn-sm btn-outline-primary" title="Edit"
                                             data-bs-toggle="modal" data-bs-target="#modalEdit"
                                             onclick="isiEdit(
                                                 <?= (int) $row['id'] ?>,
                                                 <?= json_encode($row['nama']) ?>,
                                                 <?= json_encode($row['email']) ?>,
                                                 <?= json_encode($row['nomor_hp'] ?? '') ?>,
                                                 <?= json_encode($row['alamat'] ?? '') ?>,
                                                 <?= json_encode($row['role']) ?>,
                                                 <?= (int) (!empty($row['is_super_admin'])) ?>
                                             )">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <?php if ($row['id'] != $adminId): ?>
                                        <a href="?hapus=<?= (int) $row['id'] ?>" class="btn btn-sm btn-outline-danger" title="Hapus"
                                           onclick="return confirm('Yakin ingin menghapus akun &quot;<?= htmlspecialchars($row['nama']) ?>&quot;?');">
                                            <i class="bi bi-trash"></i>
                                        </a>
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

<!-- ======================== MODAL TAMBAH USER ======================== -->
<div class="modal fade" id="modalTambah" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="kelola_user.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus-fill me-2 text-success"></i>Tambah Pengguna Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="t_nama" class="form-label">Nama Lengkap</label>
                        <input type="text" class="form-control" id="t_nama" name="nama" placeholder="Masukkan nama lengkap" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label for="t_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="t_email" name="email" placeholder="contoh@domain.com" required>
                        </div>
                        <div class="col-sm-6">
                            <label for="t_nomor_hp" class="form-label">No. Telepon</label>
                            <input type="tel" class="form-control" id="t_nomor_hp" name="nomor_hp" placeholder="0812xxxxxxx">
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label for="t_password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="t_password" name="password"
                               placeholder="Minimal 6 karakter" required>
                    </div>
                     <div class="row g-3">
                         <div class="col-sm-6">
                             <label for="t_role" class="form-label">Peran (Role)</label>
                             <select class="form-select" id="t_role" name="role" required>
                                 <option value="nasabah">Nasabah</option>
                                 <option value="petugas">Petugas / Teller</option>
                                 <option value="pengepul">Pengepul Mitra</option>
                                 <?php if ($isSuperAdmin): ?>
                                     <option value="admin">Admin</option>
                                 <?php endif; ?>
                             </select>
                         </div>
                         <div class="col-sm-6">
                             <label for="t_alamat" class="form-label">Alamat</label>
                             <input type="text" class="form-control" id="t_alamat" name="alamat" placeholder="Alamat lengkap">
                         </div>
                     </div>
                     <?php if ($isSuperAdmin): ?>
                     <div class="form-check mt-2" id="t_super_wrap">
                         <input class="form-check-input" type="checkbox" name="is_super_admin" value="1" id="t_is_super">
                         <label class="form-check-label" for="t_is_super">Jadikan Super Admin (hanya berlaku untuk role Admin)</label>
                     </div>
                     <?php endif; ?>
                     <div class="form-text mt-2">
                         <i class="bi bi-info-circle me-1"></i>Nasabah yang baru dibuat otomatis mendapat dompet saldo Rp 0.
                     </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah_user" class="btn btn-success btn-sm">
                        <i class="bi bi-check-lg me-1"></i>Simpan User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======================== MODAL EDIT USER ======================== -->
<div class="modal fade" id="modalEdit" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="kelola_user.php" method="POST">
                <input type="hidden" name="user_id" id="e_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-gear me-2 text-primary"></i>Edit Pengguna</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="e_nama" class="form-label">Nama Lengkap</label>
                        <input type="text" class="form-control" id="e_nama" name="nama" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label for="e_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="e_email" name="email" required>
                        </div>
                        <div class="col-sm-6">
                            <label for="e_nomor_hp" class="form-label">No. Telepon</label>
                            <input type="tel" class="form-control" id="e_nomor_hp" name="nomor_hp">
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label for="e_password" class="form-label">
                            Password <span class="text-muted fw-normal">(kosongkan jika tidak diganti)</span>
                        </label>
                        <input type="password" class="form-control" id="e_password" name="password"
                               placeholder="Minimal 6 karakter">
                    </div>
                     <div class="row g-3">
                         <div class="col-sm-6">
                             <label for="e_role" class="form-label">Peran (Role)</label>
                             <select class="form-select" id="e_role" name="role" required>
                                 <option value="nasabah">Nasabah</option>
                                 <option value="petugas">Petugas / Teller</option>
                                 <option value="pengepul">Pengepul Mitra</option>
                                 <?php if ($isSuperAdmin): ?>
                                     <option value="admin">Admin</option>
                                 <?php endif; ?>
                             </select>
                         </div>
                         <div class="col-sm-6">
                             <label for="e_alamat" class="form-label">Alamat</label>
                             <input type="text" class="form-control" id="e_alamat" name="alamat">
                         </div>
                     </div>
                     <?php if ($isSuperAdmin): ?>
                     <div class="form-check mt-2" id="e_super_wrap">
                         <input class="form-check-input" type="checkbox" name="is_super_admin" value="1" id="e_is_super">
                         <label class="form-check-label" for="e_is_super">Jadikan Super Admin (hanya berlaku untuk role Admin)</label>
                     </div>
                     <?php endif; ?>
                 </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="edit_user" class="btn btn-primary btn-sm">
                        <i class="bi bi-check-lg me-1"></i>Simpan Perubahan
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
        .stat-sub   { font-size: 12px; color: var(--text-soft); }

    .avatar-cell {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 14px;
        color: #fff;
        flex-shrink: 0;
        background: #2e7d32;
    }
    .avatar-cell[data-role="admin"]   { background: #dc3545; }
    .avatar-cell[data-role="petugas"] { background: var(--warning); color: var(--warning-text-emphasis, #664d03); }

    @media (max-width: 767px) {
        .stat-card { padding: 16px; gap: 12px; }
        .stat-icon { width: 44px; height: 44px; font-size: 18px; border-radius: 12px; }
        .stat-label { font-size: 12px; }
        .stat-value { font-size: 17px; }
    }
</style>

<script>
(function () {
    // ====== Isi modal edit ======
    window.isiEdit = function (id, nama, email, nomorHp, alamat, role, isSuper) {
        document.getElementById('e_id').value = id;
        document.getElementById('e_nama').value = nama;
        document.getElementById('e_email').value = email;
        document.getElementById('e_nomor_hp').value = nomorHp;
        document.getElementById('e_password').value = '';
        document.getElementById('e_alamat').value = alamat;
        document.getElementById('e_role').value = role;
        var cb = document.getElementById('e_is_super');
        if (cb) {
            cb.checked = !!isSuper && (role === 'admin');
        }
    };

    // ====== Pencarian & filter role ======
    const searchInput = document.getElementById('searchUser');
    const filterRole  = document.getElementById('filterRole');
    const rows        = document.querySelectorAll('#tableUsers tbody tr');

    function applyFilter() {
        const q = (searchInput.value || '').toLowerCase().trim();
        const r = filterRole.value;

        rows.forEach(function (row) {
            const text = (row.textContent || '').toLowerCase();
            const matchText = q === '' || text.indexOf(q) !== -1;
            const matchRole = r === '' || row.dataset.role === r;
            row.style.display = (matchText && matchRole) ? '' : 'none';
        });
    }

    searchInput.addEventListener('input', applyFilter);
    filterRole.addEventListener('change', applyFilter);

    // ====== Toggle super admin checkbox berdasarkan role ======
    function toggleSuper(cbId, wrapId, roleVal) {
        var cb = document.getElementById(cbId);
        var wrap = document.getElementById(wrapId);
        if (!cb || !wrap) return;
        if (roleVal === 'admin') {
            wrap.style.display = '';
            cb.disabled = false;
        } else {
            wrap.style.display = 'none';
            cb.checked = false;
            cb.disabled = true;
        }
    }
    var tRole = document.getElementById('t_role');
    var eRole = document.getElementById('e_role');
    if (tRole) {
        tRole.addEventListener('change', function () { toggleSuper('t_is_super', 't_super_wrap', this.value); });
        toggleSuper('t_is_super', 't_super_wrap', tRole.value);
    }
    if (eRole) {
        eRole.addEventListener('change', function () { toggleSuper('e_is_super', 'e_super_wrap', this.value); });
    }

    // ====== Reset form modal tambah saat ditutup ======
    document.getElementById('modalTambah').addEventListener('hidden.bs.modal', function () {
        this.querySelector('form').reset();
        if (tRole) toggleSuper('t_is_super', 't_super_wrap', tRole.value);
    });
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>