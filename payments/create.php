<?php
/**
 * Modul Pembayaran - Catat Pembayaran Baru
 * Sistem Informasi Usaha Tani Jambu Citra
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

require_login();

$pdo = get_db_connection();
$preselectedSaleId = (int) ($_GET['sale_id'] ?? 0);

// Ambil daftar transaksi penjualan berstatus confirmed yang belum lunas
$sqlUnpaid = "
    SELECT s.id, s.invoice_number, s.sale_date, s.total_amount,
           c.customer_name,
           COALESCE(p.paid_amount, 0) AS paid_amount,
           (s.total_amount - COALESCE(p.paid_amount, 0)) AS balance_due
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    LEFT JOIN (
        SELECT sale_id, SUM(amount) AS paid_amount 
        FROM sale_payments 
        GROUP BY sale_id
    ) p ON s.id = p.sale_id
    WHERE s.status = 'confirmed' 
      AND (s.total_amount - COALESCE(p.paid_amount, 0)) > 0
    ORDER BY s.sale_date ASC, s.id ASC
";
$unpaidSales = $pdo->query($sqlUnpaid)->fetchAll();

// Jika ada preselectedSaleId cari secara spesifik
$selectedSale = null;
if ($preselectedSaleId > 0) {
    foreach ($unpaidSales as $us) {
        if ((int)$us['id'] === $preselectedSaleId) {
            $selectedSale = $us;
            break;
        }
    }
    if (!$selectedSale) {
        $stmtChk = $pdo->prepare("
            SELECT s.id, s.invoice_number, s.sale_date, s.total_amount, s.status,
                   c.customer_name,
                   COALESCE(p.paid_amount, 0) AS paid_amount,
                   (s.total_amount - COALESCE(p.paid_amount, 0)) AS balance_due
            FROM sales s
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN (
                SELECT sale_id, SUM(amount) AS paid_amount 
                FROM sale_payments 
                GROUP BY sale_id
            ) p ON s.id = p.sale_id
            WHERE s.id = ?
        ");
        $stmtChk->execute([$preselectedSaleId]);
        $selectedSale = $stmtChk->fetch();
    }
}

$errors = [];
$old = [
    'sale_id'        => $preselectedSaleId ?: ($unpaidSales[0]['id'] ?? 0),
    'payment_date'   => date('Y-m-d'),
    'amount'         => ($selectedSale && $selectedSale['balance_due'] > 0) ? (string) $selectedSale['balance_due'] : '',
    'payment_method' => 'Tunai',
    'notes'          => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_die();

    $saleId        = (int) ($_POST['sale_id'] ?? 0);
    $paymentDate   = trim($_POST['payment_date'] ?? '');
    $amount        = (float) ($_POST['amount'] ?? 0);
    $paymentMethod = trim($_POST['payment_method'] ?? 'Tunai');
    $notes         = trim($_POST['notes'] ?? '');

    $old = [
        'sale_id'        => $saleId,
        'payment_date'   => $paymentDate,
        'amount'         => (string) $amount,
        'payment_method' => $paymentMethod,
        'notes'          => $notes
    ];

    if ($saleId <= 0) {
        $errors[] = 'Faktur transaksi penjualan wajib dipilih.';
    }

    if (empty($paymentDate)) {
        $errors[] = 'Tanggal pembayaran wajib diisi.';
    }

    if ($amount <= 0) {
        $errors[] = 'Jumlah pembayaran harus lebih besar dari Rp 0.';
    }

    // Hitung sisa tagihan aktual di database untuk validasi ketat
    if ($saleId > 0 && $amount > 0) {
        $stmtVerify = $pdo->prepare("
            SELECT s.id, s.invoice_number, s.total_amount, s.status,
                   COALESCE(SUM(p.amount), 0) AS already_paid
            FROM sales s
            LEFT JOIN sale_payments p ON s.id = p.sale_id
            WHERE s.id = ?
            GROUP BY s.id
        ");
        $stmtVerify->execute([$saleId]);
        $targetSale = $stmtVerify->fetch();

        if (!$targetSale) {
            $errors[] = 'Faktur penjualan tidak ditemukan.';
        } elseif ($targetSale['status'] !== 'confirmed') {
            $errors[] = 'Pembayaran hanya dapat dicatat untuk transaksi berstatus Confirmed.';
        } else {
            $actualBalanceDue = (float) $targetSale['total_amount'] - (float) $targetSale['already_paid'];
            if ($amount > ($actualBalanceDue + 0.01)) {
                $errors[] = sprintf(
                    'Jumlah pembayaran (%s) melebihi sisa tagihan faktur ini (%s).',
                    format_rupiah($amount),
                    format_rupiah($actualBalanceDue)
                );
            }
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO sale_payments (sale_id, payment_date, amount, payment_method, notes, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $saleId,
                $paymentDate,
                $amount,
                $paymentMethod,
                $notes ?: null
            ]);

            set_flash('success', 'Pembayaran sebesar ' . format_rupiah($amount) . ' berhasil dicatat!');
            redirect('payments/index.php');
            exit;

        } catch (PDOException $e) {
            error_log('Insert Payment Error: ' . $e->getMessage());
            $errors[] = 'Gagal menyimpan pembayaran: ' . $e->getMessage();
        }
    }
}

// Render HTML Layout setelah POST selesai
$pageTitle = 'Catat Pembayaran Tagihan';
$activePage = 'payments';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-wallet2 text-success me-2"></i>Catat Pembayaran Tagihan</h4>
                <p class="text-muted small mb-0">Catat penerimaan kas pelunasan faktur penjualan jambu citra.</p>
            </div>
            <a href="<?= url('payments/index.php'); ?>" class="btn btn-outline-secondary btn-sm">
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

        <?php if (empty($unpaidSales) && !$selectedSale): ?>
            <div class="alert alert-success border-0 shadow-sm p-4 text-center">
                <i class="bi bi-check-circle-fill text-success fs-1 d-block mb-2"></i>
                <h5 class="fw-bold">Seluruh Tagihan Penjualan Telah Lunas!</h5>
                <p class="text-muted mb-3">Tidak ada transaksi penjualan berstatus Confirmed yang memiliki tunggakan sisa tagihan saat ini.</p>
                <a href="<?= url('sales/index.php'); ?>" class="btn btn-outline-success">Lihat Daftar Penjualan</a>
            </div>
        <?php else: ?>
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <form method="POST" action="">
                        <?= csrf_field(); ?>

                        <div class="mb-3">
                            <label for="sale_id" class="form-label">Pilih Faktur Penjualan <span class="text-danger">*</span></label>
                            <select name="sale_id" id="sale_id" class="form-select" required onchange="updateBalanceInfo(this);">
                                <option value="">-- Pilih Faktur Tagihan --</option>
                                <?php if ($selectedSale && !in_array($selectedSale['id'], array_column($unpaidSales, 'id'))): ?>
                                    <option value="<?= $selectedSale['id']; ?>" selected 
                                            data-total="<?= $selectedSale['total_amount']; ?>" 
                                            data-paid="<?= $selectedSale['paid_amount']; ?>" 
                                            data-balance="<?= $selectedSale['balance_due']; ?>">
                                        <?= e($selectedSale['invoice_number']); ?> - <?= e($selectedSale['customer_name'] ?? 'Umum'); ?> (Sisa: <?= format_rupiah($selectedSale['balance_due']); ?>)
                                    </option>
                                <?php endif; ?>
                                <?php foreach ($unpaidSales as $us): ?>
                                    <option value="<?= $us['id']; ?>" 
                                            <?= (int)$old['sale_id'] === (int)$us['id'] ? 'selected' : ''; ?>
                                            data-total="<?= $us['total_amount']; ?>" 
                                            data-paid="<?= $us['paid_amount']; ?>" 
                                            data-balance="<?= $us['balance_due']; ?>">
                                        <?= e($us['invoice_number']); ?> - <?= e($us['customer_name'] ?? 'Umum'); ?> (Sisa: <?= format_rupiah($us['balance_due']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Card Rincian Sisa Tagihan Faktur Terpilih -->
                        <div id="invoice-info-box" class="p-3 bg-light rounded-3 border mb-3">
                            <div class="row text-center">
                                <div class="col-4">
                                    <small class="text-muted d-block">Total Faktur</small>
                                    <span class="fw-bold" id="info-total">-</span>
                                </div>
                                <div class="col-4">
                                    <small class="text-muted d-block">Sudah Dibayar</small>
                                    <span class="fw-bold text-success" id="info-paid">-</span>
                                </div>
                                <div class="col-4">
                                    <small class="text-muted d-block">Sisa Tagihan Maksimal</small>
                                    <span class="fw-bold text-danger fs-6" id="info-balance">-</span>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="payment_date" class="form-label">Tanggal Pembayaran <span class="text-danger">*</span></label>
                                <input type="date" name="payment_date" id="payment_date" class="form-control" 
                                       value="<?= e($old['payment_date']); ?>" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="payment_method" class="form-label">Metode Pembayaran <span class="text-danger">*</span></label>
                                <select name="payment_method" id="payment_method" class="form-select" required>
                                    <option value="Tunai" <?= $old['payment_method'] === 'Tunai' ? 'selected' : ''; ?>>Tunai / Cash</option>
                                    <option value="Transfer Bank" <?= $old['payment_method'] === 'Transfer Bank' ? 'selected' : ''; ?>>Transfer Bank (BCA/BRI/Mandiri)</option>
                                    <option value="QRIS" <?= $old['payment_method'] === 'QRIS' ? 'selected' : ''; ?>>QRIS / Dompet Digital</option>
                                    <option value="Lainnya" <?= $old['payment_method'] === 'Lainnya' ? 'selected' : ''; ?>>Lainnya</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="payment_amount" class="form-label fw-bold text-dark">Jumlah Uang yang Dibayarkan (Rp) <span class="text-danger">*</span></label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text fw-bold text-success bg-white">Rp</span>
                                <input type="number" step="1" min="1" name="amount" id="payment_amount" class="form-control fw-bold text-success" 
                                       value="<?= e($old['amount']); ?>" placeholder="0" required autofocus>
                            </div>
                            <small class="text-muted" id="amount-helper">Maksimal pembayaran sesuai sisa tagihan.</small>
                        </div>

                        <div class="mb-4">
                            <label for="notes" class="form-label">Keterangan / Nomor Bukti Transfer (Opsional)</label>
                            <textarea name="notes" id="notes" rows="2" class="form-control" 
                                      placeholder="Contoh: Bukti transfer BCA No. Ref 482910"><?= e($old['notes']); ?></textarea>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="<?= url('payments/index.php'); ?>" class="btn btn-light border px-4">Batal</a>
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-save me-1"></i> Simpan Pembayaran
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function formatRupiahJs(num) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(num);
}

function updateBalanceInfo(selectEl) {
    const selectedOption = selectEl.options[selectEl.selectedIndex];
    const total = parseFloat(selectedOption.getAttribute('data-total')) || 0;
    const paid = parseFloat(selectedOption.getAttribute('data-paid')) || 0;
    const balance = parseFloat(selectedOption.getAttribute('data-balance')) || 0;

    const infoTotal = document.getElementById('info-total');
    const infoPaid = document.getElementById('info-paid');
    const infoBalance = document.getElementById('info-balance');
    const amountInput = document.getElementById('payment_amount');
    const helper = document.getElementById('amount-helper');

    if (infoTotal) infoTotal.textContent = formatRupiahJs(total);
    if (infoPaid) infoPaid.textContent = formatRupiahJs(paid);
    if (infoBalance) infoBalance.textContent = formatRupiahJs(balance);

    if (amountInput && balance > 0 && (!amountInput.value || amountInput.value == 0)) {
        amountInput.value = balance;
    }
    if (amountInput) {
        amountInput.max = balance;
    }
    if (helper) {
        helper.textContent = 'Maksimal pembayaran untuk faktur ini: ' + formatRupiahJs(balance);
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const select = document.getElementById('sale_id');
    if (select && select.value) {
        updateBalanceInfo(select);
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
