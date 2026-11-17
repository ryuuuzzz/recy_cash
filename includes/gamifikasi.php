<?php

require_once __DIR__ . '/../config/koneksi.php';

function ambilTanggalSetoran(PDO $pdo, int $nasabahId): array
{
    $stmt = $pdo->prepare("
        SELECT DISTINCT DATE(created_at) AS tgl
        FROM deposit_transactions
        WHERE nasabah_id = :id
        ORDER BY tgl DESC
    ");
    $stmt->execute([':id' => $nasabahId]);
    return array_column($stmt->fetchAll(), 'tgl');
}

function hitungStreakHarian(array $tanggalSetoran): int
{
    if (empty($tanggalSetoran)) {
        return 0;
    }
    $tanggalSet = array_flip($tanggalSetoran);
    $hariIni    = date('Y-m-d');
    $kemarin    = date('Y-m-d', strtotime('-1 day'));

    $mulai = isset($tanggalSet[$hariIni]) ? $hariIni : (isset($tanggalSet[$kemarin]) ? $kemarin : null);
    if ($mulai === null) {
        return 0;
    }

    $streak = 0;
    $cursor = $mulai;
    while (isset($tanggalSet[$cursor])) {
        $streak++;
        $cursor = date('Y-m-d', strtotime($cursor . ' -1 day'));
    }
    return $streak;
}

function hitungSetoranPekanIni(array $tanggalSetoran): array
{
    $senin = date('Y-m-d', strtotime('monday this week'));
    $hariIni = date('Y-m-d');
    $map = array_fill_keys($tanggalSetoran, true);
    $hasil = [];
    for ($i = 0; $i < 7; $i++) {
        $tgl = date('Y-m-d', strtotime($senin . " +$i day"));
        if ($tgl <= $hariIni) {
            $hasil[] = [
                'tgl'      => $tgl,
                'ada'      => isset($map[$tgl]),
                'hari'     => date('D', strtotime($tgl)),
                'tanggal'  => date('j', strtotime($tgl)),
            ];
        }
    }
    return $hasil;
}

function tentukanRank(int $streakHari): array
{
    $tiers = [
        ['min' => 21, 'nama' => 'Legenda Hijau',   'ikon' => 'bi-trophy-fill',  'warna' => '#f59e0b', 'deskripsi' => '3 minggu penuh beruntun! Kamu legenda.'],
        ['min' => 14, 'nama' => 'Duta Lingkungan','ikon' => 'bi-award-fill',   'warna' => '#8b5cf6', 'deskripsi' => '2 minggu penuh beruntun, contoh nyata!'],
        ['min' => 7,  'nama' => 'Pahlawan Bumi',  'ikon' => 'bi-stars',        'warna' => '#10b981', 'deskripsi' => 'Seminggu penuh tanpa henti!'],
        ['min' => 4,  'nama' => 'Sahabat Bumi',   'ikon' => 'bi-tree-fill',    'warna' => '#22c55e', 'deskripsi' => 'Kebiasaan hijau mulai terbentuk.'],
        ['min' => 2,  'nama' => 'Penyetor Aktif', 'ikon' => 'bi-recycle',      'warna' => '#0ea5e9', 'deskripsi' => 'Setoranmu sudah konsisten.'],
        ['min' => 0,  'nama' => 'Pemula Hijau',   'ikon' => 'bi-leaf-fill',    'warna' => '#94a3b8', 'deskripsi' => 'Mulai langkah hijau-mu sekarang!'],
    ];
    foreach ($tiers as $t) {
        if ($streakHari >= $t['min']) {
            $idx = array_search($t, $tiers, true);
            $t['selanjutnya'] = $idx > 0 ? $tiers[$idx - 1] : null;
            $t['tingkat'] = count($tiers) - $idx;
            return $t;
        }
    }
    return $tiers[5];
}

function dataGamifikasi(PDO $pdo, int $nasabahId): array
{
    $tanggal   = ambilTanggalSetoran($pdo, $nasabahId);
    $streak    = hitungStreakHarian($tanggal);
    $pekanIni  = hitungSetoranPekanIni($tanggal);
    $rank      = tentukanRank($streak);

    $progress = 100;
    if (!empty($rank['selanjutnya'])) {
        $next     = $rank['selanjutnya'];
        $dasar    = $rank['min'];
        $progress = round((($streak - $dasar) / max(1, $next['min'] - $dasar)) * 100);
        $progress = max(0, min(100, $progress));
    }

    return [
        'tanggal_setoran'   => $tanggal,
        'streak_hari'       => $streak,
        'pekan_ini'         => $pekanIni,
        'rank'              => $rank,
        'progress_naik'     => $progress,
        'hari_minggu_penuh' => max(0, 7 - $streak),
    ];
}
