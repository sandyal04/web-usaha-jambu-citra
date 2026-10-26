<?php
/**
 * Modul Penjualan Jambu Citra - Edit Transaksi Penjualan
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

$stmt = $pdo->prepare("SELECT * FROM sales WHERE id = ?");
$stmt->execute([$id]);
$sale = $stmt->fetch();

if (!$sale) {
    set_flash('danger', 'Transaksi penjualan tidak ditemukan.');
    redirect('sales/index.php');
    exit;
}

$customers = $pdo->query("SELECT id, customer_name, phone FROM customers ORDER BY customer_name ASC")->fetchAll();

$stmtItems = $pdo->prepare("SELECT * FROM sale_items WHERE sale_id = ? ORDER BY id ASC");
$stmtItems->execute([$id]);
$existingItems = $stmtItems->fetchAll();

$errors = [];
$isConfirmed = ($sale['status'] === 'confirmed');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_die();

    $customerId = !empty($_POST['customer_id']) ? (int) $_POST['customer_id'] : null;
    $saleDate   = trim($_POST['sale_date'] ?? '');
    $notes      = trim($_POST['notes'] ?? '');

    if (empty($saleDate)) {
        $errors[] = 'Tanggal transaksi wajib diisi.';
    }

    if (!$isConfirmed) {
        $itemGrades = $_POST['items']['product_grade'] ?? [];
        $itemWeights= $_POST['items']['weight_kg'] ?? [];
        $itemPrices = $_POST['items']['price_per_kg'] ?? [];
        $itemNotes  = $_POST['items']['notes'] ?? [];
        $status     = trim($_POST['status'] ?? 'draft');

        $sanitizedItems = [];
        $calculatedTotal = 0.0;

        for ($i = 0; $i < count($itemGrades); $i++) {
            $grade  = trim($itemGrades[$i] ?? '');
            $weight = (float) ($itemWeights[$i] ?? 0);
            $price  = (float) ($itemPrices[$i] ?? 0);
            $note   = trim($itemNotes[$i] ?? '');

            if (!empty($grade) && $weight > 0 && $price >= 0) {
                $subtotal = $weight * $price;
                $calculatedTotal += $subtotal;
                $sanitizedItems[] = [
                    'product_grade' => $grade,
                    'weight_kg'     => $weight,
                    'price_per_kg'  => $price,
                    'subtotal'      => $subtotal,
                    'notes'         => $note
                ];
            }
        }

        if (empty($sanitizedItems)) {
            $errors[] = 'Harap isi minimal 1 item produk dengan berat dan harga yang valid.';
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    UPDATE sales 
                    SET customer_id = ?, sale_date = ?, total_amount = ?, status = ?, notes = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$customerId, $saleDate, $calculatedTotal, $status, $notes ?: null, $id]);

                // Hapus item lama dan ganti dengan item baru
                $pdo->prepare("DELETE FROM sale_items WHERE sale_id = ?")->execute([$id]);

                $stmtItem = $pdo->prepare("
                    INSERT INTO sale_items (sale_id, product_grade, weight_kg, price_per_kg, subtotal, notes)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                foreach ($sanitizedItems as $item) {
                    $stmtItem->execute([
                        $id,
                        $item['product_grade'],
                        $item['weight_kg'],
                        $item['price_per_kg'],
                        $item['subtotal'],
                        $item['notes'] ?: null
                    ]);
                }

                $pdo->commit();
                set_flash('success', 'Faktur draft penjualan berhasil diperbarui!');
                redirect('sales/index.php');
                exit;

            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log('Update Sale Error: ' . $e->getMessage());
                $errors[] = 'Gagal memperbarui transaksi: ' . $e->getMessage();
            }
        }
    } else {
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE sales 
                    SET customer_id = ?, sale_date = ?, notes = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$customerId, $saleDate, $notes ?: null, $id]);

                set_flash('success', 'Catatan dan informasi transaksi berhasil diperbarui.');
                redirect('sales/index.php');
                exit;
            } catch (PDOException $e) {
                error_log('Update Confirmed Sale Error: ' . $e->getMessage());
                $errors[] = 'Gagal memperbarui transaksi: ' . $e->getMessage();
            }
        }
    }
}

// Render HTML Layout setelah POST selesai
$pageTitle = 'Edit Transaksi Penjualan';
$activePage = 'sales';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-pencil-square text-success me-2"></i>Edit Transaksi Penjualan</h4>
        <p class="text-muted small mb-0">Nomor Faktur: <strong><?= e($sale['invoice_number']); ?></strong></p>
    </div>
    <a href="<?= url('sales/detail.php?id=' . $sale['id']); ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Kembali ke Detail
    </a>
</div>

<?php if ($isConfirmed): ?>
    <div class="alert alert-warning border-0 shadow-sm d-flex align-items-center mb-3">
        <i class="bi bi-shield-exclamation fs-4 me-3 text-warning"></i>
        <div class="small">
            <strong>Proteksi Integritas Finansial:</strong> Transaksi ini berstatus <strong>Confirmed (Sah)</strong>. Demi keamanan pembukuan dan audit keuangan, rincian item buah tidak dapat diubah secara diam-diam. Anda dapat memperbarui informasi pembeli dan catatan, atau membatalkan transaksi ini melalui tombol pembatalan di halaman detail.
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger py-2 mb-3">
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST" action="">
    <?= csrf_field(); ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <span class="fw-bold"><i class="bi bi-info-circle text-success me-1"></i> Data Transaksi</span>
                </div>
                <div class="card-body p-3">
                    <div class="mb-3">
                        <label class="form-label">Nomor Faktur</label>
                        <input type="text" class="form-control bg-light fw-bold" value="<?= e($sale['invoice_number']); ?>" readonly>
                    </div>

                    <div class="mb-3">
                        <label for="sale_date" class="form-label">Tanggal Transaksi <span class="text-danger">*</span></label>
                        <input type="date" name="sale_date" id="sale_date" class="form-control" 
                               value="<?= e($sale['sale_date']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="customer_id" class="form-label">Pelanggan</label>
                        <select name="customer_id" id="customer_id" class="form-select">
                            <option value="">-- Pembeli Umum / Cash --</option>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?= $c['id']; ?>" <?= (int)$sale['customer_id'] === (int)$c['id'] ? 'selected' : ''; ?>>
                                    <?= e($c['customer_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if (!$isConfirmed): ?>
                        <div class="mb-3">
                            <label for="status" class="form-label">Status Penjualan</label>
                            <select name="status" id="status" class="form-select">
                                <option value="draft" <?= $sale['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                <option value="confirmed" <?= $sale['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="notes" class="form-label">Keterangan Transaksi</label>
                        <textarea name="notes" id="notes" rows="3" class="form-control"><?= e($sale['notes'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <span class="fw-bold"><i class="bi bi-box-seam text-success me-1"></i> Rincian Produk Buah</span>
                    <?php if (!$isConfirmed): ?>
                        <button type="button" id="add-item-btn" class="btn btn-outline-success btn-sm">
                            <i class="bi bi-plus-lg me-1"></i> Tambah Baris
                        </button>
                    <?php endif; ?>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="sale-items-table">
                        <thead class="table-light">
                            <tr>
                                <th>Produk / Grade Buah</th>
                                <th style="width: 140px;">Berat (Kg)</th>
                                <th style="width: 160px;">Harga / Kg</th>
                                <th style="width: 160px;">Subtotal (Rp)</th>
                                <th>Catatan</th>
                                <?php if (!$isConfirmed): ?><th style="width: 50px;"></th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($existingItems as $idx => $item): ?>
                                <tr class="item-row">
                                    <td>
                                        <?php if ($isConfirmed): ?>
                                            <strong><?= e($item['product_grade']); ?></strong>
                                        <?php else: ?>
                                            <select name="items[product_grade][]" class="form-select" required>
                                                <option value="Jambu Citra Grade Super" <?= $item['product_grade'] === 'Jambu Citra Grade Super' ? 'selected' : ''; ?>>Jambu Citra Grade Super</option>
                                                <option value="Jambu Citra Grade A" <?= $item['product_grade'] === 'Jambu Citra Grade A' ? 'selected' : ''; ?>>Jambu Citra Grade A</option>
                                                <option value="Jambu Citra Grade B" <?= $item['product_grade'] === 'Jambu Citra Grade B' ? 'selected' : ''; ?>>Jambu Citra Grade B</option>
                                                <option value="Jambu Citra Grade Standar" <?= $item['product_grade'] === 'Jambu Citra Grade Standar' ? 'selected' : ''; ?>>Jambu Citra Grade Standar</option>
                                                <option value="Jambu Citra Sortiran (BS)" <?= $item['product_grade'] === 'Jambu Citra Sortiran (BS)' ? 'selected' : ''; ?>>Jambu Citra Sortiran (BS)</option>
                                            </select>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($isConfirmed): ?>
                                            <?= format_number($item['weight_kg'], 2); ?> Kg
                                        <?php else: ?>
                                            <div class="input-group">
                                                <input type="number" step="0.01" min="0.01" name="items[weight_kg][]" class="form-control item-weight" value="<?= e($item['weight_kg']); ?>" required>
                                                <span class="input-group-text">Kg</span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($isConfirmed): ?>
                                            <?= format_rupiah($item['price_per_kg']); ?>
                                        <?php else: ?>
                                            <div class="input-group">
                                                <span class="input-group-text">Rp</span>
                                                <input type="number" step="1" min="0" name="items[price_per_kg][]" class="form-control item-price" value="<?= e($item['price_per_kg']); ?>" required>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="input-group">
                                            <span class="input-group-text">Rp</span>
                                            <input type="number" step="1" name="items[subtotal][]" class="form-control item-subtotal bg-light" value="<?= e($item['subtotal']); ?>" readonly>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($isConfirmed): ?>
                                            <small class="text-muted"><?= e($item['notes'] ?: '-'); ?></small>
                                        <?php else: ?>
                                            <input type="text" name="items[notes][]" class="form-control" value="<?= e($item['notes'] ?? ''); ?>">
                                        <?php endif; ?>
                                    </td>
                                    <?php if (!$isConfirmed): ?>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-outline-danger btn-sm remove-item-btn" title="Hapus Baris">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="card-footer bg-white p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fs-5 fw-bold text-secondary">Total Nilai Penjualan:</span>
                        <div>
                            <span class="fs-3 fw-bold text-success" id="grand-total-display"><?= format_rupiah($sale['total_amount']); ?></span>
                            <input type="hidden" name="total_amount" id="sale-grand-total" value="<?= e($sale['total_amount']); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="<?= url('sales/index.php'); ?>" class="btn btn-light border px-4">Batal</a>
                <button type="submit" class="btn btn-primary px-4 py-2">
                    <i class="bi bi-save me-1"></i> Perbarui Transaksi
                </button>
            </div>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
