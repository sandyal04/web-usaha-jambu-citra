<?php
/**
 * Modul Data Kebun - Daftar Kebun
 * Sistem Informasi Usaha Tani Jambu Citra
 */

declare(strict_types=1);

$pageTitle = 'Data Kebun Jambu Citra';
$activePage = 'farms';

require_once __DIR__ . '/../includes/header.php';

$pdo = get_db_connection();

// Pencarian
$search = trim($_GET['q'] ?? '');
$sql = "
    SELECT f.*, 
           (SELECT COUNT(*) FROM operational_costs c WHERE c.farm_id = f.id) AS costs_count,
           (SELECT COUNT(*) FROM harvests h WHERE h.farm_id = f.id) AS harvests_count
    FROM farms f
";
$params = [];

if (!empty($search)) {
    $sql .= " WHERE f.farm_name LIKE ? OR f.location LIKE ? OR f.notes LIKE ?";
    $searchTerm = '%' . $search . '%';
    $params = [$searchTerm, $searchTerm, $searchTerm];
}

$sql .= " ORDER BY f.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$farms = $stmt->fetchAll();

// Ringkasan Statistik Kebun
$totalFarms = count($farms);
$totalTrees = array_sum(array_column($farms, 'tree_count'));
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-tree-fill text-success me-2"></i>Daftar Lahan Kebun</h4>
        <p class="text-muted small mb-0">Kelola master data lahan dan pohon jambu citra sebagai referensi biaya dan panen.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('farms/create.php'); ?>" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> Tambah Kebun Baru
        </a>
    </div>
</div>

<!-- Kartu Ringkasan -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-md-4">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-geo-alt-fill"></i></div>
            <div>
                <div class="stat-label">Total Lahan Kebun</div>
                <div class="stat-value"><?= $totalFarms; ?></div>
                <div class="stat-sub">Lokasi aktif tercatat</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-md-4">
        <div class="stat-card">
            <div class="stat-icon purple"><i class="bi bi-flower2"></i></div>
            <div>
                <div class="stat-label">Total Populasi Pohon</div>
                <div class="stat-value"><?= format_number($totalTrees, 0); ?></div>
                <div class="stat-sub">Pohon jambu citra produktif</div>
            </div>
        </div>
    </div>
</div>

<!-- Tabel Data Kebun -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3">
        <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Cari nama kebun / lokasi..." value="<?= e($search); ?>">
                    <button class="btn btn-outline-secondary" type="submit">Cari</button>
                    <?php if (!empty($search)): ?>
                        <a href="<?= url('farms/index.php'); ?>" class="btn btn-outline-danger">Reset</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 50px;">No</th>
                    <th>Nama Kebun</th>
                    <th>Lokasi Lahan</th>
                    <th>Luas Lahan</th>
                    <th>Jumlah Pohon</th>
                    <th>Aktivitas Terkait</th>
                    <th>Keterangan</th>
                    <th class="text-center" style="width: 130px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($farms)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-tree text-secondary fs-1 d-block mb-2"></i>
                            Belum ada data kebun. Silakan klik tombol <strong>Tambah Kebun Baru</strong>.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($farms as $farm): ?>
                        <tr>
                            <td class="text-muted"><?= $no++; ?></td>
                            <td>
                                <strong class="text-dark d-block"><?= e($farm['farm_name']); ?></strong>
                                <small class="text-muted">ID: #<?= (int) $farm['id']; ?></small>
                            </td>
                            <td><?= e($farm['location']); ?></td>
                            <td><?= format_number($farm['area_size'], 2); ?> <?= e($farm['area_unit']); ?></td>
                            <td><span class="badge bg-success-subtle text-success fs-6 fw-semibold"><?= format_number($farm['tree_count'], 0); ?> Pohon</span></td>
                            <td>
                                <span class="badge bg-light text-secondary border me-1"><?= (int) $farm['costs_count']; ?> Biaya</span>
                                <span class="badge bg-light text-secondary border"><?= (int) $farm['harvests_count']; ?> Panen</span>
                            </td>
                            <td><small class="text-muted"><?= e($farm['notes'] ?: '-'); ?></small></td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= url('farms/edit.php?id=' . $farm['id']); ?>" class="btn btn-outline-primary" title="Edit Kebun">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="<?= url('farms/delete.php'); ?>" class="d-inline" onsubmit="return confirm('Hapus kebun <?= e(addslashes($farm['farm_name'])); ?>?');">
                                        <?= csrf_field(); ?>
                                        <input type="hidden" name="id" value="<?= (int) $farm['id']; ?>">
                                        <button type="submit" class="btn btn-outline-danger" title="Hapus Kebun">
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
