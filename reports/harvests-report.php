<?php
/**
 * Modul Laporan - Laporan Hasil Panen Jambu Citra
 * Sistem Informasi Usaha Tani Jambu Citra
 */

declare(strict_types=1);

$pageTitle = 'Laporan Hasil Panen';
$activePage = 'report_harvests';

require_once __DIR__ . '/../includes/header.php';

$pdo = get_db_connection();

$filterFarm  = isset($_GET['farm_id']) && $_GET['farm_id'] !== '' ? (int) $_GET['farm_id'] : 0;
$filterGrade = trim($_GET['grade'] ?? '');
$startDate   = trim($_GET['start_date'] ?? date('Y-01-01'));
$endDate     = trim($_GET['end_date'] ?? date('Y-m-d'));

$where = ["1=1"];
$params = [];

if ($filterFarm > 0) {
    $where[] = "h.farm_id = ?";
    $params[] = $filterFarm;
}

if (!empty($filterGrade)) {
    $where[] = "h.fruit_grade = ?";
    $params[] = $filterGrade;
}

if (!empty($startDate)) {
    $where[] = "h.harvest_date >= ?";
    $params[] = $startDate;
}

if (!empty($endDate)) {
    $where[] = "h.harvest_date <= ?";
    $params[] = $endDate;
}

$whereClause = implode(" AND ", $where);

$sql = "
    SELECT h.*, f.farm_name, f.location
    FROM harvests h
    JOIN farms f ON h.farm_id = f.id
    WHERE $whereClause
    ORDER BY h.harvest_date ASC, h.id ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$harvests = $stmt->fetchAll();

$totalWeight = array_sum(array_column($harvests, 'weight_kg'));

// Ringkasan per Grade
$gradeTotals = [];
foreach ($harvests as $h) {
    $g = $h['fruit_grade'];
    if (!isset($gradeTotals[$g])) {
        $gradeTotals[$g] = 0.0;
    }
    $gradeTotals[$g] += (float) $h['weight_kg'];
}

$allFarms = $pdo->query("SELECT id, farm_name FROM farms ORDER BY farm_name ASC")->fetchAll();
?>

<!-- Header Print CSS -->
<div class="print-header">
    <h3 style="margin-bottom: 2px;">USAHA TANI JAMBU CITRA</h3>
    <h4 style="margin-bottom: 5px;">LAPORAN HASIL PANEN BUAH</h4>
    <p style="font-size: 0.9rem; color: #444;">
        Periode: <strong><?= format_date($startDate); ?></strong> s/d <strong><?= format_date($endDate); ?></strong>
    </p>
</div>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 no-print">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-file-earmark-bar-graph-fill text-success me-2"></i>Laporan Hasil Panen Jambu Citra</h4>
        <p class="text-muted small mb-0">Rekapitulasi berat timbangan produksi panen buah per kebun dan grade kualitas.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= url('reports/index.php'); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-folder2-open me-1"></i> Semua Laporan
        </a>
        <button type="button" class="btn btn-outline-dark btn-sm" onclick="window.print();">
            <i class="bi bi-printer me-1"></i> Cetak Laporan
        </button>
        <a href="<?= url('reports/export-csv.php?type=harvests&farm_id=' . $filterFarm . '&grade=' . urlencode($filterGrade) . '&start_date=' . urlencode($startDate) . '&end_date=' . urlencode($endDate)); ?>" class="btn btn-outline-success btn-sm">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Ekspor CSV
        </a>
    </div>
</div>

<!-- Filter Laporan -->
<div class="card mb-4 border-0 shadow-sm no-print">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1">Pilih Kebun</label>
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
                <label class="form-label small mb-1">Grade Kualitas</label>
                <select name="grade" class="form-select form-select-sm">
                    <option value="">-- Seluruh Grade --</option>
                    <option value="Grade Super" <?= $filterGrade === 'Grade Super' ? 'selected' : ''; ?>>Grade Super</option>
                    <option value="Grade A" <?= $filterGrade === 'Grade A' ? 'selected' : ''; ?>>Grade A</option>
                    <option value="Grade B" <?= $filterGrade === 'Grade B' ? 'selected' : ''; ?>>Grade B</option>
                    <option value="Grade Standar" <?= $filterGrade === 'Grade Standar' ? 'selected' : ''; ?>>Grade Standar</option>
                    <option value="Sortiran (BS)" <?= $filterGrade === 'Sortiran (BS)' ? 'selected' : ''; ?>>Sortiran (BS)</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Mulai Tanggal</label>
                <input type="date" name="start_date" class="form-control form-control-sm" value="<?= e($startDate); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Sampai Tanggal</label>
                <input type="date" name="end_date" class="form-control form-control-sm" value="<?= e($endDate); ?>">
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <a href="<?= url('reports/harvests-report.php'); ?>" class="btn btn-outline-secondary btn-sm" title="Reset">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Ringkasan Komposisi Grade -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon purple"><i class="bi bi-basket3-fill"></i></div>
            <div>
                <div class="stat-label">Total Panen Periode</div>
                <div class="stat-value text-dark"><?= format_number($totalWeight, 2); ?> <span class="fs-6 fw-normal text-muted">Kg</span></div>
                <div class="stat-sub">Volume fisik buah petik</div>
            </div>
        </div>
    </div>
    <div class="col-md-9">
        <div class="card h-100 p-3 border-0 shadow-sm d-flex flex-row flex-wrap align-items-center gap-3">
            <div class="text-secondary small fw-bold text-uppercase me-2">Rekapitulasi Mutu:</div>
            <?php if (empty($gradeTotals)): ?>
                <span class="text-muted small">Belum ada hasil panen yang tercatat.</span>
            <?php else: ?>
                <?php foreach ($gradeTotals as $gName => $kgSum): 
                    $pct = ($totalWeight > 0) ? ($kgSum / $totalWeight) * 100 : 0;
                ?>
                    <div class="badge bg-light text-dark border p-2 d-flex align-items-center gap-2">
                        <span class="fw-semibold"><?= e($gName); ?>:</span>
                        <span class="text-success fw-bold"><?= format_number($kgSum, 2); ?> Kg</span>
                        <span class="text-muted small">(<?= number_format($pct, 1, ',', '.'); ?>%)</span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Tabel Laporan -->
<div class="card shadow-sm border-0 mb-4">
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 40px;">No</th>
                    <th style="width: 120px;">Tanggal Panen</th>
                    <th>Kebun Lokasi Panen</th>
                    <th>Lokasi Lahan</th>
                    <th class="text-center" style="width: 160px;">Grade Kualitas</th>
                    <th class="text-end" style="width: 150px;">Berat Panen (Kg)</th>
                    <th>Catatan Lapangan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($harvests)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            Tidak ada data hasil panen pada rentang periode yang dipilih.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($harvests as $h): ?>
                        <tr>
                            <td class="text-muted text-center"><?= $no++; ?></td>
                            <td><?= format_date($h['harvest_date'], true); ?></td>
                            <td><strong><?= e($h['farm_name']); ?></strong></td>
                            <td><?= e($h['location']); ?></td>
                            <td class="text-center">
                                <span class="badge bg-success-subtle text-success fs-6"><?= e($h['fruit_grade']); ?></span>
                            </td>
                            <td class="text-end fw-bold text-dark fs-6"><?= format_number($h['weight_kg'], 2); ?> Kg</td>
                            <td><small class="text-muted"><?= e($h['notes'] ?: '-'); ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <th colspan="5" class="text-end fs-6">TOTAL HASIL PANEN:</th>
                    <th class="text-end text-success fs-5"><?= format_number($totalWeight, 2); ?> Kg</th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Kolom Tanda Tangan Cetak -->
<div class="print-signature">
    <div class="print-signature-box">
        <p style="margin-bottom: 60px;">Pengelola Usaha Tani Jambu Citra,</p>
        <p style="border-bottom: 1px solid #000; font-weight: bold; margin-bottom: 2px;">
            <?= e($currentUser['full_name'] ?? 'Pengelola'); ?>
        </p>
        <small style="color: #666;">Laporan Produksi Kebun</small>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
