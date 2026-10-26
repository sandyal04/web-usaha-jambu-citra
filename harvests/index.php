<?php
/**
 * Modul Hasil Panen - Daftar Panen Jambu Citra
 * Sistem Informasi Usaha Tani Jambu Citra
 */

declare(strict_types=1);

$pageTitle = 'Pencatatan Hasil Panen';
$activePage = 'harvests';

require_once __DIR__ . '/../includes/header.php';

$pdo = get_db_connection();

// Filter & Pencarian
$filterFarm = isset($_GET['farm_id']) && $_GET['farm_id'] !== '' ? (int) $_GET['farm_id'] : 0;
$filterGrade = trim($_GET['grade'] ?? '');
$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');

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

// Ambil data panen
$sql = "
    SELECT h.*, f.farm_name, f.location 
    FROM harvests h
    JOIN farms f ON h.farm_id = f.id
    WHERE $whereClause
    ORDER BY h.harvest_date DESC, h.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$harvests = $stmt->fetchAll();

// Total panen Kg
$totalKg = array_sum(array_column($harvests, 'weight_kg'));

// Rekap Panen Berdasarkan Grade
$gradeSummary = [];
foreach ($harvests as $h) {
    $g = $h['fruit_grade'];
    if (!isset($gradeSummary[$g])) {
        $gradeSummary[$g] = 0.0;
    }
    $gradeSummary[$g] += (float) $h['weight_kg'];
}

$allFarms = $pdo->query("SELECT id, farm_name FROM farms ORDER BY farm_name ASC")->fetchAll();
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-box-seam-fill text-success me-2"></i>Pencatatan Hasil Panen</h4>
        <p class="text-muted small mb-0">Catat berat panen buah jambu citra per kebun dan grade kualitas.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('harvests/create.php'); ?>" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> Catat Hasil Panen Baru
        </a>
    </div>
</div>

<!-- Informasi Pemisahan Hasil Panen dan Pendapatan -->
<div class="alert alert-info border-0 shadow-sm d-flex align-items-center mb-4 py-2">
    <i class="bi bi-info-circle-fill fs-5 me-2 text-primary"></i>
    <div class="small">
        <strong>Ketentuan Finansial:</strong> Hasil panen adalah data produksi fisik kebun (Kg). Hasil panen <em>tidak otomatis menjadi pendapatan</em> sampai tercatat transaksi penjualan buah yang dikonfirmasi.
    </div>
</div>

<!-- Filter Pencarian -->
<div class="card mb-4 border-0 shadow-sm">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1">Pilih Kebun</label>
                <select name="farm_id" class="form-select form-select-sm">
                    <option value="0">-- Semua Kebun --</option>
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
                    <option value="">-- Semua Grade --</option>
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
                <a href="<?= url('harvests/index.php'); ?>" class="btn btn-outline-secondary btn-sm" title="Reset">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Kartu Rekap Hasil Panen -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon purple"><i class="bi bi-basket3-fill"></i></div>
            <div>
                <div class="stat-label">Total Panen Terpilih</div>
                <div class="stat-value text-dark"><?= format_number($totalKg, 2); ?> <span class="fs-6 fw-normal text-muted">Kg</span></div>
                <div class="stat-sub">Dari <?= count($harvests); ?> sesi pemetikan</div>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card h-100 p-3 border-0 shadow-sm d-flex flex-row flex-wrap align-items-center gap-3">
            <div class="text-secondary small fw-bold text-uppercase me-2">Komposisi Grade:</div>
            <?php if (empty($gradeSummary)): ?>
                <span class="text-muted small">Belum ada data untuk periode ini.</span>
            <?php else: ?>
                <?php foreach ($gradeSummary as $gradeName => $kgVal): ?>
                    <div class="badge bg-light text-dark border p-2 d-flex align-items-center gap-2">
                        <span class="fw-semibold"><?= e($gradeName); ?>:</span>
                        <span class="text-success fw-bold"><?= format_number($kgVal, 2); ?> Kg</span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Tabel Data Panen -->
<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 50px;">No</th>
                    <th>Tanggal Panen</th>
                    <th>Kebun Terkait</th>
                    <th class="text-center">Kualitas / Grade</th>
                    <th class="text-end">Berat Panen (Kg)</th>
                    <th>Catatan Lapangan</th>
                    <th class="text-center" style="width: 120px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($harvests)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-box-seam text-secondary fs-1 d-block mb-2"></i>
                            Tidak ada data hasil panen yang tercatat untuk filter ini.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($harvests as $h): ?>
                        <tr>
                            <td class="text-muted"><?= $no++; ?></td>
                            <td>
                                <strong class="text-dark"><?= format_date($h['harvest_date'], true); ?></strong>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><?= e($h['farm_name']); ?></span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-success-subtle text-success fs-6"><?= e($h['fruit_grade']); ?></span>
                            </td>
                            <td class="text-end fw-bold fs-6 text-dark">
                                <?= format_number($h['weight_kg'], 2); ?> Kg
                            </td>
                            <td>
                                <small class="text-muted"><?= e($h['notes'] ?: '-'); ?></small>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= url('harvests/edit.php?id=' . $h['id']); ?>" class="btn btn-outline-primary" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="<?= url('harvests/delete.php'); ?>" class="d-inline" onsubmit="return confirm('Hapus catatan panen <?= format_number($h['weight_kg'], 2); ?> Kg ini?');">
                                        <?= csrf_field(); ?>
                                        <input type="hidden" name="id" value="<?= (int) $h['id']; ?>">
                                        <button type="submit" class="btn btn-outline-danger" title="Hapus">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
