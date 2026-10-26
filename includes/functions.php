<?php
/**
 * Kumpulan Fungsi Pembantu (Helper Functions)
 * Sistem Informasi Usaha Tani Jambu Citra
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Aktifkan Output Buffering untuk mencegah error "Headers already sent"
if (!ob_get_level()) {
    ob_start();
}

/**
 * Menghitung base path URL aplikasi secara dinamis dan aman
 * Mendukung instalasi di root maupun subfolder XAMPP (e.g. /usaha-tani-jambu-citra/)
 *
 * @return string
 */
function get_base_url(): string
{
    $scriptFilename = str_replace('\\', '/', realpath($_SERVER['SCRIPT_FILENAME'] ?? '') ?: ($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $projectDir = str_replace('\\', '/', dirname(__DIR__));
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

    // 1. Perhitungan presisi berbasis perbandingan direktori root proyek
    if (!empty($scriptFilename) && strpos($scriptFilename, $projectDir) === 0) {
        $relPath = '/' . ltrim(substr($scriptFilename, strlen($projectDir)), '/');
        if (substr($scriptName, -strlen($relPath)) === $relPath) {
            return rtrim(substr($scriptName, 0, -strlen($relPath)), '/');
        }
    }

    // 2. Pola subfolder modul internal dengan pembatas garis miring ('/')
    // Mencegah nama file seperti 'costs-report.php' salah dideteksi sebagai folder '/costs'
    $subdirs = [
        '/auth/', '/farms/', '/cost-categories/', '/costs/', 
        '/harvests/', '/customers/', '/sales/', '/payments/', 
        '/reports/', '/assets/', '/config/', '/database/', '/includes/'
    ];

    foreach ($subdirs as $dir) {
        $pos = strpos($scriptName, $dir);
        if ($pos !== false) {
            return rtrim(substr($scriptName, 0, $pos), '/');
        }
    }

    $dir = dirname($scriptName);
    return ($dir === '/' || $dir === '\\') ? '' : rtrim(str_replace('\\', '/', $dir), '/');
}

/**
 * Menghasilkan URL absolut web berdasarkan path relatif
 *
 * @param string $path
 * @return string
 */
function url(string $path = ''): string
{
    $base = get_base_url();
    $path = ltrim($path, '/');
    return ($base === '' ? '' : $base) . '/' . $path;
}

/**
 * Mengalihkan halaman (Redirect) dengan opsi notifikasi pesan flash
 * Aman dan tahan terhadap kondisi output buffering
 *
 * @param string $path
 * @param string $flashType 'success' | 'danger' | 'warning' | 'info'
 * @param string $flashMessage
 * @return void
 */
function redirect(string $path, string $flashType = '', string $flashMessage = ''): void
{
    if ($flashType !== '' && $flashMessage !== '') {
        set_flash($flashType, $flashMessage);
    }
    
    // Periksa apakah path adalah URL eksternal atau lokal
    $targetUrl = (str_starts_with($path, 'http://') || str_starts_with($path, 'https://'))
        ? $path
        : url($path);

    // Jika header belum terkirim, gunakan header HTTP 302 standar
    if (!headers_sent()) {
        header("Location: " . $targetUrl);
        exit;
    }

    // Jika header terlanjur terkirim, gunakan pengalihan JavaScript & Meta Refresh
    echo '<script type="text/javascript">window.location.replace(' . json_encode($targetUrl) . ');</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($targetUrl, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    echo '<p style="font-family:sans-serif;padding:20px;text-align:center;">Mengalihkan ke <a href="' . htmlspecialchars($targetUrl, ENT_QUOTES, 'UTF-8') . '">halaman tujuan</a>...</p>';
    exit;
}

/**
 * Menyimpan pesan flash (notifikasi sekali pakai) ke dalam sesi
 *
 * @param string $type success|danger|warning|info
 * @param string $message
 * @return void
 */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash_notification'] = [
        'type'    => $type,
        'message' => $message
    ];
}

/**
 * Mengambil dan menghapus pesan flash dari sesi
 *
 * @return array|null
 */
function get_flash(): ?array
{
    if (!empty($_SESSION['flash_notification'])) {
        $flash = $_SESSION['flash_notification'];
        unset($_SESSION['flash_notification']);
        return $flash;
    }
    return null;
}

/**
 * Menampilkan pesan flash dalam bentuk alert HTML Bootstrap
 *
 * @return string
 */
function render_flash(): string
{
    $flash = get_flash();
    if (!$flash) {
        return '';
    }

    $type = htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8');
    $message = htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8');

    $icons = [
        'success' => 'bi-check-circle-fill',
        'danger'  => 'bi-exclamation-triangle-fill',
        'warning' => 'bi-exclamation-circle-fill',
        'info'    => 'bi-info-circle-fill'
    ];
    $icon = $icons[$type] ?? 'bi-info-circle';

    return sprintf(
        '<div class="alert alert-%s alert-dismissible fade show d-flex align-items-center shadow-sm" role="alert">
            <i class="bi %s me-2 fs-5"></i>
            <div>%s</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>',
        $type,
        $icon,
        $message
    );
}

/**
 * Sanitasi output string untuk mencegah XSS
 *
 * @param mixed $value
 * @return string
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Format mata uang Indonesia (Rupiah)
 * Contoh: Rp 1.500.000
 *
 * @param float|int|string|null $amount
 * @param bool $showPrefix
 * @return string
 */
function format_rupiah(float|int|string|null $amount, bool $showPrefix = true): string
{
    $numeric = (float) ($amount ?? 0);
    $formatted = number_format($numeric, 0, ',', '.');
    return $showPrefix ? 'Rp ' . $formatted : $formatted;
}

/**
 * Format angka desimal bahasa Indonesia
 * Contoh: 125,50
 *
 * @param float|int|string|null $number
 * @param int $decimals
 * @return string
 */
function format_number(float|int|string|null $number, int $decimals = 2): string
{
    $numeric = (float) ($number ?? 0);
    // Jika tidak ada sisa pecahan desimal, tampilkan bulat
    if (floor($numeric) == $numeric && $decimals > 0) {
        return number_format($numeric, 0, ',', '.');
    }
    return number_format($numeric, $decimals, ',', '.');
}

/**
 * Format tanggal Indonesia
 * Contoh: 25 Oktober 2026 atau 25 Okt 2026
 *
 * @param string|null $dateString YYYY-MM-DD atau datetime
 * @param bool $shortMonth
 * @param bool $includeTime
 * @return string
 */
function format_date(?string $dateString, bool $shortMonth = false, bool $includeTime = false): string
{
    if (empty($dateString) || $dateString === '0000-00-00' || $dateString === '0000-00-00 00:00:00') {
        return '-';
    }

    $timestamp = strtotime($dateString);
    if ($timestamp === false) {
        return '-';
    }

    $monthsFull = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];

    $monthsShort = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
        5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu',
        9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
    ];

    $day = (int) date('d', $timestamp);
    $monthNum = (int) date('m', $timestamp);
    $year = date('Y', $timestamp);

    $monthName = $shortMonth ? ($monthsShort[$monthNum] ?? '') : ($monthsFull[$monthNum] ?? '');
    $formatted = sprintf('%02d %s %s', $day, $monthName, $year);

    if ($includeTime) {
        $formatted .= ' ' . date('H:i', $timestamp) . ' WIB';
    }

    return $formatted;
}

/**
 * Menghitung margin keuntungan (%) dengan aman (menghindari division by zero)
 * Rumus: (Keuntungan Operasional / Total Pendapatan) * 100
 *
 * @param float $profit
 * @param float $revenue
 * @return float|null Mengembalikan null jika pendapatan <= 0 (tidak dapat dihitung)
 */
function calc_margin(float $profit, float $revenue): ?float
{
    if ($revenue <= 0) {
        return null;
    }
    return ($profit / $revenue) * 100.0;
}

/**
 * Format tampilan margin keuntungan dalam persen
 *
 * @param float|null $margin
 * @return string
 */
function format_margin(?float $margin): string
{
    if ($margin === null) {
        return '<span class="text-muted fst-italic">Tidak dapat dihitung (Pendapatan Rp 0)</span>';
    }

    $formatted = number_format($margin, 2, ',', '.') . '%';
    if ($margin > 0) {
        return '<span class="text-success fw-bold">+' . $formatted . '</span>';
    } elseif ($margin < 0) {
        return '<span class="text-danger fw-bold">' . $formatted . '</span>';
    }
    return '<span class="text-secondary fw-bold">' . $formatted . '</span>';
}

/**
 * Menghasilkan nomor invoice penjualan unik otomatis berurutan
 * Format: JC-YYYYMMDD-XXXX (contoh: JC-20261025-0001)
 *
 * @param PDO $pdo
 * @param string|null $date YYYY-MM-DD (opsional, default hari ini)
 * @return string
 */
function generate_invoice_number(PDO $pdo, ?string $date = null): string
{
    $datePart = $date ? date('Ymd', strtotime($date)) : date('Ymd');
    $prefix = 'JC-' . $datePart . '-';

    $stmt = $pdo->prepare("SELECT invoice_number FROM sales WHERE invoice_number LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $lastInvoice = $stmt->fetchColumn();

    if ($lastInvoice) {
        $lastSeq = (int) substr($lastInvoice, -4);
        $nextSeq = $lastSeq + 1;
    } else {
        $nextSeq = 1;
    }

    return $prefix . str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
}

/**
 * Sanitasi string untuk ekspor CSV (Mencegah CSV Formula Injection)
 *
 * @param mixed $data
 * @return string
 */
function sanitize_csv_cell(mixed $data): string
{
    $str = (string) ($data ?? '');
    // Jika diawali =, +, -, @ tambahkan petik tunggal di awal
    if (isset($str[0]) && in_array($str[0], ['=', '+', '-', '@'], true)) {
        return "'" . $str;
    }
    return $str;
}
