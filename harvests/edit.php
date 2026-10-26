<?php
/**
 * Modul Hasil Panen - Edit Catatan Panen
 * Sistem Informasi Usaha Tani Jambu Citra
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

require_login();

$pdo = get_db_connection();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM harvests WHERE id = ?");
$stmt->execute([$id]);
$harvest = $stmt->fetch();

if (!$harvest) {
    set_flash('danger', 'Catatan hasil panen tidak ditemukan.');
    redirect('harvests/index.php');
    exit;
}

$farms = $pdo->query("SELECT id, farm_name FROM farms ORDER BY farm_name ASC")->fetchAll();

$errors = [];
$old = [
    'farm_id'      => $harvest['farm_id'],
    'harvest_date' => $harvest['harvest_date'],
    'weight_kg'    => (string) $harvest['weight_kg'],
    'fruit_grade'  => $harvest['fruit_grade'],
    'notes'        => (string) ($harvest['notes'] ?? '')
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_die();

    $farmId      = (int) ($_POST['farm_id'] ?? 0);
    $harvestDate = trim($_POST['harvest_date'] ?? '');
    $weightKg    = (float) ($_POST['weight_kg'] ?? 0);
    $fruitGrade  = trim($_POST['fruit_grade'] ?? 'Grade A');
    $notes       = trim($_POST['notes'] ?? '');

    $old = [
        'farm_id'      => $farmId,
        'harvest_date' => $harvestDate,
        'weight_kg'    => (string) $weightKg,
        'fruit_grade'  => $fruitGrade,
        'notes'        => $notes
    ];

    if ($farmId <= 0) {
        $errors[] = 'Kebun lokasi panen wajib dipilih.';
    }

    if (empty($harvestDate)) {
        $errors[] = 'Tanggal panen wajib diisi.';
    }

    if ($weightKg <= 0) {
        $errors[] = 'Berat hasil panen harus lebih besar dari 0 Kg.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE harvests 
                SET farm_id = ?, harvest_date = ?, weight_kg = ?, fruit_grade = ?, notes = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$farmId, $harvestDate, $weightKg, $fruitGrade, $notes ?: null, $id]);

            set_flash('success', 'Catatan hasil panen berhasil diperbarui!');
            redirect('harvests/index.php');
            exit;
        } catch (PDOException $e) {
            error_log('Update Harvest Error: ' . $e->getMessage());
            $errors[] = 'Gagal memperbarui hasil panen: ' . $e->getMessage();
        }
    }
}

// Render HTML Layout setelah POST selesai
$pageTitle = 'Edit Catatan Panen';
$activePage = 'harvests';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-pencil-square text-success me-2"></i>Edit Catatan Panen</h4>
                <p class="text-muted small mb-0">Perbarui data berat timbangan dan grade panen.</p>
            </div>
            <a href="<?= url('harvests/index.php'); ?>" class="btn btn-outline-secondary btn-sm">
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
                        <label for="farm_id" class="form-label">Kebun Lokasi Panen <span class="text-danger">*</span></label>
                        <select name="farm_id" id="farm_id" class="form-select" required>
                            <?php foreach ($farms as $farm): ?>
                                <option value="<?= $farm['id']; ?>" <?= (int)$old['farm_id'] === (int)$farm['id'] ? 'selected' : ''; ?>>
                                    <?= e($farm['farm_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="harvest_date" class="form-label">Tanggal Petik / Panen <span class="text-danger">*</span></label>
                            <input type="date" name="harvest_date" id="harvest_date" class="form-control" 
                                   value="<?= e($old['harvest_date']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="fruit_grade" class="form-label">Kualitas / Grade Buah <span class="text-danger">*</span></label>
                            <select name="fruit_grade" id="fruit_grade" class="form-select" required>
                                <option value="Grade Super" <?= $old['fruit_grade'] === 'Grade Super' ? 'selected' : ''; ?>>Grade Super</option>
                                <option value="Grade A" <?= $old['fruit_grade'] === 'Grade A' ? 'selected' : ''; ?>>Grade A</option>
                                <option value="Grade B" <?= $old['fruit_grade'] === 'Grade B' ? 'selected' : ''; ?>>Grade B</option>
                                <option value="Grade Standar" <?= $old['fruit_grade'] === 'Grade Standar' ? 'selected' : ''; ?>>Grade Standar</option>
                                <option value="Sortiran (BS)" <?= $old['fruit_grade'] === 'Sortiran (BS)' ? 'selected' : ''; ?>>Sortiran (BS)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="weight_kg" class="form-label">Berat Hasil Panen (Kilogram) <span class="text-danger">*</span></label>
                        <div class="input-group input-group-lg">
                            <input type="number" step="0.01" min="0.01" name="weight_kg" id="weight_kg" class="form-control fw-bold" 
                                   value="<?= e($old['weight_kg']); ?>" required>
                            <span class="input-group-text fw-bold">Kg</span>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="notes" class="form-label">Keterangan / Catatan Lapangan</label>
                        <textarea name="notes" id="notes" rows="3" class="form-control"><?= e($old['notes']); ?></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?= url('harvests/index.php'); ?>" class="btn btn-light border px-4">Batal</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-save me-1"></i> Perbarui Catatan Panen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
