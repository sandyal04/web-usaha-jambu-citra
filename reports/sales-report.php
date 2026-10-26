<?php
/**
 * Modul Laporan - Laporan Penjualan Buah Jambu Citra
 * Sistem Informasi Usaha Tani Jambu Citra
 */

declare(strict_types=1);

$pageTitle = 'Laporan Penjualan Jambu Citra';
$activePage = 'report_sales';

require_once __DIR__ . '/../includes/header.php';

$pdo = get_db_connection();

$filterStatus   = trim($_GET['status'] ?? 'confirmed'); // Default confirmed
$filterCustomer = isset($_GET['customer_id']) && $_GET['customer_id'] !== '' ? (int) $_GET['customer_id'] : 0;
$startDate      = trim($_GET['start_date'] ?? date('Y-01-01'));
$endDate        = trim($_GET['end_date'] ?? date('Y-m-d'));

$where = ["1=1"];
$params = [];

if (!empty($filterStatus)) {
    $where[] = "s.status = ?";
    $params[] = $filterStatus;
}

if ($filterCustomer > 0) {
    $where[] = "s.customer_id = ?";
    $params[] = $filterCustomer;
}

if (!empty($startDate)) {
    $where[] = "s.sale_date >= ?";
    $params[] = $startDate;
}

if (!empty($endDate)) {
    $where[] = "s.sale_date <= ?";
    $params[] = $endDate;
}

$whereClause = implode(" AND ", $where);

$sql = "
    SELECT s.*, c.customer_name, c.phone,
           (SELECT SUM(weight_kg) FROM sale_items si WHERE si.sale_id = s.id) AS total_weight_kg,
           COALESCE(p.paid_amount, 0) AS paid_amount,
           (s.total_amount - COALESCE(p.paid_amount, 0)) AS balance_due
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    LEFT JOIN (
        SELECT sale_id, SUM(amount) AS paid_amount 
        FROM sale_payments 
        GROUP BY sale_id
    ) p ON s.id = p.sale_id
    WHERE $whereClause
    ORDER BY s.sale_date ASC, s.id ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll();

$totalSalesAmount = array_sum(array_column($sales, 'total_amount'));
$totalWeightSold = array_sum(array_column($sales, 'total_weight_kg'));
$totalPaid = array_sum(array_column($sales, 'paid_amount'));
$totalBalanceDue = array_sum(array_column($sales, 'balance_due'));

$customersList = $pdo->query("SELECT id, customer_name FROM customers ORDER BY customer_name ASC")->fetchAll();
?>

<!-- Header Print CSS -->
<div class="print-header">
    <h3 style="margin-bottom: 2px;">USAHA TANI JAMBU CITRA</h3>
    <h4 style="margin-bottom: 5px;">LAPORAN TRANSAKSI PENJUALAN BUAH</h4>
    <p style="font-size: 0.9rem; color: #444;">
        Periode: <strong><?= format_date($startDate); ?></strong> s/d <strong><?= format_date($endDate); ?></strong> |
        Status Faktur: <strong><?= strtoupper($filterStatus ?: 'SEMUA STATUS'); ?></strong>
    </p>
</div>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 no-print">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-file-earmark-text-fill text-success me-2"></i>Laporan Penjualan Jambu Citra</h4>
        <p class="text-muted small mb-0">Rekapitulasi omset penjualan buah jambu citra, volume terdistribusi, dan piutang penjualan.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= url('reports/index.php'); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-folder2-open me-1"></i> Semua Laporan
        </a>
        <button type="button" class="btn btn-outline-dark btn-sm" onclick="window.print();">
            <i class="bi bi-printer me-1"></i> Cetak Laporan
        </button>
        <a href="<?= url('reports/export-csv.php?type=sales&status=' . urlencode($filterStatus) . '&customer_id=' . $filterCustomer . '&start_date=' . urlencode($startDate) . '&end_date=' . urlencode($endDate)); ?>" class="btn btn-outline-success btn-sm">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Ekspor CSV
        </a>
    </div>
</div>

<!-- Filter Laporan -->
<div class="card mb-4 border-0 shadow-sm no-print">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1">Status Penjualan</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">-- Semua Status --</option>
                    <option value="confirmed" <?= $filterStatus === 'confirmed' ? 'selected' : ''; ?>>Confirmed (Sah - Pendapatan)</option>
                    <option value="draft" <?= $filterStatus === 'draft' ? 'selected' : ''; ?>>Draft</option>
                    <option value="cancelled" <?= $filterStatus === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Pelanggan</label>
                <select name="customer_id" class="form-select form-select-sm">
                    <option value="0">-- Semua Pelanggan --</option>
                    <?php foreach ($customersList as $cust): ?>
                        <option value="<?= $cust['id']; ?>" <?= $filterCustomer === (int)$cust['id'] ? 'selected' : ''; ?>>
                            <?= e($cust['customer_name']); ?>
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
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <a href="<?= url('reports/sales-report.php'); ?>" class="btn btn-outline-secondary btn-sm" title="Reset">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Ringkasan Statistik Penjualan -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-cash-coin"></i></div>
            <div>
                <div class="stat-label">Total Nilai Penjualan</div>
                <div class="stat-value text-success"><?= format_rupiah($totalSalesAmount); ?></div>
                <div class="stat-sub"><?= count($sales); ?> transaksi terpilih</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon purple"><i class="bi bi-basket2-fill"></i></div>
            <div>
                <div class="stat-label">Total Berat Terjual</div>
                <div class="stat-value text-dark"><?= format_number($totalWeightSold, 2); ?> <span class="fs-6 fw-normal text-muted">Kg</span></div>
                <div class="stat-sub">Volume fisik terkirim</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-wallet2"></i></div>
            <div>
                <div class="stat-label">Kas Masuk Terbayar</div>
                <div class="stat-value text-primary"><?= format_rupiah($totalPaid); ?></div>
                <div class="stat-sub">Pelunasan diterima</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon red"><i class="bi bi-clock-history"></i></div>
            <div>
                <div class="stat-label">Sisa Piutang Berjalan</div>
                <div class="stat-value text-danger"><?= format_rupiah($totalBalanceDue); ?></div>
                <div class="stat-sub">Tagihan belum tertagih</div>
            </div>
        </div>
    </div>
</div>

<!-- Tabel Laporan Penjualan -->
<div class="card shadow-sm border-0 mb-4">
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 40px;">No</th>
                    <th style="width: 140px;">No. Faktur</th>
                    <th style="width: 110px;">Tanggal</th>
                    <th>Nama Pelanggan</th>
                    <th class="text-center" style="width: 120px;">Berat (Kg)</th>
                    <th class="text-end" style="width: 150px;">Total Penjualan</th>
                    <th class="text-end" style="width: 140px;">Terbayar</th>
                    <th class="text-end" style="width: 140px;">Sisa Tagihan</th>
                    <th class="text-center" style="width: 110px;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sales)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            Tidak ada transaksi penjualan pada rentang periode yang dipilih.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($sales as $s): ?>
                        <tr>
                            <td class="text-muted text-center"><?= $no++; ?></td>
                            <td>
                                <strong><?= e($s['invoice_number']); ?></strong>
                            </td>
                            <td><?= format_date($s['sale_date'], true); ?></td>
                            <td><?= e($s['customer_name'] ?? 'Pelanggan Umum'); ?></td>
                            <td class="text-center fw-semibold"><?= format_number($s['total_weight_kg'], 2); ?> Kg</td>
                            <td class="text-end fw-bold text-dark"><?= format_rupiah($s['total_amount']); ?></td>
                            <td class="text-end text-success"><?= format_rupiah($s['paid_amount']); ?></td>
                            <td class="text-end">
                                <?= $s['balance_due'] > 0 ? '<span class="fw-bold text-danger">' . format_rupiah($s['balance_due']) . '</span>' : '<span class="badge badge-paid">Lunas</span>'; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($s['status'] === 'confirmed'): ?>
                                    <span class="badge badge-status badge-confirmed">Confirmed</span>
                                <?php elseif ($s['status'] === 'draft'): ?>
                                    <span class="badge badge-status badge-draft">Draft</span>
                                <?php else: ?>
                                    <span class="badge badge-status badge-cancelled">Cancelled</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <th colspan="4" class="text-end fs-6">TOTAL:</th>
                    <th class="text-center fs-6 text-dark"><?= format_number($totalWeightSold, 2); ?> Kg</th>
                    <th class="text-end text-success fs-5"><?= format_rupiah($totalSalesAmount); ?></th>
                    <th class="text-end text-primary fs-6"><?= format_rupiah($totalPaid); ?></th>
                    <th class="text-end text-danger fs-6"><?= format_rupiah($totalBalanceDue); ?></th>
                    <th></th>
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
        <small style="color: #666;">Laporan Penjualan Resmi</small>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
