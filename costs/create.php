<?php
/**
 * Modul Biaya Operasional - Catat Pengeluaran Baru
 * Sistem Informasi Usaha Tani Jambu Citra
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

require_login();

$pdo = get_db_connection();

$farms = $pdo->query("SELECT id, farm_name FROM farms ORDER BY farm_name ASC")->fetchAll();
$categories = $pdo->query("SELECT id, category_name FROM cost_categories ORDER BY category_name ASC")->fetchAll();

if (empty($farms)) {
    set_flash('warning', 'Harap tambahkan data kebun terlebih dahulu sebelum mencatat biaya.');
    redirect('farms/create.php');
    exit;
}

$errors = [];
$old = [
    'farm_id'     => $farms[0]['id'] ?? '',
    'category_id' => $categories[0]['id'] ?? '',
    'cost_date'   => date('Y-m-d'),
    'item_name'   => '',
    'quantity'    => '1',
    'unit'        => 'paket',
    'unit_price'  => '',
    'total_cost'  => '',
    'notes'       => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_die();

    $farmId     = (int) ($_POST['farm_id'] ?? 0);
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $costDate   = trim($_POST['cost_date'] ?? '');
    $itemName   = trim($_POST['item_name'] ?? '');
    $quantity   = (float) ($_POST['quantity'] ?? 1);
    $unit       = trim($_POST['unit'] ?? 'paket');
    $unitPrice  = (float) ($_POST['unit_price'] ?? 0);
    $totalCost  = (float) ($_POST['total_cost'] ?? 0);
    $notes      = trim($_POST['notes'] ?? '');

    // Logika perhitungan keuangan:
    // Jika kuantitas & harga satuan diisi dan total biaya kosong atau 0:
    if ($quantity > 0 && $unitPrice > 0 && $totalCost <= 0) {
        $totalCost = $quantity * $unitPrice;
    }

    $old = [
        'farm_id'     => $farmId,
        'category_id' => $categoryId,
        'cost_date'   => $costDate,
        'item_name'   => $itemName,
        'quantity'    => (string) $quantity,
        'unit'        => $unit,
        'unit_price'  => (string) $unitPrice,
        'total_cost'  => (string) $totalCost,
        'notes'       => $notes
    ];

    if ($farmId <= 0) {
        $errors[] = 'Pilihan kebun wajib ditentukan.';
    }

    if ($categoryId <= 0) {
        $errors[] = 'Kategori biaya wajib dipilih.';
    }

    if (empty($costDate)) {
        $errors[] = 'Tanggal pengeluaran biaya wajib diisi.';
    }

    if (empty($itemName)) {
        $errors[] = 'Nama kebutuhan atau pengeluaran wajib diisi.';
    }

    if ($quantity <= 0) {
        $errors[] = 'Kuantitas harus lebih besar dari 0.';
    }

    if ($totalCost <= 0) {
        $errors[] = 'Total biaya harus lebih besar dari Rp 0.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO operational_costs 
                (farm_id, category_id, cost_date, item_name, quantity, unit, unit_price, total_cost, notes, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $farmId,
                $categoryId,
                $costDate,
                $itemName,
                $quantity,
                $unit,
                $unitPrice,
                $totalCost,
                $notes ?: null
            ]);

            set_flash('success', 'Biaya operasional berhasil dicatat!');
            redirect('costs/index.php');
            exit;
        } catch (PDOException $e) {
            error_log('Insert Cost Error: ' . $e->getMessage());
            $errors[] = 'Gagal menyimpan biaya: ' . $e->getMessage();
        }
    }
}

// Render HTML Layout hanya setelah proses POST selesai
$pageTitle = 'Catat Biaya Operasional';
$activePage = 'costs';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-cash-stack text-success me-2"></i>Catat Pengeluaran Biaya</h4>
                <p class="text-muted small mb-0">Catat pembelian sarana produksi, upah pekerja, dan biaya kebun lainnya.</p>
            </div>
            <a href="<?= url('costs/index.php'); ?>" class="btn btn-outline-secondary btn-sm">
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

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="farm_id" class="form-label">Kebun Terkait <span class="text-danger">*</span></label>
                            <select name="farm_id" id="farm_id" class="form-select" required>
                                <?php foreach ($farms as $farm): ?>
                                    <option value="<?= $farm['id']; ?>" <?= (int)$old['farm_id'] === (int)$farm['id'] ? 'selected' : ''; ?>>
                                        <?= e($farm['farm_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="category_id" class="form-label">Kategori Biaya <span class="text-danger">*</span></label>
                            <select name="category_id" id="category_id" class="form-select" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id']; ?>" <?= (int)$old['category_id'] === (int)$cat['id'] ? 'selected' : ''; ?>>
                                        <?= e($cat['category_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="cost_date" class="form-label">Tanggal Pengeluaran <span class="text-danger">*</span></label>
                            <input type="date" name="cost_date" id="cost_date" class="form-control" 
                                   value="<?= e($old['cost_date']); ?>" required>
                        </div>
                        <div class="col-md-8 mb-3">
                            <label for="item_name" class="form-label">Nama Kebutuhan / Pengeluaran <span class="text-danger">*</span></label>
                            <input type="text" name="item_name" id="item_name" class="form-control" 
                                   value="<?= e($old['item_name']); ?>" placeholder="Contoh: Pupuk NPK Mutiara 16-16-16" required>
                        </div>
                    </div>

                    <div class="bg-light p-3 rounded-3 mb-3 border">
                        <h6 class="fw-bold mb-3 text-secondary"><i class="bi bi-calculator me-1"></i> Rincian Kuantitas & Biaya</h6>
                        <div class="row">
                            <div class="col-md-4 mb-2">
                                <label for="cost_quantity" class="form-label">Kuantitas <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0.01" name="quantity" id="cost_quantity" class="form-control" 
                                       value="<?= e($old['quantity']); ?>" required>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label for="unit" class="form-label">Satuan</label>
                                <input type="text" name="unit" id="unit" class="form-control" 
                                       value="<?= e($old['unit']); ?>" placeholder="karung / kg / liter / hari / paket" required>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label for="cost_unit_price" class="form-label">Harga Satuan (Rp)</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" step="1" min="0" name="unit_price" id="cost_unit_price" class="form-control" 
                                           value="<?= e($old['unit_price']); ?>" placeholder="0">
                                </div>
                                <small class="text-muted">Biarkan kosong jika borongan</small>
                            </div>
                        </div>

                        <div class="mt-2 pt-2 border-top">
                            <label for="cost_total" class="form-label fw-bold text-dark">Total Biaya Operasional (Rp) <span class="text-danger">*</span></label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text fw-bold bg-white text-danger">Rp</span>
                                <input type="number" step="1" min="1" name="total_cost" id="cost_total" class="form-control fw-bold text-danger" 
                                       value="<?= e($old['total_cost']); ?>" placeholder="0" required>
                            </div>
                            <small class="text-muted">Otomatis dihitung dari (Kuantitas &times; Harga Satuan), atau dapat diisi langsung jika berupa biaya gelondongan/borongan.</small>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="notes" class="form-label">Keterangan / Catatan Tambahan (Opsional)</label>
                        <textarea name="notes" id="notes" rows="2" class="form-control" 
                                  placeholder="Contoh: Pembelian di Toko Tani Makmur, faktur No. 8923"><?= e($old['notes']); ?></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?= url('costs/index.php'); ?>" class="btn btn-light border px-4">Batal</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-save me-1"></i> Simpan Catatan Biaya
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
