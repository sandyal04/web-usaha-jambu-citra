<?php
/**
 * Modul Data Pelanggan - Edit Pelanggan
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

$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$id]);
$customer = $stmt->fetch();

if (!$customer) {
    set_flash('danger', 'Data pelanggan tidak ditemukan.');
    redirect('customers/index.php');
    exit;
}

$errors = [];
$old = [
    'customer_name' => $customer['customer_name'],
    'phone'         => (string) ($customer['phone'] ?? ''),
    'address'       => (string) ($customer['address'] ?? ''),
    'notes'         => (string) ($customer['notes'] ?? '')
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_die();

    $customerName = trim($_POST['customer_name'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $address      = trim($_POST['address'] ?? '');
    $notes        = trim($_POST['notes'] ?? '');

    $old = [
        'customer_name' => $customerName,
        'phone'         => $phone,
        'address'       => $address,
        'notes'         => $notes
    ];

    if (empty($customerName)) {
        $errors[] = 'Nama pelanggan wajib diisi.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE customers 
                SET customer_name = ?, phone = ?, address = ?, notes = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $customerName,
                $phone ?: null,
                $address ?: null,
                $notes ?: null,
                $id
            ]);

            set_flash('success', 'Data pelanggan berhasil diperbarui!');
            redirect('customers/index.php');
            exit;
        } catch (PDOException $e) {
            error_log('Update Customer Error: ' . $e->getMessage());
            $errors[] = 'Gagal memperbarui data pelanggan: ' . $e->getMessage();
        }
    }
}

// Render HTML Layout setelah POST selesai
$pageTitle = 'Edit Data Pelanggan';
$activePage = 'customers';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-pencil-square text-success me-2"></i>Edit Data Pelanggan</h4>
                <p class="text-muted small mb-0">Perbarui kontak dan alamat pelanggan <?= e($customer['customer_name']); ?></p>
            </div>
            <a href="<?= url('customers/index.php'); ?>" class="btn btn-outline-secondary btn-sm">
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

        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <form method="POST" action="">
                    <?= csrf_field(); ?>

                    <div class="mb-3">
                        <label for="customer_name" class="form-label">Nama Pelanggan / Toko <span class="text-danger">*</span></label>
                        <input type="text" name="customer_name" id="customer_name" class="form-control" 
                               value="<?= e($old['customer_name']); ?>" required autofocus>
                    </div>

                    <div class="mb-3">
                        <label for="phone" class="form-label">Nomor Telepon / WhatsApp</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                            <input type="text" name="phone" id="phone" class="form-control" 
                                   value="<?= e($old['phone']); ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="address" class="form-label">Alamat / Lokasi Pengiriman</label>
                        <textarea name="address" id="address" rows="2" class="form-control"><?= e($old['address']); ?></textarea>
                    </div>

                    <div class="mb-4">
                        <label for="notes" class="form-label">Keterangan / Preferensi Pelanggan (Opsional)</label>
                        <textarea name="notes" id="notes" rows="2" class="form-control"><?= e($old['notes']); ?></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?= url('customers/index.php'); ?>" class="btn btn-light border px-4">Batal</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-save me-1"></i> Perbarui Pelanggan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
