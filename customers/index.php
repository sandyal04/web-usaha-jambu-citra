<?php
/**
 * Modul Data Pelanggan - Daftar Pembeli Jambu Citra
 * Sistem Informasi Usaha Tani Jambu Citra
 */

declare(strict_types=1);

$pageTitle = 'Data Pelanggan';
$activePage = 'customers';

require_once __DIR__ . '/../includes/header.php';

$pdo = get_db_connection();

$search = trim($_GET['q'] ?? '');
$sql = "
    SELECT c.*, 
           COUNT(s.id) AS total_orders,
           COALESCE(SUM(CASE WHEN s.status = 'confirmed' THEN s.total_amount ELSE 0 END), 0) AS total_spent
    FROM customers c
    LEFT JOIN sales s ON c.id = s.customer_id
";
$params = [];

if (!empty($search)) {
    $sql .= " WHERE c.customer_name LIKE ? OR c.phone LIKE ? OR c.address LIKE ?";
    $params = ["%$search%", "%$search%", "%$search%"];
}

$sql .= " GROUP BY c.id ORDER BY c.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-people-fill text-success me-2"></i>Data Pelanggan</h4>
        <p class="text-muted small mb-0">Kelola informasi kontak pembeli, tengkulak, toko buah, atau pelanggan retail.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('customers/create.php'); ?>" class="btn btn-primary">
            <i class="bi bi-person-plus-fill me-1"></i> Tambah Pelanggan Baru
        </a>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3">
        <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Cari nama / telepon / alamat..." value="<?= e($search); ?>">
                    <button class="btn btn-outline-secondary" type="submit">Cari</button>
                    <?php if (!empty($search)): ?>
                        <a href="<?= url('customers/index.php'); ?>" class="btn btn-outline-danger">Reset</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 50px;">No</th>
                    <th>Nama Pelanggan</th>
                    <th>No. Telepon / WhatsApp</th>
                    <th>Alamat</th>
                    <th class="text-center">Total Transaksi</th>
                    <th class="text-end">Total Pembelian</th>
                    <th>Keterangan</th>
                    <th class="text-center" style="width: 120px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($customers)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-people text-secondary fs-1 d-block mb-2"></i>
                            Belum ada data pelanggan yang tersimpan.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($customers as $c): ?>
                        <tr>
                            <td class="text-muted"><?= $no++; ?></td>
                            <td>
                                <strong class="text-dark d-block"><?= e($c['customer_name']); ?></strong>
                                <small class="text-muted">ID: #CUST-<?= (int)$c['id']; ?></small>
                            </td>
                            <td>
                                <?php if (!empty($c['phone'])): ?>
                                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $c['phone']); ?>" target="_blank" class="text-decoration-none">
                                        <i class="bi bi-whatsapp text-success me-1"></i> <?= e($c['phone']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><small><?= e($c['address'] ?: '-'); ?></small></td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border px-2 py-1"><?= (int) $c['total_orders']; ?> Order</span>
                            </td>
                            <td class="text-end fw-semibold text-success">
                                <?= format_rupiah($c['total_spent']); ?>
                            </td>
                            <td><small class="text-muted"><?= e($c['notes'] ?: '-'); ?></small></td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= url('customers/edit.php?id=' . $c['id']); ?>" class="btn btn-outline-primary" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="<?= url('customers/delete.php'); ?>" class="d-inline" onsubmit="return confirm('Hapus pelanggan <?= e(addslashes($c['customer_name'])); ?>?');">
                                        <?= csrf_field(); ?>
                                        <input type="hidden" name="id" value="<?= (int) $c['id']; ?>">
                                        <button type="submit" class="btn btn-outline-danger" title="Hapus">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
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
