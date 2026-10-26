<?php
/**
 * Modul Autentikasi dan Manajemen Sesi Pengguna Tunggal
 * Sistem Informasi Usaha Tani Jambu Citra
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    // Pengaturan cookie sesi yang aman
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

/**
 * Memeriksa apakah pengguna saat ini telah login
 *
 * @return bool
 */
function is_authenticated(): bool
{
    return !empty($_SESSION['user_id']) && !empty($_SESSION['username']);
}

/**
 * Mendapatkan data pengguna yang sedang login
 *
 * @return array|null
 */
function get_authenticated_user(): ?array
{
    if (!is_authenticated()) {
        return null;
    }

    return [
        'id'        => $_SESSION['user_id'],
        'username'  => $_SESSION['username'],
        'full_name' => $_SESSION['full_name'] ?? 'Pengelola Kebun',
        'email'     => $_SESSION['email'] ?? ''
    ];
}

/**
 * Mewajibkan login untuk mengakses halaman. Jika belum login, redirect ke form login.
 *
 * @return void
 */
function require_login(): void
{
    if (!is_authenticated()) {
        set_flash('warning', 'Silakan login terlebih dahulu untuk mengakses sistem.');
        redirect('auth/login.php');
        exit;
    }
}

/**
 * Mewajibkan status tamu (belum login). Jika sudah login, redirect ke dashboard.
 *
 * @return void
 */
function require_guest(): void
{
    if (is_authenticated()) {
        redirect('index.php');
        exit;
    }
}

/**
 * Mendaftarkan sesi pengguna setelah verifikasi login berhasil
 *
 * @param array $user Data baris tabel users
 * @return void
 */
function login_user(array $user): void
{
    // Regenerasi ID sesi untuk mencegah Session Fixation attack
    session_regenerate_id(true);

    $_SESSION['user_id']   = (int) $user['id'];
    $_SESSION['username']  = (string) $user['username'];
    $_SESSION['full_name'] = (string) $user['full_name'];
    $_SESSION['email']     = (string) ($user['email'] ?? '');
    $_SESSION['last_login']= time();

    // Reset hitungan percobaan login gagal
    unset($_SESSION['login_attempts'], $_SESSION['login_lockout_time']);
}

/**
 * Logout pengguna, menghapus seluruh data sesi secara aman
 *
 * @return void
 */
function logout_user(): void
{
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();
}

/**
 * Memeriksa apakah login terkunci karena terlalu banyak percobaan gagal (Throttling)
 *
 * @return bool True jika terkunci
 */
function is_login_throttled(): bool
{
    $lockoutDuration = 300; // 5 menit lockout
    $maxAttempts = 5;

    if (!empty($_SESSION['login_lockout_time'])) {
        if (time() < $_SESSION['login_lockout_time']) {
            return true;
        } else {
            // Masa lockout telah berakhir
            unset($_SESSION['login_lockout_time'], $_SESSION['login_attempts']);
        }
    }

    return false;
}

/**
 * Menambah hitungan percobaan login yang gagal
 *
 * @return void
 */
function record_failed_login(): void
{
    $maxAttempts = 5;
    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;

    if ($_SESSION['login_attempts'] >= $maxAttempts) {
        $_SESSION['login_lockout_time'] = time() + 300; // Lock 5 menit
    }
}

/**
 * Memeriksa apakah sistem sudah memiliki akun pengguna
 *
 * @return bool
 */
function has_any_user(): bool
{
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->query("SELECT COUNT(*) FROM `users`");
        return (int) $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        return false;
    }
}
