<?php
/**
 * Modul Penjualan Jambu Citra - Detail Faktur & Pelunasan
 * Sistem Informasi Usaha Tani Jambu Citra
 */

declare(strict_types=1);

$pageTitle = 'Detail Faktur Penjualan';
$activePage = 'sales';

require_once __DIR__ . '/../includes/header.php';

$pdo = get_db_connection();
$id = (int) ($_GET['id'] ?? 0);

// Ambil Header Penjualan
$stmt = $pdo->prepare("
    SELECT s.*, c.customer_name, c.phone, c.address, c.notes AS cust_notes
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    WHERE s.id = ?
");
$stmt->execute([$id]);
$sale = $stmt->fetch();

if (!$sale) {
    set_flash('danger', 'Transaksi penjualan tidak ditemukan.');
    redirect('sales/index.php');
    exit;
}

// Ambil Item Produk
$stmt = $pdo->prepare("SELECT * FROM sale_items WHERE sale_id = ? ORDER BY id ASC");
$stmt->execute([$id]);
$items = $stmt->fetchAll();

// Ambil Riwayat Pembayaran
$stmt = $pdo->prepare("SELECT * FROM sale_payments WHERE sale_id = ? ORDER BY payment_date ASC, id ASC");
$stmt->execute([$id]);
$payments = $stmt->fetchAll();

// Hitung total terbayar dan sisa tagihan
$totalPaid = array_sum(array_column($payments, 'amount'));
$balanceDue = (float) $sale['total_amount'] - $totalPaid;
if ($balanceDue < 0) $balanceDue = 0;
?>

<!-- Header Cetak Khusus Print CSS -->
<div class="print-header">
    <h3 style="margin-bottom: 2px;">USAHA TANI JAMBU CITRA</h3>
    <p style="margin-bottom: 5px; font-size: 0.9rem;">Sentra Budidaya & Penjualan Buah Jambu Citra Segar Berkualitas</p>
    <div style="font-size: 0.85rem; color: #555;">FAKTUR PENJUALAN / INVOICE: <strong><?= e($sale['invoice_number']); ?></strong></div>
</div>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 no-print">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h4 class="fw-bold mb-0"><?= e($sale['invoice_number']); ?></h4>
            <?php if ($sale['status'] === 'confirmed'): ?>
                <span class="badge badge-status badge-confirmed">Confirmed (Sah)</span>
            <?php elseif ($sale['status'] === 'draft'): ?>
                <span class="badge badge-status badge-draft">Draft</span>
            <?php else: ?>
                <span class="badge badge-status badge-cancelled">Cancelled (Dibatalkan)</span>
            <?php endif; ?>
        </div>
        <p class="text-muted small mb-0">Dibuat pada <?= format_date($sale['created_at'], false, true); ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= url('sales/index.php'); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Kembali
        </a>
        <button type="button" class="btn btn-outline-dark btn-sm" onclick="window.print();">
            <i class="bi bi-printer me-1"></i> Cetak Faktur
        </button>

        <?php if ($sale['status'] === 'draft'): ?>
            <a href="<?= url('sales/edit.php?id=' . $sale['id']); ?>" class="btn btn-primary btn-sm">
                <i class="bi bi-pencil me-1"></i> Edit Faktur
            </a>
            <form method="POST" action="<?= url('sales/status.php'); ?>" class="d-inline" onsubmit="return confirm('Konfirmasi transaksi ini menjadi sah (Confirmed)?');">
                <?= csrf_field(); ?>
                <input type="hidden" name="id" value="<?= (int) $sale['id']; ?>">
                <input type="hidden" name="action" value="confirm">
                <button type="submit" class="btn btn-success btn-sm">
                    <i class="bi bi-check-circle me-1"></i> Konfirmasi Transaksi
                </button>
            </form>
        <?php elseif ($sale['status'] === 'confirmed'): ?>
            <form method="POST" action="<?= url('sales/status.php'); ?>" class="d-inline" onsubmit="return confirm('PERINGATAN: Batalkan transaksi penjualan ini? Transaksi yang dibatalkan tidak lagi dihitung sebagai pendapatan.');">
                <?= csrf_field(); ?>
                <input type="hidden" name="id" value="<?= (int) $sale['id']; ?>">
                <input type="hidden" name="action" value="cancel">
                <button type="submit" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-x-circle me-1"></i> Batalkan Transaksi
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <!-- Informasi Pembeli & Faktur -->
    <div class="col-md-6">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <span class="fw-bold"><i class="bi bi-person text-success me-1"></i> Informasi Pembeli / Pelanggan</span>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <th style="width: 130px;" class="text-muted">Nama:</th>
                        <td><strong class="text-dark"><?= e($sale['customer_name'] ?? 'Pembeli Umum / Cash'); ?></strong></td>
                    </tr>
                    <tr>
                        <th class="text-muted">No. Telepon:</th>
                        <td><?= e($sale['phone'] ?: '-'); ?></td>
                    </tr>
                    <tr>
                        <th class="text-muted">Alamat:</th>
                        <td><?= e($sale['address'] ?: '-'); ?></td>
                    </tr>
                    <tr>
                        <th class="text-muted">Catatan Transaksi:</th>
                        <td><?= nl2br(e($sale['notes'] ?: '-')); ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Ringkasan Status Keuangan -->
    <div class="col-md-6">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <span class="fw-bold"><i class="bi bi-cash-stack text-success me-1"></i> Status Pembayaran & Tagihan</span>
            </div>
            <div class="card-body">
                <div class="row g-3 text-center">
                    <div class="col-4">
                        <small class="text-muted text-uppercase d-block mb-1">Total Penjualan</small>
                        <span class="fw-bold fs-6 text-dark"><?= format_rupiah($sale['total_amount']); ?></span>
                    </div>
                    <div class="col-4">
                        <small class="text-muted text-uppercase d-block mb-1">Total Terbayar</small>
                        <span class="fw-bold fs-6 text-success"><?= format_rupiah($totalPaid); ?></span>
                    </div>
                    <div class="col-4">
                        <small class="text-muted text-uppercase d-block mb-1">Sisa Tagihan</small>
                        <span class="fw-bold fs-6 <?= $balanceDue > 0 ? 'text-danger' : 'text-muted'; ?>">
                            <?= format_rupiah($balanceDue); ?>
                        </span>
                    </div>
                </div>

                <div class="mt-3 pt-3 border-top d-flex justify-content-between align-items-center">
                    <div>
                        <?php if ($balanceDue <= 0 && $sale['status'] === 'confirmed'): ?>
                            <span class="badge badge-status badge-paid fs-6"><i class="bi bi-check-circle-fill me-1"></i> LUNAS</span>
                        <?php elseif ($sale['status'] === 'confirmed'): ?>
                            <span class="badge badge-status badge-partial fs-6"><i class="bi bi-clock-history me-1"></i> Sisa Tagihan: <?= format_rupiah($balanceDue); ?></span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Bukan Tagihan Aktif</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($sale['status'] === 'confirmed' && $balanceDue > 0): ?>
                        <a href="<?= url('payments/create.php?sale_id=' . $sale['id']); ?>" class="btn btn-success btn-sm no-print">
                            <i class="bi bi-wallet2 me-1"></i> Catat Pembayaran Tagihan
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabel Rincian Buah Jambu Citra -->
<div class="card shadow-sm border-0 my-4">
    <div class="card-header bg-white py-3">
        <span class="fw-bold"><i class="bi bi-basket3 text-success me-1"></i> Rincian Produk Buah Terjual</span>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 50px;">No</th>
                    <th>Nama Produk / Grade Buah</th>
                    <th class="text-center" style="width: 150px;">Berat Terjual (Kg)</th>
                    <th class="text-end" style="width: 180px;">Harga per Kg</th>
                    <th class="text-end" style="width: 200px;">Subtotal (Rp)</th>
                    <th>Keterangan Item</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1; 
                $totalWeight = 0.0;
                foreach ($items as $item): 
                    $totalWeight += (float) $item['weight_kg'];
                ?>
                    <tr>
                        <td class="text-muted"><?= $no++; ?></td>
                        <td><strong class="text-dark"><?= e($item['product_grade']); ?></strong></td>
                        <td class="text-center fw-semibold"><?= format_number($item['weight_kg'], 2); ?> Kg</td>
                        <td class="text-end"><?= format_rupiah($item['price_per_kg']); ?></td>
                        <td class="text-end fw-bold text-dark"><?= format_rupiah($item['subtotal']); ?></td>
                        <td><small class="text-muted"><?= e($item['notes'] ?: '-'); ?></small></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <th colspan="2" class="text-end">TOTAL VOL. BUAH & PENJUALAN:</th>
                    <th class="text-center text-success fs-6"><?= format_number($totalWeight, 2); ?> Kg</th>
                    <th></th>
                    <th class="text-end text-success fs-5"><?= format_rupiah($sale['total_amount']); ?></th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Riwayat Pembayaran Tagihan -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <span class="fw-bold"><i class="bi bi-clock-history text-success me-1"></i> Riwayat Pembayaran Tagihan</span>
        <?php if ($sale['status'] === 'confirmed' && $balanceDue > 0): ?>
            <a href="<?= url('payments/create.php?sale_id=' . $sale['id']); ?>" class="btn btn-outline-success btn-sm no-print">
                <i class="bi bi-plus-circle me-1"></i> Tambah Pembayaran
            </a>
        <?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 50px;">No</th>
                    <th>Tanggal Pembayaran</th>
                    <th>Metode Pembayaran</th>
                    <th class="text-end">Jumlah Bayar</th>
                    <th>Catatan Pembayaran</th>
                    <th class="text-center no-print" style="width: 80px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payments)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            Belum ada catatan pembayaran yang diterima untuk transaksi ini.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($payments as $pay): ?>
                        <tr>
                            <td class="text-muted"><?= $no++; ?></td>
                            <td><?= format_date($pay['payment_date'], true); ?></td>
                            <td><span class="badge bg-light text-dark border"><?= e($pay['payment_method']); ?></span></td>
                            <td class="text-end fw-bold text-success"><?= format_rupiah($pay['amount']); ?></td>
                            <td><small class="text-muted"><?= e($pay['notes'] ?: '-'); ?></small></td>
                            <td class="text-center no-print">
                                <form method="POST" action="<?= url('payments/delete.php'); ?>" class="d-inline" onsubmit="return confirm('Hapus riwayat pembayaran senilai <?= format_rupiah($pay['amount']); ?> ini?');">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?= (int) $pay['id']; ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Hapus Pembayaran">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Kolom Tanda Tangan Cetak -->
<div class="print-signature">
    <div class="print-signature-box">
        <p style="margin-bottom: 60px;">Pengelola Usaha Tani Jambu Citra,</p>
        <p style="border-bottom: 1px solid #000; font-weight: bold; margin-bottom: 2px;">
            <?= e($currentUser['full_name'] ?? 'Pengelola Usaha Tani'); ?>
        </p>
        <small style="color: #666;">Faktur Sah Usaha Tani</small>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
