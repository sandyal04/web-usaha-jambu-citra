<?php
/**
 * Modul Laporan - Analisis Keuntungan Operasional (Laba Rugi Usaha Tani)
 * Sistem Informasi Usaha Tani Jambu Citra
 */

declare(strict_types=1);

$pageTitle = 'Analisis Keuntungan Operasional';
$activePage = 'profit_analysis';

require_once __DIR__ . '/../includes/header.php';

$pdo = get_db_connection();

// Rentang Tanggal Filter (Default: Awal bulan ini s/d hari ini)
$startDate = trim($_GET['start_date'] ?? date('Y-m-01'));
$endDate   = trim($_GET['end_date'] ?? date('Y-m-d'));

// 1. Total Pendapatan Penjualan (HANYA status 'confirmed')
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(total_amount), 0) AS total_revenue,
           COUNT(id) AS sales_count
    FROM sales
    WHERE status = 'confirmed'
      AND sale_date >= ? AND sale_date <= ?
");
$stmt->execute([$startDate, $endDate]);
$revStats = $stmt->fetch();
$totalRevenue = (float) $revStats['total_revenue'];
$salesCount = (int) $revStats['sales_count'];

// 2. Total Biaya Operasional pada rentang tanggal yang persis sama
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(total_cost), 0) AS total_costs,
           COUNT(id) AS costs_count
    FROM operational_costs
    WHERE cost_date >= ? AND cost_date <= ?
");
$stmt->execute([$startDate, $endDate]);
$costStats = $stmt->fetch();
$totalCosts = (float) $costStats['total_costs'];
$costsCount = (int) $costStats['costs_count'];

// 3. Keuntungan Operasional Bersih & Margin Keuntungan
$operatingProfit = $totalRevenue - $totalCosts;
$profitMargin = calc_margin($operatingProfit, $totalRevenue);

// 4. Rincian Biaya per Kategori dalam Periode
$stmt = $pdo->prepare("
    SELECT cat.category_name, 
           COALESCE(SUM(c.total_cost), 0) AS category_total,
           COUNT(c.id) AS item_count
    FROM cost_categories cat
    LEFT JOIN operational_costs c ON cat.id = c.category_id 
         AND c.cost_date >= ? AND c.cost_date <= ?
    GROUP BY cat.id
    HAVING category_total > 0
    ORDER BY category_total DESC
");
$stmt->execute([$startDate, $endDate]);
$costsByCategory = $stmt->fetchAll();

// 5. Rincian Penjualan per Grade Buah dalam Periode
$stmt = $pdo->prepare("
    SELECT si.product_grade,
           SUM(si.weight_kg) AS total_weight,
           SUM(si.subtotal) AS total_subtotal
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    WHERE s.status = 'confirmed'
      AND s.sale_date >= ? AND s.sale_date <= ?
    GROUP BY si.product_grade
    ORDER BY total_subtotal DESC
");
$stmt->execute([$startDate, $endDate]);
$salesByGrade = $stmt->fetchAll();

// 6. Data Harian untuk Grafik Tren Keuntungan dalam Periode
$stmt = $pdo->prepare("
    SELECT s.sale_date AS date_val, SUM(s.total_amount) AS revenue, 0 AS cost
    FROM sales s
    WHERE s.status = 'confirmed' AND s.sale_date >= ? AND s.sale_date <= ?
    GROUP BY s.sale_date
    UNION ALL
    SELECT c.cost_date AS date_val, 0 AS revenue, SUM(c.total_cost) AS cost
    FROM operational_costs c
    WHERE c.cost_date >= ? AND c.cost_date <= ?
    GROUP BY c.cost_date
    ORDER BY date_val ASC
");
$stmt->execute([$startDate, $endDate, $startDate, $endDate]);
$dailyRecords = $stmt->fetchAll();

$trendDates = [];
$trendRev = [];
$trendCost = [];
$dailyMap = [];

foreach ($dailyRecords as $dr) {
    $d = $dr['date_val'];
    if (!isset($dailyMap[$d])) {
        $dailyMap[$d] = ['rev' => 0.0, 'cost' => 0.0];
    }
    $dailyMap[$d]['rev'] += (float) $dr['revenue'];
    $dailyMap[$d]['cost'] += (float) $dr['cost'];
}

ksort($dailyMap);
foreach ($dailyMap as $dateKey => $vals) {
    $trendDates[] = date('d M', strtotime($dateKey));
    $trendRev[] = $vals['rev'];
    $trendCost[] = $vals['cost'];
}
?>

<!-- Header Print CSS -->
<div class="print-header">
    <h3 style="margin-bottom: 2px;">USAHA TANI JAMBU CITRA</h3>
    <h4 style="margin-bottom: 5px;">LAPORAN ANALISIS KEUNTUNGAN OPERASIONAL</h4>
    <p style="font-size: 0.9rem; color: #444;">
        Periode: <strong><?= format_date($startDate); ?></strong> s/d <strong><?= format_date($endDate); ?></strong>
    </p>
</div>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 no-print">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-graph-up-arrow text-success me-2"></i>Analisis Keuntungan Usaha Tani</h4>
        <p class="text-muted small mb-0">Evaluasi pendapatan penjualan, efisiensi biaya operasional, dan margin laba bersih usaha tani.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= url('reports/index.php'); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-folder2-open me-1"></i> Semua Laporan
        </a>
        <button type="button" class="btn btn-outline-dark btn-sm" onclick="window.print();">
            <i class="bi bi-printer me-1"></i> Cetak Laporan
        </button>
        <a href="<?= url('reports/export-csv.php?type=profit&start_date=' . urlencode($startDate) . '&end_date=' . urlencode($endDate)); ?>" class="btn btn-outline-success btn-sm">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Ekspor CSV
        </a>
    </div>
</div>

<!-- Filter Periode Laporan -->
<div class="card mb-4 border-0 shadow-sm no-print">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small mb-1">Mulai Tanggal</label>
                <input type="date" name="start_date" class="form-control" value="<?= e($startDate); ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-1">Sampai Tanggal</label>
                <input type="date" name="end_date" class="form-control" value="<?= e($endDate); ?>" required>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-search me-1"></i> Hitung Analisis
                </button>
                <a href="<?= url('reports/profit-analysis.php'); ?>" class="btn btn-outline-secondary">Reset Bulan Ini</a>
            </div>
        </form>
    </div>
</div>

<!-- Kartu Ringkasan Hasil Analisis -->
<div class="row g-3 mb-4">
    <!-- Total Pendapatan -->
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-cash-stack"></i></div>
            <div>
                <div class="stat-label">Total Pendapatan</div>
                <div class="stat-value text-success"><?= format_rupiah($totalRevenue); ?></div>
                <div class="stat-sub"><?= $salesCount; ?> faktur confirmed</div>
            </div>
        </div>
    </div>

    <!-- Total Biaya -->
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon red"><i class="bi bi-receipt"></i></div>
            <div>
                <div class="stat-label">Biaya Operasional</div>
                <div class="stat-value text-danger"><?= format_rupiah($totalCosts); ?></div>
                <div class="stat-sub"><?= $costsCount; ?> pengeluaran tercatat</div>
            </div>
        </div>
    </div>

    <!-- Keuntungan Operasional -->
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon <?= $operatingProfit >= 0 ? 'blue' : 'amber'; ?>">
                <i class="bi bi-wallet2"></i>
            </div>
            <div>
                <div class="stat-label">Keuntungan Operasional</div>
                <div class="stat-value <?= $operatingProfit >= 0 ? 'text-primary' : 'text-danger'; ?>">
                    <?= format_rupiah($operatingProfit); ?>
                </div>
                <div class="stat-sub"><?= $operatingProfit >= 0 ? 'Laba Usaha Bersih' : 'Rugi Periode Ini'; ?></div>
            </div>
        </div>
    </div>

    <!-- Margin Keuntungan -->
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon purple"><i class="bi bi-percent"></i></div>
            <div>
                <div class="stat-label">Margin Keuntungan</div>
                <div class="stat-value">
                    <?php if ($profitMargin === null): ?>
                        <span class="fs-6 text-muted">0.00%</span>
                    <?php else: ?>
                        <?= number_format($profitMargin, 2, ',', '.'); ?>%
                    <?php endif; ?>
                </div>
                <div class="stat-sub">
                    <?= format_margin($profitMargin); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Grafik Tren Keuangan dalam Periode -->
<div class="card shadow-sm border-0 mb-4 no-print">
    <div class="card-header bg-white py-3">
        <span class="fw-bold"><i class="bi bi-bar-chart-fill text-success me-1"></i> Tren Harian Pendapatan vs Biaya Operasional</span>
    </div>
    <div class="card-body">
        <div style="position: relative; height: 300px;">
            <canvas id="profitTrendChart"></canvas>
        </div>
    </div>
</div>

<!-- Rincian Breakdown: Kategori Biaya vs Penjualan Buah -->
<div class="row g-4 mb-4">
    <!-- Pengeluaran Biaya per Kategori -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3">
                <span class="fw-bold"><i class="bi bi-tags-fill text-danger me-1"></i> Rincian Pengeluaran Biaya per Kategori</span>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Kategori Biaya</th>
                            <th class="text-center" style="width: 100px;">Transaksi</th>
                            <th class="text-end" style="width: 160px;">Total Pengeluaran</th>
                            <th class="text-end" style="width: 90px;">Porsi (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($costsByCategory)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">Tidak ada pengeluaran biaya pada periode ini.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($costsByCategory as $cbc): 
                                $pct = ($totalCosts > 0) ? ($cbc['category_total'] / $totalCosts) * 100 : 0;
                            ?>
                                <tr>
                                    <td><strong><?= e($cbc['category_name']); ?></strong></td>
                                    <td class="text-center"><?= (int) $cbc['item_count']; ?></td>
                                    <td class="text-end fw-semibold text-danger"><?= format_rupiah($cbc['category_total']); ?></td>
                                    <td class="text-end text-muted small"><?= number_format($pct, 1, ',', '.'); ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="2" class="text-end">TOTAL BIAYA:</th>
                            <th class="text-end text-danger fs-6"><?= format_rupiah($totalCosts); ?></th>
                            <th class="text-end">100%</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Pendapatan per Grade Buah -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3">
                <span class="fw-bold"><i class="bi bi-basket3-fill text-success me-1"></i> Rincian Penjualan per Grade Buah</span>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Kualitas / Grade Jambu Citra</th>
                            <th class="text-center" style="width: 120px;">Volume (Kg)</th>
                            <th class="text-end" style="width: 160px;">Nilai Penjualan</th>
                            <th class="text-end" style="width: 90px;">Porsi (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($salesByGrade)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">Tidak ada penjualan dikonfirmasi pada periode ini.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($salesByGrade as $sbg): 
                                $pct = ($totalRevenue > 0) ? ($sbg['total_subtotal'] / $totalRevenue) * 100 : 0;
                            ?>
                                <tr>
                                    <td><strong><?= e($sbg['product_grade']); ?></strong></td>
                                    <td class="text-center fw-semibold"><?= format_number($sbg['total_weight'], 2); ?> Kg</td>
                                    <td class="text-end fw-semibold text-success"><?= format_rupiah($sbg['total_subtotal']); ?></td>
                                    <td class="text-end text-muted small"><?= number_format($pct, 1, ',', '.'); ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="2" class="text-end">TOTAL PENDAPATAN:</th>
                            <th class="text-end text-success fs-6"><?= format_rupiah($totalRevenue); ?></th>
                            <th class="text-end">100%</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Tanda Tangan Cetak -->
<div class="print-signature">
    <div class="print-signature-box">
        <p style="margin-bottom: 60px;">Pengelola Usaha Tani Jambu Citra,</p>
        <p style="border-bottom: 1px solid #000; font-weight: bold; margin-bottom: 2px;">
            <?= e($currentUser['full_name'] ?? 'Pengelola'); ?>
        </p>
        <small style="color: #666;">Laporan Resmi Keuangan</small>
    </div>
</div>

<!-- Inisialisasi Chart Trend Harian -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const dates = <?= json_encode($trendDates); ?>;
    const revs  = <?= json_encode($trendRev); ?>;
    const costs = <?= json_encode($trendCost); ?>;

    const ctx = document.getElementById('profitTrendChart');
    if (ctx && dates.length > 0) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: dates,
                datasets: [
                    {
                        label: 'Pendapatan (Rp)',
                        data: revs,
                        backgroundColor: 'rgba(21, 128, 61, 0.85)',
                        borderColor: '#15803d',
                        borderWidth: 1,
                        borderRadius: 4
                    },
                    {
                        label: 'Biaya Operasional (Rp)',
                        data: costs,
                        backgroundColor: 'rgba(239, 68, 68, 0.8)',
                        borderColor: '#ef4444',
                        borderWidth: 1,
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(val) {
                                return 'Rp ' + (val / 1000).toLocaleString('id-ID') + 'k';
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
