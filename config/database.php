<?php
/**
 * Konfigurasi Koneksi Database MySQL menggunakan PDO
 * Sistem Informasi Usaha Tani Jambu Citra
 */

declare(strict_types=1);

// Parameter Konfigurasi Database
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_PORT')) define('DB_PORT', 3306);
if (!defined('DB_NAME')) define('DB_NAME', 'db_usaha_jambu_citra');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');

/**
 * Mengambil koneksi PDO database secara konsisten (Singleton)
 *
 * @return PDO
 * @throws PDOException
 */
function get_db_connection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            DB_HOST,
            (int) DB_PORT,
            DB_NAME
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Catat log error internal secara aman
            error_log('Database Connection Error: ' . $e->getMessage());

            // Tampilkan pesan bersahabat untuk pengguna lokal XAMPP
            $errorMessage = '<h3>Gagal Terhubung ke Database MySQL!</h3>';
            $errorMessage .= '<p>Pastikan layanan <strong>MySQL di XAMPP Control Panel</strong> sudah dalam status <em>Start (Running)</em> dan database <strong>' . htmlspecialchars(DB_NAME) . '</strong> sudah dibuat atau diimpor.</p>';
            $errorMessage .= '<p><small>Detail Teknis: ' . htmlspecialchars($e->getMessage()) . '</small></p>';

            // Hentikan eksekusi dengan pesan yang informatif
            die('<div style="font-family:sans-serif;max-width:650px;margin:40px auto;padding:24px;background:#fef2f2;border:1px solid #f87171;border-radius:8px;color:#991b1b;">' . $errorMessage . '</div>');
        }
    }

    return $pdo;
}
