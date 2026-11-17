<?php
/**
 * TEMPLATE konfigurasi koneksi database.
 * Salin file ini menjadi "koneksi.php" lalu isi sesuai environment Anda.
 *
 * cp config/koneksi.example.php config/koneksi.php
 */
$host   = "localhost";
$dbname = "db_bank_sampah";
$user   = "root";
$pass   = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}