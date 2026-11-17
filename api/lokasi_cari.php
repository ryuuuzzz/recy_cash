<?php
/**
 * API: pencarian alamat (autocomplete) ala ojek online.
 * GET api/lokasi_cari.php?q=<kata kunci>
 */

require_once __DIR__ . '/../includes/api_helper.php';
require_once __DIR__ . '/../includes/lokasi.php';

apiWajibLogin();

$q = apiInput('q');

if (mb_strlen($q) < 3) {
    apiJson([
        'ok'       => true,
        'provider' => lokasiProviderLabel(),
        'results'  => [],
    ]);
}

$hasil = lokasiCari($q);

apiJson([
    'ok'       => true,
    'provider' => lokasiProviderLabel(),
    'results'  => array_values(array_map(static function (array $r): array {
        return [
            'id'     => $r['id'],
            'label'  => $r['label'],
            'detail' => $r['detail'],
            'lat'    => $r['lat'],
            'lng'    => $r['lng'],
        ];
    }, $hasil)),
]);
