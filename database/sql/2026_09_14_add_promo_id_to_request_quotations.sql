-- Jalankan pada database quotation yang dituju. Aman dijalankan ulang.

SET @promo_table_exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'request_quotation');
SET @promo_column_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'request_quotation' AND COLUMN_NAME = 'promo_id');
SET @promo_after = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'request_quotation' AND COLUMN_NAME = 'kode_promo'), ' AFTER `kode_promo`', '');
SET @promo_sql = IF(@promo_table_exists > 0 AND @promo_column_exists = 0, CONCAT('ALTER TABLE `request_quotation` ADD COLUMN `promo_id` BIGINT UNSIGNED NULL', @promo_after), 'SELECT \'request_quotation: dilewati\' AS hasil');
PREPARE promo_statement FROM @promo_sql;
EXECUTE promo_statement;
DEALLOCATE PREPARE promo_statement;

SET @promo_table_exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'request_quotation_kontrak_H');
SET @promo_column_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'request_quotation_kontrak_H' AND COLUMN_NAME = 'promo_id');
SET @promo_after = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'request_quotation_kontrak_H' AND COLUMN_NAME = 'kode_promo'), ' AFTER `kode_promo`', '');
SET @promo_sql = IF(@promo_table_exists > 0 AND @promo_column_exists = 0, CONCAT('ALTER TABLE `request_quotation_kontrak_H` ADD COLUMN `promo_id` BIGINT UNSIGNED NULL', @promo_after), 'SELECT \'request_quotation_kontrak_H: dilewati\' AS hasil');
PREPARE promo_statement FROM @promo_sql;
EXECUTE promo_statement;
DEALLOCATE PREPARE promo_statement;

SET @promo_table_exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'request_quotation_kontrak_D');
SET @promo_column_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'request_quotation_kontrak_D' AND COLUMN_NAME = 'promo_id');
SET @promo_after = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'request_quotation_kontrak_D' AND COLUMN_NAME = 'kode_promo'), ' AFTER `kode_promo`', '');
SET @promo_sql = IF(@promo_table_exists > 0 AND @promo_column_exists = 0, CONCAT('ALTER TABLE `request_quotation_kontrak_D` ADD COLUMN `promo_id` BIGINT UNSIGNED NULL', @promo_after), 'SELECT \'request_quotation_kontrak_D: dilewati\' AS hasil');
PREPARE promo_statement FROM @promo_sql;
EXECUTE promo_statement;
DEALLOCATE PREPARE promo_statement;
