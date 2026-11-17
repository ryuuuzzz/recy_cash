<?php
/**
 * Helper fitur "Teller Terdekat": lokasi teller, pencarian alamat (autocomplete),
 * perhitungan jarak (Haversine), pemilihan teller terdekat otomatis, dan
 * tabel chat dua arah antara nasabah & teller.
 *
 * Provider lokasi:
 *  - Google Places/Geocoding  (bila GOOGLE_MAPS_API_KEY diisi)
 *  - OpenStreetMap/Nominatim  (default, gratis tanpa key)
 */

require_once __DIR__ . '/../config/maps_config.php';
require_once __DIR__ . '/pickup_helper.php';

/* ============================== MIGRASI OTOMATIS ============================== */

/**
 * Tabel lokasi teller + chat pesan, serta kolom lokasi pada pickup_requests.
 * DDL tidak boleh dijalankan di dalam transaksi PDO (implicit commit MySQL).
 */
function pastikanTabelLokasi(PDO $pdo): void {
    static $siap = false;

    if ($siap || $pdo->inTransaction()) {
        return;
    }

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS teller_locations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            nama_outlet VARCHAR(120) NOT NULL DEFAULT 'Outlet',
            alamat VARCHAR(255) NULL,
            latitude DECIMAL(10,7) NOT NULL,
            longitude DECIMAL(10,7) NOT NULL,
            radius_km DECIMAL(5,2) NOT NULL DEFAULT 5.00,
            status_aktif TINYINT(1) NOT NULL DEFAULT 1,
            catatan VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_teller_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS chat_pesan (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_id INT NOT NULL,
            pengirim_id INT NOT NULL,
            pesan TEXT NOT NULL,
            dibaca TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_chat_request (request_id, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (PDOException $e) {
        return; // tabel gagal dibuat, biarkan fitur nonaktif
    }

    // Kolom lokasi pada pickup_requests
    pastikanKolomPickup($pdo, 'latitude', 'DECIMAL(10,7) NULL');
    pastikanKolomPickup($pdo, 'longitude', 'DECIMAL(10,7) NULL');
    pastikanKolomPickup($pdo, 'lokasi_label', 'VARCHAR(255) NULL');
    pastikanKolomPickup($pdo, 'eta_menit', 'INT NULL');
    pastikanKolomPickup($pdo, 'assign_at', 'DATETIME NULL');
    pastikanKolomPickup($pdo, 'tiba_at', 'DATETIME NULL');

    $siap = true;
}

/* ================================ UTILITAS ================================ */

/** Provider aktif: 'google' atau 'osm'. */
function lokasiProvider(): string {
    $provider = strtolower(trim((string) MAPS_PROVIDER));

    if ($provider === 'google' && GOOGLE_MAPS_API_KEY !== '') {
        return 'google';
    }
    if ($provider === 'osm') {
        return 'osm';
    }

    return GOOGLE_MAPS_API_KEY !== '' ? 'google' : 'osm';
}

/** Nama provider untuk ditampilkan di UI. */
function lokasiProviderLabel(): string {
    return lokasiProvider() === 'google'
        ? 'Google Places'
        : 'OpenStreetMap';
}

/** Permintaan HTTP dengan timeout & User-Agent. Return string|null. */
function lokasiHttp(string $url, string $method = 'GET', ?string $body = null, array $headers = []): ?string {
    $ua = (string) MAPS_USER_AGENT;
    $timeout = 8;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $head = array_merge(['User-Agent: ' . $ua, 'Accept: application/json'], $headers);

        if ($method === 'POST') {
            $head[] = 'Content-Type: application/json';
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER     => $head,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body ?? '');
        }

        $res = curl_exec($ch);
        $err = curl_errno($ch);
        curl_close($ch);

        return ($err === 0 && is_string($res) && $res !== '') ? $res : null;
    }

    $ctx = stream_context_create([
        'http' => [
            'method'        => $method,
            'timeout'       => $timeout,
            'ignore_errors' => true,
            'header'        => implode("\r\n", array_merge(
                ['User-Agent: ' . $ua, 'Accept: application/json'],
                $method === 'POST' ? ['Content-Type: application/json'] : [],
                $headers
            )),
        ],
    ]);

    $res = @file_get_contents($url, false, $ctx);

    return is_string($res) && $res !== '' ? $res : null;
}

/* ============================== GEOGRAFIS ============================== */

/** Jarak garis lurus (km) antara dua titik koordinat. */
function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float {
    $earthRadius = 6371.0;

    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);

    $a = sin($dLat / 2) ** 2
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

    return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

/** Format jarak untuk tampilan: "850 m" / "1,4 km". */
function fmtJarak(float $km): string {
    if ($km < 0.999) {
        return number_format($km * 1000, 0, ',', '.') . ' m';
    }
    return number_format($km, 1, ',', '.') . ' km';
}

/** Estimasi menit tiba di lokasi. */
function etaMenit(float $km): int {
    $kecepatan = max(5.0, (float) MAPS_ETA_KECEPATAN_KMH);
    $menit = (int) ceil(($km / $kecepatan) * 60) + (int) MAPS_ETA_MENIT_PERSIAPAN;

    return max(1, $menit);
}

/** Link navigasi Google Maps ke sebuah koordinat. */
function linkNavigasi(float $lat, float $lng, string $label = ''): string {
    $q = $label !== '' ? $label : $lat . ',' . $lng;

    return 'https://www.google.com/maps/dir/?api=1&destination='
        . rawurlencode($lat . ',' . $lng)
        . '&travelmode=driving';
}

/** Tampilkan koordinat ringkas di UI. */
function fmtKoordinat(?float $lat, ?float $lng): string {
    if ($lat === null || $lng === null) {
        return '-';
    }

    return number_format($lat, 5, ',', '.') . ', ' . number_format($lng, 5, ',', '.');
}

/* ============================ PENCARIAN ALAMAT ============================ */

/**
 * Cari alamat (autocomplete). Return array of:
 * ['id','label','detail','lat','lng','provider']
 */
function lokasiCari(string $q, int $limit = MAPS_MAX_HASIL): array {
    $q = trim($q);

    if (mb_strlen($q) < 3) {
        return [];
    }

    $limit = max(1, min(10, $limit));

    return lokasiProvider() === 'google'
        ? lokasiCariGoogle($q, $limit)
        : lokasiCariOsm($q, $limit);
}

/** Pencarian via OpenStreetMap Nominatim (gratis). */
function lokasiCariOsm(string $q, int $limit): array {
    $url = NOMINATIM_URL . '?' . http_build_query([
        'q'                => $q,
        'format'           => 'jsonv2',
        'limit'            => $limit,
        'countrycodes'     => 'id',
        'addressdetails'   => 1,
        'accept-language'  => 'id',
    ]);

    $raw = lokasiHttp($url);
    $data = $raw ? json_decode($raw, true) : null;

    if (!is_array($data)) {
        return [];
    }

    $hasil = [];

    foreach ($data as $item) {
        if (!isset($item['lat'], $item['lon'])) {
            continue;
        }

        $label = (string) ($item['display_name'] ?? $q);
        $nama  = (string) ($item['name'] ?? '');

        $hasil[] = [
            'id'       => 'osm:' . ($item['place_id'] ?? $item['osm_id'] ?? $label),
            'label'    => $label,
            'detail'   => $nama !== '' && $nama !== $label ? $nama : '',
            'lat'      => (float) $item['lat'],
            'lng'      => (float) $item['lon'],
            'provider' => 'osm',
        ];
    }

    return $hasil;
}

/** Pencarian via Google Places API (New). */
function lokasiCariGoogle(string $q, int $limit): array {
    $url = 'https://places.googleapis.com/v1/places:autocomplete';

    $body = json_encode([
        'input'              => $q,
        'languageCode'       => 'id',
        'regionCode'         => 'ID',
        'includedRegionCodes' => ['id'],
    ]);

    $raw = lokasiHttp($url, 'POST', $body, [
        'X-Goog-Api-Key: ' . GOOGLE_MAPS_API_KEY,
        'X-Goog-FieldMask: suggestions.placePrediction.placeId,suggestions.placePrediction.text,suggestions.placePrediction.structuredFormat',
    ]);

    $data = $raw ? json_decode($raw, true) : null;
    $hasil = [];

    foreach (($data['suggestions'] ?? []) as $s) {
        $pred = $s['placePrediction'] ?? [];

        if (empty($pred['placeId'])) {
            continue;
        }

        $teks  = (string) ($pred['text']['text'] ?? '');
        $main  = (string) ($pred['structuredFormat']['mainText']['text'] ?? '');
        $sek   = (string) ($pred['structuredFormat']['secondaryText']['text'] ?? '');

        $hasil[] = [
            'id'       => 'g:' . $pred['placeId'],
            'label'    => $sek !== '' ? $main . ' — ' . $sek : $teks,
            'detail'   => $main !== '' && $main !== $teks ? $main : '',
            'lat'      => null, // diambil lewat lokasiDetail()
            'lng'      => null,
            'provider' => 'google',
        ];

        if (count($hasil) >= $limit) {
            break;
        }
    }

    return $hasil;
}

/** Ambil detail lokasi (lat/lng) dari place id. */
function lokasiDetail(string $placeId): ?array {
    if (str_starts_with($placeId, 'osm:')) {
        return null; // OSM sudah menyertakan koordinat saat pencarian
    }

    if (!str_starts_with($placeId, 'g:')) {
        return null;
    }

    $id = substr($placeId, 2);
    $url = 'https://places.googleapis.com/v1/places/' . rawurlencode($id) . '?' . http_build_query([
        'key'    => GOOGLE_MAPS_API_KEY,
        'fields' => 'location,displayName,formattedAddress',
    ]);

    $raw = lokasiHttp($url);
    $data = $raw ? json_decode($raw, true) : null;

    if (!is_array($data) || empty($data['location'])) {
        return null;
    }

    return [
        'id'       => $placeId,
        'label'    => (string) ($data['formattedAddress'] ?? $data['displayName']['text'] ?? ''),
        'detail'   => (string) ($data['displayName']['text'] ?? ''),
        'lat'      => (float) $data['location']['latitude'],
        'lng'      => (float) $data['location']['longitude'],
        'provider' => 'google',
    ];
}

/** Nama alamat dari koordinat (reverse geocoding), best-effort. */
function lokasiReverse(float $lat, float $lng): string {
    if (lokasiProvider() === 'google') {
        $url = 'https://maps.googleapis.com/maps/api/geocode/json?' . http_build_query([
            'latlng' => $lat . ',' . $lng,
            'key'    => GOOGLE_MAPS_API_KEY,
            'language' => 'id',
        ]);

        $raw = lokasiHttp($url);
        $data = $raw ? json_decode($raw, true) : null;

        if (!empty($data['results'][0]['formatted_address'])) {
            return (string) $data['results'][0]['formatted_address'];
        }

        return '';
    }

    $url = NOMINATIM_REVERSE_URL . '?' . http_build_query([
        'lat'             => $lat,
        'lon'             => $lng,
        'format'          => 'jsonv2',
        'accept-language' => 'id',
    ]);

    $raw = lokasiHttp($url);
    $data = $raw ? json_decode($raw, true) : null;

    return (string) ($data['display_name'] ?? '');
}

/* ============================ LOKASI TELLER ============================ */

/** Ambil data lokasi outlet milik seorang petugas. */
function tellerLokasiAmbil(PDO $pdo, int $petugasId): ?array {
    if ($petugasId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT * FROM teller_locations WHERE user_id = :uid LIMIT 1");
    $stmt->execute([':uid' => $petugasId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

/** Simpan (atau perbarui) lokasi outlet petugas. */
function tellerLokasiSimpan(PDO $pdo, int $petugasId, array $data): bool {
    if ($petugasId <= 0) {
        return false;
    }

    $lat = isset($data['lat']) ? (float) $data['lat'] : 0.0;
    $lng = isset($data['lng']) ? (float) $data['lng'] : 0.0;

    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        return false;
    }

    $nama   = mb_substr(trim((string) ($data['nama_outlet'] ?? 'Outlet')), 0, 120);
    $alamat = mb_substr(trim((string) ($data['alamat'] ?? '')), 0, 255);
    $radius = isset($data['radius_km']) ? max(0.5, min(50.0, (float) $data['radius_km'])) : 5.0;
    $aktif  = !empty($data['status_aktif']) ? 1 : 0;
    $note   = mb_substr(trim((string) ($data['catatan'] ?? '')), 0, 255);

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO teller_locations (user_id, nama_outlet, alamat, latitude, longitude, radius_km, status_aktif, catatan)
             VALUES (:uid, :nama, :alamat, :lat, :lng, :radius, :aktif, :catatan)
             ON DUPLICATE KEY UPDATE
                nama_outlet = VALUES(nama_outlet),
                alamat      = VALUES(alamat),
                latitude    = VALUES(latitude),
                longitude   = VALUES(longitude),
                radius_km   = VALUES(radius_km),
                status_aktif= VALUES(status_aktif),
                catatan     = VALUES(catatan)"
        );

        $stmt->execute([
            ':uid'    => $petugasId,
            ':nama'   => $nama !== '' ? $nama : 'Outlet',
            ':alamat' => $alamat !== '' ? $alamat : null,
            ':lat'    => $lat,
            ':lng'    => $lng,
            ':radius' => $radius,
            ':aktif'  => $aktif,
            ':catatan'=> $note !== '' ? $note : null,
        ]);

        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Daftar teller aktif diurutkan dari yang terdekat.
 * Return array of ['user_id','nama','nama_outlet','alamat','lat','lng','jarak_km','eta_menit','telepon','foto']
 */
function tellerTerdekat(PDO $pdo, float $lat, float $lng, int $limit = 10): array {
    pastikanTabelLokasi($pdo);

    try {
        $stmt = $pdo->query(
            "SELECT tl.user_id, tl.nama_outlet, tl.alamat, tl.latitude, tl.longitude, tl.radius_km, tl.status_aktif,
                    u.nama, u.nomor_hp, u.foto
             FROM teller_locations tl
             INNER JOIN users u ON u.id = tl.user_id
             WHERE tl.status_aktif = 1 AND u.status_akun = 'approved'
             ORDER BY tl.updated_at DESC"
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }

    $hasil = [];

    foreach ($rows as $row) {
        $jarak = haversineKm($lat, $lng, (float) $row['latitude'], (float) $row['longitude']);

        $hasil[] = [
            'user_id'     => (int) $row['user_id'],
            'nama'        => (string) $row['nama'],
            'nama_outlet' => (string) ($row['nama_outlet'] ?: 'Outlet ' . $row['nama']),
            'alamat'      => (string) ($row['alamat'] ?? ''),
            'lat'         => (float) $row['latitude'],
            'lng'         => (float) $row['longitude'],
            'radius_km'   => (float) $row['radius_km'],
            'jarak_km'    => $jarak,
            'eta_menit'   => etaMenit($jarak),
            'telepon'     => (string) ($row['nomor_hp'] ?? ''),
            'foto'        => (string) ($row['foto'] ?? ''),
        ];
    }

    usort($hasil, static fn(array $a, array $b): int => $a['jarak_km'] <=> $b['jarak_km']);

    // Batasi radius pencarian maksimal
    $maks = (float) MAPS_MAX_RADIUS_KM;
    $hasil = array_values(array_filter($hasil, static fn(array $t): bool => $t['jarak_km'] <= $maks));

    return array_slice($hasil, 0, max(1, $limit));
}

/**
 * Pilih 1 teller terdekat (untuk auto-assign). Return null bila tidak ada teller.
 */
function tellerPilihTerdekat(PDO $pdo, float $lat, float $lng, int $excludeId = 0): ?array {
    $daftar = tellerTerdekat($pdo, $lat, $lng, 10);

    foreach ($daftar as $t) {
        if ($excludeId > 0 && (int) $t['user_id'] === $excludeId) {
            continue;
        }

        return $t;
    }

    return null;
}

/* ================================ CHAT ================================ */

/** Pesan chat untuk sebuah request penjemputan. */
function chatPesanAmbil(PDO $pdo, int $requestId, int $limit = 100): array {
    pastikanTabelLokasi($pdo);

    try {
        $stmt = $pdo->prepare(
            "SELECT c.id, c.request_id, c.pengirim_id, c.pesan, c.dibaca, c.created_at,
                    u.nama AS nama_pengirim, u.foto AS foto_pengirim, u.role AS role_pengirim
             FROM chat_pesan c
             LEFT JOIN users u ON u.id = c.pengirim_id
             WHERE c.request_id = :rid
             ORDER BY c.id ASC
             LIMIT $limit"
        );
        $stmt->execute([':rid' => $requestId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/** Simpan pesan chat baru. Return id pesan atau 0 bila gagal. */
function chatPesanSimpan(PDO $pdo, int $requestId, int $pengirimId, string $pesan): int {
    if ($requestId <= 0 || $pengirimId <= 0) {
        return 0;
    }

    $pesan = trim($pesan);

    if ($pesan === '') {
        return 0;
    }

    pastikanTabelLokasi($pdo);

    try {
        $stmt = $pdo->prepare("INSERT INTO chat_pesan (request_id, pengirim_id, pesan) VALUES (:rid, :uid, :pesan)");
        $stmt->execute([
            ':rid'   => $requestId,
            ':uid'   => $pengirimId,
            ':pesan' => mb_substr($pesan, 0, 2000),
        ]);

        return (int) $pdo->lastInsertId();
    } catch (PDOException $e) {
        return 0;
    }
}

/** Tandai pesan sudah dibaca oleh penerima. */
function chatPesanTandaiDibaca(PDO $pdo, int $requestId, int $penerimaId): void {
    try {
        $stmt = $pdo->prepare("UPDATE chat_pesan SET dibaca = 1 WHERE request_id = :rid AND pengirim_id <> :uid AND dibaca = 0");
        $stmt->execute([':rid' => $requestId, ':uid' => $penerimaId]);
    } catch (PDOException $e) {
        // abaikan
    }
}

/**
 * Apakah user boleh mengakses percakapan request ini?
 * (nasabah pemilik request atau teller yang ditugaskan)
 */
function chatBolehAkses(PDO $pdo, int $requestId, int $userId): bool {
    try {
        $stmt = $pdo->prepare("SELECT nasabah_id, petugas_id FROM pickup_requests WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $requestId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return false;
    }

    if (!$row) {
        return false;
    }

    return (int) $row['nasabah_id'] === $userId || (int) ($row['petugas_id'] ?? 0) === $userId;
}
