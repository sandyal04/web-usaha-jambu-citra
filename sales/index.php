<?php
/**
 * Modul Penjualan Jambu Citra - Daftar Transaksi Penjualan
 * Sistem Informasi Usaha Tani Jambu Citra
 */

declare(strict_types=1);

$pageTitle = 'Penjualan Jambu Citra';
$activePage = 'sales';

require_once __DIR__ . '/../includes/header.php';

$pdo = get_db_connection();

// Filter & Pencarian
$search = trim($_GET['q'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$filterCustomer = isset($_GET['customer_id']) && $_GET['customer_id'] !== '' ? (int) $_GET['customer_id'] : 0;
$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');

$where = ["1=1"];
$params = [];

if (!empty($search)) {
    $where[] = "(s.invoice_number LIKE ? OR s.notes LIKE ? OR c.customer_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

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

// Ambil data transaksi penjualan beserta total pembayaran terbayar
$sql = "
    SELECT s.*, 
           c.customer_name, c.phone,
           COALESCE(p.total_paid, 0) AS total_paid,
           (s.total_amount - COALESCE(p.total_paid, 0)) AS balance_due,
           (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id) AS items_count,
           (SELECT SUM(weight_kg) FROM sale_items si WHERE si.sale_id = s.id) AS total_weight_kg
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    LEFT JOIN (
        SELECT sale_id, SUM(amount) AS total_paid 
        FROM sale_payments 
        GROUP BY sale_id
    ) p ON s.id = p.sale_id
    WHERE $whereClause
    ORDER BY s.sale_date DESC, s.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll();

// Total Ringkasan Terpilih
$confirmedTotal = 0.0;
$draftTotal = 0.0;
$cancelledTotal = 0.0;
$totalKgSold = 0.0;

foreach ($sales as $sale) {
    if ($sale['status'] === 'confirmed') {
        $confirmedTotal += (float) $sale['total_amount'];
        $totalKgSold += (float) $sale['total_weight_kg'];
    } elseif ($sale['status'] === 'draft') {
        $draftTotal += (float) $sale['total_amount'];
    } elseif ($sale['status'] === 'cancelled') {
        $cancelledTotal += (float) $sale['total_amount'];
    }
}

$customersList = $pdo->query("SELECT id, customer_name FROM customers ORDER BY customer_name ASC")->fetchAll();
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-cart-check-fill text-success me-2"></i>Transaksi Penjualan Jambu Citra</h4>
        <p class="text-muted small mb-0">Kelola pesanan, faktur penjualan buah, status konfirmasi, dan pelunasan piutang.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('sales/create.php'); ?>" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> Transaksi Penjualan Baru
        </a>
    </div>
</div>

<!-- Kartu Ringkasan Penjualan -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-check-circle-fill"></i></div>
            <div>
                <div class="stat-label">Pendapatan Sah (Confirmed)</div>
                <div class="stat-value text-success"><?= format_rupiah($confirmedTotal); ?></div>
                <div class="stat-sub">Dihitung dalam laba rugi</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon purple"><i class="bi bi-box-seam"></i></div>
            <div>
                <div class="stat-label">Volume Buah Terjual</div>
                <div class="stat-value text-dark"><?= format_number($totalKgSold, 2); ?> <span class="fs-6 fw-normal text-muted">Kg</span></div>
                <div class="stat-sub">Dari faktur berstatus confirmed</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon amber"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="stat-label">Penjualan Draft</div>
                <div class="stat-value text-warning"><?= format_rupiah($draftTotal); ?></div>
                <div class="stat-sub">Belum masuk pendapatan</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon red"><i class="bi bi-x-circle-fill"></i></div>
            <div>
                <div class="stat-label">Penjualan Dibatalkan</div>
                <div class="stat-value text-danger"><?= format_rupiah($cancelledTotal); ?></div>
                <div class="stat-sub">Tidak menambah pendapatan</div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Pencarian -->
<div class="card mb-4 border-0 shadow-sm">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1">Cari No. Faktur / Catatan</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Nomor faktur..." value="<?= e($search); ?>">
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Status Penjualan</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">-- Semua Status --</option>
                    <option value="confirmed" <?= $filterStatus === 'confirmed' ? 'selected' : ''; ?>>Confirmed (Sah)</option>
                    <option value="draft" <?= $filterStatus === 'draft' ? 'selected' : ''; ?>>Draft</option>
                    <option value="cancelled" <?= $filterStatus === 'cancelled' ? 'selected' : ''; ?>>Cancelled (Batal)</option>
                </select>
            </div>
            <div class="col-md-2">
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
            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1" title="Terapkan Filter">
                    <i class="bi bi-funnel"></i>
                </button>
                <a href="<?= url('sales/index.php'); ?>" class="btn btn-outline-secondary btn-sm" title="Reset">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Tabel Transaksi Penjualan -->
<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 50px;">No</th>
                    <th>No. Faktur</th>
                    <th>Tanggal</th>
                    <th>Pelanggan</th>
                    <th class="text-center">Berat (Kg)</th>
                    <th class="text-end">Total Nilai</th>
                    <th class="text-end">Terbayar</th>
                    <th class="text-end">Sisa Tagihan</th>
                    <th class="text-center">Status</th>
                    <th class="text-center" style="width: 140px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sales)): ?>
                    <tr>
                        <td colspan="10" class="text-center py-5 text-muted">
                            <i class="bi bi-receipt text-secondary fs-1 d-block mb-2"></i>
                            Tidak ada transaksi penjualan yang sesuai dengan filter.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($sales as $s): ?>
                        <tr>
                            <td class="text-muted"><?= $no++; ?></td>
                            <td>
                                <a href="<?= url('sales/detail.php?id=' . $s['id']); ?>" class="fw-bold text-decoration-none">
                                    <?= e($s['invoice_number']); ?>
                                </a>
                                <div class="small text-muted"><?= (int)$s['items_count']; ?> jenis item</div>
                            </td>
                            <td><?= format_date($s['sale_date'], true); ?></td>
                            <td>
                                <strong class="text-dark d-block"><?= e($s['customer_name'] ?? 'Pelanggan Umum'); ?></strong>
                                <?php if (!empty($s['phone'])): ?>
                                    <small class="text-muted"><?= e($s['phone']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="text-center fw-semibold">
                                <?= format_number($s['total_weight_kg'], 2); ?> Kg
                            </td>
                            <td class="text-end fw-bold text-dark">
                                <?= format_rupiah($s['total_amount']); ?>
                            </td>
                            <td class="text-end text-success fw-semibold">
                                <?= format_rupiah($s['total_paid']); ?>
                            </td>
                            <td class="text-end">
                                <?php if ($s['status'] === 'cancelled'): ?>
                                    <span class="text-muted small">Dibatalkan</span>
                                <?php elseif ($s['balance_due'] <= 0): ?>
                                    <span class="badge badge-status badge-paid"><i class="bi bi-check2"></i> Lunas</span>
                                <?php else: ?>
                                    <span class="fw-bold text-danger"><?= format_rupiah($s['balance_due']); ?></span>
                                <?php endif; ?>
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
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= url('sales/detail.php?id=' . $s['id']); ?>" class="btn btn-outline-secondary" title="Lihat Faktur & Pembayaran">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if ($s['status'] === 'draft'): ?>
                                        <a href="<?= url('sales/edit.php?id=' . $s['id']); ?>" class="btn btn-outline-primary" title="Edit Draft">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form method="POST" action="<?= url('sales/delete.php'); ?>" class="d-inline" onsubmit="return confirm('Hapus draft transaksi <?= e($s['invoice_number']); ?>?');">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="id" value="<?= (int) $s['id']; ?>">
                                            <button type="submit" class="btn btn-outline-danger" title="Hapus Draft">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
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
