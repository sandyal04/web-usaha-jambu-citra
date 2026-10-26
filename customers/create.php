<?php
/**
 * Modul Data Pelanggan - Tambah Pelanggan Baru
 * Sistem Informasi Usaha Tani Jambu Citra
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

require_login();

$pdo = get_db_connection();
$errors = [];
$old = [
    'customer_name' => '',
    'phone'         => '',
    'address'       => '',
    'notes'         => ''
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
                INSERT INTO customers (customer_name, phone, address, notes, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $customerName,
                $phone ?: null,
                $address ?: null,
                $notes ?: null
            ]);

            set_flash('success', 'Data pelanggan berhasil ditambahkan!');
            redirect('customers/index.php');
            exit;
        } catch (PDOException $e) {
            error_log('Insert Customer Error: ' . $e->getMessage());
            $errors[] = 'Gagal menyimpan data pelanggan: ' . $e->getMessage();
        }
    }
}

// Render HTML Layout setelah POST selesai
$pageTitle = 'Tambah Pelanggan Baru';
$activePage = 'customers';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-person-plus-fill text-success me-2"></i>Tambah Pelanggan Baru</h4>
                <p class="text-muted small mb-0">Simpan informasi kontak pembeli atau mitra usaha tani.</p>
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
                               value="<?= e($old['customer_name']); ?>" placeholder="Contoh: Toko Buah Segar Mandiri / Bpk. Bambang" required autofocus>
                    </div>

                    <div class="mb-3">
                        <label for="phone" class="form-label">Nomor Telepon / WhatsApp</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                            <input type="text" name="phone" id="phone" class="form-control" 
                                   value="<?= e($old['phone']); ?>" placeholder="Contoh: 081234567890">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="address" class="form-label">Alamat / Lokasi Pengiriman</label>
                        <textarea name="address" id="address" rows="2" class="form-control" 
                                  placeholder="Contoh: Pasar Induk Kramat Jati Kios No. 12"><?= e($old['address']); ?></textarea>
                    </div>

                    <div class="mb-4">
                        <label for="notes" class="form-label">Keterangan / Preferensi Pelanggan (Opsional)</label>
                        <textarea name="notes" id="notes" rows="2" class="form-control" 
                                  placeholder="Contoh: Langganan rutin buah Grade Super dan Grade A tiap panen raya"><?= e($old['notes']); ?></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?= url('customers/index.php'); ?>" class="btn btn-light border px-4">Batal</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-save me-1"></i> Simpan Pelanggan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
