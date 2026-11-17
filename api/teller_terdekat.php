<?php
/**
 * API: daftar teller terdekat dari sebuah koordinat.
 * GET api/teller_terdekat.php?lat=<lat>&lng=<lng>
 */

require_once __DIR__ . '/../includes/api_helper.php';
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../includes/lokasi.php';

apiWajibRole(['nasabah', 'petugas', 'admin']);

$lat = apiFloat('lat');
$lng = apiFloat('lng');

if ($lat === null || $lng === null || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    apiJson(['ok' => false, 'message' => 'Koordinat tidak valid.'], 400);
}

$daftar = tellerTerdekat($pdo, $lat, $lng, 10);

apiJson([
    'ok'       => true,
    'provider' => lokasiProviderLabel(),
    'jumlah'   => count($daftar),
    'tellers'  => array_map(static function (array $t): array {
        return [
            'user_id'     => $t['user_id'],
            'nama'        => $t['nama'],
            'nama_outlet' => $t['nama_outlet'],
            'alamat'      => $t['alamat'],
            'lat'         => $t['lat'],
            'lng'         => $t['lng'],
            'jarak_km'    => round($t['jarak_km'], 2),
            'jarak_teks'  => fmtJarak($t['jarak_km']),
            'eta_menit'   => $t['eta_menit'],
            'telepon'     => $t['telepon'],
        ];
    }, $daftar),
]);
