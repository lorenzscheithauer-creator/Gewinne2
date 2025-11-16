CREATE DATABASE IF NOT EXISTS gewinnspiele_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE gewinnspiele_db;

CREATE TABLE IF NOT EXISTS gewinnspiele (
    id INT AUTO_INCREMENT PRIMARY KEY,
    link_zur_webseite VARCHAR(500) NOT NULL,
    beschreibung TEXT NULL
);

-- UNIQUE-Index, damit kein Link doppelt eingefügt wird
SET @unique_index_exists := (
    SELECT COUNT(1)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'gewinnspiele'
      AND index_name = 'unique_link'
);

SET @ddl_statement := IF(
    @unique_index_exists = 0,
    'ALTER TABLE gewinnspiele ADD UNIQUE KEY unique_link (link_zur_webseite);',
    'SELECT 1'
);

PREPARE stmt FROM @ddl_statement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
