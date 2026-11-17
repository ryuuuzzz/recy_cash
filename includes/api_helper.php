<?php
/**
 * Helper untuk endpoint JSON di folder api/.
 * Seluruh endpoint memakai session PHP (sama seperti halaman biasa).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Kirim response JSON lalu hentikan eksekusi. */
function apiJson(array $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Wajib sudah login. */
function apiWajibLogin(): void {
    if (empty($_SESSION['id_user'])) {
        apiJson(['ok' => false, 'message' => 'Sesi berakhir, silakan masuk kembali.'], 401);
    }
}

/** Wajib login dengan salah satu role yang diizinkan. */
function apiWajibRole(array $roles): void {
    apiWajibLogin();

    if (!in_array((string) ($_SESSION['role'] ?? ''), $roles, true)) {
        apiJson(['ok' => false, 'message' => 'Akses ditolak.'], 403);
    }
}

/** Ambil input teks dari GET/POST. */
function apiInput(string $key, string $default = ''): string {
    $val = $_POST[$key] ?? $_GET[$key] ?? $default;

    return trim((string) $val);
}

/** Ambil input angka dari GET/POST. */
function apiFloat(string $key, ?float $default = null): ?float {
    $val = $_POST[$key] ?? $_GET[$key] ?? null;

    if ($val === null || $val === '' || !is_numeric($val)) {
        return $default;
    }

    return (float) $val;
}

/** Ambil input integer dari GET/POST. */
function apiInt(string $key, int $default = 0): int {
    $val = apiFloat($key, null);

    return $val === null ? $default : (int) $val;
}
