<?php
/**
 * Modul Laporan - Laporan Biaya Operasional
 * Sistem Informasi Usaha Tani Jambu Citra
 */

declare(strict_types=1);

$pageTitle = 'Laporan Biaya Operasional';
$activePage = 'report_costs';

require_once __DIR__ . '/../includes/header.php';

$pdo = get_db_connection();

$filterFarm = isset($_GET['farm_id']) && $_GET['farm_id'] !== '' ? (int) $_GET['farm_id'] : 0;
$filterCat  = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? (int) $_GET['category_id'] : 0;
$startDate  = trim($_GET['start_date'] ?? date('Y-01-01'));
$endDate    = trim($_GET['end_date'] ?? date('Y-m-d'));

$where = ["1=1"];
$params = [];

if ($filterFarm > 0) {
    $where[] = "c.farm_id = ?";
    $params[] = $filterFarm;
}

if ($filterCat > 0) {
    $where[] = "c.category_id = ?";
    $params[] = $filterCat;
}

if (!empty($startDate)) {
    $where[] = "c.cost_date >= ?";
    $params[] = $startDate;
}

if (!empty($endDate)) {
    $where[] = "c.cost_date <= ?";
    $params[] = $endDate;
}

$whereClause = implode(" AND ", $where);

$sql = "
    SELECT c.*, f.farm_name, cat.category_name
    FROM operational_costs c
    JOIN farms f ON c.farm_id = f.id
    JOIN cost_categories cat ON c.category_id = cat.id
    WHERE $whereClause
    ORDER BY c.cost_date ASC, c.id ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$costs = $stmt->fetchAll();

$totalCost  = array_sum(array_column($costs, 'total_cost'));
$costCount  = count($costs);
$avgCost    = $costCount > 0 ? ($totalCost / $costCount) : 0;

// Analisis Rincian per Kategori
$catBreakdown = [];
foreach ($costs as $c) {
    $cName = $c['category_name'];
    $catBreakdown[$cName] = ($catBreakdown[$cName] ?? 0) + (float) $c['total_cost'];
}
arsort($catBreakdown);
$topCatName   = !empty($catBreakdown) ? (string) array_key_first($catBreakdown) : '-';
$topCatAmount = !empty($catBreakdown) ? (float) reset($catBreakdown) : 0.0;

// Analisis Rincian per Kebun
$farmBreakdown = [];
foreach ($costs as $c) {
    $fName = $c['farm_name'];
    $farmBreakdown[$fName] = ($farmBreakdown[$fName] ?? 0) + (float) $c['total_cost'];
}
arsort($farmBreakdown);
$topFarmName   = !empty($farmBreakdown) ? (string) array_key_first($farmBreakdown) : '-';
$topFarmAmount = !empty($farmBreakdown) ? (float) reset($farmBreakdown) : 0.0;

$allFarms = $pdo->query("SELECT id, farm_name FROM farms ORDER BY farm_name ASC")->fetchAll();
$allCategories = $pdo->query("SELECT id, category_name FROM cost_categories ORDER BY category_name ASC")->fetchAll();
?>

<!-- Header Print Khusus Cetak Kertas -->
<div class="print-header">
    <h3 style="margin-bottom: 2px;">USAHA TANI JAMBU CITRA</h3>
    <h4 style="margin-bottom: 5px;">LAPORAN BIAYA OPERASIONAL KEBUN</h4>
    <p style="font-size: 0.9rem; color: #444;">
        Periode: <strong><?= format_date($startDate); ?></strong> s/d <strong><?= format_date($endDate); ?></strong>
    </p>
</div>

<!-- Header Aksi & Navigasi -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 no-print">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item"><a href="<?= url('reports/index.php'); ?>" class="text-decoration-none">Pusat Laporan</a></li>
                <li class="breadcrumb-item active" aria-current="page">Laporan Biaya Operasional</li>
            </ol>
        </nav>
        <h4 class="fw-bold mb-1 text-dark">
            <i class="bi bi-wallet2 text-danger me-2"></i>Laporan Biaya Operasional Kebun
        </h4>
        <p class="text-muted small mb-0">Rekapitulasi seluruh pengeluaran perawatan, pupuk, tenaga kerja, dan operasional jambu citra.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= url('reports/index.php'); ?>" class="btn btn-outline-secondary btn-sm shadow-sm">
            <i class="bi bi-grid me-1"></i> Semua Laporan
        </a>
        <button type="button" class="btn btn-dark btn-sm shadow-sm" onclick="window.print();">
            <i class="bi bi-printer me-1"></i> Cetak Laporan
        </button>
        <a href="<?= url('reports/export-csv.php?type=costs&farm_id=' . $filterFarm . '&category_id=' . $filterCat . '&start_date=' . urlencode($startDate) . '&end_date=' . urlencode($endDate)); ?>" class="btn btn-success btn-sm shadow-sm">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Ekspor CSV
        </a>
    </div>
</div>

<!-- Kartu Ringkasan Metrik (KPI) -->
<div class="row g-3 mb-4 no-print">
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold">TOTAL PENGELUARAN</span>
                    <div class="p-2 rounded-2 bg-danger-subtle text-danger">
                        <i class="bi bi-cash-stack fs-5"></i>
                    </div>
                </div>
                <h4 class="fw-bold text-danger mb-1"><?= format_rupiah($totalCost); ?></h4>
                <div class="small text-muted">
                    <span class="badge bg-danger-subtle text-danger me-1"><?= number_format($costCount); ?> Transaksi</span>
                    <span>pada periode terpilih</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold">RATA-RATA PENGELUARAN</span>
                    <div class="p-2 rounded-2 bg-primary-subtle text-primary">
                        <i class="bi bi-calculator fs-5"></i>
                    </div>
                </div>
                <h4 class="fw-bold text-dark mb-1"><?= format_rupiah($avgCost); ?></h4>
                <div class="small text-muted">Rata-rata nominal per transaksi</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold">KATEGORI TERTINGGI</span>
                    <div class="p-2 rounded-2 bg-warning-subtle text-warning">
                        <i class="bi bi-pie-chart-fill fs-5"></i>
                    </div>
                </div>
                <h5 class="fw-bold text-dark mb-1 text-truncate" title="<?= e($topCatName); ?>"><?= e($topCatName); ?></h5>
                <div class="small text-muted fw-semibold text-danger"><?= format_rupiah($topCatAmount); ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold">KEBUN BIAYA TERTINGGI</span>
                    <div class="p-2 rounded-2 bg-success-subtle text-success">
                        <i class="bi bi-tree-fill fs-5"></i>
                    </div>
                </div>
                <h5 class="fw-bold text-dark mb-1 text-truncate" title="<?= e($topFarmName); ?>"><?= e($topFarmName); ?></h5>
                <div class="small text-muted fw-semibold text-danger"><?= format_rupiah($topFarmAmount); ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Visualisasi Alokasi Biaya per Kategori -->
<?php if (!empty($catBreakdown) && $totalCost > 0): ?>
<div class="card border-0 shadow-sm rounded-3 mb-4 no-print">
    <div class="card-body p-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-bar-chart-steps text-danger me-2"></i>Distribusi Biaya per Kategori</h6>
            <small class="text-muted">Total: <?= format_rupiah($totalCost); ?></small>
        </div>
        <div class="progress mb-3" style="height: 12px; border-radius: 6px;">
            <?php 
            $colors = ['bg-danger', 'bg-warning', 'bg-primary', 'bg-success', 'bg-info', 'bg-secondary'];
            $i = 0;
            foreach ($catBreakdown as $cName => $amt): 
                $pct = ($amt / $totalCost) * 100;
                $color = $colors[$i % count($colors)];
                $i++;
            ?>
                <div class="progress-bar <?= $color; ?>" role="progressbar" style="width: <?= $pct; ?>%;" title="<?= e($cName); ?>: <?= number_format($pct, 1); ?>%"></div>
            <?php endforeach; ?>
        </div>
        <div class="d-flex flex-wrap gap-3 small">
            <?php 
            $i = 0;
            foreach ($catBreakdown as $cName => $amt): 
                $pct = ($amt / $totalCost) * 100;
                $color = $colors[$i % count($colors)];
                $i++;
            ?>
                <div class="d-flex align-items-center gap-1">
                    <span class="badge <?= $color; ?> p-1 rounded-circle" style="width: 10px; height: 10px; display: inline-block;"></span>
                    <span class="fw-medium text-dark"><?= e($cName); ?>:</span>
                    <span class="text-muted"><?= format_rupiah($amt); ?> (<?= number_format($pct, 1); ?>%)</span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Filter & Pencarian Laporan -->
<div class="card mb-4 border-0 shadow-sm rounded-3 no-print">
    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-funnel-fill text-success me-2"></i>Filter Rentang Data & Lokasi Kebun</h6>
        <div class="d-none d-md-flex gap-1">
            <a href="?start_date=<?= date('Y-m-01'); ?>&end_date=<?= date('Y-m-d'); ?>&farm_id=<?= $filterFarm; ?>&category_id=<?= $filterCat; ?>" class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:0.75rem;">Bulan Ini</a>
            <a href="?start_date=<?= date('Y-01-01'); ?>&end_date=<?= date('Y-m-d'); ?>&farm_id=<?= $filterFarm; ?>&category_id=<?= $filterCat; ?>" class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:0.75rem;">Tahun Ini</a>
            <a href="?start_date=&end_date=&farm_id=<?= $filterFarm; ?>&category_id=<?= $filterCat; ?>" class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:0.75rem;">Semua Data</a>
        </div>
    </div>
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1 fw-semibold text-muted">Pilih Kebun</label>
                <select name="farm_id" class="form-select form-select-sm">
                    <option value="0">-- Seluruh Kebun --</option>
                    <?php foreach ($allFarms as $f): ?>
                        <option value="<?= $f['id']; ?>" <?= $filterFarm === (int)$f['id'] ? 'selected' : ''; ?>>
                            <?= e($f['farm_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1 fw-semibold text-muted">Kategori Biaya</label>
                <select name="category_id" class="form-select form-select-sm">
                    <option value="0">-- Seluruh Kategori --</option>
                    <?php foreach ($allCategories as $cat): ?>
                        <option value="<?= $cat['id']; ?>" <?= $filterCat === (int)$cat['id'] ? 'selected' : ''; ?>>
                            <?= e($cat['category_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1 fw-semibold text-muted">Mulai Tanggal</label>
                <input type="date" name="start_date" class="form-control form-control-sm" value="<?= e($startDate); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1 fw-semibold text-muted">Sampai Tanggal</label>
                <input type="date" name="end_date" class="form-control form-control-sm" value="<?= e($endDate); ?>">
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                    <i class="bi bi-funnel me-1"></i> Terapkan
                </button>
                <a href="<?= url('reports/costs-report.php'); ?>" class="btn btn-outline-secondary btn-sm" title="Reset Filter">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Tabel Laporan Biaya Operasional -->
<div class="card shadow-sm border-0 rounded-3 mb-4">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <div>
            <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-table text-primary me-2"></i>Daftar Rincian Pengeluaran Operasional</h6>
            <small class="text-muted">Menampilkan <?= number_format($costCount); ?> catatan transaksi</small>
        </div>
        <span class="badge bg-danger-subtle text-danger px-3 py-2 fw-bold">
            Total: <?= format_rupiah($totalCost); ?>
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 45px;" class="text-center">No</th>
                    <th style="width: 120px;">Tanggal</th>
                    <th>Kebun</th>
                    <th>Kategori Biaya</th>
                    <th>Nama Kebutuhan / Uraian</th>
                    <th class="text-center" style="width: 110px;">Kuantitas</th>
                    <th class="text-end" style="width: 130px;">Harga Satuan</th>
                    <th class="text-end" style="width: 150px;">Total Biaya</th>
                    <th>Catatan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($costs)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-2 text-secondary d-block mb-2"></i>
                            Tidak ada catatan biaya pada rentang periode dan filter yang dipilih.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($costs as $c): ?>
                        <tr>
                            <td class="text-muted text-center"><?= $no++; ?></td>
                            <td class="fw-medium"><?= format_date($c['cost_date'], true); ?></td>
                            <td><span class="badge bg-light text-dark border"><i class="bi bi-geo-alt me-1 text-success"></i><?= e($c['farm_name']); ?></span></td>
                            <td><span class="badge bg-secondary-subtle text-secondary border"><?= e($c['category_name']); ?></span></td>
                            <td><strong class="text-dark"><?= e($c['item_name']); ?></strong></td>
                            <td class="text-center fw-medium"><?= format_number((float)$c['quantity'], 2); ?> <small class="text-muted"><?= e($c['unit']); ?></small></td>
                            <td class="text-end text-muted"><?= (float)$c['unit_price'] > 0 ? format_rupiah((float)$c['unit_price']) : '-'; ?></td>
                            <td class="text-end fw-bold text-danger"><?= format_rupiah((float)$c['total_cost']); ?></td>
                            <td><small class="text-muted"><?= e($c['notes'] ?: '-'); ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <th colspan="7" class="text-end py-3 fs-6">TOTAL KESELURUHAN BIAYA OPERASIONAL:</th>
                    <th class="text-end py-3 text-danger fs-5 fw-bold"><?= format_rupiah($totalCost); ?></th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Kolom Tanda Tangan Cetak Fisik -->
<div class="print-signature">
    <div class="print-signature-box">
        <p style="margin-bottom: 60px;">Pengelola Usaha Tani Jambu Citra,</p>
        <p style="border-bottom: 1px solid #000; font-weight: bold; margin-bottom: 2px;">
            <?= e($currentUser['full_name'] ?? 'Pengelola'); ?>
        </p>
        <small style="color: #666;">Dicetak pada: <?= date('d/m/Y H:i'); ?></small>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
