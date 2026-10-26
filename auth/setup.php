<?php
/**
 * Setup Akun Pengelola Utama Usaha Tani Jambu Citra
 * File ini hanya dapat digunakan saat sistem belum memiliki akun pengguna.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

$pdo = get_db_connection();

// Cek apakah akun pengelola sudah ada
if (has_any_user()) {
    set_flash('info', 'Akun pengelola utama sudah dibuat sebelumnya. Silakan login.');
    redirect('auth/login.php');
    exit;
}

$errors = [];
$old = [
    'username'  => '',
    'full_name' => '',
    'email'     => '',
    'phone'     => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_die();

    $username  = trim($_POST['username'] ?? '');
    $fullName  = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $password  = $_POST['password'] ?? '';
    $passConf  = $_POST['password_confirmation'] ?? '';

    $old['username']  = $username;
    $old['full_name'] = $fullName;
    $old['email']     = $email;
    $old['phone']     = $phone;

    // Validasi
    if (empty($username)) {
        $errors[] = 'Username wajib diisi.';
    } elseif (strlen($username) < 3 || strlen($username) > 30) {
        $errors[] = 'Username harus terdiri dari 3 hingga 30 karakter.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = 'Username hanya boleh memuat huruf, angka, dan garis bawah (_).';
    }

    if (empty($fullName)) {
        $errors[] = 'Nama lengkap pengelola wajib diisi.';
    }

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format alamat email tidak valid.';
    }

    if (empty($password)) {
        $errors[] = 'Kata sandi (password) wajib diisi.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Kata sandi minimal 6 karakter demi keamanan.';
    }

    if ($password !== $passConf) {
        $errors[] = 'Konfirmasi kata sandi tidak cocok dengan kata sandi.';
    }

    if (empty($errors)) {
        try {
            $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 11]);

            $stmt = $pdo->prepare("
                INSERT INTO `users` (`username`, `password_hash`, `full_name`, `email`, `phone`, `created_at`)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $username,
                $passwordHash,
                $fullName,
                $email ?: null,
                $phone ?: null
            ]);

            set_flash('success', 'Akun pengelola utama berhasil dibuat! Silakan login untuk mengelola usaha tani.');
            redirect('auth/login.php');
            exit;

        } catch (PDOException $e) {
            error_log('Setup Account Error: ' . $e->getMessage());
            $errors[] = 'Gagal menyimpan akun: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inisialisasi Akun Pengelola - Usaha Tani Jambu Citra</title>
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
            <h4 class="fw-bold mb-1">Inisialisasi Akun Utama</h4>
            <p class="text-muted small mb-0">Buat akun pengelola utama usaha tani jambu citra Anda.</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger py-2 small mb-3">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <?= csrf_field(); ?>

            <div class="mb-3">
                <label for="username" class="form-label">Username Pengelola <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" name="username" id="username" class="form-control" 
                           value="<?= e($old['username']); ?>" placeholder="Contoh: pengelola" required autofocus>
                </div>
                <small class="text-muted">Hanya huruf, angka, dan garis bawah (_)</small>
            </div>

            <div class="mb-3">
                <label for="full_name" class="form-label">Nama Lengkap Pengelola <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-card-text"></i></span>
                    <input type="text" name="full_name" id="full_name" class="form-control" 
                           value="<?= e($old['full_name']); ?>" placeholder="Contoh: H. Ahmad Subandi" required>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="phone" class="form-label">Nomor Telepon/WA</label>
                    <input type="text" name="phone" id="phone" class="form-control" 
                           value="<?= e($old['phone']); ?>" placeholder="08123456789">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="email" class="form-label">Email (Opsional)</label>
                    <input type="email" name="email" id="email" class="form-control" 
                           value="<?= e($old['email']); ?>" placeholder="ahmad@gmail.com">
                </div>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Kata Sandi (Password) <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-key"></i></span>
                    <input type="password" name="password" id="password" class="form-control" 
                           placeholder="Minimal 6 karakter" required>
                </div>
            </div>

            <div class="mb-4">
                <label for="password_confirmation" class="form-label">Konfirmasi Kata Sandi <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-check2-circle"></i></span>
                    <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" 
                           placeholder="Ulangi kata sandi" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fs-6 shadow-sm">
                <i class="bi bi-check-lg me-1"></i> Simpan & Buat Akun
            </button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
