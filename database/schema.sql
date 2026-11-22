-- Create database
CREATE DATABASE IF NOT EXISTS mystudy_ng CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE mystudy_ng;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    user_type ENUM('secondary', 'university', 'admin') NOT NULL,
    profile_picture VARCHAR(255) DEFAULT NULL,
    university VARCHAR(100) DEFAULT NULL,
    department VARCHAR(100) DEFAULT NULL,
    level VARCHAR(50) DEFAULT NULL,
    email_verified BOOLEAN DEFAULT FALSE,
    verification_token VARCHAR(100) DEFAULT NULL,
    reset_token VARCHAR(100) DEFAULT NULL,
    reset_token_expires DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login DATETIME DEFAULT NULL,
    last_active DATETIME DEFAULT NULL,
    status ENUM('active', 'suspended', 'banned') DEFAULT 'active',
    report_strikes INT DEFAULT 0,
    is_frozen TINYINT(1) DEFAULT 0,
    INDEX idx_email (email),
    INDEX idx_user_type (user_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User sessions table
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_token VARCHAR(100) NOT NULL,
    user_agent TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_session_token (session_token),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- No admin row is seeded here.
--
-- This file used to create an admin account whose password was a well-known
-- default. That is a real login, not a placeholder, and this repository is
-- the one the mobile app's backend is deployed from - so the row is gone.
--
-- The administrator account is created by the person who owns the site, with
-- a password only they choose, and it is never written down in source.
