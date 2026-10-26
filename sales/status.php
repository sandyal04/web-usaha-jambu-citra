<?php
/**
 * Modul Penjualan Jambu Citra - Manajemen Status Transaksi (Konfirmasi / Pembatalan Aman)
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
$action = trim($_POST['action'] ?? '');
$pdo = get_db_connection();

$stmt = $pdo->prepare("SELECT * FROM sales WHERE id = ?");
$stmt->execute([$id]);
$sale = $stmt->fetch();

if (!$sale) {
    set_flash('danger', 'Transaksi penjualan tidak ditemukan.');
    redirect('sales/index.php');
    exit;
}

try {
    if ($action === 'confirm') {
        // Konfirmasi draft menjadi penjualan sah
        $stmt = $pdo->prepare("UPDATE sales SET status = 'confirmed', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);

        set_flash('success', "Transaksi " . $sale['invoice_number'] . " berhasil dikonfirmasi dan masuk ke dalam perhitungan pendapatan!");
    } elseif ($action === 'cancel') {
        // Pembatalan transaksi tercatat dengan timestamp
        $timestamp = date('d-m-Y H:i');
        $cancellationNote = trim($sale['notes'] . " [Dibatalkan pada $timestamp WIB]");

        $stmt = $pdo->prepare("UPDATE sales SET status = 'cancelled', notes = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$cancellationNote, $id]);

        set_flash('warning', "Transaksi " . $sale['invoice_number'] . " berhasil dibatalkan. Transaksi ini tidak lagi dihitung sebagai pendapatan usaha.");
    } else {
        set_flash('danger', 'Aksi status tidak valid.');
    }
} catch (PDOException $e) {
    error_log('Sale Status Change Error: ' . $e->getMessage());
    set_flash('danger', 'Gagal memproses perubahan status: ' . $e->getMessage());
}

redirect('sales/detail.php?id=' . $id);
exit;
