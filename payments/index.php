<?php
/**
 * Modul Pembayaran - Daftar Pembayaran & Penerimaan Kas
 * Sistem Informasi Usaha Tani Jambu Citra
 */

declare(strict_types=1);

$pageTitle = 'Pencatatan Pembayaran Pelanggan';
$activePage = 'payments';

require_once __DIR__ . '/../includes/header.php';

$pdo = get_db_connection();

$search = trim($_GET['q'] ?? '');
$methodFilter = trim($_GET['method'] ?? '');
$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');

$where = ["1=1"];
$params = [];

if (!empty($search)) {
    $where[] = "(s.invoice_number LIKE ? OR c.customer_name LIKE ? OR p.notes LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($methodFilter)) {
    $where[] = "p.payment_method = ?";
    $params[] = $methodFilter;
}

if (!empty($startDate)) {
    $where[] = "p.payment_date >= ?";
    $params[] = $startDate;
}

if (!empty($endDate)) {
    $where[] = "p.payment_date <= ?";
    $params[] = $endDate;
}

$whereClause = implode(" AND ", $where);

$sql = "
    SELECT p.*, s.invoice_number, s.total_amount, s.status AS sale_status,
           c.customer_name
    FROM sale_payments p
    JOIN sales s ON p.sale_id = s.id
    LEFT JOIN customers c ON s.customer_id = c.id
    WHERE $whereClause
    ORDER BY p.payment_date DESC, p.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

$totalCollected = array_sum(array_column($payments, 'amount'));
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-wallet2 text-success me-2"></i>Pencatatan Pembayaran Pelanggan</h4>
        <p class="text-muted small mb-0">Kelola penerimaan kas pelunasan faktur penjualan jambu citra.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('payments/create.php'); ?>" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> Catat Pembayaran Baru
        </a>
    </div>
</div>

<!-- Pemberitahuan Akuntansi -->
<div class="alert alert-light border shadow-sm d-flex align-items-center mb-4 py-2">
    <i class="bi bi-info-circle-fill fs-5 me-2 text-success"></i>
    <div class="small">
        <strong>Aturan Finansial:</strong> Pembayaran mencatat aliran pelunasan piutang/kas masuk. Pembayaran <em>tidak dihitung sebagai pendapatan baru</em> agar tidak terjadi perhitungan ganda atas omset penjualan yang telah diakui pada faktur.
    </div>
</div>

<!-- Filter Pembayaran -->
<div class="card mb-4 border-0 shadow-sm">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1">Cari No. Faktur / Pelanggan</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Faktur / Pelanggan..." value="<?= e($search); ?>">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Metode Pembayaran</label>
                <select name="method" class="form-select form-select-sm">
                    <option value="">-- Semua Metode --</option>
                    <option value="Tunai" <?= $methodFilter === 'Tunai' ? 'selected' : ''; ?>>Tunai / Cash</option>
                    <option value="Transfer Bank" <?= $methodFilter === 'Transfer Bank' ? 'selected' : ''; ?>>Transfer Bank</option>
                    <option value="QRIS" <?= $methodFilter === 'QRIS' ? 'selected' : ''; ?>>QRIS</option>
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
                <a href="<?= url('payments/index.php'); ?>" class="btn btn-outline-secondary btn-sm" title="Reset">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Ringkasan Total Kas Diterima -->
<div class="alert alert-light border d-flex justify-content-between align-items-center mb-3 py-2 px-3">
    <span class="text-secondary small">
        Menampilkan <strong><?= count($payments); ?></strong> transaksi pembayaran
    </span>
    <span class="fs-6">
        Total Kas Masuk Diterima: <strong class="text-success fs-5"><?= format_rupiah($totalCollected); ?></strong>
    </span>
</div>

<!-- Tabel Riwayat Pembayaran -->
<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 50px;">No</th>
                    <th>Tanggal Pembayaran</th>
                    <th>Nomor Faktur Terkait</th>
                    <th>Pelanggan</th>
                    <th>Metode Pembayaran</th>
                    <th class="text-end">Jumlah Pembayaran</th>
                    <th>Keterangan / Ref</th>
                    <th class="text-center" style="width: 100px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payments)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-wallet2 text-secondary fs-1 d-block mb-2"></i>
                            Tidak ada riwayat pembayaran yang sesuai dengan kriteria filter.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($payments as $p): ?>
                        <tr>
                            <td class="text-muted"><?= $no++; ?></td>
                            <td><?= format_date($p['payment_date'], true); ?></td>
                            <td>
                                <a href="<?= url('sales/detail.php?id=' . $p['sale_id']); ?>" class="fw-bold text-decoration-none">
                                    <?= e($p['invoice_number']); ?>
                                </a>
                                <small class="text-muted d-block">Nilai Faktur: <?= format_rupiah($p['total_amount']); ?></small>
                            </td>
                            <td><?= e($p['customer_name'] ?? 'Pelanggan Umum'); ?></td>
                            <td>
                                <span class="badge bg-light text-dark border"><?= e($p['payment_method']); ?></span>
                            </td>
                            <td class="text-end fw-bold text-success fs-6">
                                <?= format_rupiah($p['amount']); ?>
                            </td>
                            <td><small class="text-muted"><?= e($p['notes'] ?: '-'); ?></small></td>
                            <td class="text-center">
                                <form method="POST" action="<?= url('payments/delete.php'); ?>" class="d-inline" onsubmit="return confirm('Hapus pembayaran <?= format_rupiah($p['amount']); ?> untuk faktur <?= e($p['invoice_number']); ?>?');">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?= (int) $p['id']; ?>">
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
