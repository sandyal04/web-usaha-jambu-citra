<?php
/**
 * Modul Laporan - Ekspor Data CSV Aman
 * Sistem Informasi Usaha Tani Jambu Citra
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

$pdo = get_db_connection();
$type = trim($_GET['type'] ?? 'profit');
$startDate = trim($_GET['start_date'] ?? date('Y-01-01'));
$endDate   = trim($_GET['end_date'] ?? date('Y-m-d'));

$filename = 'laporan_' . $type . '_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// Tambahkan UTF-8 Byte Order Mark (BOM) agar Microsoft Excel di Windows membaca UTF-8 dengan benar
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

if ($type === 'costs') {
    // 1. Ekspor Biaya Operasional
    fputcsv($output, ['No', 'Tanggal', 'Kebun', 'Kategori Biaya', 'Nama Kebutuhan', 'Kuantitas', 'Satuan', 'Harga Satuan (Rp)', 'Total Biaya (Rp)', 'Catatan']);

    $filterFarm = isset($_GET['farm_id']) && $_GET['farm_id'] !== '' ? (int) $_GET['farm_id'] : 0;
    $filterCat  = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? (int) $_GET['category_id'] : 0;

    $where = ["1=1"];
    $params = [];
    if ($filterFarm > 0) { $where[] = "c.farm_id = ?"; $params[] = $filterFarm; }
    if ($filterCat > 0) { $where[] = "c.category_id = ?"; $params[] = $filterCat; }
    if (!empty($startDate)) { $where[] = "c.cost_date >= ?"; $params[] = $startDate; }
    if (!empty($endDate)) { $where[] = "c.cost_date <= ?"; $params[] = $endDate; }

    $sql = "
        SELECT c.*, f.farm_name, cat.category_name 
        FROM operational_costs c
        JOIN farms f ON c.farm_id = f.id
        JOIN cost_categories cat ON c.category_id = cat.id
        WHERE " . implode(" AND ", $where) . "
        ORDER BY c.cost_date ASC, c.id ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $no = 1;
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $no++,
            $row['cost_date'],
            sanitize_csv_cell($row['farm_name']),
            sanitize_csv_cell($row['category_name']),
            sanitize_csv_cell($row['item_name']),
            $row['quantity'],
            sanitize_csv_cell($row['unit']),
            $row['unit_price'],
            $row['total_cost'],
            sanitize_csv_cell($row['notes'])
        ]);
    }

} elseif ($type === 'harvests') {
    // 2. Ekspor Hasil Panen
    fputcsv($output, ['No', 'Tanggal Panen', 'Kebun Lokasi', 'Lokasi Lahan', 'Kualitas / Grade', 'Berat Panen (Kg)', 'Catatan']);

    $filterFarm  = isset($_GET['farm_id']) && $_GET['farm_id'] !== '' ? (int) $_GET['farm_id'] : 0;
    $filterGrade = trim($_GET['grade'] ?? '');

    $where = ["1=1"];
    $params = [];
    if ($filterFarm > 0) { $where[] = "h.farm_id = ?"; $params[] = $filterFarm; }
    if (!empty($filterGrade)) { $where[] = "h.fruit_grade = ?"; $params[] = $filterGrade; }
    if (!empty($startDate)) { $where[] = "h.harvest_date >= ?"; $params[] = $startDate; }
    if (!empty($endDate)) { $where[] = "h.harvest_date <= ?"; $params[] = $endDate; }

    $sql = "
        SELECT h.*, f.farm_name, f.location 
        FROM harvests h
        JOIN farms f ON h.farm_id = f.id
        WHERE " . implode(" AND ", $where) . "
        ORDER BY h.harvest_date ASC, h.id ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $no = 1;
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $no++,
            $row['harvest_date'],
            sanitize_csv_cell($row['farm_name']),
            sanitize_csv_cell($row['location']),
            sanitize_csv_cell($row['fruit_grade']),
            $row['weight_kg'],
            sanitize_csv_cell($row['notes'])
        ]);
    }

} elseif ($type === 'sales') {
    // 3. Ekspor Transaksi Penjualan
    fputcsv($output, ['No', 'Nomor Faktur', 'Tanggal Transaksi', 'Pelanggan', 'Berat Terjual (Kg)', 'Total Nilai (Rp)', 'Status Faktur', 'Terbayar (Rp)', 'Sisa Tagihan (Rp)', 'Catatan']);

    $filterStatus   = trim($_GET['status'] ?? '');
    $filterCustomer = isset($_GET['customer_id']) && $_GET['customer_id'] !== '' ? (int) $_GET['customer_id'] : 0;

    $where = ["1=1"];
    $params = [];
    if (!empty($filterStatus)) { $where[] = "s.status = ?"; $params[] = $filterStatus; }
    if ($filterCustomer > 0) { $where[] = "s.customer_id = ?"; $params[] = $filterCustomer; }
    if (!empty($startDate)) { $where[] = "s.sale_date >= ?"; $params[] = $startDate; }
    if (!empty($endDate)) { $where[] = "s.sale_date <= ?"; $params[] = $endDate; }

    $sql = "
        SELECT s.*, c.customer_name,
               (SELECT SUM(weight_kg) FROM sale_items si WHERE si.sale_id = s.id) AS total_weight_kg,
               COALESCE(p.paid_amount, 0) AS paid_amount,
               (s.total_amount - COALESCE(p.paid_amount, 0)) AS balance_due
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN (
            SELECT sale_id, SUM(amount) AS paid_amount 
            FROM sale_payments 
            GROUP BY sale_id
        ) p ON s.id = p.sale_id
        WHERE " . implode(" AND ", $where) . "
        ORDER BY s.sale_date ASC, s.id ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $no = 1;
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $no++,
            sanitize_csv_cell($row['invoice_number']),
            $row['sale_date'],
            sanitize_csv_cell($row['customer_name'] ?? 'Pelanggan Umum'),
            $row['total_weight_kg'] ?? 0,
            $row['total_amount'],
            $row['status'],
            $row['paid_amount'],
            $row['balance_due'],
            sanitize_csv_cell($row['notes'])
        ]);
    }

} elseif ($type === 'payments') {
    // 4. Ekspor Pembayaran
    fputcsv($output, ['No', 'Tanggal Bayar', 'Nomor Faktur', 'Pelanggan', 'Metode Pembayaran', 'Jumlah Bayar (Rp)', 'Catatan']);

    $filterMethod = trim($_GET['method'] ?? '');

    $where = ["1=1"];
    $params = [];
    if (!empty($filterMethod)) { $where[] = "p.payment_method = ?"; $params[] = $filterMethod; }
    if (!empty($startDate)) { $where[] = "p.payment_date >= ?"; $params[] = $startDate; }
    if (!empty($endDate)) { $where[] = "p.payment_date <= ?"; $params[] = $endDate; }

    $sql = "
        SELECT p.*, s.invoice_number, c.customer_name
        FROM sale_payments p
        JOIN sales s ON p.sale_id = s.id
        LEFT JOIN customers c ON s.customer_id = c.id
        WHERE " . implode(" AND ", $where) . "
        ORDER BY p.payment_date ASC, p.id ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $no = 1;
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $no++,
            $row['payment_date'],
            sanitize_csv_cell($row['invoice_number']),
            sanitize_csv_cell($row['customer_name'] ?? 'Pelanggan Umum'),
            sanitize_csv_cell($row['payment_method']),
            $row['amount'],
            sanitize_csv_cell($row['notes'])
        ]);
    }

} else {
    // 5. Ekspor Analisis Keuntungan Operasional
    fputcsv($output, ['PARAMETER ANALISIS KEUNTUNGAN', 'NILAI / KETERANGAN']);
    fputcsv($output, ['Periode Awal', $startDate]);
    fputcsv($output, ['Periode Akhir', $endDate]);

    $stmtRev = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE status = 'confirmed' AND sale_date >= ? AND sale_date <= ?");
    $stmtRev->execute([$startDate, $endDate]);
    $rev = (float) $stmtRev->fetchColumn();

    $stmtCost = $pdo->prepare("SELECT COALESCE(SUM(total_cost), 0) FROM operational_costs WHERE cost_date >= ? AND cost_date <= ?");
    $stmtCost->execute([$startDate, $endDate]);
    $cost = (float) $stmtCost->fetchColumn();

    $profit = $rev - $cost;
    $margin = ($rev > 0) ? ($profit / $rev) * 100 : null;

    fputcsv($output, ['Total Pendapatan (Confirmed Sales)', $rev]);
    fputcsv($output, ['Total Biaya Operasional', $cost]);
    fputcsv($output, ['Keuntungan Operasional Bersih', $profit]);
    fputcsv($output, ['Margin Keuntungan (%)', $margin !== null ? number_format($margin, 2, '.', '') . '%' : 'Tidak dapat dihitung (Pendapatan 0)']);
}

fclose($output);
exit;
