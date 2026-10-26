-- =====================================================================
-- SISTEM INFORMASI PENGELOLAAN BIAYA OPERASIONAL DAN ANALISIS KEUNTUNGAN
-- USAHA TANI JAMBU CITRA BERBASIS WEBSITE
-- Database: db_usaha_jambu_citra
-- Engine: InnoDB, Charset: utf8mb4_unicode_ci
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `db_usaha_jambu_citra` 
DEFAULT CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `db_usaha_jambu_citra`;

-- ---------------------------------------------------------------------
-- 1. TABEL PENGGUNA (Hanya 1 akun pengelola utama usaha tani)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `email` VARCHAR(100) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. TABEL DATA KEBUN (Farms)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `farms` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `farm_name` VARCHAR(100) NOT NULL,
    `location` VARCHAR(255) NOT NULL,
    `area_size` DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'Luas lahan',
    `area_unit` VARCHAR(20) NOT NULL DEFAULT 'm2' COMMENT 'm2, hektar, bata, dsb.',
    `tree_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Jumlah pohon jambu citra',
    `notes` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_farms_name` (`farm_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. TABEL KATEGORI BIAYA OPERASIONAL (Cost Categories)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cost_categories` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `category_name` VARCHAR(100) NOT NULL UNIQUE,
    `description` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_cost_categories_name` (`category_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Kategori Biaya Standar Usaha Tani Jambu Citra
INSERT INTO `cost_categories` (`id`, `category_name`, `description`) VALUES
(1, 'Pupuk', 'Pengadaan pupuk organik, NPK, pupuk kandang, dan pembenah tanah'),
(2, 'Pestisida & Fungisida', 'Pengendalian hama lalat buah, ulat, kutu putih, dan jamur'),
(3, 'Tenaga Kerja', 'Upah pemangkasan, penyiangan, pemupukan, dan perawatan'),
(4, 'Pengairan', 'Biaya bahan bakar pompa, listrik irigasi, dan saluran air'),
(5, 'Pembungkusan Buah', 'Plastik khusus, kertas pembungkus buah jambu citra, tali perondong'),
(6, 'Peralatan & Perlengkapan', 'Gunting pangkas, semprotan knapsack, keranjang panen, tangga'),
(7, 'Transportasi & Logistik', 'BBM distribusi panen dan angkut sarana produksi'),
(8, 'Perawatan Kebun', 'Pembersihan gulma, perbaikan pagar, pemangkasan ranting mati'),
(9, 'Biaya Lainnya', 'Biaya operasional tak terduga lainnya')
ON DUPLICATE KEY UPDATE `category_name` = VALUES(`category_name`);

-- ---------------------------------------------------------------------
-- 4. TABEL BIAYA OPERASIONAL (Operational Costs)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `operational_costs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `farm_id` INT UNSIGNED NOT NULL,
    `category_id` INT UNSIGNED NOT NULL,
    `cost_date` DATE NOT NULL,
    `item_name` VARCHAR(150) NOT NULL,
    `quantity` DECIMAL(12, 2) NOT NULL DEFAULT 1.00,
    `unit` VARCHAR(30) NOT NULL DEFAULT 'paket',
    `unit_price` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    `total_cost` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    `notes` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_costs_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_costs_category` FOREIGN KEY (`category_id`) REFERENCES `cost_categories` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX `idx_costs_date` (`cost_date`),
    INDEX `idx_costs_farm` (`farm_id`),
    INDEX `idx_costs_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. TABEL PENCATATAN HASIL PANEN (Harvests)
-- (Catatan: Hasil panen murni data fisik hasil kebun, bukan pendapatan finansial)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `harvests` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `farm_id` INT UNSIGNED NOT NULL,
    `harvest_date` DATE NOT NULL,
    `weight_kg` DECIMAL(12, 2) NOT NULL DEFAULT 0.00 COMMENT 'Berat panen dalam Kg',
    `fruit_grade` VARCHAR(50) NOT NULL DEFAULT 'Grade A' COMMENT 'Grade A / Super / Grade B / BS',
    `notes` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_harvests_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX `idx_harvests_date` (`harvest_date`),
    INDEX `idx_harvests_farm` (`farm_id`),
    INDEX `idx_harvests_grade` (`fruit_grade`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. TABEL PELANGGAN (Customers)
-- (Tidak memiliki hak login, hanya data referensi transaksi penjualan)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `customers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `customer_name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(25) DEFAULT NULL,
    `address` VARCHAR(255) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_customers_name` (`customer_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. TABEL TRANSAKSI PENJUALAN (Sales)
-- (Hanya status 'confirmed' yang dihitung sebagai Pendapatan Usaha)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sales` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `invoice_number` VARCHAR(50) NOT NULL UNIQUE,
    `customer_id` INT UNSIGNED DEFAULT NULL,
    `sale_date` DATE NOT NULL,
    `total_amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    `status` ENUM('draft', 'confirmed', 'cancelled') NOT NULL DEFAULT 'confirmed',
    `notes` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_sales_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX `idx_sales_invoice` (`invoice_number`),
    INDEX `idx_sales_date` (`sale_date`),
    INDEX `idx_sales_status` (`status`),
    INDEX `idx_sales_customer` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8. TABEL RINCIAN ITEM PENJUALAN (Sale Items)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sale_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `sale_id` INT UNSIGNED NOT NULL,
    `product_grade` VARCHAR(50) NOT NULL COMMENT 'Jambu Citra Grade Super/A/B/dsb',
    `weight_kg` DECIMAL(12, 2) NOT NULL DEFAULT 0.00 COMMENT 'Kuantitas terjual (Kg)',
    `price_per_kg` DECIMAL(15, 2) NOT NULL DEFAULT 0.00 COMMENT 'Harga jual per Kg',
    `subtotal` DECIMAL(15, 2) NOT NULL DEFAULT 0.00 COMMENT 'weight_kg * price_per_kg',
    `notes` VARCHAR(255) DEFAULT NULL,
    CONSTRAINT `fk_sale_items_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX `idx_sale_items_sale` (`sale_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 9. TABEL PEMBAYARAN PENJUALAN (Sale Payments)
-- (Pembayaran mencatat pelunasan piutang, bukan menambah pendapatan dua kali)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sale_payments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `sale_id` INT UNSIGNED NOT NULL,
    `payment_date` DATE NOT NULL,
    `amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    `payment_method` VARCHAR(50) NOT NULL DEFAULT 'Tunai' COMMENT 'Tunai, Transfer Bank, QRIS, dsb',
    `notes` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_payments_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX `idx_payments_sale` (`sale_id`),
    INDEX `idx_payments_date` (`payment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
