-- Upgrade for sites that already installed the first version.
-- Run this ONCE in phpMyAdmin (SQL tab) on your existing database, then upload the new files.
-- It switches swimmers from long login codes to 4-digit PINs and adds the kiosk table.
-- Afterwards, open Team and click "Create PINs for swimmers who need one".

SET NAMES utf8mb4;

ALTER TABLE swimmers
  ADD COLUMN pin_hash VARCHAR(255) NULL AFTER last_name,
  ADD COLUMN failed_pins TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER pin_hash,
  ADD COLUMN locked_until DATETIME NULL AFTER failed_pins;

ALTER TABLE swimmers DROP INDEX uq_swimmer_code;
ALTER TABLE swimmers DROP COLUMN code_hash;

CREATE TABLE kiosk_devices (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  selector CHAR(18) NOT NULL,
  validator_hash CHAR(64) NOT NULL,
  label VARCHAR(60) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_kiosk_selector (selector)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
