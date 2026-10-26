<?php
/**
 * Modul Data Kebun - Hapus Kebun
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
    redirect('farms/index.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$pdo = get_db_connection();

try {
    // Periksa apakah kebun digunakan dalam pencatatan biaya operasional
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM operational_costs WHERE farm_id = ?");
    $stmt->execute([$id]);
    $costsCount = (int) $stmt->fetchColumn();

    // Periksa apakah kebun digunakan dalam pencatatan panen
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM harvests WHERE farm_id = ?");
    $stmt->execute([$id]);
    $harvestsCount = (int) $stmt->fetchColumn();

    if ($costsCount > 0 || $harvestsCount > 0) {
        set_flash('danger', "Kebun tidak dapat dihapus karena masih memiliki riwayat terkait ($costsCount biaya operasional, $harvestsCount hasil panen). Hapus atau pindahkan data terkait terlebih dahulu.");
        redirect('farms/index.php');
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM farms WHERE id = ?");
    $stmt->execute([$id]);

    set_flash('success', 'Data kebun berhasil dihapus.');
} catch (PDOException $e) {
    error_log('Delete Farm Error: ' . $e->getMessage());
    set_flash('danger', 'Gagal menghapus kebun: ' . $e->getMessage());
}

redirect('farms/index.php');
exit;
