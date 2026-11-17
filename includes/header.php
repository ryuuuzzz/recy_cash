<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/avatar_helper.php';

$role = $_SESSION['role'] ?? null;
$nama = $_SESSION['nama'] ?? 'User';
$foto = basename((string) ($_SESSION['foto'] ?? ''));

// URL dasar aplikasi yang valid untuk browser (hindari path Windows C:\...)
$_docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']));
$_appPath = str_replace('\\', '/', dirname(__DIR__));
$baseUrl  = str_replace((string)$_docRoot, '', (string)$_appPath);
// Biarkan kosong jika aplikasi berada tepat di document root,
// agar hasil penggabungan menjadi '/auth/login.php' bukan '//auth/login.php'
$baseUrl  = rtrim($baseUrl, '/');

$fotoUrl = '';
if ($foto !== '' && !in_array($foto, ['default.svg', 'default.png', 'default.jpg', 'default.jpeg'], true)) {
    $fotoPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'profile' . DIRECTORY_SEPARATOR . $foto;
    if (is_file($fotoPath)) {
        $fotoUrl = $baseUrl . '/assets/img/profile/' . rawurlencode($foto);
    }
}

if ($role === null) {
    header('Location: ' . $baseUrl . '/auth/login.php');
    exit;
}

$allowedRole = null;
$currentDir = basename(dirname($_SERVER['SCRIPT_FILENAME']));

switch ($currentDir) {
    case 'Admin':
        $allowedRole = 'admin';
        break;
    case 'Teller':
        $allowedRole = 'petugas';
        break;
    case 'Nasabah':
        $allowedRole = 'nasabah';
        break;
    case 'Pengepul':
        $allowedRole = 'pengepul';
        break;
    default:
        $allowedRole = null;
}

if ($allowedRole !== null && $role !== $allowedRole) {
    $_SESSION['error'] = "Anda tidak memiliki akses ke halaman tersebut.";
    header('Location: ' . $baseUrl . '/auth/login.php');
    exit;
}

$currentPage = basename($_SERVER['SCRIPT_FILENAME'], '.php');

$roleLabels = [
    'admin'    => 'Administrator',
    'petugas'  => 'Petugas Teller',
    'nasabah'  => 'Nasabah',
    'pengepul' => 'Pengepul Mitra',
];

$roleLabel = $roleLabels[$role] ?? ucfirst((string) $role);
$pageTitle = ucwords(str_replace(['_', '.'], ' ', $currentPage));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RecyCash - <?= ucfirst(str_replace('_', ' ', $currentPage)) ?></title>
    <script>
        // Terapkan tema tersimpan sebelum render agar tidak "flash" terang
        (function () {
            try {
                var saved = localStorage.getItem('recycash-theme');
                var dark = saved === 'dark';
                if (dark) {
                    document.documentElement.setAttribute('data-theme', 'dark');
                    document.documentElement.setAttribute('data-bs-theme', 'dark');
                }
            } catch (e) {}
        })();
    </script>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 48 48'%3E%3Crect width='48' height='48' rx='14' fill='%2314532D'/%3E%3Cpath d='M32 12c-8 1-13 6-13 14 7 0 12-5 13-14Z' fill='%23fff'/%3E%3Ccircle cx='36' cy='36' r='8' fill='%23F2C55C'/%3E%3C/svg%3E">
    <meta name="theme-color" content="#14532D">
    <link rel="manifest" href="<?= $baseUrl ?>/manifest.webmanifest">
    <link rel="apple-touch-icon" href="<?= $baseUrl ?>/assets/img/icon-192.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 260px;
            --primary: #2e7d32;
            --primary-dark: #1b5e20;
            --bg-body: #f1f5f9;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: var(--bg-body);
            min-height: 100vh;
        }

        /* ======================== TOMBOL MODE GELAP/TERANG ======================== */
        .btn-theme {
            width: 38px;
            height: 38px;
            border: none;
            border-radius: 10px;
            background: var(--surface-muted);
            color: var(--text-strong);
            font-size: 17px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            cursor: pointer;
            transition: background 0.2s, color 0.2s;
        }

        .btn-theme:hover {
            background: #e2e8f0;
        }

        .btn-theme .theme-icon-light {
            display: none;
        }

        /* ======================== MODE GELAP ======================== */
        html[data-theme="dark"] {
            --bg-body: #0a130e;
            color-scheme: dark;
        }

        html[data-theme="dark"] body {
            background: var(--bg-body);
            color: var(--text-body);
        }

        html[data-theme="dark"] .top-navbar {
            background: #10231a;
            border-bottom-color: #2b4635;
        }

        html[data-theme="dark"] .btn-menu-toggle {
            color: var(--text-muted);
        }

        html[data-theme="dark"] .btn-menu-toggle:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        html[data-theme="dark"] .top-navbar .page-title,
        html[data-theme="dark"] .top-navbar .user-name {
            color: var(--text-strong);
        }

        html[data-theme="dark"] .top-navbar .user-role {
            color: var(--text-muted);
        }

        html[data-theme="dark"] .main-footer {
            color: var(--text-muted);
            border-top-color: #2b4635;
        }

        /* Kartu, tabel & modal Bootstrap ikut berwarna tema hijau gelap agar kontras */
        html[data-theme="dark"] .card {
            --bs-card-bg: #12291c;
            border-color: #2b4635;
        }

        html[data-theme="dark"] .card-header {
            border-bottom-color: #2b4635;
        }

        html[data-theme="dark"] .modal-content {
            background: #12291c;
        }

        html[data-theme="dark"] .btn-theme {
            background: rgba(255, 255, 255, 0.1);
            color: var(--lime-300);
            border: 1px solid rgba(255, 255, 255, 0.12);
        }

        html[data-theme="dark"] .btn-theme:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        html[data-theme="dark"] .btn-theme .theme-icon-dark {
            display: none;
        }

        html[data-theme="dark"] .btn-theme .theme-icon-light {
            display: inline;
        }

        /* Koreksi teks yang di-hardcode gelap pada tiap view */
        html[data-theme="dark"] .stat-label,
        html[data-theme="dark"] .price-label,
        html[data-theme="dark"] .unit-hint,
        html[data-theme="dark"] .harga-satuan,
        html[data-theme="dark"] .nota-meta .label {
            color: var(--text-muted);
        }

        html[data-theme="dark"] .stat-value,
        html[data-theme="dark"] .produk-nama,
        html[data-theme="dark"] .info-label {
            color: var(--text-strong);
        }

        html[data-theme="dark"] .stat-sub {
            color: var(--text-muted);
        }

        .avatar-inline {
            border-radius: 50%;
            overflow: hidden;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--mint-50);
            color: var(--eco-700);
            font-weight: 700;
            font-size: 14px;
            flex-shrink: 0;
            vertical-align: middle;
        }

        .avatar-inline img,
        .avatar-cell img,
        .nasabah-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .avatar-cell,
        .nasabah-avatar {
            overflow: hidden;
        }

        /* ======================== NAVBAR ======================== */
        .top-navbar {
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            right: 0;
            height: 64px;
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 28px;
            z-index: 1000;
            transition: left 0.3s ease;
        }

        .top-navbar .navbar-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-menu-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--text-strong);
            font-size: 26px;
            line-height: 1;
            padding: 2px 8px;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-menu-toggle:hover {
            background: #f1f5f9;
        }

        .top-navbar .page-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-strong);
        }

        .top-navbar .user-area {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .top-navbar .user-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: var(--primary);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
            overflow: hidden;
            flex-shrink: 0;
        }

        .top-navbar .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .top-navbar .user-info {
            line-height: 1.3;
        }

        .top-navbar .user-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-strong);
        }

        .top-navbar .user-role {
            font-size: 11px;
            color: var(--text-muted);
            text-transform: capitalize;
        }

        .btn-logout {
            background: none;
            border: 1.5px solid #ef4444;
            color: var(--danger);
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-logout:hover {
            background: #ef4444;
            color: #fff;
        }

        /* ======================== CONTENT ======================== */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 84px 28px 28px;
            transition: margin-left 0.3s ease;
        }

        .main-footer {
            text-align: center;
            padding: 20px;
            font-size: 13px;
            color: var(--text-muted);
            border-top: 1px solid var(--border);
            margin-top: 40px;
        }

        /* ======================== RESPONSIVE ======================== */
        /* Tablet & Mobile: hamburger tampil, sidebar disembunyikan,
           konten memakai 1 kolom penuh */
        @media (max-width: 991px) {
            .top-navbar {
                left: 0;
                padding: 0 16px;
            }

            .btn-menu-toggle {
                display: inline-flex;
            }

            .main-content {
                margin-left: 0;
                padding: 84px 14px 14px;
            }

            /* Sembunyikan detail user agar navbar tidak sempit */
            .user-info {
                display: none;
            }

            .btn-logout {
                padding: 6px 10px;
            }

            .btn-logout .logout-text {
                display: none;
            }
        }

        /* Handphone kecil: teks & tabel menyesuaikan layar */
        @media (max-width: 767px) {
            .top-navbar {
                height: 56px;
            }

            .top-navbar .page-title {
                font-size: 15px;
            }

            .btn-logout {
                padding: 5px 8px;
                font-size: 12px;
            }

            .main-content {
                padding: 72px 12px 12px;
            }
        }

        /* Penyesuaian ukuran teks & tabel pada layar kecil secara global */
        @media (max-width: 767px) {
            body {
                font-size: 14px;
            }

            .table {
                font-size: 12.5px;
            }

            .table > :not(caption) > * > * {
                padding: 0.5rem 0.55rem;
            }

            .card-header {
                padding: 12px 14px;
            }

            .card-header h6 {
                font-size: 14px;
            }

            .main-footer {
                font-size: 12px;
                padding: 14px;
            }
        }
    </style>
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/app.css">
    <noscript>
        <style>[data-aos] { opacity: 1 !important; transform: none !important; }</style>
    </noscript>
</head>
<body class="app-shell">

<a class="skip-link" href="#mainContent">Lewati ke konten utama</a>

<!-- TOP NAVBAR -->
<nav class="top-navbar">
    <div class="navbar-left">
        <button class="btn-menu-toggle" id="btnMenuToggle" type="button" data-sidebar-toggle aria-label="Buka menu navigasi" aria-controls="sidebar" aria-expanded="false">
            <i class="bi bi-list"></i>
        </button>
        <div class="page-context">
            <span class="page-eyebrow"><?= htmlspecialchars($roleLabel) ?></span>
            <div class="page-title"><?= htmlspecialchars($pageTitle) ?></div>
        </div>
    </div>
    <div class="navbar-actions">
        <span class="eco-status"><span class="status-dot"></span> Eco Mode</span>
        <button class="btn-theme" id="themeToggle" type="button" aria-label="Ganti mode gelap / terang" title="Ganti mode gelap / terang">
            <i class="bi bi-moon-stars-fill theme-icon-dark"></i>
            <i class="bi bi-sun-fill theme-icon-light"></i>
        </button>
        <div class="user-chip">
            <div class="user-avatar">
                <?php if ($fotoUrl !== ''): ?>
                    <img src="<?= htmlspecialchars($fotoUrl) ?>" alt="Foto profil <?= htmlspecialchars($nama) ?>">
                <?php else: ?>
                    <?= htmlspecialchars(strtoupper(mb_substr($nama, 0, 1))) ?>
                <?php endif; ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($nama) ?></div>
                <div class="user-role"><?= htmlspecialchars($roleLabel) ?></div>
            </div>
        </div>
        <a href="<?= $baseUrl ?>/auth/logout.php"
           class="btn-logout"
           onclick="return confirm('Apakah Anda yakin ingin keluar?');">
            <i class="bi bi-box-arrow-right"></i> <span class="logout-text">Keluar</span>
        </a>
    </div>
</nav>

<!-- WRAPPER CONTENT -->
<div class="main-content" id="mainContent">
