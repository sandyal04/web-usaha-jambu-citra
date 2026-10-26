<?php
/**
 * Modul Pembayaran - Hapus Catatan Pembayaran
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
    redirect('payments/index.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$pdo = get_db_connection();

$stmt = $pdo->prepare("SELECT * FROM sale_payments WHERE id = ?");
$stmt->execute([$id]);
$pay = $stmt->fetch();

if (!$pay) {
    set_flash('danger', 'Catatan pembayaran tidak ditemukan.');
    redirect('payments/index.php');
    exit;
}

try {
    $stmtDel = $pdo->prepare("DELETE FROM sale_payments WHERE id = ?");
    $stmtDel->execute([$id]);

    set_flash('success', 'Catatan pembayaran senilai ' . format_rupiah($pay['amount']) . ' berhasil dihapus.');
} catch (PDOException $e) {
    error_log('Delete Payment Error: ' . $e->getMessage());
    set_flash('danger', 'Gagal menghapus pembayaran: ' . $e->getMessage());
}

if (!empty($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'sales/detail.php') !== false) {
    redirect('sales/detail.php?id=' . $pay['sale_id']);
} else {
    redirect('payments/index.php');
}
exit;
