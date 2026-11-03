CREATE DATABASE IF NOT EXISTS `asset_management` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `asset_management`;

-- Drop existing tables in reverse dependency order for clean re-instantiation
DROP TABLE IF EXISTS `assets`;
DROP TABLE IF EXISTS `brands`;
DROP TABLE IF EXISTS `locations`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `users`;

-- ============================================================================
-- 1. Categories Table
-- ============================================================================
CREATE TABLE `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. Locations Table
-- ============================================================================
CREATE TABLE `locations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `address` VARCHAR(255) NULL,
    `description` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3. Brands Table
-- ============================================================================
CREATE TABLE `brands` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 4. Users Table (Authentication)
-- ============================================================================
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 5. Assets Table
-- ============================================================================
CREATE TABLE `assets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `asset_tag` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `category_id` INT NULL,
    `location_id` INT NULL,
    `brand_id` INT NULL,
    `serial_number` VARCHAR(100) NULL,
    `purchase_date` DATE NULL,
    `purchase_cost` DECIMAL(10,2) NULL,
    `status` ENUM('Available', 'In Use', 'Under Maintenance', 'Retired') NOT NULL DEFAULT 'Available',
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT `fk_assets_categories` 
        FOREIGN KEY (`category_id`) 
        REFERENCES `categories` (`id`) 
        ON DELETE SET NULL 
        ON UPDATE CASCADE,

    CONSTRAINT `fk_assets_locations` 
        FOREIGN KEY (`location_id`) 
        REFERENCES `locations` (`id`) 
        ON DELETE SET NULL 
        ON UPDATE CASCADE,

    CONSTRAINT `fk_assets_brands` 
        FOREIGN KEY (`brand_id`) 
        REFERENCES `brands` (`id`) 
        ON DELETE SET NULL 
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Seed Default Initial Data
-- ============================================================================

-- Categories Seed Data
INSERT INTO `categories` (`name`, `description`) VALUES
('Laptops & Workstations', 'Company-issued laptops, desktops, and workstations.'),
('Peripherals', 'Keyboards, mice, monitors, and docks.'),
('Mobile Devices', 'Smartphones and tablets.');

-- Locations Seed Data
INSERT INTO `locations` (`name`, `description`) VALUES
('Headquarters - Main Office', 'Primary corporate building'),
('Storage Closet B', 'IT equipment storage room'),
('Remote / Home Office', 'Assigned to remote employees');

-- Brands Seed Data
INSERT INTO `brands` (`name`, `description`) VALUES
('Apple', 'Laptops, phones, tablets, and accessories'),
('Dell', 'Monitors, servers, and PC workstations'),
('Logitech', 'Keyboards, mice, and conference gear');

-- Default User Seed (Username: admin | Password: Password123!)
INSERT INTO `users` (`username`, `password`) VALUES 
('admin', '$2y$10$w09ZqW8V6u5L1R3qKx.yee.N7pL4A0h3hR7bYpM.W5G6a3O2E4r2O');

-- Initial Assets Seed Data
INSERT INTO `assets` (`asset_tag`, `name`, `category_id`, `location_id`, `brand_id`, `serial_number`, `purchase_date`, `purchase_cost`, `status`, `notes`) VALUES
('AST-00001', 'MacBook Pro 16"', 1, 1, 1, 'C02G1234MD6R', '2023-01-15', 2499.00, 'In Use', 'Assigned to Lead Dev'),
('AST-00002', 'Dell UltraSharp 27" Monitor', 2, 2, 2, 'CN-098765-XYZ', '2023-02-10', 450.50, 'Available', 'Located in Storage Closet B'),
('AST-00003', 'iPad Pro 11"', 3, 3, 1, 'DMP987654321', '2023-05-20', 899.99, 'Under Maintenance', 'Screen replacement in progress');
