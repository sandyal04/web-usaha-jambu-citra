<?php
/**
 * Modul Data Pelanggan - Hapus Pelanggan
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
    redirect('customers/index.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$pdo = get_db_connection();

try {
    $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
    $stmt->execute([$id]);

    set_flash('success', 'Data pelanggan berhasil dihapus.');
} catch (PDOException $e) {
    error_log('Delete Customer Error: ' . $e->getMessage());
    set_flash('danger', 'Gagal menghapus pelanggan: ' . $e->getMessage());
}

redirect('customers/index.php');
exit;
