<?php
/**
 * Modul Data Kebun - Tambah Kebun Baru
 * Sistem Informasi Usaha Tani Jambu Citra
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

require_login();

$pdo = get_db_connection();
$errors = [];
$old = [
    'farm_name'  => '',
    'location'   => '',
    'area_size'  => '1000',
    'area_unit'  => 'm2',
    'tree_count' => '50',
    'notes'      => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_die();

    $farmName  = trim($_POST['farm_name'] ?? '');
    $location  = trim($_POST['location'] ?? '');
    $areaSize  = (float) ($_POST['area_size'] ?? 0);
    $areaUnit  = trim($_POST['area_unit'] ?? 'm2');
    $treeCount = (int) ($_POST['tree_count'] ?? 0);
    $notes     = trim($_POST['notes'] ?? '');

    $old = [
        'farm_name'  => $farmName,
        'location'   => $location,
        'area_size'  => (string) $areaSize,
        'area_unit'  => $areaUnit,
        'tree_count' => (string) $treeCount,
        'notes'      => $notes
    ];

    if (empty($farmName)) {
        $errors[] = 'Nama kebun wajib diisi.';
    }

    if (empty($location)) {
        $errors[] = 'Lokasi kebun wajib diisi.';
    }

    if ($areaSize <= 0) {
        $errors[] = 'Luas lahan harus lebih besar dari 0.';
    }

    if ($treeCount < 0) {
        $errors[] = 'Jumlah pohon tidak boleh bernilai negatif.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO farms (farm_name, location, area_size, area_unit, tree_count, notes, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$farmName, $location, $areaSize, $areaUnit, $treeCount, $notes ?: null]);

            set_flash('success', 'Data kebun berhasil ditambahkan!');
            redirect('farms/index.php');
            exit;
        } catch (PDOException $e) {
            error_log('Insert Farm Error: ' . $e->getMessage());
            $errors[] = 'Gagal menyimpan data kebun: ' . $e->getMessage();
        }
    }
}

// Render HTML Layout setelah POST selesai
$pageTitle = 'Tambah Kebun Baru';
$activePage = 'farms';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-plus-circle-fill text-success me-2"></i>Tambah Data Kebun Baru</h4>
                <p class="text-muted small mb-0">Masukkan informasi lahan kebun jambu citra.</p>
            </div>
            <a href="<?= url('farms/index.php'); ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar
            </a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger py-2 mb-3">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <form method="POST" action="">
                    <?= csrf_field(); ?>

                    <div class="mb-3">
                        <label for="farm_name" class="form-label">Nama Kebun / Blok <span class="text-danger">*</span></label>
                        <input type="text" name="farm_name" id="farm_name" class="form-control" 
                               value="<?= e($old['farm_name']); ?>" placeholder="Contoh: Kebun Jambu Blok Barat" required autofocus>
                    </div>

                    <div class="mb-3">
                        <label for="location" class="form-label">Lokasi / Alamat Lahan <span class="text-danger">*</span></label>
                        <input type="text" name="location" id="location" class="form-control" 
                               value="<?= e($old['location']); ?>" placeholder="Contoh: Desa Sidomulyo, RT 02 / RW 03" required>
                    </div>

                    <div class="row">
                        <div class="col-md-5 mb-3">
                            <label for="area_size" class="form-label">Luas Lahan <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" name="area_size" id="area_size" class="form-control" 
                                   value="<?= e($old['area_size']); ?>" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="area_unit" class="form-label">Satuan Luas</label>
                            <select name="area_unit" id="area_unit" class="form-select">
                                <option value="m2" <?= $old['area_unit'] === 'm2' ? 'selected' : ''; ?>>m² (Meter Persegi)</option>
                                <option value="hektar" <?= $old['area_unit'] === 'hektar' ? 'selected' : ''; ?>>Hektar (ha)</option>
                                <option value="bata" <?= $old['area_unit'] === 'bata' ? 'selected' : ''; ?>>Bata / Ubin</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="tree_count" class="form-label">Jumlah Pohon Jambu</label>
                            <div class="input-group">
                                <input type="number" min="0" name="tree_count" id="tree_count" class="form-control" 
                                       value="<?= e($old['tree_count']); ?>" placeholder="0">
                                <span class="input-group-text">Pohon</span>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="notes" class="form-label">Catatan Tambahan (Opsional)</label>
                        <textarea name="notes" id="notes" rows="3" class="form-control" 
                                  placeholder="Contoh: Varietas Jambu Citra umur tanam 4 tahun, jenis tanah lempung berpasir."><?= e($old['notes']); ?></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?= url('farms/index.php'); ?>" class="btn btn-light border px-4">Batal</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-save me-1"></i> Simpan Data Kebun
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
