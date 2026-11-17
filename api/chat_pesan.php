<?php
/**
 * API: percakapan chat nasabah <-> teller untuk sebuah request penjemputan.
 *  GET  api/chat_pesan.php?request_id=<id>            -> daftar pesan
 *  POST api/chat_pesan.php?request_id=<id>&pesan=<isi> -> kirim pesan
 */

require_once __DIR__ . '/../includes/api_helper.php';
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../includes/lokasi.php';
require_once __DIR__ . '/../includes/notifikasi.php';

apiWajibRole(['nasabah', 'petugas', 'admin']);

$userId = (int) ($_SESSION['id_user'] ?? 0);
$requestId = apiInt('request_id');

if ($requestId <= 0) {
    apiJson(['ok' => false, 'message' => 'Request tidak valid.'], 400);
}

if (!chatBolehAkses($pdo, $requestId, $userId)) {
    apiJson(['ok' => false, 'message' => 'Anda tidak memiliki akses ke percakapan ini.'], 403);
}

// Data request + lawan bicara
$stmt = $pdo->prepare(
    "SELECT p.*, ns.nama AS nama_nasabah, ns.nomor_hp AS hp_nasabah, ns.foto AS foto_nasabah, ns.alamat AS alamat_nasabah,
            pt.nama AS nama_petugas, pt.nomor_hp AS hp_petugas, pt.foto AS foto_petugas
     FROM pickup_requests p
     LEFT JOIN users ns ON ns.id = p.nasabah_id
     LEFT JOIN users pt ON pt.id = p.petugas_id
     WHERE p.id = :id LIMIT 1"
);
$stmt->execute([':id' => $requestId]);
$req = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$req) {
    apiJson(['ok' => false, 'message' => 'Request tidak ditemukan.'], 404);
}

$userRole = (string) ($_SESSION['role'] ?? '');
$isNasabah = $userRole === 'nasabah';

$lawan = $isNasabah
    ? [
        'nama'    => (string) ($req['nama_petugas'] ?? 'Petugas'),
        'telepon' => (string) ($req['hp_petugas'] ?? ''),
        'foto'    => (string) ($req['foto_petugas'] ?? ''),
        'role'    => 'petugas',
    ]
    : [
        'nama'    => (string) ($req['nama_nasabah'] ?? 'Nasabah'),
        'telepon' => (string) ($req['hp_nasabah'] ?? ''),
        'foto'    => (string) ($req['foto_nasabah'] ?? ''),
        'role'    => 'nasabah',
        'alamat'  => (string) ($req['alamat_jemput'] ?? ''),
    ];

/* ------------------------------ KIRIM PESAN ------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pesan = trim((string) ($_POST['pesan'] ?? ''));

    if ($pesan === '') {
        apiJson(['ok' => false, 'message' => 'Pesan tidak boleh kosong.'], 400);
    }

    $idPesan = chatPesanSimpan($pdo, $requestId, $userId, $pesan);

    if ($idPesan <= 0) {
        apiJson(['ok' => false, 'message' => 'Gagal mengirim pesan.'], 500);
    }

    // Kabari lawan bicara lewat notifikasi in-app (best-effort)
    $lawanId = $isNasabah ? (int) ($req['petugas_id'] ?? 0) : (int) ($req['nasabah_id'] ?? 0);

    if ($lawanId > 0) {
        $preview = mb_substr($pesan, 0, 80);
        notifBuat($pdo, $lawanId, 'Pesan baru JMP-' . $requestId . ': ' . $preview, 'chat.php?request_id=' . $requestId);
    }

    auditCatat($pdo, $userId, 'KIRIM_CHAT', 'chat_pesan', $idPesan, 'JMP-' . $requestId . ': ' . mb_substr($pesan, 0, 200));

    // Ambil waktu dari database agar konsisten dengan data tersimpan
    $stmtWaktu = $pdo->prepare("SELECT created_at FROM chat_pesan WHERE id = :id");
    $stmtWaktu->execute([':id' => $idPesan]);
    $waktuDb = (string) ($stmtWaktu->fetchColumn() ?: date('Y-m-d H:i:s'));

    apiJson([
        'ok'       => true,
        'id'       => $idPesan,
        'waktu'    => $waktuDb,
        'message'  => 'Pesan terkirim.',
    ]);
}

/* ------------------------------ DAFTAR PESAN ------------------------------ */
$pesanList = chatPesanAmbil($pdo, $requestId);
chatPesanTandaiDibaca($pdo, $requestId, $userId);

$out = [];

foreach ($pesanList as $p) {
    $out[] = [
        'id'           => (int) $p['id'],
        'pengirim_id'  => (int) $p['pengirim_id'],
        'saya'         => (int) $p['pengirim_id'] === $userId,
        'nama'         => (string) ($p['nama_pengirim'] ?? ''),
        'pesan'        => (string) $p['pesan'],
        'waktu'        => (string) $p['created_at'],
        'waktu_teks'   => date('d M Y H:i', strtotime((string) $p['created_at'])),
        'dibaca'       => (int) $p['dibaca'] === 1,
    ];
}

apiJson([
    'ok'       => true,
    'total'    => count($out),
    'messages' => $out,
    'lawan'    => $lawan,
]);
