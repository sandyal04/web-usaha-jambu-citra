<?php
/**
 * Modul Kategori Biaya - Daftar Kategori
 * Sistem Informasi Usaha Tani Jambu Citra
 */

declare(strict_types=1);

$pageTitle = 'Kategori Biaya Operasional';
$activePage = 'cost_categories';

require_once __DIR__ . '/../includes/header.php';

$pdo = get_db_connection();

$stmt = $pdo->query("
    SELECT c.*, 
           COUNT(o.id) AS usage_count,
           COALESCE(SUM(o.total_cost), 0) AS total_expense
    FROM cost_categories c
    LEFT JOIN operational_costs o ON c.id = o.category_id
    GROUP BY c.id
    ORDER BY c.id ASC
");
$categories = $stmt->fetchAll();
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-tags-fill text-success me-2"></i>Kategori Biaya Operasional</h4>
        <p class="text-muted small mb-0">Kelola kelompok pengeluaran usaha tani jambu citra untuk memudahkan pembukuan dan analisis.</p>
    </div>
    <div>
        <a href="<?= url('cost-categories/create.php'); ?>" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> Tambah Kategori Baru
        </a>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 50px;">No</th>
                    <th>Nama Kategori</th>
                    <th>Deskripsi / Penggunaan</th>
                    <th class="text-center">Jumlah Transaksi</th>
                    <th class="text-end">Total Pengeluaran</th>
                    <th class="text-center" style="width: 130px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($categories)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-tags text-secondary fs-1 d-block mb-2"></i>
                            Belum ada kategori biaya. Silakan klik tombol Tambah Kategori Baru.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($categories as $cat): ?>
                        <tr>
                            <td class="text-muted"><?= $no++; ?></td>
                            <td>
                                <strong class="text-dark fs-6"><?= e($cat['category_name']); ?></strong>
                            </td>
                            <td>
                                <span class="text-muted small"><?= e($cat['description'] ?: '-'); ?></span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-secondary border px-2 py-1">
                                    <?= (int) $cat['usage_count']; ?> Biaya
                                </span>
                            </td>
                            <td class="text-end fw-semibold text-danger">
                                <?= format_rupiah($cat['total_expense']); ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= url('cost-categories/edit.php?id=' . $cat['id']); ?>" class="btn btn-outline-primary" title="Edit Kategori">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php if ((int)$cat['usage_count'] === 0): ?>
                                        <form method="POST" action="<?= url('cost-categories/delete.php'); ?>" class="d-inline" onsubmit="return confirm('Hapus kategori <?= e(addslashes($cat['category_name'])); ?>?');">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="id" value="<?= (int) $cat['id']; ?>">
                                            <button type="submit" class="btn btn-outline-danger" title="Hapus Kategori">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-outline-secondary opacity-50" title="Tidak dapat dihapus karena sedang digunakan oleh transaksi" disabled>
                                            <i class="bi bi-lock-fill"></i>
                                        </button>
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
