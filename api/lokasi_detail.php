<?php
/**
 * API: detail koordinat dari place id (dipakai provider Google Places).
 * GET api/lokasi_detail.php?id=<place id>
 */

require_once __DIR__ . '/../includes/api_helper.php';
require_once __DIR__ . '/../includes/lokasi.php';

apiWajibLogin();

$id = apiInput('id');

if ($id === '') {
    apiJson(['ok' => false, 'message' => 'Place id tidak valid.'], 400);
}

$detail = lokasiDetail($id);

if (!$detail) {
    apiJson(['ok' => false, 'message' => 'Lokasi tidak ditemukan.'], 404);
}

apiJson([
    'ok'      => true,
    'id'      => $detail['id'],
    'label'   => $detail['label'],
    'detail'  => $detail['detail'],
    'lat'     => $detail['lat'],
    'lng'     => $detail['lng'],
]);
