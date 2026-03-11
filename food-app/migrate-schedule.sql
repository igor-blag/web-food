-- Миграция: учебный график (каникулы, учебный год)
-- Выполнить после основной schema.sql
-- Совместимо с MySQL 8.0+

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `vacations` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `academic_year` VARCHAR(9)   NOT NULL COMMENT 'напр. 2025-2026',
    `label`         VARCHAR(100) NOT NULL COMMENT 'напр. Осенние каникулы',
    `date_from`     DATE         NOT NULL,
    `date_to`       DATE         NOT NULL,
    `created_at`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_year`  (`academic_year`),
    INDEX `idx_dates` (`date_from`, `date_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Добавить колонки в kitchen_settings (MySQL 8.0 не поддерживает IF NOT EXISTS для ADD COLUMN)
-- Выполнять вручную или через PHP-скрипт, если колонки ещё не существуют:
-- ALTER TABLE `kitchen_settings` ADD COLUMN `academic_year_start` VARCHAR(5) NOT NULL DEFAULT '09-01';
-- ALTER TABLE `kitchen_settings` ADD COLUMN `academic_year_end` VARCHAR(5) NOT NULL DEFAULT '05-31';
-- ALTER TABLE `kitchen_settings` ADD COLUMN `reset_cycle_after_vacation` TINYINT(1) NOT NULL DEFAULT 0;
-- ALTER TABLE `departments` ADD COLUMN `ignore_vacations` TINYINT(1) NOT NULL DEFAULT 0;
-- UPDATE `departments` SET `ignore_vacations` = 1 WHERE `code` = 'preschool';
