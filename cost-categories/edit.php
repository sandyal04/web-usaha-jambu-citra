<?php
/**
 * Modul Kategori Biaya - Edit Kategori
 * Sistem Informasi Usaha Tani Jambu Citra
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

require_login();

$pdo = get_db_connection();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM cost_categories WHERE id = ?");
$stmt->execute([$id]);
$category = $stmt->fetch();

if (!$category) {
    set_flash('danger', 'Kategori biaya tidak ditemukan.');
    redirect('cost-categories/index.php');
    exit;
}

$errors = [];
$old = [
    'category_name' => $category['category_name'],
    'description'   => (string) ($category['description'] ?? '')
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_die();

    $categoryName = trim($_POST['category_name'] ?? '');
    $description  = trim($_POST['description'] ?? '');

    $old = [
        'category_name' => $categoryName,
        'description'   => $description
    ];

    if (empty($categoryName)) {
        $errors[] = 'Nama kategori biaya wajib diisi.';
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM cost_categories WHERE category_name = ? AND id != ?");
        $stmt->execute([$categoryName, $id]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Kategori dengan nama tersebut sudah ada.';
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE cost_categories 
                SET category_name = ?, description = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$categoryName, $description ?: null, $id]);

            set_flash('success', 'Kategori biaya berhasil diperbarui!');
            redirect('cost-categories/index.php');
            exit;
        } catch (PDOException $e) {
            error_log('Update Cost Category Error: ' . $e->getMessage());
            $errors[] = 'Gagal memperbarui kategori: ' . $e->getMessage();
        }
    }
}

// Render HTML Layout setelah POST selesai
$pageTitle = 'Edit Kategori Biaya';
$activePage = 'cost_categories';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-pencil-square text-success me-2"></i>Edit Kategori Biaya</h4>
                <p class="text-muted small mb-0">Perbarui informasi kategori biaya operasional.</p>
            </div>
            <a href="<?= url('cost-categories/index.php'); ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Kembali
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

        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <form method="POST" action="">
                    <?= csrf_field(); ?>

                    <div class="mb-3">
                        <label for="category_name" class="form-label">Nama Kategori Biaya <span class="text-danger">*</span></label>
                        <input type="text" name="category_name" id="category_name" class="form-control" 
                               value="<?= e($old['category_name']); ?>" required autofocus>
                    </div>

                    <div class="mb-4">
                        <label for="description" class="form-label">Deskripsi / Contoh Pengeluaran</label>
                        <textarea name="description" id="description" rows="3" class="form-control"><?= e($old['description']); ?></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?= url('cost-categories/index.php'); ?>" class="btn btn-light border px-4">Batal</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-save me-1"></i> Perbarui Kategori
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
