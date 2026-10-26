<?php
/**
 * Modul Penjualan Jambu Citra - Hapus Transaksi Penjualan
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
    redirect('sales/index.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$pdo = get_db_connection();

$stmt = $pdo->prepare("SELECT * FROM sales WHERE id = ?");
$stmt->execute([$id]);
$sale = $stmt->fetch();

if (!$sale) {
    set_flash('danger', 'Transaksi penjualan tidak ditemukan.');
    redirect('sales/index.php');
    exit;
}

// Cek pembayaran
$stmtPay = $pdo->prepare("SELECT COUNT(*) FROM sale_payments WHERE sale_id = ?");
$stmtPay->execute([$id]);
$paymentCount = (int) $stmtPay->fetchColumn();

if ($sale['status'] === 'confirmed' && $paymentCount > 0) {
    set_flash('danger', 'Transaksi yang berstatus Confirmed dan sudah memiliki riwayat pembayaran tidak dapat dihapus. Silakan gunakan fitur pembatalan transaksi di halaman detail.');
    redirect('sales/detail.php?id=' . $id);
    exit;
}

try {
    $stmtDel = $pdo->prepare("DELETE FROM sales WHERE id = ?");
    $stmtDel->execute([$id]);

    set_flash('success', 'Transaksi penjualan ' . $sale['invoice_number'] . ' berhasil dihapus.');
} catch (PDOException $e) {
    error_log('Delete Sale Error: ' . $e->getMessage());
    set_flash('danger', 'Gagal menghapus transaksi: ' . $e->getMessage());
}

redirect('sales/index.php');
exit;
