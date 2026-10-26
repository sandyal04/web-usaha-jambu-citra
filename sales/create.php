<?php
/**
 * Modul Penjualan Jambu Citra - Buat Transaksi Baru
 * Sistem Informasi Usaha Tani Jambu Citra
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

require_login();

$pdo = get_db_connection();

$invoiceNumber = generate_invoice_number($pdo);
$customers = $pdo->query("SELECT id, customer_name, phone FROM customers ORDER BY customer_name ASC")->fetchAll();

$errors = [];
$old = [
    'invoice_number' => $invoiceNumber,
    'customer_id'    => '',
    'sale_date'      => date('Y-m-d'),
    'status'         => 'confirmed',
    'notes'          => '',
    'items'          => [
        [
            'product_grade' => 'Jambu Citra Grade A',
            'weight_kg'     => '',
            'price_per_kg'  => '25000',
            'subtotal'      => '',
            'notes'         => ''
        ]
    ],
    'initial_payment' => '',
    'payment_method'  => 'Tunai'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_die();

    $invNum     = trim($_POST['invoice_number'] ?? '');
    $customerId = !empty($_POST['customer_id']) ? (int) $_POST['customer_id'] : null;
    $saleDate   = trim($_POST['sale_date'] ?? '');
    $status     = trim($_POST['status'] ?? 'confirmed');
    $notes      = trim($_POST['notes'] ?? '');

    $itemGrades = $_POST['items']['product_grade'] ?? [];
    $itemWeights= $_POST['items']['weight_kg'] ?? [];
    $itemPrices = $_POST['items']['price_per_kg'] ?? [];
    $itemNotes  = $_POST['items']['notes'] ?? [];

    $initialPayment = (float) ($_POST['initial_payment'] ?? 0);
    $paymentMethod  = trim($_POST['payment_method'] ?? 'Tunai');

    if (empty($invNum)) {
        $invNum = generate_invoice_number($pdo);
    }

    if (empty($saleDate)) {
        $errors[] = 'Tanggal transaksi wajib diisi.';
    }

    if (!in_array($status, ['confirmed', 'draft'], true)) {
        $status = 'confirmed';
    }

    // Validasi dan hitung total item
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
        $errors[] = 'Harap isi minimal 1 item produk dengan berat dan harga jual yang valid.';
    }

    if ($calculatedTotal <= 0) {
        $errors[] = 'Total nilai transaksi penjualan harus lebih besar dari Rp 0.';
    }

    if ($initialPayment > $calculatedTotal) {
        $errors[] = 'Jumlah pembayaran awal tidak boleh melebihi total penjualan (' . format_rupiah($calculatedTotal) . ').';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // 1. Simpan Header Transaksi Penjualan
            $stmt = $pdo->prepare("
                INSERT INTO sales (invoice_number, customer_id, sale_date, total_amount, status, notes, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $invNum,
                $customerId,
                $saleDate,
                $calculatedTotal,
                $status,
                $notes ?: null
            ]);
            $saleId = (int) $pdo->lastInsertId();

            // 2. Simpan Rincian Item Penjualan
            $stmtItem = $pdo->prepare("
                INSERT INTO sale_items (sale_id, product_grade, weight_kg, price_per_kg, subtotal, notes)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            foreach ($sanitizedItems as $item) {
                $stmtItem->execute([
                    $saleId,
                    $item['product_grade'],
                    $item['weight_kg'],
                    $item['price_per_kg'],
                    $item['subtotal'],
                    $item['notes'] ?: null
                ]);
            }

            // 3. Simpan Pembayaran Awal Jika Ada (Hanya jika status confirmed)
            if ($status === 'confirmed' && $initialPayment > 0) {
                $stmtPay = $pdo->prepare("
                    INSERT INTO sale_payments (sale_id, payment_date, amount, payment_method, notes, created_at)
                    VALUES (?, ?, ?, ?, 'Pembayaran awal saat transaksi', NOW())
                ");
                $stmtPay->execute([
                    $saleId,
                    $saleDate,
                    $initialPayment,
                    $paymentMethod
                ]);
            }

            $pdo->commit();

            set_flash('success', "Transaksi penjualan $invNum berhasil dibuat!");
            redirect('sales/index.php');
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('Create Sale Error: ' . $e->getMessage());
            $errors[] = 'Gagal menyimpan transaksi penjualan: ' . $e->getMessage();
        }
    }
}

// Render HTML Layout setelah POST selesai
$pageTitle = 'Transaksi Penjualan Baru';
$activePage = 'sales';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-cart-plus-fill text-success me-2"></i>Transaksi Penjualan Baru</h4>
        <p class="text-muted small mb-0">Catat transaksi penjualan buah jambu citra kepada pelanggan atau pedagang.</p>
    </div>
    <a href="<?= url('sales/index.php'); ?>" class="btn btn-outline-secondary btn-sm">
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

<form method="POST" action="">
    <?= csrf_field(); ?>

    <div class="row g-4">
        <!-- Informasi Utama Transaksi -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 sticky-top" style="top: 80px;">
                <div class="card-header bg-white py-3">
                    <span class="fw-bold"><i class="bi bi-info-circle me-1 text-success"></i> Data Faktur & Pelanggan</span>
                </div>
                <div class="card-body p-3">
                    <div class="mb-3">
                        <label for="invoice_number" class="form-label">Nomor Faktur (Otomatis)</label>
                        <input type="text" name="invoice_number" id="invoice_number" class="form-control fw-bold bg-light" 
                               value="<?= e($old['invoice_number']); ?>" readonly>
                    </div>

                    <div class="mb-3">
                        <label for="sale_date" class="form-label">Tanggal Transaksi <span class="text-danger">*</span></label>
                        <input type="date" name="sale_date" id="sale_date" class="form-control" 
                               value="<?= e($old['sale_date']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="customer_id" class="form-label">Pelanggan</label>
                        <select name="customer_id" id="customer_id" class="form-select">
                            <option value="">-- Pembeli Umum / Retail Cash --</option>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?= $c['id']; ?>" <?= (string)$old['customer_id'] === (string)$c['id'] ? 'selected' : ''; ?>>
                                    <?= e($c['customer_name']); ?> <?= $c['phone'] ? '('.e($c['phone']).')' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="d-flex justify-content-between mt-1">
                            <small class="text-muted">Opsional jika cash eceran</small>
                            <a href="<?= url('customers/create.php'); ?>" target="_blank" class="small text-decoration-none">+ Tambah Pelanggan</a>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="status" class="form-label">Status Penjualan <span class="text-danger">*</span></label>
                        <select name="status" id="status" class="form-select fw-semibold">
                            <option value="confirmed" <?= $old['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed (Sah - Menambah Pendapatan)</option>
                            <option value="draft" <?= $old['status'] === 'draft' ? 'selected' : ''; ?>>Draft (Konsep Sementara)</option>
                        </select>
                        <small class="text-muted">Hanya status Confirmed yang dihitung dalam Laporan Laba Rugi.</small>
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label">Keterangan Transaksi</label>
                        <textarea name="notes" id="notes" rows="2" class="form-control" 
                                  placeholder="Contoh: Pengiriman via armada pickup, DP lunas"><?= e($old['notes']); ?></textarea>
                    </div>

                    <!-- Pembayaran Awal Kasir -->
                    <div class="p-3 bg-light rounded-3 border">
                        <label for="initial_payment" class="form-label fw-bold text-dark small mb-1">
                            <i class="bi bi-wallet2 text-success me-1"></i> Pembayaran Awal / Kas (Opsional)
                        </label>
                        <div class="input-group mb-2">
                            <span class="input-group-text">Rp</span>
                            <input type="number" step="1" min="0" name="initial_payment" id="initial_payment" class="form-control" 
                                   value="<?= e($old['initial_payment']); ?>" placeholder="0">
                        </div>
                        <select name="payment_method" class="form-select form-select-sm">
                            <option value="Tunai">Tunai / Cash</option>
                            <option value="Transfer Bank">Transfer Bank</option>
                            <option value="QRIS">QRIS</option>
                        </select>
                        <small class="text-muted d-block mt-1">Kosongkan jika transaksi ini tempo (piutang).</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabel Item Buah Terjual -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <span class="fw-bold"><i class="bi bi-box-seam text-success me-1"></i> Rincian Item Buah Jambu Citra</span>
                    <button type="button" id="add-item-btn" class="btn btn-outline-success btn-sm">
                        <i class="bi bi-plus-lg me-1"></i> Tambah Baris Produk
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="sale-items-table">
                        <thead class="table-light">
                            <tr>
                                <th style="min-width: 200px;">Produk / Grade Buah</th>
                                <th style="width: 140px;">Berat (Kg)</th>
                                <th style="width: 160px;">Harga / Kg</th>
                                <th style="width: 160px;">Subtotal (Rp)</th>
                                <th>Catatan Item</th>
                                <th style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="item-row">
                                <td>
                                    <select name="items[product_grade][]" class="form-select" required>
                                        <option value="Jambu Citra Grade Super">Jambu Citra Grade Super</option>
                                        <option value="Jambu Citra Grade A" selected>Jambu Citra Grade A</option>
                                        <option value="Jambu Citra Grade B">Jambu Citra Grade B</option>
                                        <option value="Jambu Citra Grade Standar">Jambu Citra Grade Standar</option>
                                        <option value="Jambu Citra Sortiran (BS)">Jambu Citra Sortiran (BS)</option>
                                    </select>
                                </td>
                                <td>
                                    <div class="input-group">
                                        <input type="number" step="0.01" min="0.01" name="items[weight_kg][]" class="form-control item-weight" placeholder="0" required>
                                        <span class="input-group-text">Kg</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="input-group">
                                        <span class="input-group-text">Rp</span>
                                        <input type="number" step="1" min="0" name="items[price_per_kg][]" class="form-control item-price" value="25000" required>
                                    </div>
                                </td>
                                <td>
                                    <div class="input-group">
                                        <span class="input-group-text">Rp</span>
                                        <input type="number" step="1" name="items[subtotal][]" class="form-control item-subtotal bg-light" readonly>
                                    </div>
                                </td>
                                <td>
                                    <input type="text" name="items[notes][]" class="form-control" placeholder="Opsional">
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-outline-danger btn-sm remove-item-btn" title="Hapus Baris">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="card-footer bg-white p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fs-5 fw-bold text-secondary">Total Nilai Penjualan:</span>
                        <div>
                            <span class="fs-3 fw-bold text-success" id="grand-total-display">Rp 0</span>
                            <input type="hidden" name="total_amount" id="sale-grand-total" value="0">
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="<?= url('sales/index.php'); ?>" class="btn btn-light border px-4">Batal</a>
                <button type="submit" class="btn btn-primary px-4 py-2 fs-6">
                    <i class="bi bi-check-circle me-1"></i> Simpan Transaksi Penjualan
                </button>
            </div>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
