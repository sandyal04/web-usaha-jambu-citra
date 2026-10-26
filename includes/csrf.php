<?php
/**
 * Pengelolaan Token CSRF (Cross-Site Request Forgery)
 * Sistem Informasi Usaha Tani Jambu Citra
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Mendapatkan atau membuat token CSRF sesi saat ini
 *
 * @return string
 */
function get_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Alias untuk get_csrf_token() guna menjaga kompatibilitas
 *
 * @return string
 */
function generate_csrf_token(): string
{
    return get_csrf_token();
}

/**
 * Menghasilkan input hidden HTML untuk form CSRF
 *
 * @return string
 */
function csrf_field(): string
{
    $token = get_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Memverifikasi validitas token CSRF dari request POST
 *
 * @param string|null $token
 * @return bool
 */
function verify_csrf_token(?string $token = null): bool
{
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? '';
    }

    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], (string) $token);
}

/**
 * Validasi ketat token CSRF: jika tidak valid, hentikan request dengan HTTP 403
 *
 * @return void
 */
function validate_csrf_or_die(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf_token()) {
            http_response_code(403);
            die('<div style="font-family:sans-serif;max-width:500px;margin:50px auto;padding:20px;border:1px solid #ef4444;border-radius:8px;background:#fef2f2;color:#991b1b;text-align:center;">
                <h3>Validasi Keamanan Gagal (403 Forbidden)</h3>
                <p>Token keamanan (CSRF) tidak valid atau telah kedaluwarsa. Silakan muat ulang halaman formulir dan coba kembali.</p>
                <p><a href="javascript:history.back()" style="display:inline-block;padding:8px 16px;background:#15803d;color:#fff;text-decoration:none;border-radius:6px;">Kembali</a></p>
            </div>');
        }
    }
}
