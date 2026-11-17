<?php
/**
 * TEMPLATE konfigurasi peta & lokasi (pencarian teller terdekat).
 * Salin file ini menjadi "maps_config.php" lalu isi dengan kredensial Anda.
 *
 * cp config/maps_config.example.php config/maps_config.php
 *
 * CARA PAKAI:
 * 1. Tanpa API key (default) -> memakai OpenStreetMap/Nominatim (GRATIS).
 *    Kotak pencarian tetap berfungsi seperti aplikasi ojek online, dan peta
 *    digambar dengan Leaflet + tile OpenStreetMap.
 *
 * 2. Ingin memakai Google Places (tampilan persis ala Grab/Gojek)?
 *    - Buka https://console.cloud.google.com
 *    - Aktifkan "Places API (New)" dan "Geocoding API"
 *    - Buat API key (billing wajib aktif di Google Cloud)
 *    - Tempel key-nya ke GOOGLE_MAPS_API_KEY di bawah
 *    - Ubah MAPS_PROVIDER menjadi 'google'
 *    Semua kode otomatis beralih ke provider Google tanpa perubahan lain.
 */
define('GOOGLE_MAPS_API_KEY', '');

// 'auto'  -> pakai Google bila key tersedia, selain itu OpenStreetMap
// 'google' -> paksa Google (perlu key)
// 'osm'   -> paksa OpenStreetMap
define('MAPS_PROVIDER', 'auto');

// Endpoint OpenStreetMap Nominatim (geocoding gratis, tanpa key)
define('NOMINATIM_URL', 'https://nominatim.openstreetmap.org/search');
define('NOMINATIM_REVERSE_URL', 'https://nominatim.openstreetmap.org/reverse');

// User-Agent wajib untuk kebijakan pemakaian Nominatim
define('MAPS_USER_AGENT', 'RecyCash/1.0 (aplikasi bank sampah digital)');

// Batas radius pencarian teller terdekat (km)
define('MAPS_MAX_RADIUS_KM', 30.0);

// Kecepatan rata-rata kota untuk menghitung estimasi tiba (km/jam)
define('MAPS_ETA_KECEPATAN_KMH', 25.0);

// Menit tambahan untuk waktu siap-siaga petugas
define('MAPS_ETA_MENIT_PERSIAPAN', 3);

// Zoom bawaan peta
define('MAPS_DEFAULT_ZOOM', 13);

// Maksimal hasil pencarian yang dikembalikan
define('MAPS_MAX_HASIL', 6);