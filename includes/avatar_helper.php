<?php

if (!function_exists('fotoProfilUrl')) {
    function fotoProfilUrl(?string $foto, string $baseUrl = ''): string
    {
        $foto = basename((string) $foto);

        if ($foto === '') {
            $foto = 'default.svg';
        }

        $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'profile' . DIRECTORY_SEPARATOR . $foto;

        if (!is_file($path)) {
            return '';
        }

        return rtrim($baseUrl, '/') . '/assets/img/profile/' . rawurlencode($foto);
    }
}

if (!function_exists('renderAvatarContent')) {
    function renderAvatarContent(?string $nama, ?string $foto, string $baseUrl = ''): string
    {
        $url = fotoProfilUrl($foto, $baseUrl);

        if ($url !== '') {
            return '<img src="' . htmlspecialchars($url) . '" alt="Foto ' . htmlspecialchars((string) $nama) . '">';
        }

        $nama    = trim((string) $nama);
        $initial = $nama !== '' ? strtoupper(mb_substr($nama, 0, 1)) : '?';

        return htmlspecialchars($initial);
    }
}

if (!function_exists('renderAvatar')) {
    function renderAvatar(?string $nama, ?string $foto, string $baseUrl = '', int $size = 36, string $extraClass = ''): string
    {
        $class = 'avatar-inline' . ($extraClass !== '' ? ' ' . $extraClass : '');

        return '<div class="' . htmlspecialchars($class) . '" style="width:' . $size . 'px;height:' . $size . 'px;">'
            . renderAvatarContent($nama, $foto, $baseUrl)
            . '</div>';
    }
}
