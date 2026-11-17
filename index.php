<?php
session_start();

if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: views/Admin/dashboard.php');
        exit;
    } elseif ($_SESSION['role'] === 'petugas') {
        header('Location: views/Teller/dashboard.php');
        exit;
    } elseif ($_SESSION['role'] === 'nasabah') {
        header('Location: views/Nasabah/dashboard.php');
        exit;
    }

    header('Location: auth/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Bank Sampah Digital — platform bank sampah yang mudah, transparan, dan menguntungkan. Ubah sampah jadi tabungan, mulai dari rumah.">
    <meta name="theme-color" content="#0d3823">
    <title>RecyCash — Ubah Sampah Jadi Tabungan</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 48 48'%3E%3Crect width='48' height='48' rx='14' fill='%2314532D'/%3E%3Cpath d='M32 12c-8 1-13 6-13 14 7 0 12-5 13-14Z' fill='%23fff'/%3E%3Ccircle cx='36' cy='36' r='8' fill='%23F2C55C'/%3E%3C/svg%3E">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/landing.css">
    <script>
        // Terapkan tema tersimpan sebelum render agar tidak "flash" terang
        (function () {
            try {
                var saved = localStorage.getItem('recycash-theme');
                var dark = saved ? saved === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (dark) document.documentElement.setAttribute('data-theme', 'dark');
            } catch (e) {}
        })();
        document.documentElement.classList.add('js');
    </script>
</head>
<body>
    <a class="skip-link" href="#main">Lewati ke konten utama</a>

    <header class="site-header" id="siteHeader">
        <div class="container nav-wrap">
            <a class="brand" href="#beranda" aria-label="Bank Sampah Digital — Beranda">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 48 48" fill="none">
                        <path d="M31.8 12.2c-7.7.5-12.5 4.8-12.8 12.9 7.4.1 12-4.1 12.8-12.9Z" fill="#fff"/>
                        <path d="M20.3 24.2c1.7-4.4 4.8-7.3 9-9.1" stroke="#2C8A56" stroke-width="1.5" stroke-linecap="round"/>
                        <path d="M13.8 28.2a9.2 9.2 0 0 0 14.2 7.6" stroke="#fff" stroke-width="2.4" stroke-linecap="round"/>
                        <path d="m25.4 33.6 2.8 2.2-2.8 2.2" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="37" cy="37" r="8.3" fill="#F4C95D" stroke="#fff" stroke-width="2"/>
                        <path d="m33.8 39.3 2.3-2.4 1.7 1.6 2.2-2.9" stroke="#124B2E" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M39.1 35.3h1.9v1.9" stroke="#124B2E" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
                <span class="brand-copy">
                    <strong>RECYCASH</strong>
                    <span> BANK SAMPAH DIGITAL</span>
                </span>
            </a>

            <nav class="main-nav" id="mainNav" aria-label="Navigasi utama">
                <a href="#beranda" class="is-active">Beranda</a>
                <a href="#fitur">Fitur</a>
                <a href="#layanan">Layanan</a>
                <a href="#tentang">Tentang Kami</a>
                <a href="#kontak">Kontak</a>
            </nav>

            <div class="nav-actions">
                <button class="theme-toggle" id="themeToggle" type="button" aria-label="Ganti ke mode gelap" title="Mode gelap / terang">
                    <svg class="icon-moon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M20.5 14.3A8.5 8.5 0 0 1 9.7 3.5a8.5 8.5 0 1 0 10.8 10.8Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                        <path d="M16.6 4.6c.3 1.2 1.2 2.1 2.4 2.4-1.2.3-2.1 1.2-2.4 2.4-.3-1.2-1.2-2.1-2.4-2.4 1.2-.3 2.1-1.2 2.4-2.4Z" fill="currentColor"/>
                    </svg>
                    <svg class="icon-sun" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle cx="12" cy="12" r="4.2" stroke="currentColor" stroke-width="1.8"/>
                        <path d="M12 2.6v2.2M12 19.2v2.2M2.6 12h2.2M19.2 12h2.2M5.2 5.2l1.6 1.6M17.2 17.2l1.6 1.6M18.8 5.2l-1.6 1.6M6.8 17.2l-1.6 1.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                </button>
                <a class="btn-nav" href="auth/login.php">
                    <span class="nav-btn-full">Login / Daftar</span>
                    <span class="nav-btn-short">Masuk</span>
                </a>
                <button class="menu-toggle" id="menuToggle" type="button" aria-label="Buka menu navigasi" aria-controls="mainNav" aria-expanded="false">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>
        </div>
    </header>

    <main id="main">
        <section class="hero" id="beranda" aria-labelledby="hero-title">
            <div class="container hero-grid">
                <div class="hero-copy">
                    <span class="eyebrow hero-enter">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M20 4c-8.5.6-13 5.1-13 13 7.4.1 12-4.3 13-13Z" fill="currentColor"/>
                            <path d="M6 20c1.2-4.2 3.6-7.4 7.2-9.8" stroke="#fff" stroke-width="1.6" stroke-linecap="round"/>
                        </svg>
                        Sampahmu punya nilai
                    </span>
                    <h1 id="hero-title" class="hero-enter">Ubah Sampah<br>Jadi <span class="highlight">Tabungan,</span><br>Mulai Dari Rumah</h1>
                    <p class="hero-subtitle hero-enter">Platform Digital Bank Sampah yang Mudah, Transparan, dan Menguntungkan untuk Semua.</p>

                    <div class="hero-actions hero-enter">
                        <a class="btn btn-primary" href="auth/register.php">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M20 4c-8.5.6-13 5.1-13 13 7.4.1 12-4.3 13-13Z" fill="currentColor"/>
                                <path d="M6 20c1.2-4.2 3.6-7.4 7.2-9.8" stroke="#12502f" stroke-width="1.6" stroke-linecap="round"/>
                            </svg>
                            Gabung Sekarang
                        </a>
                        <a class="hero-link" href="#layanan">
                            <span class="play">
                                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M12 5v14m0 0 5-5m-5 5-5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                            Lihat cara kerja
                        </a>
                    </div>

                    <ul class="hero-benefits hero-enter">
                        <li>
                            <span class="mini-check">
                                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="m6 12 4 4 8-9" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                            Gratis bergabung
                        </li>
                        <li>
                            <span class="mini-check">
                                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="m6 12 4 4 8-9" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                            Saldo aman &amp; transparan
                        </li>
                    </ul>
                </div>

                <div class="hero-visual hero-enter">
                    <div class="visual-frame">
                        <svg class="hero-art" viewBox="0 0 620 520" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-labelledby="heroArtTitle heroArtDesc">
                            <title id="heroArtTitle">Keluarga memilah sampah menjadi saldo digital</title>
                            <desc id="heroArtDesc">Ilustrasi keluarga bahagia memilah botol plastik dan kardus ke tempat sampah berwarna, terhubung dengan ponsel yang menampilkan saldo dompet digital.</desc>
                            <defs>
                                <linearGradient id="heroWalletGradient" x1="436" y1="167" x2="570" y2="246" gradientUnits="userSpaceOnUse">
                                    <stop stop-color="#30B371"/>
                                    <stop offset="1" stop-color="#11613C"/>
                                </linearGradient>
                                <linearGradient id="heroPhoneBody" x1="418" y1="93" x2="588" y2="465" gradientUnits="userSpaceOnUse">
                                    <stop stop-color="#1D533A"/>
                                    <stop offset="1" stop-color="#0B2C1D"/>
                                </linearGradient>
                                <clipPath id="heroPhoneScreenClip">
                                    <rect x="426" y="102" width="154" height="354" rx="24"/>
                                </clipPath>
                            </defs>

                            <path d="M59 261C59 137 165 43 314 43C463 43 565 137 563 267C561 397 465 478 311 478C158 478 59 386 59 261Z" fill="#E7F7EA"/>
                            <path d="M86 261C86 156 188 70 313 70C439 70 536 157 536 268C536 379 447 450 311 450C177 450 86 367 86 261Z" stroke="#FFFFFF" stroke-opacity=".7" stroke-width="1.5"/>
                            <circle cx="520" cy="92" r="55" fill="#FFF0BB"/>
                            <circle cx="520" cy="92" r="38" fill="#FFF7D8" fill-opacity=".72"/>
                            <ellipse cx="315" cy="451" rx="263" ry="33" fill="#C9E8D2" fill-opacity=".86"/>
                            <path d="M83 444c59-27 138-38 232-32 98 6 181 16 229 36" stroke="#FFFFFF" stroke-opacity=".48" stroke-width="2"/>

                            <g opacity=".78">
                                <path d="M73 170c13-31 37-40 61-30-9 28-31 41-61 30Z" fill="#95DBA7"/>
                                <path d="M89 155c12-11 25-17 38-19" stroke="#DDF5E3" stroke-width="1.5" stroke-linecap="round"/>
                                <path d="M42 207c10-20 27-27 43-22-5 18-19 28-43 22Z" fill="#BCEAC8"/>
                            </g>

                            <g>
                                <path d="M48 450c-1-20 4-34 17-48" stroke="#2F8450" stroke-width="3" stroke-linecap="round"/>
                                <path d="M60 421c-12-2-18-9-18-18 11-1 17 6 18 18Z" fill="#59BA7B"/>
                                <path d="M58 431c-2-13 4-22 14-27 4 12-1 22-14 27Z" fill="#3FA668"/>
                                <path d="M61 424c11-1 18-7 20-17-11-1-18 6-20 17Z" fill="#7DD394"/>
                                <path d="M53 420h31l-5 27c-.4 3-2.8 5-5.8 5h-8.4c-3 0-5.4-2-5.8-5l-6-27Z" fill="#DCAB73"/>
                                <rect x="50" y="412" width="37" height="9" rx="4" fill="#C9945B"/>
                            </g>

                            <path d="M385 330c22-18 13-38 38-45" stroke="#4BB173" stroke-width="2.5" stroke-dasharray="4 8" stroke-linecap="round"/>
                            <circle cx="406" cy="302" r="3.2" fill="#4BB173"/>
                            <circle cx="399" cy="180" r="17" fill="#F4C95D" stroke="#FFFFFF" stroke-width="2"/>
                            <text x="399" y="183.5" text-anchor="middle" fill="#654C0D" font-size="7.5" font-weight="800">Rp</text>
                            <path d="m383 155 2.8-8.5 2.8 8.5-8.6-2.7 8.6-2.7-2.8 8.4Z" fill="#F4C95D"/>

                            <g>
                                <path d="M107 276c6 0 10 5 10 11v75h-17v-72c0-8 2-14 7-14Z" fill="#244438"/>
                                <path d="M142 277c6 0 10 5 10 11v74h-17v-70c0-8 2-15 7-15Z" fill="#1E392E"/>
                                <path d="M90 346c0-4 4-7 8-7h14c4 0 7 3 7 7v10H90v-10Z" fill="#D0A06B"/>
                                <path d="M139 346c0-4 4-7 8-7h13c4 0 7 3 7 7v10h-28v-10Z" fill="#C59460"/>
                                <path d="M89 201c0-21 16-37 37-37h16c21 0 37 16 37 37v75c0 14-11 25-25 25h-40c-14 0-25-11-25-25v-75Z" fill="#F6E8D1"/>
                                <path d="M111 193h35c7 0 12 5 12 12v55c0 12-9 21-21 21h-17c-12 0-21-9-21-21v-55c0-7 5-12 12-12Z" fill="#2E7C55"/>
                                <path d="m108 196-11 25M149 196l11 25" stroke="#2E7C55" stroke-width="8" stroke-linecap="round"/>
                                <rect x="124" y="218" width="22" height="17" rx="4" fill="#256B48" fill-opacity=".55"/>
                                <path d="M94 219c-12 25-10 44 1 55" stroke="#F6E8D1" stroke-width="19" stroke-linecap="round"/>
                                <path d="M159 219c13 17 15 35 8 49" stroke="#F6E8D1" stroke-width="19" stroke-linecap="round"/>
                                <rect x="121" y="172" width="19" height="20" rx="8" fill="#E3B38B"/>
                                <circle cx="130" cy="146" r="31" fill="#F1C6A0"/>
                                <circle cx="100" cy="149" r="6" fill="#E9B991"/>
                                <circle cx="160" cy="149" r="6" fill="#E9B991"/>
                                <path d="M97 148c-1-27 13-43 34-43 22 0 35 16 34 42-7-14-18-21-34-21-16 0-27 7-34 22Z" fill="#263B31"/>
                                <path d="M105 128c8-10 19-14 31-12" stroke="#3C5347" stroke-width="3" stroke-linecap="round" opacity=".7"/>
                                <circle cx="119" cy="148" r="2" fill="#24352C"/>
                                <circle cx="141" cy="148" r="2" fill="#24352C"/>
                                <path d="M123 159c4 5 11 5 15 0" stroke="#9C5A45" stroke-width="2.2" stroke-linecap="round"/>
                                <path d="M114 141c3-2 6-2 9-1M139 140c3-1 6-1 9 1" stroke="#5A3E31" stroke-width="1.8" stroke-linecap="round"/>
                                <path d="M158 271c7-11 15-24 17-37" stroke="#F1C6A0" stroke-width="11" stroke-linecap="round"/>
                                <g transform="rotate(22 174 213)">
                                    <rect x="164" y="184" width="21" height="53" rx="8" fill="#BEE8F4" fill-opacity=".92" stroke="#73B8D0" stroke-width="1.5"/>
                                    <rect x="170" y="175" width="9" height="12" rx="3" fill="#2C88AA"/>
                                    <rect x="168" y="201" width="13" height="18" rx="2" fill="#FDF6E5" fill-opacity=".9"/>
                                    <path d="M169 209h11" stroke="#88C5D8" stroke-width="1.3"/>
                                </g>
                                <circle cx="174" cy="235" r="8" fill="#F1C6A0"/>
                            </g>

                            <g>
                                <path d="M217 145c0-28 16-45 39-45 23 0 40 18 40 45v62c-9 6-19 6-27-2-10 9-22 9-33 1-6 5-13 6-19 3v-64Z" fill="#3A2A23"/>
                                <path d="M220 283h76l-4 66h-68l-4-66Z" fill="#E9D8BA"/>
                                <path d="M216 200c0-19 15-34 34-34h12c19 0 34 15 34 34v73c0 14-11 25-25 25h-30c-14 0-25-11-25-25v-73Z" fill="#389D68"/>
                                <path d="M220 213c-14 24-17 48-8 65M292 213c14 24 17 48 8 65" stroke="#389D68" stroke-width="17" stroke-linecap="round"/>
                                <rect x="247" y="159" width="18" height="19" rx="8" fill="#DEAD84"/>
                                <circle cx="256" cy="135" r="30" fill="#EEC199"/>
                                <circle cx="226" cy="139" r="6" fill="#E4B18A"/>
                                <circle cx="286" cy="139" r="6" fill="#E4B18A"/>
                                <path d="M225 139c-2-26 13-44 31-44 20 0 34 17 32 43-4-11-12-18-23-21-12 9-25 15-40 17Z" fill="#3A2A23"/>
                                <circle cx="283" cy="106" r="12" fill="#3A2A23"/>
                                <circle cx="245" cy="138" r="2" fill="#24352C"/>
                                <circle cx="267" cy="138" r="2" fill="#24352C"/>
                                <path d="M249 149c4 5 10 5 14 0" stroke="#9C5A45" stroke-width="2.2" stroke-linecap="round"/>
                                <path d="M239 131c3-2 6-2 9-1M264 130c3-1 6-1 9 1" stroke="#5A3E31" stroke-width="1.8" stroke-linecap="round"/>
                                <path d="M227 256c12 8 17 19 17 28M285 256c-12 8-17 19-17 28" stroke="#EEC199" stroke-width="10" stroke-linecap="round"/>
                                <g transform="rotate(-2 283 270)">
                                    <rect x="239" y="238" width="89" height="60" rx="5" fill="#D8A66B"/>
                                    <path d="M239 248h89M283 238v60" stroke="#C18C52" stroke-width="2"/>
                                    <path d="m260 238 13-12 13 12" fill="#D8A66B" stroke="#C18C52" stroke-width="2" stroke-linejoin="round"/>
                                    <circle cx="283" cy="272" r="12" fill="#F8FBF7" fill-opacity=".94"/>
                                    <path d="M278 272h10M283 267v10" stroke="#2F9360" stroke-width="2" stroke-linecap="round"/>
                                </g>
                                <circle cx="239" cy="282" r="9" fill="#EEC199"/>
                                <circle cx="327" cy="282" r="9" fill="#EEC199"/>
                            </g>

                            <g>
                                <path d="M338 310c1 0 6 4 6 9v33h-12v-33c0-5 2-9 6-9Z" fill="#E5B58D"/>
                                <path d="M366 310c4 0 6 4 6 9v33h-12v-33c0-5 2-9 6-9Z" fill="#E5B58D"/>
                                <path d="M328 292h53l-4 28h-18l-3-10-4 10h-19l-5-28Z" fill="#5689AC"/>
                                <path d="M329 246c0-16 11-28 27-28 15 0 26 12 26 28v49c0 9-7 16-16 16h-21c-9 0-16-7-16-16v-49Z" fill="#F2C458"/>
                                <path d="M333 257c-7 13-8 24-4 34M379 256c8 12 9 22 6 32" stroke="#F2C458" stroke-width="12" stroke-linecap="round"/>
                                <rect x="348" y="208" width="15" height="16" rx="6" fill="#DEAD84"/>
                                <circle cx="356" cy="205" r="26" fill="#F0C39B"/>
                                <circle cx="331" cy="208" r="5" fill="#E5B58D"/>
                                <circle cx="381" cy="208" r="5" fill="#E5B58D"/>
                                <path d="M330 205c-1-22 11-35 27-35 17 0 27 13 26 34-7-10-16-15-27-15-12 0-21 6-26 16Z" fill="#2C3B30"/>
                                <circle cx="347" cy="207" r="1.8" fill="#24352C"/>
                                <circle cx="365" cy="207" r="1.8" fill="#24352C"/>
                                <path d="M350 216c3 4 8 4 11 0" stroke="#9C5A45" stroke-width="1.9" stroke-linecap="round"/>
                                <g transform="rotate(-18 391 252)">
                                    <rect x="384" y="223" width="16" height="46" rx="6" fill="#C3EAF6" stroke="#76BBD2" stroke-width="1.3"/>
                                    <rect x="388" y="216" width="7" height="10" rx="2" fill="#2C88AA"/>
                                    <rect x="386" y="239" width="12" height="15" rx="2" fill="#FFF8E7"/>
                                </g>
                                <circle cx="391" cy="270" r="7" fill="#F0C39B"/>
                            </g>

                            <g>
                                <ellipse cx="139" cy="459" rx="54" ry="8" fill="#144328" opacity=".12"/>
                                <path d="M101 343h76l-6 102c-.5 8-7 14-15 14h-34c-8 0-14.5-6-15-14l-6-102Z" fill="#2F9D62"/>
                                <path d="M165 343h12l-6 102c-.5 8-7 14-15 14h-2c7-1 12-7 12-14l-1-102Z" fill="#288956" fill-opacity=".56"/>
                                <rect x="92" y="326" width="94" height="25" rx="12" fill="#1E7C4D"/>
                                <ellipse cx="139" cy="326" rx="47" ry="13" fill="#5CC77F"/>
                                <rect x="123" y="316" width="32" height="9" rx="4.5" fill="#1E7C4D"/>
                                <rect x="111" y="378" width="56" height="46" rx="9" fill="#F8FCF7"/>
                                <g transform="translate(129 382)">
                                    <path d="M9 4h8l1.5 4H7.5L9 4Z" stroke="#2E8B58" stroke-width="1.5" stroke-linejoin="round"/>
                                    <path d="M8 8h10v10a3 3 0 0 1-3 3h-4a3 3 0 0 1-3-3V8Z" stroke="#2E8B58" stroke-width="1.5"/>
                                    <path d="M11 12v5M15 12v5" stroke="#2E8B58" stroke-width="1.4" stroke-linecap="round"/>
                                </g>
                                <text x="139" y="419" text-anchor="middle" fill="#2A744C" font-size="6.7" font-weight="800" letter-spacing=".6">PLASTIK</text>
                            </g>

                            <g>
                                <ellipse cx="238" cy="459" rx="54" ry="8" fill="#6C4A12" opacity=".10"/>
                                <path d="M200 343h76l-6 102c-.5 8-7 14-15 14h-34c-8 0-14.5-6-15-14l-6-102Z" fill="#F0B74D"/>
                                <path d="M264 343h12l-6 102c-.5 8-7 14-15 14h-2c7-1 12-7 12-14l-1-102Z" fill="#DDA032" fill-opacity=".65"/>
                                <rect x="191" y="326" width="94" height="25" rx="12" fill="#D29A2F"/>
                                <ellipse cx="238" cy="326" rx="47" ry="13" fill="#F9CE6F"/>
                                <rect x="222" y="316" width="32" height="9" rx="4.5" fill="#D29A2F"/>
                                <rect x="210" y="378" width="56" height="46" rx="9" fill="#FFFDF7"/>
                                <g transform="translate(228 382)">
                                    <path d="M5 4h11l4 4v12H5V4Z" stroke="#9B721C" stroke-width="1.5" stroke-linejoin="round"/>
                                    <path d="M15 4v5h5M8.5 14h7M8.5 17h5" stroke="#9B721C" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
                                </g>
                                <text x="238" y="419" text-anchor="middle" fill="#8A6419" font-size="6.7" font-weight="800" letter-spacing=".7">KERTAS</text>
                            </g>

                            <g>
                                <ellipse cx="337" cy="459" rx="54" ry="8" fill="#1A5370" opacity=".10"/>
                                <path d="M299 343h76l-6 102c-.5 8-7 14-15 14h-34c-8 0-14.5-6-15-14l-6-102Z" fill="#63B3DD"/>
                                <path d="M363 343h12l-6 102c-.5 8-7 14-15 14h-2c7-1 12-7 12-14l-1-102Z" fill="#4597C5" fill-opacity=".70"/>
                                <rect x="290" y="326" width="94" height="25" rx="12" fill="#438FB9"/>
                                <ellipse cx="337" cy="326" rx="47" ry="13" fill="#8CCFEC"/>
                                <rect x="321" y="316" width="32" height="9" rx="4.5" fill="#438FB9"/>
                                <rect x="309" y="378" width="56" height="46" rx="9" fill="#F9FDFF"/>
                                <g transform="translate(327 383)">
                                    <rect x="4" y="5" width="12" height="13" rx="3" stroke="#357A9E" stroke-width="1.5"/>
                                    <ellipse cx="10" cy="5" rx="6" ry="2.2" stroke="#357A9E" stroke-width="1.4"/>
                                    <path d="M8 10h4M8 13h4" stroke="#357A9E" stroke-width="1.3" stroke-linecap="round"/>
                                </g>
                                <text x="337" y="419" text-anchor="middle" fill="#397B9A" font-size="6.7" font-weight="800" letter-spacing=".8">LOGAM</text>
                            </g>

                            <g transform="rotate(4 503 276)">
                                <ellipse cx="505" cy="477" rx="100" ry="14" fill="#0A3A23" opacity=".10"/>
                                <rect x="413" y="88" width="180" height="382" rx="36" fill="#0D2E1F"/>
                                <rect x="418" y="93" width="170" height="372" rx="31" fill="url(#heroPhoneBody)" stroke="#2B694A" stroke-width="1.5"/>
                                <rect x="426" y="102" width="154" height="354" rx="24" fill="#F9FCF7"/>
                                <rect x="465" y="109" width="48" height="8" rx="4" fill="#0E3422" opacity=".92"/>
                                <g clip-path="url(#heroPhoneScreenClip)">
                                    <rect x="426" y="102" width="154" height="354" fill="#F9FCF7"/>
                                    <circle cx="550" cy="142" r="33" fill="#EDF9EF" fill-opacity=".85"/>
                                    <text x="436" y="123" fill="#486256" font-size="5.5" font-weight="700">9:41</text>
                                    <rect x="551" y="118" width="3" height="4" rx="1" fill="#6B8173"/>
                                    <rect x="556" y="116" width="3" height="6" rx="1" fill="#6B8173"/>
                                    <path d="M564 120c2-2.2 5-2.2 7 0" stroke="#6B8173" stroke-width="1.3" stroke-linecap="round"/>
                                    <circle cx="574" cy="119" r="1.7" fill="#6B8173"/>

                                    <text x="436" y="142" fill="#7B8F82" font-size="5.5" font-weight="500">Selamat pagi,</text>
                                    <text x="436" y="156" fill="#153B28" font-size="10.5" font-weight="800">Rani</text>
                                    <circle cx="564" cy="148" r="10" fill="#DFF3E3"/>
                                    <text x="564" y="151" text-anchor="middle" fill="#1F7C4A" font-size="7" font-weight="800">R</text>

                                    <rect x="436" y="167" width="134" height="79" rx="12" fill="url(#heroWalletGradient)"/>
                                    <circle cx="553" cy="187" r="27" fill="#FFFFFF" fill-opacity=".055"/>
                                    <circle cx="539" cy="231" r="35" fill="#FFFFFF" fill-opacity=".04"/>
                                    <path d="M544 174c10-10 21-9 29 1-8 8-19 9-29-1Z" fill="#FFFFFF" fill-opacity=".17"/>
                                    <text x="446" y="184" fill="#D7F8E2" fill-opacity=".82" font-size="5.5" font-weight="500">Total saldo</text>
                                    <text x="445" y="203" fill="#FFFFFF" font-size="14.5" font-weight="800">Rp 248.500</text>
                                    <rect x="445" y="213" width="82" height="14" rx="7" fill="#FFFFFF" fill-opacity=".13"/>
                                    <path d="M451 221.5 454 218l3 2 3-4" stroke="#C9FFDA" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <text x="463" y="222" fill="#DBFFE6" font-size="4.8" font-weight="600">+Rp32.000 minggu ini</text>
                                    <circle cx="557" cy="229" r="9" fill="#F4C95D"/>
                                    <text x="557" y="232" text-anchor="middle" fill="#66500E" font-size="5.5" font-weight="800">Rp</text>

                                    <g>
                                        <circle cx="450" cy="265" r="11" fill="#ECF7EE"/>
                                        <path d="M450 259v8m0 0 2.5-2.5M450 267l-2.5-2.5" stroke="#21894F" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M445 271h10" stroke="#21894F" stroke-width="1.3" stroke-linecap="round"/>
                                        <text x="450" y="285" text-anchor="middle" fill="#455D4F" font-size="5" font-weight="600">Setor</text>

                                        <circle cx="503" cy="265" r="11" fill="#FFF4DA"/>
                                        <path d="M503 271v-8m0 0 2.5 2.5M503 263l-2.5 2.5" stroke="#9A741B" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M498 258h10" stroke="#9A741B" stroke-width="1.3" stroke-linecap="round"/>
                                        <text x="503" y="285" text-anchor="middle" fill="#455D4F" font-size="5" font-weight="600">Tarik</text>

                                        <circle cx="556" cy="265" r="11" fill="#EBF3F9"/>
                                        <circle cx="556" cy="264" r="4.2" stroke="#377B9E" stroke-width="1.3"/>
                                        <path d="M556 261.7V264l1.7 1" stroke="#377B9E" stroke-width="1.1" stroke-linecap="round"/>
                                        <text x="556" y="285" text-anchor="middle" fill="#455D4F" font-size="5" font-weight="600">Riwayat</text>
                                    </g>

                                    <text x="436" y="305" fill="#41594B" font-size="5.6" font-weight="700">Transaksi terakhir</text>
                                    <text x="572" y="305" text-anchor="end" fill="#21894F" font-size="4.8" font-weight="700">Lihat semua</text>

                                    <rect x="436" y="312" width="136" height="28" rx="8" fill="#FFFFFF" stroke="#E8F0E6"/>
                                    <circle cx="447" cy="326" r="8" fill="#EAF7ED"/>
                                    <path d="M445 323h4l.8 2h-5.6l.8-2Zm-.5 2h5v3.2a1.4 1.4 0 0 1-1.4 1.4h-2.2a1.4 1.4 0 0 1-1.4-1.4V325Z" fill="#21894F"/>
                                    <text x="458" y="324" fill="#233D2C" font-size="5.4" font-weight="700">Plastik PET</text>
                                    <text x="458" y="332" fill="#8A998F" font-size="4.2" font-weight="500">Hari ini · 09.12</text>
                                    <text x="563" y="326" text-anchor="end" fill="#1F8A51" font-size="5.2" font-weight="800">+Rp 24.000</text>

                                    <rect x="436" y="346" width="136" height="28" rx="8" fill="#FFFFFF" stroke="#E8F0E6"/>
                                    <circle cx="447" cy="360" r="8" fill="#FFF5DD"/>
                                    <path d="M444 357h6l1 1.5v5h-7V357Z" stroke="#A17A1B" stroke-width="1"/>
                                    <text x="458" y="358" fill="#233D2C" font-size="5.4" font-weight="700">Kardus</text>
                                    <text x="458" y="366" fill="#8A998F" font-size="4.2" font-weight="500">Kemarin · 16.40</text>
                                    <text x="563" y="360" text-anchor="end" fill="#1F8A51" font-size="5.2" font-weight="800">+Rp 8.000</text>

                                    <rect x="436" y="383" width="136" height="40" rx="9" fill="#EAF7ED"/>
                                    <text x="446" y="397" fill="#1B5B3A" font-size="5.7" font-weight="800">Jemput sampah</text>
                                    <text x="446" y="407" fill="#6E8576" font-size="4.2" font-weight="500">Pesan kurir, tanpa repot</text>
                                    <circle cx="557" cy="403" r="9" fill="#208B53"/>
                                    <path d="M554 403h6m0 0-2-2m2 2-2 2" stroke="#FFFFFF" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>

                                    <path d="M436 432h134" stroke="#E8F0E6"/>
                                    <rect x="442" y="438" width="23" height="15" rx="7.5" fill="#E6F6EA"/>
                                    <path d="M450 443.5 453 441l3 2.5v5h-6v-5Z" stroke="#21894F" stroke-width="1.1" stroke-linejoin="round"/>
                                    <circle cx="503" cy="445" r="4" stroke="#A1B2A7" stroke-width="1.2"/>
                                    <path d="M503 443.5v2l1.3.8" stroke="#A1B2A7" stroke-width="1" stroke-linecap="round"/>
                                    <circle cx="556" cy="443" r="2.2" stroke="#A1B2A7" stroke-width="1.2"/>
                                    <path d="M552 448c.5-2.2 2-3.3 4-3.3s3.5 1.1 4 3.3" stroke="#A1B2A7" stroke-width="1.2" stroke-linecap="round"/>
                                </g>
                                <rect x="426" y="102" width="154" height="354" rx="24" stroke="#DDEADF" stroke-width=".7"/>
                                <rect x="593" y="181" width="2.5" height="34" rx="1.2" fill="#0A2719"/>
                                <rect x="410" y="173" width="3" height="25" rx="1.5" fill="#0A2719"/>
                            </g>

                            <path d="M78 269c8-5 14-4 20 2-7 5-14 5-20-2Z" fill="#73CF8D" fill-opacity=".7"/>
                            <circle cx="99" cy="228" r="3" fill="#8BD89F" fill-opacity=".7"/>
                            <circle cx="469" cy="177" r="2.5" fill="#F4C95D"/>
                        </svg>
                    </div>

                    <div class="hero-toast" aria-hidden="true">
                        <span class="toast-icon">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="m6 12 4 4 8-9" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <span class="toast-copy">
                            <span>Setoran berhasil dicatat</span>
                            <strong>+ Rp 32.000</strong>
                        </span>
                    </div>

                    <span class="hero-seal" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M20 4c-8.5.6-13 5.1-13 13 7.4.1 12-4.3 13-13Z" fill="currentColor"/>
                        </svg>
                        100% Digital
                    </span>
                </div>
            </div>
        </section>

        <section class="features" id="fitur" aria-labelledby="fitur-title">
            <div class="container">
                <div class="section-head reveal">
                    <span class="eyebrow">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M12 3v18M3 12h18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                        Fitur Unggulan Kami
                    </span>
                    <h2 id="fitur-title">Lebih mudah menabung sampah,<br>lebih besar dampaknya.</h2>
                    <p>Semua kebutuhan bank sampahmu dalam satu platform yang simpel, aman, dan bisa diakses kapan saja.</p>
                </div>

                <div class="features-grid">
                    <article class="feature-card reveal">
                        <span class="feature-index">01</span>
                        <span class="feature-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none">
                                <path d="M12 4v16M5 8h14M7 8l-3 6h6L7 8Zm10 0-3 6h6l-3-6Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M7 20h10" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                            </svg>
                        </span>
                        <h3>Setoran Mudah <span>(Teller)</span></h3>
                        <p>Catat setoran sampah dengan cepat</p>
                    </article>

                    <article class="feature-card reveal">
                        <span class="feature-index">02</span>
                        <span class="feature-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none">
                                <path d="M4 8a2.5 2.5 0 0 1 2.5-2.5h11A2.5 2.5 0 0 1 20 8v8a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 16V8Z" stroke="currentColor" stroke-width="1.7"/>
                                <path d="M16 10h4v4h-4a2 2 0 0 1 0-4Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                                <path d="M16.5 12h.01" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
                                <path d="M7 5V4.5A2.5 2.5 0 0 1 9.5 2h5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                            </svg>
                        </span>
                        <h3>Cek Saldo Real-time</h3>
                        <p>Pantau saldo &amp; riwayat kapan saja</p>
                    </article>

                    <article class="feature-card reveal">
                        <span class="feature-index">03</span>
                        <span class="feature-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none">
                                <rect x="3.5" y="3.5" width="7" height="7" rx="2" stroke="currentColor" stroke-width="1.7"/>
                                <rect x="13.5" y="3.5" width="7" height="7" rx="2" stroke="currentColor" stroke-width="1.7"/>
                                <rect x="3.5" y="13.5" width="7" height="7" rx="2" stroke="currentColor" stroke-width="1.7"/>
                                <path d="M17 13.5v7M13.5 17h7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                            </svg>
                        </span>
                        <h3>Kategori Sampah Lengkap</h3>
                        <p>Harga transparan per kilogram</p>
                    </article>

                    <article class="feature-card reveal">
                        <span class="feature-index">04</span>
                        <span class="feature-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none">
                                <path d="M12 3v9m0 0 3.5-3.5M12 12 8.5 8.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M4 14v2.5A3.5 3.5 0 0 0 7.5 20h9a3.5 3.5 0 0 0 3.5-3.5V14" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                            </svg>
                        </span>
                        <h3>Penarikan Dana Instan</h3>
                        <p>Tarik saldo ke rekening atau e-wallet</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="how" id="layanan" aria-labelledby="layanan-title">
            <div class="container">
                <div class="section-head reveal">
                    <span class="eyebrow">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M4 18 9 7l4 8 3-5 4 8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Bagaimana Caranya?
                    </span>
                    <h2 id="layanan-title">Tiga langkah kecil,<br>dampak yang besar.</h2>
                    <p>Dari rumah sampai saldo digital, proses menabung sampah dibuat sesederhana mungkin untukmu.</p>
                </div>

                <div class="steps">
                    <article class="step-card reveal">
                        <div class="step-visual">
                            <svg class="step-art" viewBox="0 0 340 200" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
                                <circle cx="276" cy="45" r="36" fill="#FFFFFF" fill-opacity=".52"/>
                                <circle cx="276" cy="45" r="24" fill="#FFFFFF" fill-opacity=".32"/>
                                <path d="M28 169c60-16 126-17 196-4 37 6 66 7 88 3" stroke="#BADFC5" stroke-width="2" stroke-linecap="round" stroke-dasharray="2 7"/>
                                <path d="M34 79c9-19 24-25 40-18-5 17-19 25-40 18Z" fill="#A5E1B5"/>
                                <path d="M46 68c9-8 19-11 29-11" stroke="#E7F8EB" stroke-width="1.3" stroke-linecap="round"/>
                                <circle cx="306" cy="112" r="3" fill="#9CD8AD"/>
                                <path d="m42 112 2.7-8 2.7 8-8.2-2.6 8.3-2.6-2.8 8.2Z" fill="#F4C95D"/>

                                <path d="M98 84c-7 5-10 8-12 13" stroke="#61B77B" stroke-width="1.6" stroke-dasharray="2 4" stroke-linecap="round"/>
                                <path d="M168 72v19" stroke="#D5A236" stroke-width="1.6" stroke-dasharray="2 4" stroke-linecap="round"/>
                                <path d="M248 70c3 7 5 13 6 22" stroke="#5397B7" stroke-width="1.6" stroke-dasharray="2 4" stroke-linecap="round"/>

                                <g transform="rotate(-16 98 55)">
                                    <rect x="87" y="35" width="18" height="43" rx="7" fill="#CBEAF5" stroke="#73B7D0" stroke-width="1.4"/>
                                    <rect x="92" y="27" width="8" height="11" rx="2" fill="#2E86A8"/>
                                    <rect x="90" y="50" width="12" height="14" rx="2" fill="#FFF9E9"/>
                                    <path d="M91 58h10" stroke="#A5D5E3" stroke-width="1"/>
                                </g>

                                <g transform="rotate(8 167 47)">
                                    <rect x="148" y="31" width="38" height="32" rx="3" fill="#E7BE84"/>
                                    <path d="M148 40h38M167 31v32" stroke="#C79A5F" stroke-width="1.5"/>
                                    <path d="M154 56h10M163 56h8" stroke="#F8DEC0" stroke-width="1.4" stroke-linecap="round"/>
                                </g>

                                <g transform="rotate(13 248 50)">
                                    <rect x="239" y="32" width="18" height="30" rx="4" fill="#B9DCEA" stroke="#68AAC6" stroke-width="1.3"/>
                                    <ellipse cx="248" cy="33" rx="9" ry="3" fill="#90C7DD" stroke="#68AAC6" stroke-width="1"/>
                                    <path d="M242 43h12M242 55h12" stroke="#8FC5D9" stroke-width="1.2" stroke-linecap="round"/>
                                </g>

                                <g>
                                    <ellipse cx="85" cy="168" rx="38" ry="6" fill="#15452A" opacity=".10"/>
                                    <path d="M54 112h62l-5 45c-.4 5-4.6 8-9.6 8H69c-5 0-9-3-9.5-8l-5-45Z" fill="#2F9D62"/>
                                    <path d="M104 112h12l-5 45c-.4 5-4.6 8-9.6 8h-2c4.6-.5 8-3.8 8.6-8.5l-4-44.5Z" fill="#278A56" fill-opacity=".55"/>
                                    <rect x="49" y="98" width="72" height="18" rx="9" fill="#1E7C4D"/>
                                    <ellipse cx="85" cy="98" rx="36" ry="10" fill="#5CC77F"/>
                                    <rect x="73" y="90" width="24" height="7" rx="3.5" fill="#1E7C4D"/>
                                    <rect x="63" y="122" width="44" height="35" rx="7" fill="#F8FCF7"/>
                                    <g transform="translate(76 125)">
                                        <path d="M7 3h6l1 3H6l1-3Z" stroke="#2E8B58" stroke-width="1.2" stroke-linejoin="round"/>
                                        <path d="M6 6h8v8a2.5 2.5 0 0 1-2.5 2.5h-3A2.5 2.5 0 0 1 6 14V6Z" stroke="#2E8B58" stroke-width="1.2"/>
                                        <path d="M8.5 9v4M11.5 9v4" stroke="#2E8B58" stroke-width="1.1" stroke-linecap="round"/>
                                    </g>
                                    <text x="85" y="151" text-anchor="middle" fill="#2A744C" font-size="5.1" font-weight="800" letter-spacing=".45">PLASTIK</text>
                                </g>

                                <g>
                                    <ellipse cx="170" cy="168" rx="38" ry="6" fill="#6C4A12" opacity=".09"/>
                                    <path d="M139 112h62l-5 45c-.4 5-4.6 8-9.6 8h-32.8c-5 0-9.2-3-9.6-8l-5-45Z" fill="#F0B74D"/>
                                    <path d="M189 112h12l-5 45c-.4 5-4.6 8-9.6 8h-2c4.6-.5 8-3.8 8.6-8.5l-4-44.5Z" fill="#DAA033" fill-opacity=".58"/>
                                    <rect x="134" y="98" width="72" height="18" rx="9" fill="#D29A2F"/>
                                    <ellipse cx="170" cy="98" rx="36" ry="10" fill="#F9CE6F"/>
                                    <rect x="158" y="90" width="24" height="7" rx="3.5" fill="#D29A2F"/>
                                    <rect x="148" y="122" width="44" height="35" rx="7" fill="#FFFDF7"/>
                                    <g transform="translate(161 125)">
                                        <path d="M4 3h8l4 4v10H4V3Z" stroke="#9B721C" stroke-width="1.2" stroke-linejoin="round"/>
                                        <path d="M12 3v4h4M7 11h6M7 14h4" stroke="#9B721C" stroke-width="1.1" stroke-linecap="round" stroke-linejoin="round"/>
                                    </g>
                                    <text x="170" y="151" text-anchor="middle" fill="#8A6419" font-size="5.1" font-weight="800" letter-spacing=".5">KERTAS</text>
                                </g>

                                <g>
                                    <ellipse cx="255" cy="168" rx="38" ry="6" fill="#1A5370" opacity=".09"/>
                                    <path d="M224 112h62l-5 45c-.4 5-4.6 8-9.6 8h-32.8c-5 0-9.2-3-9.6-8l-5-45Z" fill="#63B3DD"/>
                                    <path d="M274 112h12l-5 45c-.4 5-4.6 8-9.6 8h-2c4.6-.5 8-3.8 8.6-8.5l-4-44.5Z" fill="#4A9BC4" fill-opacity=".62"/>
                                    <rect x="219" y="98" width="72" height="18" rx="9" fill="#438FB9"/>
                                    <ellipse cx="255" cy="98" rx="36" ry="10" fill="#8CCFEC"/>
                                    <rect x="243" y="90" width="24" height="7" rx="3.5" fill="#438FB9"/>
                                    <rect x="233" y="122" width="44" height="35" rx="7" fill="#F9FDFF"/>
                                    <g transform="translate(246 126)">
                                        <rect x="3" y="4" width="10" height="11" rx="2.5" stroke="#357A9E" stroke-width="1.2"/>
                                        <ellipse cx="8" cy="4" rx="5" ry="1.8" stroke="#357A9E" stroke-width="1.1"/>
                                        <path d="M6.5 8.7h3M6.5 11.5h3" stroke="#357A9E" stroke-width="1.1" stroke-linecap="round"/>
                                    </g>
                                    <text x="255" y="151" text-anchor="middle" fill="#397B9A" font-size="5.1" font-weight="800" letter-spacing=".6">LOGAM</text>
                                </g>

                                <path d="M300 148c7-8 16-10 24-5-3 9-12 13-24 5Z" fill="#70C98A" fill-opacity=".8"/>
                                <circle cx="299" cy="166" r="2.3" fill="#8DD79F"/>
                            </svg>
                        </div>
                        <div class="step-content">
                            <h3 class="step-title"><span class="step-num">1.</span><span>Pilih &amp; Pilah Sampah</span></h3>
                            <p>Pisahkan plastik, kertas, dan logam. Cek kategori agar sampahmu siap disetor.</p>
                        </div>
                    </article>

                    <article class="step-card reveal">
                        <div class="step-visual">
                            <svg class="step-art" viewBox="0 0 340 200" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
                                <circle cx="279" cy="38" r="34" fill="#FFFFFF" fill-opacity=".58"/>
                                <circle cx="279" cy="38" r="22" fill="#FFFFFF" fill-opacity=".30"/>
                                <path d="M30 171c60-11 126-12 196-2 34 4 60 4 82 1" stroke="#E6D3A4" stroke-width="2" stroke-linecap="round" stroke-dasharray="2 7"/>
                                <path d="M48 94c9-20 25-26 41-19-5 18-20 26-41 19Z" fill="#9BDFAE" fill-opacity=".8"/>
                                <circle cx="305" cy="105" r="3" fill="#E5BE59"/>

                                <rect x="111" y="22" width="118" height="24" rx="12" fill="#FFFFFF" stroke="#E8E4D3"/>
                                <circle cx="127" cy="34" r="5" fill="#E7F6EA"/>
                                <path d="M125 34h4M127 32v4" stroke="#2B8B57" stroke-width="1.2" stroke-linecap="round"/>
                                <text x="181" y="37" text-anchor="middle" fill="#1C6C45" font-size="8.5" font-weight="800" letter-spacing="2">TELLER</text>

                                <path d="M67 48h206a9 9 0 0 1 9 9v23H58V57a9 9 0 0 1 9-9Z" fill="#1F7C4D"/>
                                <path d="M84 48h26v23H84zM144 48h26v23h-26zM204 48h26v23h-26zM264 48h9v23h-9z" fill="#F4C95D"/>
                                <path d="M58 71h224v10H58z" fill="#72C58A" fill-opacity=".28"/>
                                <path d="M72 81c6 8 13 8 19 0 6 8 13 8 19 0 6 8 13 8 19 0 6 8 13 8 19 0 6 8 13 8 19 0 6 8 13 8 19 0 6 8 13 8 19 0 6 8 13 8 19 0 6 8 13 8 19 0 6 8 13 8 19 0" stroke="#F7F8F0" stroke-width="4" stroke-linecap="round"/>

                                <path d="M143 124c2-17 14-27 32-27s30 10 32 27v12h-64v-12Z" fill="#2D8E5D"/>
                                <rect x="159" y="99" width="15" height="17" rx="6" fill="#E6B58E"/>
                                <circle cx="167" cy="90" r="18" fill="#F0C49E"/>
                                <circle cx="149" cy="92" r="4" fill="#E8B992"/>
                                <circle cx="185" cy="92" r="4" fill="#E8B992"/>
                                <path d="M149 91c-1-16 8-25 19-25 12 0 20 10 19 24-5-7-11-10-19-10-9 0-15 3-19 11Z" fill="#2D3B31"/>
                                <circle cx="161" cy="91" r="1.3" fill="#29372F"/>
                                <circle cx="174" cy="91" r="1.3" fill="#29372F"/>
                                <path d="M163 97c2.5 3 6.5 3 9 0" stroke="#9C5A45" stroke-width="1.4" stroke-linecap="round"/>
                                <path d="M153 114h28v13h-28z" fill="#E7F4E8" fill-opacity=".28"/>

                                <rect x="78" y="123" width="184" height="13" rx="6.5" fill="#F8FBF5" stroke="#D8E5D5"/>
                                <path d="M78 136h184v38c0 7-6 13-13 13H91c-7 0-13-6-13-13v-38Z" fill="#247B4E"/>
                                <path d="M78 136h184v12H78z" fill="#2F8D59"/>
                                <path d="M153 156h60M158 163h50" stroke="#72C38A" stroke-width="3" stroke-linecap="round" stroke-opacity=".55"/>

                                <g>
                                    <rect x="104" y="95" width="35" height="23" rx="3" fill="#DDAC6E"/>
                                    <path d="M104 101h35M121.5 95v23" stroke="#C49155" stroke-width="1.2"/>
                                    <rect x="109" y="86" width="25" height="11" rx="2.5" fill="#EBCA9E"/>
                                    <rect x="103" y="116" width="37" height="7" rx="3.5" fill="#EAF2E8" stroke="#D2E1D2" stroke-width=".8"/>
                                    <path d="M114 123h15l3 9h-21l3-9Z" fill="#2C6B49"/>
                                    <circle cx="121.5" cy="127" r="2" fill="#F4C95D"/>
                                    <rect x="139" y="117" width="24" height="8" rx="4" fill="#FFFFFF" stroke="#D5E4D4" stroke-width=".8"/>
                                    <text x="151" y="122.8" text-anchor="middle" fill="#2B8B57" font-size="3.8" font-weight="800">2.4 kg</text>
                                </g>

                                <g transform="rotate(7 270 116)">
                                    <rect x="245" y="82" width="51" height="69" rx="7" fill="#FFFFFF" stroke="#E4EBDD"/>
                                    <circle cx="270.5" cy="99" r="9" fill="#DCF4E4"/>
                                    <path d="m266.5 99 2.7 2.8 5-5.6" stroke="#248D54" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M254 116h33M254 123h27M254 130h31" stroke="#D8E4D6" stroke-width="1.6" stroke-linecap="round"/>
                                    <rect x="254" y="138" width="33" height="5" rx="2.5" fill="#2B8E5E"/>
                                </g>

                                <g>
                                    <circle cx="292" cy="57" r="15" fill="#F4C95D" stroke="#FFFFFF" stroke-width="1.5"/>
                                    <text x="292" y="60" text-anchor="middle" fill="#6A510B" font-size="6.5" font-weight="800">Rp</text>
                                </g>
                                <path d="M302 147c7-7 15-8 22-4-3 8-11 11-22 4Z" fill="#65C481" fill-opacity=".85"/>
                                <path d="m50 137 2.5-7.5 2.5 7.5-7.8-2.5 7.8-2.5-2.5 7.5Z" fill="#F4C95D"/>
                            </svg>
                        </div>
                        <div class="step-content">
                            <h3 class="step-title"><span class="step-num">2.</span><span>Setor ke Teller</span></h3>
                            <p>Datang ke teller terdekat atau jadwalkan penjemputan. Sampah ditimbang dan dicatat.</p>
                        </div>
                    </article>

                    <article class="step-card reveal">
                        <div class="step-visual">
                            <svg class="step-art" viewBox="0 0 340 200" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
                                <circle cx="281" cy="41" r="35" fill="#FFFFFF" fill-opacity=".58"/>
                                <circle cx="281" cy="41" r="23" fill="#FFFFFF" fill-opacity=".30"/>
                                <path d="M31 158c38-9 62-42 79-82" stroke="#9CC9BB" stroke-width="2" stroke-dasharray="2 7" stroke-linecap="round"/>
                                <path d="m99 87 10-5-1 11" stroke="#68AE97" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M44 176c53-9 109-10 168-3 36 4 65 4 89 1" stroke="#BBD9E4" stroke-width="2" stroke-linecap="round" stroke-dasharray="2 7"/>

                                <g transform="rotate(-4 175 100)">
                                    <rect x="128" y="10" width="94" height="180" rx="23" fill="#0E3021"/>
                                    <rect x="133" y="15" width="84" height="170" rx="19" fill="#F9FCF7"/>
                                    <rect x="161" y="20" width="28" height="4.5" rx="2.2" fill="#153A26"/>

                                    <circle cx="144" cy="32" r="4.2" fill="#E4F6E8"/>
                                    <path d="M146.5 29.7c-2.6.2-4.1 1.7-4.1 4.1 2.4.1 4-1.3 4.1-4.1Z" fill="#21894F"/>
                                    <text x="152" y="32" fill="#486054" font-size="3.8" font-weight="700">BANK SAMPAH DIGITAL</text>

                                    <text x="142" y="46" fill="#7A8C7F" font-size="4.5" font-weight="500">Total saldo</text>
                                    <text x="141" y="60" fill="#123C27" font-size="11.5" font-weight="800">Rp 248.500</text>
                                    <rect x="142" y="66" width="40" height="8" rx="4" fill="#E4F6E8"/>
                                    <path d="m146 70 2-2 2 1 2-3" stroke="#238D53" stroke-width=".9" stroke-linecap="round" stroke-linejoin="round"/>
                                    <text x="154" y="71.5" fill="#238D53" font-size="3.2" font-weight="700">+12,5% bulan ini</text>

                                    <g>
                                        <circle cx="152" cy="88" r="7.5" fill="#EAF7ED"/>
                                        <path d="M152 84v8m0 0 1.8-1.8M152 92l-1.8-1.8" stroke="#21894F" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"/>
                                        <text x="152" y="102" text-anchor="middle" fill="#586C5F" font-size="3.4" font-weight="600">Setor</text>

                                        <circle cx="176" cy="88" r="7.5" fill="#FFF4DA"/>
                                        <path d="M176 92v-8m0 0 1.8 1.8M176 84l-1.8 1.8" stroke="#9A741B" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"/>
                                        <text x="176" y="102" text-anchor="middle" fill="#586C5F" font-size="3.4" font-weight="600">Tarik</text>

                                        <circle cx="200" cy="88" r="7.5" fill="#EBF3F9"/>
                                        <circle cx="200" cy="87" r="3" stroke="#377B9E" stroke-width=".9"/>
                                        <path d="M200 85.2V87l1.2.7" stroke="#377B9E" stroke-width=".8" stroke-linecap="round"/>
                                        <text x="200" y="102" text-anchor="middle" fill="#586C5F" font-size="3.4" font-weight="600">Riwayat</text>
                                    </g>

                                    <text x="142" y="117" fill="#435B4C" font-size="4.4" font-weight="700">Transaksi terakhir</text>
                                    <text x="210" y="117" text-anchor="end" fill="#21894F" font-size="3.5" font-weight="700">Lihat</text>

                                    <rect x="141" y="122" width="68" height="18" rx="5" fill="#FFFFFF" stroke="#E7EFE4"/>
                                    <circle cx="148" cy="131" r="5" fill="#EAF7ED"/>
                                    <path d="M146.5 129h3l.6 1.5h-4.2l.6-1.5Zm-.4 1.5h3.8v2.4a1 1 0 0 1-1 1h-1.8a1 1 0 0 1-1-1v-2.4Z" fill="#21894F"/>
                                    <text x="156" y="130" fill="#28402F" font-size="3.4" font-weight="700">Plastik PET</text>
                                    <text x="156" y="135.5" fill="#8A998F" font-size="2.8" font-weight="500">Hari ini</text>
                                    <text x="204" y="131" text-anchor="end" fill="#1F8A51" font-size="3.4" font-weight="800">+Rp24.000</text>

                                    <rect x="141" y="143" width="68" height="18" rx="5" fill="#FFFFFF" stroke="#E7EFE4"/>
                                    <circle cx="148" cy="152" r="5" fill="#FFF5DD"/>
                                    <path d="M145.5 149.5h5l.8 1.2v4h-5.8v-5.2Z" stroke="#A17A1B" stroke-width=".8"/>
                                    <text x="156" y="151" fill="#28402F" font-size="3.4" font-weight="700">Kardus</text>
                                    <text x="156" y="156.5" fill="#8A998F" font-size="2.8" font-weight="500">Kemarin</text>
                                    <text x="204" y="152" text-anchor="end" fill="#1F8A51" font-size="3.4" font-weight="800">+Rp8.000</text>

                                    <path d="M139 167h72" stroke="#E7EFE4"/>
                                    <rect x="142" y="171" width="17" height="9" rx="4.5" fill="#E6F6EA"/>
                                    <path d="M148 176.5 150.5 174l2.5 2.5v3h-5v-3Z" stroke="#21894F" stroke-width=".8" stroke-linejoin="round"/>
                                    <circle cx="175" cy="176" r="3" stroke="#A1B2A7" stroke-width=".9"/>
                                    <path d="M175 174.5v1.7l1.1.6" stroke="#A1B2A7" stroke-width=".8" stroke-linecap="round"/>
                                    <circle cx="200" cy="175" r="1.7" stroke="#A1B2A7" stroke-width=".9"/>
                                    <path d="M197 179c.4-1.6 1.5-2.4 3-2.4s2.6.8 3 2.4" stroke="#A1B2A7" stroke-width=".9" stroke-linecap="round"/>
                                    <rect x="165" y="183" width="20" height="1.8" rx=".9" fill="#0F3322" fill-opacity=".2"/>
                                    <rect x="207" y="42" width="2" height="17" rx="1" fill="#0A2719"/>
                                </g>

                                <g>
                                    <circle cx="251" cy="77" r="19" fill="#F4C95D" stroke="#FFFFFF" stroke-width="2"/>
                                    <path d="m244 84 6-7 4 4 5-7" stroke="#6A510B" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M255 73h4v4" stroke="#6A510B" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                                </g>
                                <g>
                                    <circle cx="83" cy="60" r="13" fill="#FFE39B" stroke="#FFFFFF" stroke-width="1.5"/>
                                    <text x="83" y="63" text-anchor="middle" fill="#6A510B" font-size="5.8" font-weight="800">Rp</text>
                                </g>
                                <circle cx="75" cy="157" r="3" fill="#8DC8B0"/>
                                <path d="m295 125 2.5-7.5 2.5 7.5-7.8-2.5 7.8-2.5-2.5 7.5Z" fill="#F4C95D"/>
                                <path d="M35 101c7-15 19-19 31-14-4 13-15 20-31 14Z" fill="#90D3AA" fill-opacity=".75"/>
                                <circle cx="305" cy="151" r="2.5" fill="#A8D5E4"/>
                            </svg>
                        </div>
                        <div class="step-content">
                            <h3 class="step-title"><span class="step-num">3.</span><span>Terima Saldo Digital</span></h3>
                            <p>Saldo masuk otomatis ke akunmu. Pantau, tarik, atau simpan untuk kebutuhanmu.</p>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section class="about" id="tentang" aria-labelledby="tentang-title">
            <div class="container">
                <div class="about-panel reveal">
                    <div class="about-copy">
                        <span class="eyebrow eyebrow--light">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M20 4c-8.5.6-13 5.1-13 13 7.4.1 12-4.3 13-13Z" fill="currentColor"/>
                            </svg>
                            Tentang Kami
                        </span>
                        <h2 id="tentang-title">Bukan sekadar bank sampah. Ini gerakan bersama.</h2>
                        <p>Kami menghubungkan rumah tangga, teller, dan pengepul dalam satu ekosistem digital—supaya setiap sampah yang kamu pilah punya nilai nyata bagi dompet dan bumi.</p>

                        <ul class="about-list">
                            <li>
                                <span class="about-check">
                                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="m6 12 4 4 8-9" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </span>
                                Jaringan teller dan pengepul lokal
                            </li>
                            <li>
                                <span class="about-check">
                                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="m6 12 4 4 8-9" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </span>
                                Pencatatan saldo yang aman dan transparan
                            </li>
                            <li>
                                <span class="about-check">
                                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="m6 12 4 4 8-9" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </span>
                                Gaya hidup minim sampah mulai dari rumah
                            </li>
                        </ul>

                        <div class="about-actions">
                            <a class="btn btn-light" href="auth/register.php">
                                Gabung Sekarang
                                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M5 12h14m0 0-5-5m5 5-5 5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </a>
                            <a class="about-login" href="auth/login.php">Sudah punya akun? <strong>Masuk</strong></a>
                        </div>
                    </div>

                    <div class="about-visual">
                        <div class="impact-art">
                            <span class="orbit orbit--outer" aria-hidden="true"></span>
                            <span class="orbit orbit--inner" aria-hidden="true"></span>

                            <div class="impact-core" aria-hidden="true">
                                <svg viewBox="0 0 280 280" fill="none">
                                    <defs>
                                        <radialGradient id="impactGlow" cx="0" cy="0" r="1" gradientUnits="userSpaceOnUse" gradientTransform="translate(112 108) rotate(52) scale(156)">
                                            <stop stop-color="#F0FBF2"/>
                                            <stop offset=".7" stop-color="#DDF4E2"/>
                                            <stop offset="1" stop-color="#CDEED7"/>
                                        </radialGradient>
                                    </defs>
                                    <circle cx="140" cy="140" r="128" fill="rgba(255,255,255,.04)" stroke="rgba(255,255,255,.13)" stroke-dasharray="3 8"/>
                                    <circle cx="140" cy="140" r="101" fill="url(#impactGlow)"/>
                                    <circle cx="140" cy="140" r="101" stroke="rgba(255,255,255,.55)" stroke-width="1.5"/>
                                    <ellipse cx="140" cy="140" rx="63" ry="92" stroke="#BCE7C8" stroke-width="1.4"/>
                                    <path d="M44 142h192M58 102c45 20 119 20 164 0M58 180c45-20 119-20 164 0" stroke="#BCE7C8" stroke-width="1.4" stroke-linecap="round"/>
                                    <path d="M97 168c-8-48 18-82 82-91 3 53-25 88-82 91Z" fill="#47B26C"/>
                                    <path d="M112 154c13-24 34-41 58-52" stroke="#E7FAEB" stroke-width="3" stroke-linecap="round"/>
                                    <path d="M140 116c7 5 12 11 15 19" stroke="#A4E2B5" stroke-width="2.2" stroke-linecap="round"/>
                                </svg>
                            </div>

                            <div class="impact-pill impact-pill--one">
                                <span class="pill-icon">
                                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M20 4c-8.5.6-13 5.1-13 13 7.4.1 12-4.3 13-13Z" fill="currentColor"/>
                                        <path d="M6 20c1.2-4.2 3.6-7.4 7.2-9.8" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/>
                                    </svg>
                                </span>
                                <span>
                                    <strong>Untuk bumi</strong>
                                    <small>Setiap langkah berarti</small>
                                </span>
                            </div>

                            <div class="impact-pill impact-pill--two">
                                <span class="pill-icon">
                                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M12 3v9m0 0 3.5-3.5M12 12 8.5 8.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M4 14v2.5A3.5 3.5 0 0 0 7.5 20h9a3.5 3.5 0 0 0 3.5-3.5V14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                    </svg>
                                </span>
                                <span>
                                    <strong>Gratis bergabung</strong>
                                    <small>Tanpa biaya bulanan</small>
                                </span>
                            </div>

                            <div class="impact-coin" aria-hidden="true">
                                <span>Rp</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer" id="kontak">
        <div class="container">
            <div class="footer-cta">
                <div class="footer-cta-copy">
                    <span class="footer-kicker">Mulai dari hal kecil</span>
                    <h2>Siap mengubah sampah jadi tabungan?</h2>
                    <p>Daftar gratis hari ini dan mulai kumpulkan nilai dari sampah di rumahmu.</p>
                </div>
                <a class="btn btn-gold" href="auth/register.php">
                    Gabung Sekarang
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M5 12h14m0 0-5-5m5 5-5 5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </a>
            </div>

            <div class="footer-main">
                <div class="footer-brand">
                    <a class="brand brand--footer" href="#beranda" aria-label="Bank Sampah Digital — Beranda">
                        <span class="brand-mark" aria-hidden="true">
                            <svg viewBox="0 0 48 48" fill="none">
                                <path d="M31.8 12.2c-7.7.5-12.5 4.8-12.8 12.9 7.4.1 12-4.1 12.8-12.9Z" fill="#fff"/>
                                <path d="M20.3 24.2c1.7-4.4 4.8-7.3 9-9.1" stroke="#2C8A56" stroke-width="1.5" stroke-linecap="round"/>
                                <path d="M13.8 28.2a9.2 9.2 0 0 0 14.2 7.6" stroke="#fff" stroke-width="2.4" stroke-linecap="round"/>
                                <path d="m25.4 33.6 2.8 2.2-2.8 2.2" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
                                <circle cx="37" cy="37" r="8.3" fill="#F4C95D" stroke="#fff" stroke-width="2"/>
                                <path d="m33.8 39.3 2.3-2.4 1.7 1.6 2.2-2.9" stroke="#124B2E" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M39.1 35.3h1.9v1.9" stroke="#124B2E" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <span class="brand-copy">
                            <strong>BANK SAMPAH</strong>
                            <span>DIGITAL</span>
                        </span>
                    </a>
                    <p>Platform digital yang membantu rumah tangga mengubah sampah menjadi tabungan sekaligus menjaga bumi.</p>
                    <span class="footer-note">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M20 4c-8.5.6-13 5.1-13 13 7.4.1 12-4.3 13-13Z" fill="currentColor"/>
                        </svg>
                        Daur ulang jadi cuan.
                    </span>
                </div>

                <div class="footer-col">
                    <h3>Navigasi</h3>
                    <a href="#beranda">Beranda</a>
                    <a href="#fitur">Fitur</a>
                    <a href="#layanan">Layanan</a>
                    <a href="#tentang">Tentang Kami</a>
                </div>

                <div class="footer-col">
                    <h3>Akun</h3>
                    <a href="auth/login.php">Masuk</a>
                    <a href="auth/register.php">Daftar</a>
                    <a href="auth/lupa_password.php">Lupa Password</a>
                </div>

                <div class="footer-col">
                    <h3>Kontak</h3>
                    <ul class="contact-list">
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <rect x="3" y="5" width="18" height="14" rx="3" stroke="currentColor" stroke-width="1.7"/>
                                <path d="m4.5 7 7.5 6 7.5-6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <a href="mailto:halo@banksampahdigital.id">halo@banksampahdigital.id</a>
                        </li>
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7"/>
                                <path d="M12 7v5l3.5 2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span>Senin–Sabtu, 08.00–17.00 WIB</span>
                        </li>
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 1 1 14 0Z" stroke="currentColor" stroke-width="1.7"/>
                                <circle cx="12" cy="10" r="2.5" stroke="currentColor" stroke-width="1.7"/>
                            </svg>
                            <span>Jakarta, Indonesia</span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> Bank Sampah Digital. Semua hak dilindungi.</p>
                <div class="footer-legal">
                    <a href="#">Kebijakan Privasi</a>
                    <span aria-hidden="true"></span>
                    <a href="#">Syarat &amp; Ketentuan</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="assets/js/landing.js"></script>
</body>
</html>
