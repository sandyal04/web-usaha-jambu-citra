<?php
/**
 * Modul Laporan - Pusat Kategori Laporan Keuangan & Analisis
 * Sistem Informasi Usaha Tani Jambu Citra
 */

declare(strict_types=1);

$pageTitle = 'Pusat Laporan Keuangan & Analisis';
$activePage = 'reports_hub';

require_once __DIR__ . '/../includes/header.php';

$pdo = get_db_connection();

// Filter Periode Cepat
$currentYear = (int) date('Y');
$selectedYear = isset($_GET['year']) && $_GET['year'] !== '' ? (int) $_GET['year'] : $currentYear;

// Ringkasan Finansial Tahun Berjalan
$stmtRev = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0), COUNT(id) FROM sales WHERE status = 'confirmed' AND YEAR(sale_date) = ?");
$stmtRev->execute([$selectedYear]);
$revData = $stmtRev->fetch();
$yearRev = (float) $revData['COALESCE(SUM(total_amount), 0)'];
$yearSalesCount = (int) $revData['COUNT(id)'];

$stmtCost = $pdo->prepare("SELECT COALESCE(SUM(total_cost), 0), COUNT(id) FROM operational_costs WHERE YEAR(cost_date) = ?");
$stmtCost->execute([$selectedYear]);
$costData = $stmtCost->fetch();
$yearCost = (float) $costData['COALESCE(SUM(total_cost), 0)'];
$yearCostCount = (int) $costData['COUNT(id)'];

$stmtHrv = $pdo->prepare("SELECT COALESCE(SUM(weight_kg), 0), COUNT(id) FROM harvests WHERE YEAR(harvest_date) = ?");
$stmtHrv->execute([$selectedYear]);
$hrvData = $stmtHrv->fetch();
$yearHarvest = (float) $hrvData['COALESCE(SUM(weight_kg), 0)'];
$yearHarvestCount = (int) $hrvData['COUNT(id)'];

$stmtPay = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM sale_payments WHERE YEAR(payment_date) = ?");
$stmtPay->execute([$selectedYear]);
$yearPaid = (float) $stmtPay->fetchColumn();

$yearProfit = $yearRev - $yearCost;
$yearMargin = calc_margin($yearProfit, $yearRev);
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-folder2-open text-success me-2"></i>Pusat Laporan Keuangan & Analisis</h4>
        <p class="text-muted small mb-0">Akses seluruh modul pembukuan, evaluasi keuntungan usaha tani, dan cetak laporan resmi.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <form method="GET" action="" class="d-flex align-items-center gap-2">
            <label class="form-label small mb-0 fw-semibold text-secondary">Tahun:</label>
            <select name="year" class="form-select form-select-sm" onchange="this.form.submit();">
                <?php for ($y = $currentYear + 1; $y >= $currentYear - 4; $y--): ?>
                    <option value="<?= $y; ?>" <?= $selectedYear === $y ? 'selected' : ''; ?>>Tahun <?= $y; ?></option>
                <?php endfor; ?>
            </select>
        </form>
    </div>
</div>

<!-- Banner Ringkasan Finansial -->
<div class="card border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #09261a 0%, #15803d 100%); color: #fff;">
    <div class="card-body p-4">
        <div class="row g-3 align-items-center">
            <div class="col-lg-8">
                <span class="badge bg-success-subtle text-success mb-2 px-3 py-1 fw-bold">IKHTISAR KEUANGAN TAHUN <?= $selectedYear; ?></span>
                <h3 class="fw-bold mb-1">Performa Keuangan Usaha Tani Jambu Citra</h3>
                <p class="mb-0 opacity-75 small">Data pembukuan terintegrasi otomatis dari seluruh transaksi penjualan sah, hasil panen, dan realisasi biaya kebun.</p>
            </div>
            <div class="col-lg-4 text-lg-end">
                <div class="d-inline-block text-start bg-white text-dark p-3 rounded-3 shadow-sm">
                    <small class="text-muted text-uppercase fw-semibold d-block">Keuntungan Bersih Tahun <?= $selectedYear; ?></small>
                    <span class="fs-4 fw-bold <?= $yearProfit >= 0 ? 'text-success' : 'text-danger'; ?>">
                        <?= format_rupiah($yearProfit); ?>
                    </span>
                    <div class="small text-muted mt-1">Margin: <?= format_margin($yearMargin); ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Daftar Kategori Laporan Lengkap -->
<div class="row g-4 mb-4">
    <!-- 1. Analisis Keuntungan Operasional (Laba Rugi) -->
    <div class="col-md-6 col-xl-4">
        <div class="card h-100 shadow-sm border-0 report-card">
            <div class="card-body p-4 d-flex flex-column">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="stat-icon blue">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Laba Rugi</span>
                </div>
                <h5 class="fw-bold text-dark mb-2">Analisis Keuntungan Operasional</h5>
                <p class="text-muted small flex-grow-1 mb-3">
                    Perhitungan laba bersih usaha tani: Total Pendapatan Penjualan dikurangi seluruh Biaya Operasional pada rentang tanggal yang dipilih, lengkap dengan persentase margin keuntungan.
                </p>
                <div class="p-2 bg-light rounded-2 border mb-3 small d-flex justify-content-between">
                    <span class="text-secondary">Laba Bersih Tahun Ini:</span>
                    <strong class="<?= $yearProfit >= 0 ? 'text-success' : 'text-danger'; ?>"><?= format_rupiah($yearProfit); ?></strong>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= url('reports/profit-analysis.php'); ?>" class="btn btn-primary btn-sm flex-grow-1">
                        <i class="bi bi-eye me-1"></i> Buka Analisis
                    </a>
                    <a href="<?= url('reports/export-csv.php?type=profit'); ?>" class="btn btn-outline-success btn-sm" title="Download CSV">
                        <i class="bi bi-download"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Laporan Biaya Operasional -->
    <div class="col-md-6 col-xl-4">
        <div class="card h-100 shadow-sm border-0 report-card">
            <div class="card-body p-4 d-flex flex-column">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="stat-icon red">
                        <i class="bi bi-file-earmark-spreadsheet-fill"></i>
                    </div>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Pengeluaran</span>
                </div>
                <h5 class="fw-bold text-dark mb-2">Laporan Biaya Operasional</h5>
                <p class="text-muted small flex-grow-1 mb-3">
                    Rincian pengeluaran pupuk, fungisida, pestisida, upah tenaga kerja kebun, perawatan lahan, pengairan, dan peralatan kebun dengan filter kebun & kategori.
                </p>
                <div class="p-2 bg-light rounded-2 border mb-3 small d-flex justify-content-between">
                    <span class="text-secondary">Total Biaya Tahun Ini:</span>
                    <strong class="text-danger"><?= format_rupiah($yearCost); ?></strong>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= url('reports/costs-report.php'); ?>" class="btn btn-primary btn-sm flex-grow-1">
                        <i class="bi bi-eye me-1"></i> Buka Laporan
                    </a>
                    <a href="<?= url('reports/export-csv.php?type=costs'); ?>" class="btn btn-outline-success btn-sm" title="Download CSV">
                        <i class="bi bi-download"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Laporan Hasil Panen -->
    <div class="col-md-6 col-xl-4">
        <div class="card h-100 shadow-sm border-0 report-card">
            <div class="card-body p-4 d-flex flex-column">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="stat-icon purple">
                        <i class="bi bi-file-earmark-bar-graph-fill"></i>
                    </div>
                    <span class="badge bg-purple-subtle text-purple border" style="background:#f3e8ff; color:#7e22ce;">Produksi Fisik</span>
                </div>
                <h5 class="fw-bold text-dark mb-2">Laporan Hasil Panen Buah</h5>
                <p class="text-muted small flex-grow-1 mb-3">
                    Rekapitulasi berat timbangan (Kg) hasil panen petik kebun berdasarkan grade kualitas (Super, Grade A, Grade B, Standar, Sortiran). Dipisahkan dari pendapatan keuangan.
                </p>
                <div class="p-2 bg-light rounded-2 border mb-3 small d-flex justify-content-between">
                    <span class="text-secondary">Total Panen Tahun Ini:</span>
                    <strong class="text-dark"><?= format_number($yearHarvest, 2); ?> Kg</strong>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= url('reports/harvests-report.php'); ?>" class="btn btn-primary btn-sm flex-grow-1">
                        <i class="bi bi-eye me-1"></i> Buka Laporan
                    </a>
                    <a href="<?= url('reports/export-csv.php?type=harvests'); ?>" class="btn btn-outline-success btn-sm" title="Download CSV">
                        <i class="bi bi-download"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Laporan Penjualan Buah -->
    <div class="col-md-6 col-xl-4">
        <div class="card h-100 shadow-sm border-0 report-card">
            <div class="card-body p-4 d-flex flex-column">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="stat-icon green">
                        <i class="bi bi-file-earmark-text-fill"></i>
                    </div>
                    <span class="badge bg-success-subtle text-success border border-success-subtle">Pendapatan Sah</span>
                </div>
                <h5 class="fw-bold text-dark mb-2">Laporan Penjualan Jambu Citra</h5>
                <p class="text-muted small flex-grow-1 mb-3">
                    Rekapitulasi seluruh transaksi penjualan buah kepada pedagang, toko buah, dan pelanggan umum. Memuat status faktur (Confirmed, Draft, Cancelled) dan omset bersih.
                </p>
                <div class="p-2 bg-light rounded-2 border mb-3 small d-flex justify-content-between">
                    <span class="text-secondary">Total Penjualan Confirmed:</span>
                    <strong class="text-success"><?= format_rupiah($yearRev); ?></strong>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= url('reports/sales-report.php'); ?>" class="btn btn-primary btn-sm flex-grow-1">
                        <i class="bi bi-eye me-1"></i> Buka Laporan
                    </a>
                    <a href="<?= url('reports/export-csv.php?type=sales'); ?>" class="btn btn-outline-success btn-sm" title="Download CSV">
                        <i class="bi bi-download"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. Laporan Kas Masuk & Piutang Tagihan -->
    <div class="col-md-6 col-xl-4">
        <div class="card h-100 shadow-sm border-0 report-card">
            <div class="card-body p-4 d-flex flex-column">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="stat-icon amber">
                        <i class="bi bi-journal-check"></i>
                    </div>
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Arus Kas & Piutang</span>
                </div>
                <h5 class="fw-bold text-dark mb-2">Laporan Kas Masuk & Piutang</h5>
                <p class="text-muted small flex-grow-1 mb-3">
                    Pencatatan realisasi pelunasan kas dari pembeli serta daftar sisa tagihan (piutang usaha) yang belum diselesaikan oleh pelanggan.
                </p>
                <div class="p-2 bg-light rounded-2 border mb-3 small d-flex justify-content-between">
                    <span class="text-secondary">Kas Masuk Diterima:</span>
                    <strong class="text-success"><?= format_rupiah($yearPaid); ?></strong>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= url('reports/payments-report.php'); ?>" class="btn btn-primary btn-sm flex-grow-1">
                        <i class="bi bi-eye me-1"></i> Buka Laporan
                    </a>
                    <a href="<?= url('reports/export-csv.php?type=payments'); ?>" class="btn btn-outline-success btn-sm" title="Download CSV">
                        <i class="bi bi-download"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- 6. Pintasan Cetak & Ekspor Data -->
    <div class="col-md-6 col-xl-4">
        <div class="card h-100 shadow-sm border-0 bg-light">
            <div class="card-body p-4 d-flex flex-column justify-content-center text-center">
                <div class="stat-icon green mx-auto mb-3" style="width: 58px; height: 58px;">
                    <i class="bi bi-printer-fill fs-3"></i>
                </div>
                <h5 class="fw-bold mb-1">Cetak & Ekspor Spreadsheet</h5>
                <p class="text-muted small mb-3">
                    Seluruh laporan telah dilengkapi tata letak ramah cetak (print CSS) dan format ekspor CSV yang aman dari formula injection.
                </p>
                <div class="d-flex justify-content-center gap-2">
                    <button type="button" class="btn btn-outline-dark btn-sm" onclick="window.print();">
                        <i class="bi bi-printer me-1"></i> Cetak Halaman
                    </button>
                    <a href="<?= url('reports/export-csv.php?type=profit'); ?>" class="btn btn-outline-success btn-sm">
                        <i class="bi bi-file-earmark-excel me-1"></i> Ekspor Laba Rugi
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
