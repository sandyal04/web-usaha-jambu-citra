<?php
/**
 * Logout Pengguna Usaha Tani Jambu Citra
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

logout_user();

set_flash('info', 'Anda telah berhasil keluar dari sistem.');
redirect('auth/login.php');
exit;
