<?php
/**
 * Sidebar Navigasi Sistem Informasi Usaha Tani Jambu Citra
 */

declare(strict_types=1);

$activePage = $activePage ?? '';
?>
<aside id="sidebar">
    <div class="sidebar-brand">
        <div class="sidebar-brand-icon p-0 border-0" style="background: transparent; box-shadow: none;">
            <img src="<?= url('assets/img/logo.png'); ?>" alt="Logo Jambu Citra" style="width: 36px; height: 36px; object-fit: contain; border-radius: 50%;">
        </div>
        <div>
            <div class="lh-1">JAMBU CITRA</div>
            <small style="font-size:0.75rem; color:#86efac; font-weight:500;">Usaha Tani Keuangan</small>
        </div>
    </div>

    <ul class="sidebar-menu">
        <li class="sidebar-header">Ringkasan Utama</li>
        <li class="sidebar-item">
            <a href="<?= url('index.php'); ?>" class="sidebar-link <?= ($activePage === 'dashboard') ? 'active' : ''; ?>">
                <i class="bi bi-grid-1x2-fill"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="<?= url('reports/profit-analysis.php'); ?>" class="sidebar-link <?= ($activePage === 'profit_analysis') ? 'active' : ''; ?>">
                <i class="bi bi-graph-up-arrow"></i>
                <span>Analisis Keuntungan</span>
                <span class="badge bg-success-subtle text-success sidebar-badge">Laba Rugi</span>
            </a>
        </li>

        <li class="sidebar-header">Data Usaha</li>
        <li class="sidebar-item">
            <a href="<?= url('farms/index.php'); ?>" class="sidebar-link <?= ($activePage === 'farms') ? 'active' : ''; ?>">
                <i class="bi bi-tree-fill"></i>
                <span>Data Kebun</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="<?= url('cost-categories/index.php'); ?>" class="sidebar-link <?= ($activePage === 'cost_categories') ? 'active' : ''; ?>">
                <i class="bi bi-tags-fill"></i>
                <span>Kategori Biaya</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="<?= url('customers/index.php'); ?>" class="sidebar-link <?= ($activePage === 'customers') ? 'active' : ''; ?>">
                <i class="bi bi-people-fill"></i>
                <span>Data Pelanggan</span>
            </a>
        </li>

        <li class="sidebar-header">Operasional & Hasil</li>
        <li class="sidebar-item">
            <a href="<?= url('costs/index.php'); ?>" class="sidebar-link <?= ($activePage === 'costs') ? 'active' : ''; ?>">
                <i class="bi bi-cash-stack"></i>
                <span>Biaya Operasional</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="<?= url('harvests/index.php'); ?>" class="sidebar-link <?= ($activePage === 'harvests') ? 'active' : ''; ?>">
                <i class="bi bi-box-seam-fill"></i>
                <span>Pencatatan Panen</span>
            </a>
        </li>

        <li class="sidebar-header">Penjualan & Kas</li>
        <li class="sidebar-item">
            <a href="<?= url('sales/index.php'); ?>" class="sidebar-link <?= ($activePage === 'sales') ? 'active' : ''; ?>">
                <i class="bi bi-cart-check-fill"></i>
                <span>Penjualan Buah</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="<?= url('payments/index.php'); ?>" class="sidebar-link <?= ($activePage === 'payments') ? 'active' : ''; ?>">
                <i class="bi bi-wallet2"></i>
                <span>Pembayaran Tagihan</span>
            </a>
        </li>

        <li class="sidebar-header d-flex align-items-center justify-content-between">
            <span>Kategori Laporan</span>
            <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.65rem;">Lengkap</span>
        </li>
        <li class="sidebar-item">
            <a href="<?= url('reports/index.php'); ?>" class="sidebar-link <?= ($activePage === 'reports_hub') ? 'active' : ''; ?>">
                <i class="bi bi-folder2-open"></i>
                <span>Pusat Semua Laporan</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="<?= url('reports/costs-report.php'); ?>" class="sidebar-link <?= ($activePage === 'report_costs') ? 'active' : ''; ?>">
                <i class="bi bi-file-earmark-spreadsheet"></i>
                <span>Laporan Biaya</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="<?= url('reports/harvests-report.php'); ?>" class="sidebar-link <?= ($activePage === 'report_harvests') ? 'active' : ''; ?>">
                <i class="bi bi-file-earmark-bar-graph"></i>
                <span>Laporan Hasil Panen</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="<?= url('reports/sales-report.php'); ?>" class="sidebar-link <?= ($activePage === 'report_sales') ? 'active' : ''; ?>">
                <i class="bi bi-file-earmark-text"></i>
                <span>Laporan Penjualan</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="<?= url('reports/payments-report.php'); ?>" class="sidebar-link <?= ($activePage === 'report_payments') ? 'active' : ''; ?>">
                <i class="bi bi-journal-check"></i>
                <span>Laporan Kas & Piutang</span>
            </a>
        </li>
    </ul>

    <div class="sidebar-footer">
        <a href="<?= url('auth/profile.php'); ?>" class="d-flex align-items-center gap-2 text-decoration-none sidebar-footer-link mb-1">
            <div class="d-flex align-items-center justify-content-center rounded-circle bg-success text-white fw-bold flex-shrink-0"
                 style="width:34px;height:34px;font-size:0.95rem;background:linear-gradient(135deg,#15803d,#22c55e)!important;">
                <?= strtoupper(mb_substr($_SESSION['full_name'] ?? 'P', 0, 1)); ?>
            </div>
            <div class="overflow-hidden">
                <div class="text-white fw-semibold small text-truncate" style="max-width:150px;"><?= e($_SESSION['full_name'] ?? 'Pengelola'); ?></div>
                <div style="font-size: 0.72rem; color:#86efac;">Edit Profil &rsaquo;</div>
            </div>
        </a>
        <div class="d-flex justify-content-between align-items-center pt-2 border-top" style="border-color:rgba(255,255,255,0.08)!important;">
            <div style="font-size:0.72rem;color:#64748b;">Sistem Aktif</div>
            <a href="<?= url('auth/logout.php'); ?>" class="btn btn-sm btn-outline-danger border-0 p-1" title="Keluar dari Sistem">
                <i class="bi bi-box-arrow-right fs-5"></i>
            </a>
        </div>
    </div>
</aside>
