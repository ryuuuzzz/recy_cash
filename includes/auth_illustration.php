<?php
/**
 * Panel ilustrasi bersama untuk seluruh halaman autentikasi.
 *
 * Variabel opsional sebelum include:
 * - $authEyebrow     Teks kecil di atas judul.
 * - $authTitle       HTML judul (boleh berisi <br> dan <span class="accent">).
 * - $authDescription HTML deskripsi (escape nilai dinamis sebelum dikirim).
 * - $authBrandHref   URL logo merek.
 */
$authEyebrow = $authEyebrow ?? 'Ubah sampah jadi cuan';
$authTitle = $authTitle ?? 'Pilah sampahnya,<br>panen <span class="accent">cuannya</span>.';
$authDescription = $authDescription ?? 'Bergabung bersama keluarga hijau: setor sampah, kumpulkan koin, dan jadikan bumi lebih bersih.';
$authBrandHref = $authBrandHref ?? '../index.php';
?>

        <section class="illustration" aria-label="Ilustrasi keluarga memilah sampah">

            <a class="brand" href="<?= htmlspecialchars($authBrandHref) ?>" aria-label="RecyCash - Beranda">
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
                    <strong>RecyCash</strong>
                    <small>Bank Sampah Digital</small>
                </span>
            </a>

            <div class="illu-copy">
                <span class="eyebrow"><span class="eyebrow-dot"></span><?= htmlspecialchars($authEyebrow) ?></span>
                <h1><?= $authTitle ?></h1>
                <p><?= $authDescription ?></p>
            </div>

            <div class="scene" id="scene">
                <svg class="scene-svg" viewBox="0 0 680 440" role="img" aria-labelledby="sceneTitle sceneDesc" xmlns="http://www.w3.org/2000/svg">
                    <title id="sceneTitle">Keluarga memilah sampah dan koin RecyCash terbang</title>
                    <desc id="sceneDesc">Seorang ibu dan anak memilah sampah ke tiga tempat sampah. Koin emas terbang dari tempat sampah sebagai imbalan.</desc>

                    <defs>
                        <linearGradient id="gShirtMom" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0" stop-color="#2ca865"/>
                            <stop offset="1" stop-color="#19683d"/>
                        </linearGradient>
                        <linearGradient id="gCoin" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0" stop-color="#ffe08a"/>
                            <stop offset="1" stop-color="#f4c95d"/>
                        </linearGradient>
                    </defs>

                    <g class="scene-layer" data-depth="6">
                        <circle cx="340" cy="212" r="188" fill="#f1fbf4"/>
                        <circle class="ring" cx="340" cy="212" r="204" fill="none" stroke="#c4e8cf" stroke-width="1.5" stroke-dasharray="2 12" stroke-linecap="round"/>
                        <g class="sun">
                            <circle cx="588" cy="72" r="42" fill="#fbe7a7" opacity=".35"/>
                            <circle cx="588" cy="72" r="27" fill="#fbe7a7"/>
                        </g>
                        <g class="cloud cloud--1" fill="#ffffff" opacity=".9">
                            <ellipse cx="132" cy="92" rx="34" ry="16"/>
                            <ellipse cx="160" cy="82" rx="24" ry="13"/>
                            <ellipse cx="108" cy="83" rx="21" ry="12"/>
                        </g>
                        <g class="cloud cloud--2" fill="#ffffff" opacity=".75">
                            <ellipse cx="470" cy="62" rx="26" ry="12"/>
                            <ellipse cx="492" cy="54" rx="18" ry="10"/>
                        </g>
                        <path d="M80 58l8 6-8 6M96 54l7 6-7 6" stroke="#9ccfae" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none" opacity=".8"/>
                    </g>

                    <g class="scene-layer" data-depth="10">
                        <g class="leaf leaf--1">
                            <path d="M92 190c18-2 28-12 30-30-18 2-28 12-30 30Z" fill="#9ad6ad"/>
                        </g>
                        <g class="leaf leaf--2">
                            <path d="M610 240c-16-2-25-11-27-27 16 2 25 11 27 27Z" fill="#b3e3c2"/>
                        </g>
                        <g class="leaf leaf--3">
                            <path d="M62 306c14-8 19-19 16-33-14 8-19 19-16 33Z" fill="#b3e3c2"/>
                        </g>
                    </g>

                    <g class="scene-layer" data-depth="14">
                        <ellipse cx="340" cy="390" rx="236" ry="30" fill="#d9efdf"/>
                        <ellipse cx="340" cy="396" rx="176" ry="18" fill="#c6e8d0" opacity=".75"/>
                        <g fill="#bfe6c9">
                            <circle cx="138" cy="372" r="25"/>
                            <circle cx="168" cy="379" r="17"/>
                            <circle cx="108" cy="381" r="14"/>
                            <circle cx="556" cy="370" r="27"/>
                            <circle cx="590" cy="380" r="17"/>
                            <circle cx="530" cy="382" r="14"/>
                        </g>
                    </g>

                    <!-- Tempat sampah -->
                    <g class="scene-layer" data-depth="18">
                        <g transform="translate(285 0)">
                            <g class="bin" tabindex="0" role="group" aria-label="Tempat sampah organik">
                                <ellipse cx="0" cy="384" rx="34" ry="6" fill="#9fd3b0" opacity=".55"/>
                                <g class="bin-inner">
                                    <g class="bin-lid">
                                        <rect x="-30" y="306" width="60" height="14" rx="7" fill="#21894f"/>
                                        <rect x="-8" y="301" width="16" height="6" rx="3" fill="#21894f"/>
                                    </g>
                                    <rect x="-26" y="318" width="52" height="64" rx="9" fill="#2ca865"/>
                                    <rect x="14" y="322" width="8" height="56" rx="4" fill="#1b7a45" opacity=".35"/>
                                    <g transform="translate(0 350)" fill="#ffffff">
                                        <path d="M8 8C-4 8-10 2-10-10c12 0 18 6 18 18Z"/>
                                        <path d="M-6 4c2-5 5-8 9-9" stroke="#2ca865" stroke-width="1.4" stroke-linecap="round" fill="none"/>
                                    </g>
                                </g>
                            </g>
                        </g>

                        <g transform="translate(340 0)">
                            <g class="bin" tabindex="0" role="group" aria-label="Tempat sampah anorganik">
                                <ellipse cx="0" cy="384" rx="34" ry="6" fill="#9fd3b0" opacity=".55"/>
                                <g class="bin-inner">
                                    <g class="bin-lid">
                                        <rect x="-30" y="306" width="60" height="14" rx="7" fill="#3f8cbc"/>
                                        <rect x="-8" y="301" width="16" height="6" rx="3" fill="#3f8cbc"/>
                                    </g>
                                    <rect x="-26" y="318" width="52" height="64" rx="9" fill="#5aa7d6"/>
                                    <rect x="14" y="322" width="8" height="56" rx="4" fill="#3f8cbc" opacity=".45"/>
                                    <g transform="translate(0 351)" fill="#ffffff">
                                        <path d="M-5-13h10v5l3 4v12a4 4 0 0 1-4 4h-8a4 4 0 0 1-4-4v-12l3-4Z"/>
                                        <rect x="-2.5" y="-17" width="5" height="5" rx="1.6"/>
                                    </g>
                                </g>
                            </g>
                        </g>

                        <g transform="translate(395 0)">
                            <g class="bin" tabindex="0" role="group" aria-label="Tempat sampah B3">
                                <ellipse cx="0" cy="384" rx="34" ry="6" fill="#9fd3b0" opacity=".55"/>
                                <g class="bin-inner">
                                    <g class="bin-lid">
                                        <rect x="-30" y="306" width="60" height="14" rx="7" fill="#c58a24"/>
                                        <rect x="-8" y="301" width="16" height="6" rx="3" fill="#c58a24"/>
                                    </g>
                                    <rect x="-26" y="318" width="52" height="64" rx="9" fill="#e0a63c"/>
                                    <rect x="14" y="322" width="8" height="56" rx="4" fill="#c58a24" opacity=".45"/>
                                    <g transform="translate(0 350)">
                                        <rect x="-8" y="-11" width="16" height="24" rx="4" fill="#ffffff"/>
                                        <rect x="-3" y="-15" width="6" height="5" rx="1.5" fill="#ffffff"/>
                                        <path d="M0-6 5 0H1l-1 6-5-7h4Z" fill="#c58a24"/>
                                    </g>
                                </g>
                            </g>
                        </g>
                    </g>

                    <!-- Keluarga -->
                    <g class="scene-layer" data-depth="24">
                        <g transform="translate(190 270)">
                            <ellipse cx="2" cy="122" rx="26" ry="6" fill="#a9d8b7" opacity=".5"/>
                            <g class="person person--kid">
                                <rect x="-14" y="86" width="10" height="30" rx="5" fill="#2f5d8a"/>
                                <rect x="4" y="86" width="10" height="30" rx="5" fill="#2f5d8a"/>
                                <rect x="-17" y="112" width="15" height="7" rx="3.5" fill="#1b2b22"/>
                                <rect x="2" y="112" width="15" height="7" rx="3.5" fill="#1b2b22"/>
                                <path d="M-20 48Q-25 70-22 90L22 90Q25 70 20 48Q0 38-20 48Z" fill="#f4c95d"/>
                                <path d="M-22 84h44v6h-44z" fill="#e0a63c" opacity=".7"/>
                                <path d="M-18 52Q-28 66-27 78" stroke="#edb98f" stroke-width="8" stroke-linecap="round" fill="none"/>
                                <path d="M18 52Q28 62 31 72" stroke="#edb98f" stroke-width="8" stroke-linecap="round" fill="none"/>
                                <circle cx="31" cy="74" r="5.5" fill="#f2c49c"/>
                                <g transform="translate(36 66) rotate(-14)">
                                    <rect x="-5" y="-12" width="10" height="22" rx="4" fill="#bfe3f7" stroke="#5aa7d6" stroke-width="2"/>
                                    <rect x="-2.5" y="-17" width="5" height="6" rx="2" fill="#5aa7d6"/>
                                </g>
                                <rect x="-4" y="34" width="8" height="10" fill="#edb98f"/>
                                <circle cx="-21" cy="20" r="4" fill="#f2c49c"/>
                                <circle cx="0" cy="18" r="22" fill="#f2c49c"/>
                                <path d="M-22 16a22 22 0 0 1 44 0c-3-9-10-14-22-14S-19 7-22 16Z" fill="#2b3a33"/>
                                <circle cx="-8" cy="19" r="1.9" fill="#22302a"/>
                                <circle cx="8" cy="19" r="1.9" fill="#22302a"/>
                                <path d="M-5 26q5 4 10 0" stroke="#b7724a" stroke-width="1.8" stroke-linecap="round" fill="none"/>
                                <circle cx="-14" cy="25" r="3" fill="#ef9f8e" opacity=".4"/>
                                <circle cx="14" cy="25" r="3" fill="#ef9f8e" opacity=".4"/>
                            </g>
                        </g>

                        <g transform="translate(512 218)">
                            <ellipse cx="0" cy="172" rx="34" ry="7" fill="#a9d8b7" opacity=".5"/>
                            <g class="person person--mom">
                                <rect x="-18" y="112" width="13" height="48" rx="6" fill="#12502f"/>
                                <rect x="5" y="112" width="13" height="48" rx="6" fill="#12502f"/>
                                <rect x="-21" y="158" width="17" height="8" rx="4" fill="#1b2b22"/>
                                <rect x="4" y="158" width="17" height="8" rx="4" fill="#1b2b22"/>
                                <path d="M-24 42Q-31 76-27 114L27 114Q31 76 24 42Q0 30-24 42Z" fill="url(#gShirtMom)"/>
                                <path d="M-11 44q11 8 22 0" stroke="#fff" stroke-width="1.6" stroke-linecap="round" fill="none" opacity=".45"/>
                                <rect x="-6" y="30" width="12" height="12" fill="#edb98f"/>
                                <path d="M22 52Q32 78 29 96" stroke="#edb98f" stroke-width="9" stroke-linecap="round" fill="none"/>
                                <path d="M-22 52Q-48 62-68 78" stroke="#edb98f" stroke-width="9" stroke-linecap="round" fill="none"/>
                                <circle cx="-70" cy="80" r="6" fill="#f2c49c"/>
                                <g transform="translate(-84 66) rotate(-24)">
                                    <rect x="-6" y="-14" width="12" height="26" rx="5" fill="#bfe3f7" stroke="#5aa7d6" stroke-width="2"/>
                                    <rect x="-3" y="-20" width="6" height="7" rx="2.5" fill="#5aa7d6"/>
                                </g>
                                <circle cx="-27" cy="14" r="4.5" fill="#f2c49c"/>
                                <circle cx="27" cy="14" r="4.5" fill="#f2c49c"/>
                                <circle cx="0" cy="10" r="28" fill="#f2c49c"/>
                                <path d="M-28 10a28 28 0 0 1 56 0c-5-14-14-20-28-20S-23-4-28 10Z" fill="#3a2e2a"/>
                                <circle cx="25" cy="-17" r="11" fill="#3a2e2a"/>
                                <circle cx="-28" cy="20" r="2.6" fill="#f4c95d"/>
                                <circle cx="-9" cy="10" r="2" fill="#22302a"/>
                                <circle cx="9" cy="10" r="2" fill="#22302a"/>
                                <path d="M-6 19q6 5 12 0" stroke="#b7724a" stroke-width="1.8" stroke-linecap="round" fill="none"/>
                                <circle cx="-16" cy="18" r="3.4" fill="#ef9f8e" opacity=".4"/>
                                <circle cx="16" cy="18" r="3.4" fill="#ef9f8e" opacity=".4"/>
                            </g>
                        </g>
                    </g>

                    <!-- Koin terbang -->
                    <g class="scene-layer" data-depth="30">
                        <path d="M340 300C420 265 330 215 385 160C405 140 430 124 458 112" stroke="#e7c878" stroke-width="2" stroke-linecap="round" stroke-dasharray="3 8" fill="none" opacity=".75"/>

                        <g class="coin" transform="translate(352 288)">
                            <g class="coin-float coin--1">
                                <title>Koin RecyCash</title>
                                <circle r="11" fill="url(#gCoin)" stroke="#d9a62e" stroke-width="2"/>
                                <circle r="7" fill="none" stroke="#fff6d8" stroke-width="1.3"/>
                                <text y="3" text-anchor="middle" font-size="8" font-weight="800" fill="#8a5b00">Rp</text>
                            </g>
                        </g>
                        <g class="coin" transform="translate(382 250)">
                            <g class="coin-float coin--2">
                                <title>Koin RecyCash</title>
                                <circle r="13" fill="url(#gCoin)" stroke="#d9a62e" stroke-width="2"/>
                                <circle r="8.5" fill="none" stroke="#fff6d8" stroke-width="1.4"/>
                                <text y="3.4" text-anchor="middle" font-size="9" font-weight="800" fill="#8a5b00">Rp</text>
                            </g>
                        </g>
                        <g class="coin" transform="translate(340 214)">
                            <g class="coin-float coin--3">
                                <title>Koin RecyCash</title>
                                <circle r="10" fill="url(#gCoin)" stroke="#d9a62e" stroke-width="2"/>
                                <circle r="6.4" fill="none" stroke="#fff6d8" stroke-width="1.2"/>
                                <text y="2.8" text-anchor="middle" font-size="7.5" font-weight="800" fill="#8a5b00">Rp</text>
                            </g>
                        </g>
                        <g class="coin" transform="translate(408 200)">
                            <g class="coin-float coin--4">
                                <title>Koin RecyCash</title>
                                <circle r="14" fill="url(#gCoin)" stroke="#d9a62e" stroke-width="2"/>
                                <circle r="9.2" fill="none" stroke="#fff6d8" stroke-width="1.4"/>
                                <text y="3.6" text-anchor="middle" font-size="9.5" font-weight="800" fill="#8a5b00">Rp</text>
                            </g>
                        </g>
                        <g class="coin" transform="translate(374 158)">
                            <g class="coin-float coin--5">
                                <title>Koin RecyCash</title>
                                <circle r="11" fill="url(#gCoin)" stroke="#d9a62e" stroke-width="2"/>
                                <circle r="7" fill="none" stroke="#fff6d8" stroke-width="1.3"/>
                                <text y="3" text-anchor="middle" font-size="8" font-weight="800" fill="#8a5b00">Rp</text>
                            </g>
                        </g>
                        <g class="coin" transform="translate(432 138)">
                            <g class="coin-float coin--6">
                                <title>Koin RecyCash</title>
                                <circle r="12" fill="url(#gCoin)" stroke="#d9a62e" stroke-width="2"/>
                                <circle r="7.8" fill="none" stroke="#fff6d8" stroke-width="1.3"/>
                                <text y="3.2" text-anchor="middle" font-size="8.5" font-weight="800" fill="#8a5b00">Rp</text>
                            </g>
                        </g>

                        <g fill="#f0c75c">
                            <g transform="translate(318 166)"><path class="twinkle twinkle--1" d="M0-6 1.8-1.8 6 0 1.8 1.8 0 6-1.8 1.8-6 0-1.8-1.8Z"/></g>
                            <g transform="translate(440 218)"><path class="twinkle twinkle--2" d="M0-5 1.4-1.4 5 0 1.4 1.4 0 5-1.4 1.4-5 0-1.4-1.4Z"/></g>
                            <g transform="translate(355 244)"><path class="twinkle twinkle--3" d="M0-4 1.2-1.2 4 0 1.2 1.2 0 4-1.2 1.2-4 0-1.2-1.2Z"/></g>
                        </g>
                        <g fill="#9ad6ad">
                            <g transform="translate(462 140)"><path class="twinkle twinkle--2" d="M0-4 1.2-1.2 4 0 1.2 1.2 0 4-1.2 1.2-4 0-1.2-1.2Z"/></g>
                        </g>
                    </g>
                </svg>

                <div class="stat-chip stat-chip--top" aria-hidden="true">
                    <span class="stat-chip__icon stat-chip__icon--gold">Rp</span>
                    <span class="stat-chip__text"><strong>+Rp 24.500</strong><small>Koin sampahmu</small></span>
                </div>
                <div class="stat-chip stat-chip--bottom" aria-hidden="true">
                    <span class="stat-chip__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 4C10 5 5 10 4 20c10-1 15-6 16-16Z"/>
                            <path d="M9 15c2-4 5-7 9-9"/>
                        </svg>
                    </span>
                    <span class="stat-chip__text"><strong>12,4 kg</strong><small>Sampah terpilah</small></span>
                </div>
            </div>

            <div class="illu-trust">
                <div class="avatars" aria-hidden="true">
                    <span>AN</span><span>SR</span><span>DP</span>
                </div>
                <p><strong>2.400+ keluarga</strong> sudah menabung sampah</p>
            </div>

        </section>