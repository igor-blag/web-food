-- migrate.sql: обновление существующей базы данных
-- Запустить один раз при обновлении с версии без поддержки типов школ

SET NAMES utf8mb4;

-- 1. menu_templates: убрать UNIQUE(day_number), добавить school_type и is_boarding
ALTER TABLE `menu_templates`
    DROP INDEX `day_number`,
    ADD COLUMN `school_type` ENUM('sm','main','ss') NOT NULL DEFAULT 'sm' AFTER `day_number`,
    ADD COLUMN `is_boarding` TINYINT(1) NOT NULL DEFAULT 0 AFTER `school_type`,
    ADD UNIQUE KEY `uq_day_type` (`day_number`, `school_type`);

-- 2. menu_items: расширить ENUM приёмов пищи
ALTER TABLE `menu_items`
    MODIFY `meal_type` ENUM('breakfast','breakfast2','lunch','afternoon_snack','dinner','dinner2') NOT NULL;

-- 3. calendar: добавить school_type и изменить PRIMARY KEY
ALTER TABLE `calendar`
    DROP PRIMARY KEY,
    ADD COLUMN `school_type` ENUM('sm','main','ss') NOT NULL DEFAULT 'sm' AFTER `date`,
    ADD PRIMARY KEY (`date`, `school_type`);

-- 4. calendar: добавить is_cycle_start
ALTER TABLE `calendar`
    ADD COLUMN `is_cycle_start` TINYINT(1) NOT NULL DEFAULT 0;

-- 5. users: добавить email и email_verified
ALTER TABLE `users`
    ADD COLUMN `email`          VARCHAR(255) DEFAULT NULL UNIQUE AFTER `password_hash`,
    ADD COLUMN `email_verified` TINYINT(1)  NOT NULL DEFAULT 0  AFTER `email`;

-- 6. Таблица одноразовых кодов (setup / reset)
CREATE TABLE IF NOT EXISTS `email_tokens` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT UNSIGNED DEFAULT NULL,
    `purpose`    ENUM('setup','reset') NOT NULL,
    `code`       CHAR(6)      NOT NULL,
    `email`      VARCHAR(255) NOT NULL,
    `expires_at` DATETIME     NOT NULL,
    `used`       TINYINT(1)  NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP   DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Добавить шаблоны для main и ss (существующие 14 остаются для sm)
INSERT IGNORE INTO `menu_templates` (`day_number`, `school_type`, `label`) VALUES
(1,'main','День 1'),(2,'main','День 2'),(3,'main','День 3'),(4,'main','День 4'),
(5,'main','День 5'),(6,'main','День 6'),(7,'main','День 7'),(8,'main','День 8'),
(9,'main','День 9'),(10,'main','День 10'),(11,'main','День 11'),(12,'main','День 12'),
(13,'main','День 13'),(14,'main','День 14'),
(1,'ss','День 1'),(2,'ss','День 2'),(3,'ss','День 3'),(4,'ss','День 4'),
(5,'ss','День 5'),(6,'ss','День 6'),(7,'ss','День 7'),(8,'ss','День 8'),
(9,'ss','День 9'),(10,'ss','День 10'),(11,'ss','День 11'),(12,'ss','День 12'),
(13,'ss','День 13'),(14,'ss','День 14');
