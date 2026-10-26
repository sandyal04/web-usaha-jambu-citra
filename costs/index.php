<?php
/**
 * Modul Biaya Operasional - Daftar Pengeluaran Biaya
 * Sistem Informasi Usaha Tani Jambu Citra
 */

declare(strict_types=1);

$pageTitle = 'Biaya Operasional Kebun';
$activePage = 'costs';

require_once __DIR__ . '/../includes/header.php';

$pdo = get_db_connection();

// Filter & Pencarian
$search = trim($_GET['q'] ?? '');
$filterFarm = isset($_GET['farm_id']) && $_GET['farm_id'] !== '' ? (int) $_GET['farm_id'] : 0;
$filterCat = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? (int) $_GET['category_id'] : 0;
$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');

$where = ["1=1"];
$params = [];

if (!empty($search)) {
    $where[] = "(c.item_name LIKE ? OR c.notes LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

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

// Ambil data biaya
$sql = "
    SELECT c.*, f.farm_name, cat.category_name 
    FROM operational_costs c
    JOIN farms f ON c.farm_id = f.id
    JOIN cost_categories cat ON c.category_id = cat.id
    WHERE $whereClause
    ORDER BY c.cost_date DESC, c.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$costs = $stmt->fetchAll();

// Hitung total pengeluaran untuk filter aktif
$totalExpense = array_sum(array_column($costs, 'total_cost'));

// Master data untuk dropdown filter
$allFarms = $pdo->query("SELECT id, farm_name FROM farms ORDER BY farm_name ASC")->fetchAll();
$allCategories = $pdo->query("SELECT id, category_name FROM cost_categories ORDER BY category_name ASC")->fetchAll();
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-cash-stack text-success me-2"></i>Biaya Operasional Kebun</h4>
        <p class="text-muted small mb-0">Pencatatan pengeluaran pupuk, obat, tenaga kerja, dan sarana produksi jambu citra.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('costs/create.php'); ?>" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> Catat Biaya Baru
        </a>
    </div>
</div>

<!-- Card Filter & Pencarian -->
<div class="card mb-4 border-0 shadow-sm">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1">Cari Kebutuhan / Catatan</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Nama item..." value="<?= e($search); ?>">
                </div>
            </div>
            <div class="col-md-2">
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
            <div class="col-md-2">
                <label class="form-label small mb-1">Kategori Biaya</label>
                <select name="category_id" class="form-select form-select-sm">
                    <option value="0">-- Semua Kategori --</option>
                    <?php foreach ($allCategories as $cat): ?>
                        <option value="<?= $cat['id']; ?>" <?= $filterCat === (int)$cat['id'] ? 'selected' : ''; ?>>
                            <?= e($cat['category_name']); ?>
                        </option>
                    <?php endforeach; ?>
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
            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm w-100" title="Terapkan Filter">
                    <i class="bi bi-funnel"></i>
                </button>
                <a href="<?= url('costs/index.php'); ?>" class="btn btn-outline-secondary btn-sm" title="Reset Filter">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Ringkasan Total Biaya -->
<div class="alert alert-light border d-flex justify-content-between align-items-center mb-3 py-2 px-3">
    <span class="text-secondary small">
        Menampilkan <strong><?= count($costs); ?></strong> catatan pengeluaran operasional
    </span>
    <span class="fs-6">
        Total Biaya Terpilih: <strong class="text-danger fs-5"><?= format_rupiah($totalExpense); ?></strong>
    </span>
</div>

<!-- Tabel Data Biaya -->
<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 50px;">No</th>
                    <th>Tanggal</th>
                    <th>Kebun Terkait</th>
                    <th>Kategori</th>
                    <th>Nama Kebutuhan</th>
                    <th class="text-center">Kuantitas</th>
                    <th class="text-end">Harga Satuan</th>
                    <th class="text-end">Total Biaya</th>
                    <th class="text-center" style="width: 140px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($costs)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <i class="bi bi-receipt text-secondary fs-1 d-block mb-2"></i>
                            Tidak ada data biaya operasional yang sesuai dengan kriteria filter.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($costs as $cost): ?>
                        <tr>
                            <td class="text-muted"><?= $no++; ?></td>
                            <td>
                                <span class="fw-semibold text-dark"><?= format_date($cost['cost_date'], true); ?></span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><?= e($cost['farm_name']); ?></span>
                            </td>
                            <td>
                                <span class="badge bg-success-subtle text-success"><?= e($cost['category_name']); ?></span>
                            </td>
                            <td>
                                <strong class="text-dark d-block"><?= e($cost['item_name']); ?></strong>
                                <?php if (!empty($cost['notes'])): ?>
                                    <small class="text-muted"><?= e($cost['notes']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?= format_number($cost['quantity'], 2); ?> <?= e($cost['unit']); ?>
                            </td>
                            <td class="text-end text-muted">
                                <?= $cost['unit_price'] > 0 ? format_rupiah($cost['unit_price']) : '-'; ?>
                            </td>
                            <td class="text-end fw-bold text-danger">
                                <?= format_rupiah($cost['total_cost']); ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= url('costs/detail.php?id=' . $cost['id']); ?>" class="btn btn-outline-secondary" title="Detail">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="<?= url('costs/edit.php?id=' . $cost['id']); ?>" class="btn btn-outline-primary" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="<?= url('costs/delete.php'); ?>" class="d-inline" onsubmit="return confirm('Hapus catatan pengeluaran <?= e(addslashes($cost['item_name'])); ?> senilai <?= format_rupiah($cost['total_cost']); ?>?');">
                                        <?= csrf_field(); ?>
                                        <input type="hidden" name="id" value="<?= (int) $cost['id']; ?>">
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
