<?php
/**
 * Modul Kategori Biaya - Hapus Kategori
 * Sistem Informasi Usaha Tani Jambu Citra
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

require_login();
validate_csrf_or_die();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('cost-categories/index.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$pdo = get_db_connection();

try {
    // Periksa apakah kategori sedang digunakan oleh catatan biaya operasional
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM operational_costs WHERE category_id = ?");
    $stmt->execute([$id]);
    $usageCount = (int) $stmt->fetchColumn();

    if ($usageCount > 0) {
        set_flash('danger', "Kategori biaya tidak dapat dihapus karena sedang digunakan oleh $usageCount catatan pengeluaran biaya.");
        redirect('cost-categories/index.php');
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM cost_categories WHERE id = ?");
    $stmt->execute([$id]);

    set_flash('success', 'Kategori biaya berhasil dihapus.');
} catch (PDOException $e) {
    error_log('Delete Cost Category Error: ' . $e->getMessage());
    set_flash('danger', 'Gagal menghapus kategori: ' . $e->getMessage());
}

redirect('cost-categories/index.php');
exit;
