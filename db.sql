-- Campus Lost and Found Database Schema
-- Compatible with MySQL 8.0+ and TiDB Cloud

CREATE DATABASE IF NOT EXISTS lost_and_found_db;
USE lost_and_found_db;

-- 1. Users Table
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` INT AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(100) NOT NULL,
  `student_id` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `department` VARCHAR(100) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` VARCHAR(20) DEFAULT 'user',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Admins Table
CREATE TABLE IF NOT EXISTS `admins` (
  `admin_id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default Admin Account (username: admin, password: password123)
-- The application's verifyPasswordAndUpgrade() will automatically upgrade it to secure bcrypt on first login
INSERT INTO `admins` (`username`, `password`) 
VALUES ('admin', 'password123')
ON DUPLICATE KEY UPDATE `username` = `username`;

-- 3. Lost Items Table
CREATE TABLE IF NOT EXISTS `lost_items` (
  `lost_id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `item_name` VARCHAR(100) NOT NULL,
  `category` VARCHAR(50) NOT NULL,
  `lost_date` DATE NOT NULL,
  `lost_location` VARCHAR(255) NOT NULL,
  `contact_information` VARCHAR(255) NOT NULL,
  `secret_information` TEXT,
  `description` TEXT,
  `item_image` MEDIUMTEXT DEFAULT NULL,
  `status` ENUM('Pending', 'Approved', 'Rejected', 'Returned') DEFAULT 'Pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`user_id`),
  INDEX (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Found Items Table
CREATE TABLE IF NOT EXISTS `found_items` (
  `found_id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `item_name` VARCHAR(100) NOT NULL,
  `category` VARCHAR(50) NOT NULL,
  `found_date` DATE NOT NULL,
  `found_location` VARCHAR(255) NOT NULL,
  `finder_contact_information` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `item_image` MEDIUMTEXT DEFAULT NULL,
  `status` ENUM('Pending', 'Approved', 'Rejected', 'Returned') DEFAULT 'Pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`user_id`),
  INDEX (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Matches Table
CREATE TABLE IF NOT EXISTS `matches` (
  `match_id` INT AUTO_INCREMENT PRIMARY KEY,
  `lost_id` INT NOT NULL,
  `found_id` INT NOT NULL,
  `item_name_score` INT DEFAULT 0,
  `description_score` INT DEFAULT 0,
  `category_score` INT DEFAULT 0,
  `location_score` INT DEFAULT 0,
  `date_score` INT DEFAULT 0,
  `match_percentage` DECIMAL(5,2) DEFAULT 0.00,
  `status` VARCHAR(50) DEFAULT 'Pending',
  `claim_status` VARCHAR(50) DEFAULT 'None',
  `finder_status` VARCHAR(50) DEFAULT 'Pending',
  `owner_status` VARCHAR(50) DEFAULT 'Pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`lost_id`),
  INDEX (`found_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Notifications Table
CREATE TABLE IF NOT EXISTS `notifications` (
  `notification_id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `match_id` INT DEFAULT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `receiver` VARCHAR(50) DEFAULT 'user',
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`user_id`),
  INDEX (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
