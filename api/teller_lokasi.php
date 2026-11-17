<?php
/**
 * API: lokasi outlet teller (petugas).
 *  GET  api/teller_lokasi.php           -> lokasi outlet saya
 *  POST api/teller_lokasi.php           -> simpan lokasi / status online
 */

require_once __DIR__ . '/../includes/api_helper.php';
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../includes/lokasi.php';
require_once __DIR__ . '/../includes/notifikasi.php';

apiWajibRole(['petugas']);

$petugasId = (int) ($_SESSION['id_user'] ?? 0);
pastikanTabelLokasi($pdo);

// GET: lokasi outlet saya
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $row = tellerLokasiAmbil($pdo, $petugasId);

    apiJson([
        'ok'      => true,
        'lokasi'  => $row ? [
            'nama_outlet'  => (string) $row['nama_outlet'],
            'alamat'       => (string) ($row['alamat'] ?? ''),
            'lat'          => (float) $row['latitude'],
            'lng'          => (float) $row['longitude'],
            'radius_km'    => (float) $row['radius_km'],
            'status_aktif' => (int) $row['status_aktif'] === 1,
            'catatan'      => (string) ($row['catatan'] ?? ''),
            'updated_at'   => (string) $row['updated_at'],
        ] : null,
        'provider' => lokasiProviderLabel(),
    ]);
}

// POST: simpan
$lat = apiFloat('lat');
$lng = apiFloat('lng');

if ($lat === null || $lng === null) {
    apiJson(['ok' => false, 'message' => 'Koordinat outlet wajib diisi.'], 400);
}

$berhasil = tellerLokasiSimpan($pdo, $petugasId, [
    'nama_outlet'  => apiInput('nama_outlet', 'Outlet'),
    'alamat'       => apiInput('alamat'),
    'lat'          => $lat,
    'lng'          => $lng,
    'radius_km'    => apiFloat('radius_km', 5.0),
    'status_aktif' => apiInput('status_aktif', '1') !== '0',
    'catatan'      => apiInput('catatan'),
]);

if (!$berhasil) {
    apiJson(['ok' => false, 'message' => 'Gagal menyimpan lokasi outlet.'], 500);
}

auditCatat($pdo, $petugasId, 'UPDATE_LOKASI_OUTLET', 'teller_locations', $petugasId,
    "Outlet " . apiInput('nama_outlet', 'Outlet') . ' @ ' . fmtKoordinat($lat, $lng));

apiJson([
    'ok'       => true,
    'message'  => 'Lokasi outlet berhasil disimpan.',
    'provider' => lokasiProviderLabel(),
]);
