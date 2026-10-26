<?php
/**
 * Modul Biaya Operasional - Rincian Detail Biaya
 * Sistem Informasi Usaha Tani Jambu Citra
 */

declare(strict_types=1);

$pageTitle = 'Detail Biaya Operasional';
$activePage = 'costs';

require_once __DIR__ . '/../includes/header.php';

$pdo = get_db_connection();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT c.*, f.farm_name, f.location, cat.category_name, cat.description AS cat_desc
    FROM operational_costs c
    JOIN farms f ON c.farm_id = f.id
    JOIN cost_categories cat ON c.category_id = cat.id
    WHERE c.id = ?
");
$stmt->execute([$id]);
$cost = $stmt->fetch();

if (!$cost) {
    set_flash('danger', 'Catatan biaya operasional tidak ditemukan.');
    redirect('costs/index.php');
    exit;
}
?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-receipt text-success me-2"></i>Detail Pengeluaran Biaya</h4>
                <p class="text-muted small mb-0">Rincian informasi pengeluaran operasional kebun.</p>
            </div>
            <a href="<?= url('costs/index.php'); ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar
            </a>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <span class="fw-bold text-dark">ID Transaksi: #COST-<?= (int)$cost['id']; ?></span>
                <span class="badge bg-success-subtle text-success fs-6"><?= e($cost['category_name']); ?></span>
            </div>
            <div class="card-body p-4">
                <div class="text-center py-3 bg-light rounded-3 mb-4 border">
                    <small class="text-muted text-uppercase fw-semibold d-block">Total Nominal Biaya</small>
                    <span class="fs-1 fw-bold text-danger"><?= format_rupiah($cost['total_cost']); ?></span>
                </div>

                <div class="table-responsive">
                    <table class="table table-borderless">
                        <tbody>
                            <tr>
                                <th class="text-muted fw-normal" style="width: 35%;">Nama Kebutuhan</th>
                                <td class="fw-bold text-dark">: <?= e($cost['item_name']); ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-normal">Tanggal Pengeluaran</th>
                                <td class="fw-semibold text-dark">: <?= format_date($cost['cost_date']); ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-normal">Kebun Terkait</th>
                                <td>: <strong><?= e($cost['farm_name']); ?></strong> (<?= e($cost['location']); ?>)</td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-normal">Kategori Biaya</th>
                                <td>: <?= e($cost['category_name']); ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-normal">Kuantitas</th>
                                <td>: <?= format_number($cost['quantity'], 2); ?> <?= e($cost['unit']); ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-normal">Harga Satuan</th>
                                <td>: <?= $cost['unit_price'] > 0 ? format_rupiah($cost['unit_price']) : '-'; ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-normal">Keterangan / Faktur</th>
                                <td>: <?= nl2br(e($cost['notes'] ?: '-')); ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-normal">Waktu Pencatatan</th>
                                <td class="text-muted small">: <?= format_date($cost['created_at'], false, true); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="<?= url('costs/edit.php?id=' . $cost['id']); ?>" class="btn btn-outline-primary px-3">
                        <i class="bi bi-pencil me-1"></i> Edit Data
                    </a>
                    <form method="POST" action="<?= url('costs/delete.php'); ?>" class="d-inline" onsubmit="return confirm('Hapus pengeluaran ini secara permanen?');">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="id" value="<?= (int) $cost['id']; ?>">
                        <button type="submit" class="btn btn-outline-danger px-3">
                            <i class="bi bi-trash me-1"></i> Hapus
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
