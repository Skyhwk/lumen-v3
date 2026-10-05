SET @promo_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'request_qr')
    AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'request_qr' AND COLUMN_NAME = 'promo_id'),
    'ALTER TABLE `request_qr` ADD COLUMN `promo_id` BIGINT UNSIGNED NULL',
    'SELECT ''request_qr: dilewati'' AS hasil'
);
PREPARE promo_statement FROM @promo_sql;
EXECUTE promo_statement;
DEALLOCATE PREPARE promo_statement;
