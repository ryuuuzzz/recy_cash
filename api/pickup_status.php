<?php
/**
 * API: status live request penjemputan (dipakai polling di halaman").
 * GET api/pickup_status.php?id=<id>
 */

require_once __DIR__ . '/../includes/api_helper.php';
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../includes/lokasi.php';

apiWajibRole(['nasabah', 'petugas', 'admin']);

$userId   = (int) ($_SESSION['id_user'] ?? 0);
$requestId = apiInt('id');

if ($requestId <= 0) {
    apiJson(['ok' => false, 'message' => 'Request tidak valid.'], 400);
}

$stmt = $pdo->prepare(
    "SELECT p.*, pt.nama AS nama_petugas, pt.nomor_hp AS hp_petugas, pt.foto AS foto_petugas
     FROM pickup_requests p
     LEFT JOIN users pt ON pt.id = p.petugas_id
     WHERE p.id = :id LIMIT 1"
);
$stmt->execute([':id' => $requestId]);
$req = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$req) {
    apiJson(['ok' => false, 'message' => 'Request tidak ditemukan.'], 404);
}

$boleh = (int) $req['nasabah_id'] === $userId || (int) ($req['petugas_id'] ?? 0) === $userId;

if (!$boleh) {
    apiJson(['ok' => false, 'message' => 'Akses ditolak.'], 403);
}

// Lokasi teller saat ini (untuk jarak & ETA)
$jarakKm  = null;
$etaMenit = (int) ($req['eta_menit'] ?? 0);
$tellerLokasi = null;

if (!empty($req['petugas_id']) && $req['latitude'] !== null && $req['longitude'] !== null) {
    $tellerLokasi = tellerLokasiAmbil($pdo, (int) $req['petugas_id']);

    if ($tellerLokasi) {
        $jarakKm = haversineKm(
            (float) $req['latitude'],
            (float) $req['longitude'],
            (float) $tellerLokasi['latitude'],
            (float) $tellerLokasi['longitude']
        );
        $etaMenit = etaMenit($jarakKm);
    }
}

// Jumlah pesan belum dibaca
$stmtUnread = $pdo->prepare("SELECT COUNT(*) FROM chat_pesan WHERE request_id = :rid AND pengirim_id <> :uid AND dibaca = 0");
$stmtUnread->execute([':rid' => $requestId, ':uid' => $userId]);
$unread = (int) $stmtUnread->fetchColumn();

$status = (string) $req['status'];

$label = [
    'pending'   => 'Menunggu teller',
    'diproses'  => 'Teller dalam perjalanan',
    'disetujui' => 'Disetujui',
    'selesai'   => 'Selesai',
    'ditolak'   => 'Ditolak',
];

apiJson([
    'ok'         => true,
    'id'         => $requestId,
    'status'     => $status,
    'status_teks'=> $label[$status] ?? ucfirst($status),
    'tiba'       => !empty($req['tiba_at']),
    'tiba_at'    => $req['tiba_at'] ?? null,
    'eta_menit'  => $etaMenit,
    'jarak_km'   => $jarakKm !== null ? round($jarakKm, 2) : null,
    'jarak_teks' => $jarakKm !== null ? fmtJarak($jarakKm) : null,
    'chat_unread'=> $unread,
    'teller'     => !empty($req['petugas_id']) ? [
        'nama'    => (string) ($req['nama_petugas'] ?? ''),
        'telepon' => (string) ($req['hp_petugas'] ?? ''),
        'foto'    => (string) ($req['foto_petugas'] ?? ''),
        'lat'     => $tellerLokasi ? (float) $tellerLokasi['latitude'] : null,
        'lng'     => $tellerLokasi ? (float) $tellerLokasi['longitude'] : null,
    ] : null,
]);
