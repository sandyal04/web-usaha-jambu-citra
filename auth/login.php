<?php
/**
 * Halaman Login Pengguna Utama Usaha Tani Jambu Citra
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

// Jika sudah login, langsung ke dashboard
require_guest();

// Jika belum ada akun pengelola di database, arahkan ke halaman setup
if (!has_any_user()) {
    set_flash('info', 'Belum ada akun pengelola. Silakan lakukan inisialisasi akun pengelola pertama kali.');
    redirect('auth/setup.php');
    exit;
}

$errorMessage = '';
$usernameVal = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_die();

    if (is_login_throttled()) {
        $errorMessage = 'Terlalu banyak percobaan gagal. Silakan tunggu 5 menit sebelum mencoba kembali.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $usernameVal = $username;

        if (empty($username) || empty($password)) {
            $errorMessage = 'Username dan kata sandi wajib diisi.';
        } else {
            try {
                $pdo = get_db_connection();
                $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `username` = ? LIMIT 1");
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    // Login berhasil
                    login_user($user);
                    set_flash('success', 'Selamat datang kembali, ' . $user['full_name'] . '!');
                    redirect('index.php');
                    exit;
                } else {
                    record_failed_login();
                    $errorMessage = 'Username atau kata sandi tidak valid.';
                }
            } catch (PDOException $e) {
                error_log('Login PDO Error: ' . $e->getMessage());
                $errorMessage = 'Terjadi kesalahan sistem saat memproses login. Silakan coba sesaat lagi.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Pengelola - Usaha Tani Jambu Citra</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= url('assets/img/favicon.png'); ?>">
    <link rel="shortcut icon" href="<?= url('assets/img/favicon.png'); ?>">
    <link rel="apple-touch-icon" href="<?= url('assets/img/logo.png'); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= url('assets/css/style.css'); ?>">
</head>
<body class="bg-light">

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <div class="auth-logo p-0 border-0" style="background: transparent; box-shadow: none;">
                <img src="<?= url('assets/img/logo.png'); ?>" alt="Logo Jambu Citra" style="width: 72px; height: 72px; object-fit: contain; border-radius: 50%;" class="shadow-sm">
            </div>
            <h4 class="fw-bold mb-1">Usaha Tani Jambu Citra</h4>
            <p class="text-muted small mb-0">Sistem Informasi Pengelolaan Biaya & Analisis Keuntungan</p>
        </div>

        <?= render_flash(); ?>

        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger py-2 small mb-3 d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <div><?= e($errorMessage); ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <?= csrf_field(); ?>

            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" name="username" id="username" class="form-control" 
                           value="<?= e($usernameVal); ?>" placeholder="Masukkan username" required autofocus>
                </div>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label">Kata Sandi (Password)</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" name="password" id="password" class="form-control" 
                           placeholder="Masukkan kata sandi" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fs-6 shadow-sm">
                <i class="bi bi-box-arrow-in-right me-1"></i> Masuk ke Sistem
            </button>
        </form>

        <div class="mt-4 pt-3 border-top text-center text-muted small">
            <div>Sistem Pengelolaan Terpusat</div>
            <div>&copy; <?= date('Y'); ?> Usaha Tani Jambu Citra</div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
