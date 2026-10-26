<?php
/**
 * Modul Laporan - Laporan Pembayaran & Sisa Tagihan (Piutang)
 * Sistem Informasi Usaha Tani Jambu Citra
 */

declare(strict_types=1);

$pageTitle = 'Laporan Kas & Piutang';
$activePage = 'report_payments';

require_once __DIR__ . '/../includes/header.php';

$pdo = get_db_connection();

$filterMethod = trim($_GET['method'] ?? '');
$startDate    = trim($_GET['start_date'] ?? date('Y-01-01'));
$endDate      = trim($_GET['end_date'] ?? date('Y-m-d'));

// 1. Data Pembayaran Masuk
$wherePay = ["1=1"];
$paramsPay = [];

if (!empty($filterMethod)) {
    $wherePay[] = "p.payment_method = ?";
    $paramsPay[] = $filterMethod;
}

if (!empty($startDate)) {
    $wherePay[] = "p.payment_date >= ?";
    $paramsPay[] = $startDate;
}

if (!empty($endDate)) {
    $wherePay[] = "p.payment_date <= ?";
    $paramsPay[] = $endDate;
}

$whereClausePay = implode(" AND ", $wherePay);

$sqlPayments = "
    SELECT p.*, s.invoice_number, s.total_amount, s.sale_date,
           c.customer_name, c.phone
    FROM sale_payments p
    JOIN sales s ON p.sale_id = s.id
    LEFT JOIN customers c ON s.customer_id = c.id
    WHERE $whereClausePay
    ORDER BY p.payment_date ASC, p.id ASC
";
$stmtPay = $pdo->prepare($sqlPayments);
$stmtPay->execute($paramsPay);
$payments = $stmtPay->fetchAll();

$totalPayments = array_sum(array_column($payments, 'amount'));

// 2. Data Piutang Berjalan (Faktur Confirmed yang Belum Lunas)
$sqlUnpaid = "
    SELECT s.id, s.invoice_number, s.sale_date, s.total_amount,
           c.customer_name, c.phone,
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
    ORDER BY s.sale_date ASC
";
$unpaidInvoices = $pdo->query($sqlUnpaid)->fetchAll();
$totalOutstandingDebt = array_sum(array_column($unpaidInvoices, 'balance_due'));
?>

<!-- Header Print CSS -->
<div class="print-header">
    <h3 style="margin-bottom: 2px;">USAHA TANI JAMBU CITRA</h3>
    <h4 style="margin-bottom: 5px;">LAPORAN PENERIMAAN KAS & SISA PIUTANG PENJUALAN</h4>
    <p style="font-size: 0.9rem; color: #444;">
        Periode: <strong><?= format_date($startDate); ?></strong> s/d <strong><?= format_date($endDate); ?></strong>
    </p>
</div>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 no-print">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-journal-check text-success me-2"></i>Laporan Pembayaran & Piutang</h4>
        <p class="text-muted small mb-0">Arsip penerimaan kas dan pengawasan tagihan pelanggan yang belum terselesaikan.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('reports/index.php'); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-folder2-open me-1"></i> Semua Laporan
        </a>
        <button type="button" class="btn btn-outline-dark btn-sm" onclick="window.print();">
            <i class="bi bi-printer me-1"></i> Cetak Laporan
        </button>
        <a href="<?= url('reports/export-csv.php?type=payments&method=' . urlencode($filterMethod) . '&start_date=' . urlencode($startDate) . '&end_date=' . urlencode($endDate)); ?>" class="btn btn-outline-success btn-sm">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Ekspor CSV
        </a>
    </div>
</div>

<!-- Filter Pembayaran -->
<div class="card mb-4 border-0 shadow-sm no-print">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small mb-1">Metode Pembayaran</label>
                <select name="method" class="form-select form-select-sm">
                    <option value="">-- Seluruh Metode --</option>
                    <option value="Tunai" <?= $filterMethod === 'Tunai' ? 'selected' : ''; ?>>Tunai / Cash</option>
                    <option value="Transfer Bank" <?= $filterMethod === 'Transfer Bank' ? 'selected' : ''; ?>>Transfer Bank</option>
                    <option value="QRIS" <?= $filterMethod === 'QRIS' ? 'selected' : ''; ?>>QRIS</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Mulai Tanggal</label>
                <input type="date" name="start_date" class="form-control form-control-sm" value="<?= e($startDate); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Sampai Tanggal</label>
                <input type="date" name="end_date" class="form-control form-control-sm" value="<?= e($endDate); ?>">
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <a href="<?= url('reports/payments-report.php'); ?>" class="btn btn-outline-secondary btn-sm" title="Reset">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Kartu Ringkasan Keuangan Kas & Piutang -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-wallet2"></i></div>
            <div>
                <div class="stat-label">Total Kas Diterima (Periode Ini)</div>
                <div class="stat-value text-success"><?= format_rupiah($totalPayments); ?></div>
                <div class="stat-sub"><?= count($payments); ?> kali transaksi pembayaran</div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="stat-card">
            <div class="stat-icon red"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <div>
                <div class="stat-label">Total Sisa Piutang Aktif</div>
                <div class="stat-value text-danger"><?= format_rupiah($totalOutstandingDebt); ?></div>
                <div class="stat-sub">Dari <?= count($unpaidInvoices); ?> faktur penjualan belum lunas</div>
            </div>
        </div>
    </div>
</div>

<!-- Bagian 1: Tabel Riwayat Pembayaran Masuk -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3">
        <span class="fw-bold"><i class="bi bi-check-circle-fill text-success me-1"></i> 1. Rincian Penerimaan Kas Pembayaran</span>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 40px;">No</th>
                    <th style="width: 120px;">Tgl Bayar</th>
                    <th style="width: 150px;">Faktur Terkait</th>
                    <th>Nama Pelanggan</th>
                    <th style="width: 140px;">Metode</th>
                    <th class="text-end" style="width: 160px;">Jumlah Diterima</th>
                    <th>Catatan / Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payments)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            Tidak ada data penerimaan kas pada rentang periode yang dipilih.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($payments as $p): ?>
                        <tr>
                            <td class="text-muted text-center"><?= $no++; ?></td>
                            <td><?= format_date($p['payment_date'], true); ?></td>
                            <td><strong><?= e($p['invoice_number']); ?></strong></td>
                            <td><?= e($p['customer_name'] ?? 'Pelanggan Umum'); ?></td>
                            <td><span class="badge bg-light text-dark border"><?= e($p['payment_method']); ?></span></td>
                            <td class="text-end fw-bold text-success fs-6"><?= format_rupiah($p['amount']); ?></td>
                            <td><small class="text-muted"><?= e($p['notes'] ?: '-'); ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <th colspan="5" class="text-end fs-6">TOTAL KAS DITERIMA:</th>
                    <th class="text-end text-success fs-5"><?= format_rupiah($totalPayments); ?></th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Bagian 2: Daftar Sisa Tagihan Piutang Pelanggan -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3">
        <span class="fw-bold"><i class="bi bi-clock-history text-danger me-1"></i> 2. Rekapitulasi Sisa Tagihan (Piutang Usaha)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 40px;">No</th>
                    <th style="width: 140px;">Nomor Faktur</th>
                    <th style="width: 110px;">Tgl Transaksi</th>
                    <th>Pelanggan Berhutang</th>
                    <th class="text-end" style="width: 150px;">Total Faktur</th>
                    <th class="text-end" style="width: 150px;">Telah Dibayar</th>
                    <th class="text-end" style="width: 160px;">Sisa Tagihan (Piutang)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($unpaidInvoices)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-success fw-semibold">
                            <i class="bi bi-check2-all me-1"></i> Luar biasa! Tidak ada tagihan atau piutang yang tertunggak.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($unpaidInvoices as $ui): ?>
                        <tr>
                            <td class="text-muted text-center"><?= $no++; ?></td>
                            <td>
                                <a href="<?= url('sales/detail.php?id=' . $ui['id']); ?>" class="fw-bold text-decoration-none" target="_blank">
                                    <?= e($ui['invoice_number']); ?>
                                </a>
                            </td>
                            <td><?= format_date($ui['sale_date'], true); ?></td>
                            <td>
                                <strong><?= e($ui['customer_name'] ?? 'Pelanggan Umum'); ?></strong>
                                <?php if (!empty($ui['phone'])): ?>
                                    <small class="text-muted d-block"><?= e($ui['phone']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="text-end"><?= format_rupiah($ui['total_amount']); ?></td>
                            <td class="text-end text-success"><?= format_rupiah($ui['paid_amount']); ?></td>
                            <td class="text-end fw-bold text-danger fs-6"><?= format_rupiah($ui['balance_due']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <th colspan="6" class="text-end fs-6">TOTAL PIUTANG USAHA BERJALAN:</th>
                    <th class="text-end text-danger fs-5"><?= format_rupiah($totalOutstandingDebt); ?></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Kolom Tanda Tangan Cetak -->
<div class="print-signature">
    <div class="print-signature-box">
        <p style="margin-bottom: 60px;">Pengelola Usaha Tani Jambu Citra,</p>
        <p style="border-bottom: 1px solid #000; font-weight: bold; margin-bottom: 2px;">
            <?= e($currentUser['full_name'] ?? 'Pengelola'); ?>
        </p>
        <small style="color: #666;">Laporan Kas & Piutang</small>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
