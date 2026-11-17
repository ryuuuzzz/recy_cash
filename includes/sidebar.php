<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = basename($_SERVER['SCRIPT_FILENAME'], '.php');
$role = $role ?? $_SESSION['role'] ?? null;
$isSuperAdmin = !empty($_SESSION['is_super_admin']);

$menus = [
    'admin' => [
        'Utama' => [
            ['icon' => 'bi-grid-1x2-fill', 'label' => 'Dashboard', 'file' => 'dashboard.php'],
        ],
        'Manajemen' => [
            ['icon' => 'bi-people-fill', 'label' => 'Kelola User', 'file' => 'kelola_user.php'],
            ['icon' => 'bi-patch-check-fill', 'label' => 'Verifikasi Akun', 'file' => 'verifikasi_akun.php', 'super' => true],
            ['icon' => 'bi-truck', 'label' => 'Pengepul', 'file' => 'kelola_pengepul.php'],
            ['icon' => 'bi-tags-fill', 'label' => 'Harga Sampah', 'file' => 'master_harga.php'],
            ['icon' => 'bi-boxes', 'label' => 'Gudang', 'file' => 'gudang.php'],
        ],
        'Keuangan' => [
            ['icon' => 'bi-bar-chart-fill', 'label' => 'Laporan', 'file' => 'laporan.php'],
            ['icon' => 'bi-wallet2', 'label' => 'Verifikasi Penarikan', 'file' => 'penarikan.php'],
        ],
        'Sistem' => [
            ['icon' => 'bi-journal-text', 'label' => 'Audit Log', 'file' => 'audit_log.php'],
        ],
    ],
    'petugas' => [
        'Utama' => [
            ['icon' => 'bi-grid-1x2-fill', 'label' => 'Dashboard', 'file' => 'dashboard.php'],
        ],
        'Operasional' => [
            ['icon' => 'bi-box-arrow-in-right', 'label' => 'Input Setor', 'file' => 'input_setor.php'],
            ['icon' => 'bi-calendar-check-fill', 'label' => 'Jemputan', 'file' => 'kelola_jemput.php'],
            ['icon' => 'bi-geo-alt-fill', 'label' => 'Outlet Saya', 'file' => 'outlet.php'],
            ['icon' => 'bi-receipt', 'label' => 'Cetak Nota', 'file' => 'cetak.nota.php'],
            ['icon' => 'bi-wallet2', 'label' => 'Verifikasi Penarikan', 'file' => 'penarikan.php'],
        ],
    ],
    'nasabah' => [
        'Utama' => [
            ['icon' => 'bi-grid-1x2-fill', 'label' => 'Dashboard', 'file' => 'dashboard.php'],
        ],
        'Aktivitas' => [
            ['icon' => 'bi-tag-fill', 'label' => 'Harga Sampah', 'file' => 'harga.php'],
            ['icon' => 'bi-geo-alt-fill', 'label' => 'Cari Teller', 'file' => 'cari_teller.php'],
            ['icon' => 'bi-calendar-plus-fill', 'label' => 'Request Jemput', 'file' => 'request_jemput.php'],
            ['icon' => 'bi-clock-history', 'label' => 'Riwayat', 'file' => 'riwayat.php'],
            ['icon' => 'bi-wallet2', 'label' => 'Penarikan', 'file' => 'penarikan.php'],
        ],
    ],
    'pengepul' => [
        'Utama' => [
            ['icon' => 'bi-grid-1x2-fill', 'label' => 'Dashboard', 'file' => 'dashboard.php'],
        ],
    ],
];

$currentMenus = $menus[$role] ?? [];
$roleLabels = [
    'admin'    => 'Administrator',
    'petugas'  => 'Petugas Teller',
    'nasabah'  => 'Nasabah',
    'pengepul' => 'Pengepul Mitra',
];
$roleLabel = $roleLabels[$role] ?? ucfirst((string) $role);
$sidebarNama = $nama ?? $_SESSION['nama'] ?? 'Pengguna';
$sidebarFotoUrl = $fotoUrl ?? '';

$_docRoot  = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']));
$_appPath  = str_replace('\\', '/', dirname(__DIR__));
$baseUrl   = $baseUrl ?? str_replace((string) $_docRoot, '', (string) $_appPath);
$baseUrl   = rtrim($baseUrl, '/');
$folderMap = ['admin' => 'Admin', 'petugas' => 'Teller', 'nasabah' => 'Nasabah', 'pengepul' => 'Pengepul'];
$viewBase  = $baseUrl . '/views/' . ($folderMap[$role] ?? '');

if (!function_exists('isMenuActive')) {
    function isMenuActive(string $file, string $currentPage): bool
    {
        return $currentPage === pathinfo($file, PATHINFO_FILENAME);
    }
}
?>

<aside class="app-sidebar" id="sidebar" aria-label="Navigasi utama RecyCash">
    <button class="sidebar-close" type="button" data-sidebar-close aria-label="Tutup menu navigasi">
        <i class="bi bi-x-lg"></i>
    </button>

    <div class="sidebar-brand">
        <div class="sidebar-brand-icon">
            <i class="bi bi-recycle"></i>
            <span class="brand-dot">C</span>
        </div>
        <div class="sidebar-brand-text">
            <h2>RecyCash</h2>
            <p>Daur Ulang Jadi Cuan</p>
        </div>
    </div>

    <div class="sidebar-role">
        <span class="role-dot"></span>
        Masuk sebagai
        <strong><?= htmlspecialchars($roleLabel) ?></strong>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($currentMenus as $groupLabel => $items): ?>
            <?php
            $visibleItems = array_filter($items, static function (array $item) use ($isSuperAdmin): bool {
                return empty($item['super']) || $isSuperAdmin;
            });
            if (empty($visibleItems)) {
                continue;
            }
            ?>
            <div class="sidebar-section">
                <span class="sidebar-section-title"><?= htmlspecialchars($groupLabel) ?></span>
                <?php foreach ($visibleItems as $item): ?>
                    <?php $active = isMenuActive($item['file'], $currentPage); ?>
                    <a href="<?= $viewBase ?>/<?= $item['file'] ?>"
                       class="sidebar-link <?= $active ? 'active' : '' ?>"
                       <?= $active ? 'aria-current="page"' : '' ?>>
                        <span class="sidebar-link-icon"><i class="bi <?= $item['icon'] ?>"></i></span>
                        <span><?= htmlspecialchars($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <div class="sidebar-section">
            <span class="sidebar-section-title">Akun</span>
            <a href="<?= $baseUrl ?>/views/profile/edit_profil.php"
               class="sidebar-link <?= $currentPage === 'edit_profil' ? 'active' : '' ?>"
               <?= $currentPage === 'edit_profil' ? 'aria-current="page"' : '' ?>>
                <span class="sidebar-link-icon"><i class="bi bi-person-gear"></i></span>
                <span>Edit Profil</span>
            </a>
        </div>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="avatar-inline">
                <?php if ($sidebarFotoUrl !== ''): ?>
                    <img src="<?= htmlspecialchars($sidebarFotoUrl) ?>" alt="Foto profil <?= htmlspecialchars($sidebarNama) ?>">
                <?php else: ?>
                    <?= htmlspecialchars(strtoupper(mb_substr($sidebarNama, 0, 1))) ?>
                <?php endif; ?>
            </div>
            <div class="sidebar-user-info">
                <strong><?= htmlspecialchars($sidebarNama) ?></strong>
                <span><?= htmlspecialchars($roleLabel) ?></span>
            </div>
        </div>

        <a href="<?= $baseUrl ?>/auth/logout.php"
           class="sidebar-link sidebar-logout"
           onclick="return confirm('Apakah Anda yakin ingin keluar?');">
            <span class="sidebar-link-icon"><i class="bi bi-box-arrow-left"></i></span>
            <span>Keluar</span>
        </a>
    </div>
</aside>

<div class="sidebar-overlay" id="sidebarOverlay" data-sidebar-close></div>
