<?php
/**
 * Modul Biaya Operasional - Hapus Catatan Biaya
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
    redirect('costs/index.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$pdo = get_db_connection();

try {
    $stmt = $pdo->prepare("DELETE FROM operational_costs WHERE id = ?");
    $stmt->execute([$id]);

    set_flash('success', 'Catatan biaya operasional berhasil dihapus.');
} catch (PDOException $e) {
    error_log('Delete Cost Error: ' . $e->getMessage());
    set_flash('danger', 'Gagal menghapus biaya: ' . $e->getMessage());
}

redirect('costs/index.php');
exit;
