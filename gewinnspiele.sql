CREATE DATABASE IF NOT EXISTS gewinnspiele_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE gewinnspiele_db;

CREATE TABLE IF NOT EXISTS gewinnspiele (
    id INT AUTO_INCREMENT PRIMARY KEY,
    link_zur_webseite VARCHAR(500) NOT NULL,
    beschreibung TEXT NULL,
    status ENUM('Active','Expired') NOT NULL DEFAULT 'Active',
    endet_am DATE NULL,
    UNIQUE KEY unique_link (link_zur_webseite)
);
