<?php
/**
 * Header Layout Sistem Informasi Usaha Tani Jambu Citra
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';

// Cek autentikasi untuk halaman aplikasi (kecuali halaman auth tertentu)
if (!isset($isAuthPage) || !$isAuthPage) {
    require_login();
}

$currentUser = get_authenticated_user();
$pageTitle = $pageTitle ?? 'Sistem Informasi Usaha Tani Jambu Citra';
$activePage = $activePage ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle); ?> - Usaha Tani Jambu Citra</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= url('assets/img/favicon.png'); ?>">
    <link rel="shortcut icon" href="<?= url('assets/img/favicon.png'); ?>">
    <link rel="apple-touch-icon" href="<?= url('assets/img/logo.png'); ?>">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= url('assets/css/style.css'); ?>">
</head>
<body>

<div id="app-wrapper">
    <!-- Sidebar Navigasi -->
    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- Konten Utama -->
    <div id="main-content">
        <!-- Topbar Sticky -->
        <header id="topbar">
            <div class="d-flex align-items-center gap-2 gap-md-3">
                <button type="button" id="sidebar-toggle" class="btn btn-light d-lg-none p-2 border" aria-label="Buka Menu">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <a href="<?= url('index.php'); ?>" class="d-flex align-items-center text-decoration-none">
                    <img src="<?= url('assets/img/logo.png'); ?>" alt="Logo Jambu Citra" class="rounded-circle shadow-sm" style="width: 38px; height: 38px; object-fit: contain;">
                </a>
                <div>
                    <h5 class="mb-0 fw-bold text-dark lh-sm"><?= e($pageTitle); ?></h5>
                    <small class="text-muted d-none d-sm-block">Pengelolaan Biaya Operasional & Analisis Keuntungan</small>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <div class="text-end d-none d-md-block">
                    <span class="d-block fw-semibold text-dark fs-6"><?= e($currentUser['full_name'] ?? 'Pengelola'); ?></span>
                    <span class="badge bg-success-subtle text-success border border-success-subtle">Pengelola Utama</span>
                </div>
                <div class="dropdown">
                    <button class="btn btn-light border-0 dropdown-toggle d-flex align-items-center gap-2 p-1 pe-2 rounded-pill bg-light" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center" style="width:36px;height:36px;">
                            <i class="bi bi-person-fill fs-5"></i>
                        </div>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" style="min-width:220px;">
                        <li class="px-3 py-2 border-bottom">
                            <p class="mb-0 fw-bold"><?= e($currentUser['full_name'] ?? 'Pengguna'); ?></p>
                            <small class="text-muted">@<?= e($currentUser['username'] ?? 'user'); ?></small>
                        </li>
                        <li>
                            <a class="dropdown-item py-2" href="<?= url('auth/profile.php'); ?>">
                                <i class="bi bi-person-gear me-2 text-success"></i> Edit Profil
                            </a>
                        </li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                            <a class="dropdown-item py-2 text-danger" href="<?= url('auth/logout.php'); ?>">
                                <i class="bi bi-box-arrow-right me-2"></i> Keluar (Logout)
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Area Halaman -->
        <main class="page-container">
            <!-- Alert Pesan Flash -->
            <?= render_flash(); ?>
