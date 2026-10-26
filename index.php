<?php
/**
 * Dashboard Utama Sistem Informasi Usaha Tani Jambu Citra
 */

declare(strict_types=1);

$pageTitle = 'Dashboard Ringkasan Usaha';
$activePage = 'dashboard';

require_once __DIR__ . '/includes/header.php';

$pdo = get_db_connection();

// Filter Periode (Bulan dan Tahun)
$currentYear = (int) date('Y');
$selectedYear = isset($_GET['year']) && $_GET['year'] !== '' ? (int) $_GET['year'] : $currentYear;
$selectedMonth = isset($_GET['month']) && $_GET['month'] !== '' ? (int) $_GET['month'] : 0; // 0 = Semua Bulan

// Buat klausa filter SQL untuk periode
$costsWhere = "1=1";
$salesWhere = "s.status = 'confirmed'";
$harvestWhere = "1=1";
$paramsCosts = [];
$paramsSales = [];
$paramsHarvest = [];

if ($selectedYear > 0) {
    $costsWhere .= " AND YEAR(cost_date) = ?";
    $paramsCosts[] = $selectedYear;

    $salesWhere .= " AND YEAR(s.sale_date) = ?";
    $paramsSales[] = $selectedYear;

    $harvestWhere .= " AND YEAR(harvest_date) = ?";
    $paramsHarvest[] = $selectedYear;
}

if ($selectedMonth > 0) {
    $costsWhere .= " AND MONTH(cost_date) = ?";
    $paramsCosts[] = $selectedMonth;

    $salesWhere .= " AND MONTH(s.sale_date) = ?";
    $paramsSales[] = $selectedMonth;

    $harvestWhere .= " AND MONTH(harvest_date) = ?";
    $paramsHarvest[] = $selectedMonth;
}

// 1. Total Biaya Operasional
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_cost), 0) FROM operational_costs WHERE $costsWhere");
$stmt->execute($paramsCosts);
$totalCosts = (float) $stmt->fetchColumn();

// 2. Total Pendapatan Penjualan (HANYA status 'confirmed')
$stmt = $pdo->prepare("SELECT COALESCE(SUM(s.total_amount), 0), COUNT(s.id) FROM sales s WHERE $salesWhere");
$stmt->execute($paramsSales);
$salesStats = $stmt->fetch();
$totalRevenue = (float) ($salesStats['COALESCE(SUM(s.total_amount), 0)'] ?? 0);
$totalSalesCount = (int) ($salesStats['COUNT(s.id)'] ?? 0);

// 3. Keuntungan Operasional & Margin
$operatingProfit = $totalRevenue - $totalCosts;
$profitMargin = calc_margin($operatingProfit, $totalRevenue);

// 4. Total Berat Hasil Panen (Kg)
$stmt = $pdo->prepare("SELECT COALESCE(SUM(weight_kg), 0), COUNT(id) FROM harvests WHERE $harvestWhere");
$stmt->execute($paramsHarvest);
$harvestStats = $stmt->fetch();
$totalHarvestKg = (float) ($harvestStats['COALESCE(SUM(weight_kg), 0)'] ?? 0);
$totalHarvestCount = (int) ($harvestStats['COUNT(id)'] ?? 0);

// 5. Total Piutang Belum Terbayar (Sisa Tagihan dari Penjualan Berstatus Confirmed)
$stmt = $pdo->query("
    SELECT 
        COALESCE(SUM(s.total_amount), 0) - COALESCE(SUM(p.paid_amount), 0) AS total_unpaid
    FROM sales s
    LEFT JOIN (
        SELECT sale_id, SUM(amount) AS paid_amount 
        FROM sale_payments 
        GROUP BY sale_id
    ) p ON s.id = p.sale_id
    WHERE s.status = 'confirmed'
");
$unpaidDebt = (float) $stmt->fetchColumn();
if ($unpaidDebt < 0) $unpaidDebt = 0;

// 6. Data Bulanan untuk Grafik (Jan - Des untuk tahun yang dipilih)
$chartMonths = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$monthlyRevenue = array_fill(1, 12, 0.0);
$monthlyCosts = array_fill(1, 12, 0.0);
$monthlyProfit = array_fill(1, 12, 0.0);
$monthlyHarvest = array_fill(1, 12, 0.0);

// Query Pendapatan Bulanan
$stmt = $pdo->prepare("
    SELECT MONTH(sale_date) AS m, SUM(total_amount) AS rev 
    FROM sales 
    WHERE status = 'confirmed' AND YEAR(sale_date) = ? 
    GROUP BY MONTH(sale_date)
");
$stmt->execute([$selectedYear]);
while ($row = $stmt->fetch()) {
    $m = (int) $row['m'];
    $monthlyRevenue[$m] = (float) $row['rev'];
}

// Query Biaya Bulanan
$stmt = $pdo->prepare("
    SELECT MONTH(cost_date) AS m, SUM(total_cost) AS cst 
    FROM operational_costs 
    WHERE YEAR(cost_date) = ? 
    GROUP BY MONTH(cost_date)
");
$stmt->execute([$selectedYear]);
while ($row = $stmt->fetch()) {
    $m = (int) $row['m'];
    $monthlyCosts[$m] = (float) $row['cst'];
}

// Hitung Keuntungan Bulanan
for ($i = 1; $i <= 12; $i++) {
    $monthlyProfit[$i] = $monthlyRevenue[$i] - $monthlyCosts[$i];
}

// Query Panen Bulanan
$stmt = $pdo->prepare("
    SELECT MONTH(harvest_date) AS m, SUM(weight_kg) AS hrv 
    FROM harvests 
    WHERE YEAR(harvest_date) = ? 
    GROUP BY MONTH(harvest_date)
");
$stmt->execute([$selectedYear]);
while ($row = $stmt->fetch()) {
    $m = (int) $row['m'];
    $monthlyHarvest[$m] = (float) $row['hrv'];
}

// 7. Ambil 5 Transaksi Penjualan Terbaru
$stmt = $pdo->query("
    SELECT s.*, c.customer_name 
    FROM sales s 
    LEFT JOIN customers c ON s.customer_id = c.id 
    ORDER BY s.sale_date DESC, s.id DESC 
    LIMIT 5
");
$recentSales = $stmt->fetchAll();

// 8. Ambil 5 Pengeluaran Biaya Terbaru
$stmt = $pdo->query("
    SELECT c.*, f.farm_name, cat.category_name 
    FROM operational_costs c 
    JOIN farms f ON c.farm_id = f.id 
    JOIN cost_categories cat ON c.category_id = cat.id 
    ORDER BY c.cost_date DESC, c.id DESC 
    LIMIT 5
");
$recentCosts = $stmt->fetchAll();
?>

<!-- Filter Periode Dashboard -->
<div class="card mb-4 no-print border-0 shadow-sm">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-auto">
                <span class="fw-semibold text-secondary"><i class="bi bi-funnel me-1"></i> Filter Periode:</span>
            </div>
            <div class="col-auto">
                <select name="month" class="form-select form-select-sm">
                    <option value="0" <?= $selectedMonth === 0 ? 'selected' : ''; ?>>-- Seluruh Bulan --</option>
                    <?php
                    $monthsList = [
                        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
                    ];
                    foreach ($monthsList as $num => $name): ?>
                        <option value="<?= $num; ?>" <?= $selectedMonth === $num ? 'selected' : ''; ?>><?= $name; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <select name="year" class="form-select form-select-sm">
                    <?php for ($y = $currentYear + 1; $y >= $currentYear - 4; $y--): ?>
                        <option value="<?= $y; ?>" <?= $selectedYear === $y ? 'selected' : ''; ?>>Tahun <?= $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm px-3">
                    <i class="bi bi-search me-1"></i> Terapkan
                </button>
                <a href="<?= url('index.php'); ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Kartu Ringkasan Keuangan Utama -->
<div class="row g-3 mb-4">
    <!-- Total Pendapatan Penjualan -->
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon green">
                <i class="bi bi-cash-coin"></i>
            </div>
            <div>
                <div class="stat-label">Total Pendapatan</div>
                <div class="stat-value text-success"><?= format_rupiah($totalRevenue); ?></div>
                <div class="stat-sub"><?= $totalSalesCount; ?> transaksi penjualan dikonfirmasi</div>
            </div>
        </div>
    </div>

    <!-- Total Biaya Operasional -->
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon red">
                <i class="bi bi-receipt-cutoff"></i>
            </div>
            <div>
                <div class="stat-label">Biaya Operasional</div>
                <div class="stat-value text-danger"><?= format_rupiah($totalCosts); ?></div>
                <div class="stat-sub">Pupuk, upah, perawatan & sarana</div>
            </div>
        </div>
    </div>

    <!-- Keuntungan Operasional Bersih -->
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon <?= $operatingProfit >= 0 ? 'blue' : 'amber'; ?>">
                <i class="bi bi-graph-up-arrow"></i>
            </div>
            <div>
                <div class="stat-label">Keuntungan Operasional</div>
                <div class="stat-value <?= $operatingProfit >= 0 ? 'text-primary' : 'text-danger'; ?>">
                    <?= format_rupiah($operatingProfit); ?>
                </div>
                <div class="stat-sub">Margin: <?= format_margin($profitMargin); ?></div>
            </div>
        </div>
    </div>

    <!-- Total Hasil Panen -->
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon purple">
                <i class="bi bi-basket3-fill"></i>
            </div>
            <div>
                <div class="stat-label">Total Hasil Panen</div>
                <div class="stat-value text-dark"><?= format_number($totalHarvestKg, 2); ?> <span class="fs-6 fw-normal text-muted">Kg</span></div>
                <div class="stat-sub"><?= $totalHarvestCount; ?> kali sesi pencatatan panen</div>
            </div>
        </div>
    </div>
</div>

<!-- Grafik Analisis Interaktif (Chart.js) -->
<div class="row g-4 mb-4">
    <!-- Grafik Pendapatan vs Biaya -->
    <div class="col-lg-8">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="bi bi-bar-chart-line-fill text-success me-2"></i>Perbandingan Pendapatan & Biaya Operasional Tahun <?= $selectedYear; ?></span>
                <span class="badge bg-light text-secondary border">Dalam Rupiah</span>
            </div>
            <div class="card-body">
                <div style="position: relative; height: 320px;">
                    <canvas id="revCostChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Grafik Hasil Panen Bulanan -->
    <div class="col-lg-4">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="bi bi-box-seam-fill text-purple me-2"></i>Hasil Panen (Kg) <?= $selectedYear; ?></span>
            </div>
            <div class="card-body">
                <div style="position: relative; height: 320px;">
                    <canvas id="harvestChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabel Ringkasan Aktivitas Terbaru -->
<div class="row g-4 mb-4">
    <!-- 5 Penjualan Terbaru -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="bi bi-cart-check-fill text-success me-2"></i>Transaksi Penjualan Terbaru</span>
                <a href="<?= url('sales/index.php'); ?>" class="btn btn-sm btn-link text-decoration-none p-0">Lihat Semua &rarr;</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Invoice / Tanggal</th>
                            <th>Pelanggan</th>
                            <th class="text-end">Total</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentSales)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">Belum ada transaksi penjualan tercatat.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentSales as $sale): ?>
                                <tr>
                                    <td>
                                        <a href="<?= url('sales/detail.php?id=' . $sale['id']); ?>" class="fw-bold text-decoration-none">
                                            <?= e($sale['invoice_number']); ?>
                                        </a>
                                        <div class="small text-muted"><?= format_date($sale['sale_date'], true); ?></div>
                                    </td>
                                    <td><?= e($sale['customer_name'] ?? 'Pelanggan Umum'); ?></td>
                                    <td class="text-end fw-semibold text-dark"><?= format_rupiah($sale['total_amount']); ?></td>
                                    <td class="text-center">
                                        <?php if ($sale['status'] === 'confirmed'): ?>
                                            <span class="badge badge-status badge-confirmed">Confirmed</span>
                                        <?php elseif ($sale['status'] === 'draft'): ?>
                                            <span class="badge badge-status badge-draft">Draft</span>
                                        <?php else: ?>
                                            <span class="badge badge-status badge-cancelled">Cancelled</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 5 Biaya Operasional Terbaru -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="bi bi-receipt text-danger me-2"></i>Pengeluaran Biaya Terbaru</span>
                <a href="<?= url('costs/index.php'); ?>" class="btn btn-sm btn-link text-decoration-none p-0">Lihat Semua &rarr;</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Item / Tanggal</th>
                            <th>Kebun / Kategori</th>
                            <th class="text-end">Biaya</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentCosts)): ?>
                            <tr>
                                <td colspan="3" class="text-center py-4 text-muted">Belum ada biaya operasional tercatat.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentCosts as $cost): ?>
                                <tr>
                                    <td>
                                        <span class="fw-semibold text-dark"><?= e($cost['item_name']); ?></span>
                                        <div class="small text-muted"><?= format_date($cost['cost_date'], true); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?= e($cost['farm_name']); ?></span>
                                        <div class="small text-muted"><?= e($cost['category_name']); ?></div>
                                    </td>
                                    <td class="text-end fw-semibold text-danger"><?= format_rupiah($cost['total_cost']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Script Chart.js Inisialisasi Data Dinamis -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const months = <?= json_encode($chartMonths); ?>;
    const revData = <?= json_encode(array_values($monthlyRevenue)); ?>;
    const costData = <?= json_encode(array_values($monthlyCosts)); ?>;
    const harvestData = <?= json_encode(array_values($monthlyHarvest)); ?>;

    // 1. Chart Pendapatan vs Biaya
    const ctxRevCost = document.getElementById('revCostChart');
    if (ctxRevCost) {
        new Chart(ctxRevCost, {
            type: 'bar',
            data: {
                labels: months,
                datasets: [
                    {
                        label: 'Pendapatan (Confirmed)',
                        data: revData,
                        backgroundColor: 'rgba(21, 128, 61, 0.85)',
                        borderColor: '#15803d',
                        borderWidth: 1,
                        borderRadius: 6
                    },
                    {
                        label: 'Biaya Operasional',
                        data: costData,
                        backgroundColor: 'rgba(239, 68, 68, 0.8)',
                        borderColor: '#ef4444',
                        borderWidth: 1,
                        borderRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(context.raw);
                            }
                        }
                    }
                },
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

    // 2. Chart Hasil Panen
    const ctxHarvest = document.getElementById('harvestChart');
    if (ctxHarvest) {
        new Chart(ctxHarvest, {
            type: 'line',
            data: {
                labels: months,
                datasets: [{
                    label: 'Hasil Panen (Kg)',
                    data: harvestData,
                    borderColor: '#9333ea',
                    backgroundColor: 'rgba(147, 51, 234, 0.15)',
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: '#9333ea',
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.raw.toLocaleString('id-ID') + ' Kg';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(val) { return val + ' Kg'; }
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
