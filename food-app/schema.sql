-- Школьное меню: схема базы данных
-- Выполнить один раз при установке

SET NAMES utf8mb4;
SET time_zone = '+03:00';

CREATE TABLE IF NOT EXISTS `users` (
    `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username`       VARCHAR(50)  NOT NULL UNIQUE,
    `password_hash`  VARCHAR(255) NOT NULL,
    `email`          VARCHAR(255) DEFAULT NULL UNIQUE,
    `email_verified` TINYINT(1)  NOT NULL DEFAULT 0,
    `created_at`     TIMESTAMP   DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `email_tokens` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT UNSIGNED DEFAULT NULL COMMENT 'NULL для setup-токена',
    `purpose`    ENUM('setup','reset') NOT NULL,
    `code`       CHAR(6)      NOT NULL,
    `email`      VARCHAR(255) NOT NULL,
    `expires_at` DATETIME     NOT NULL,
    `used`       TINYINT(1)  NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP   DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `menu_templates` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `day_number`  TINYINT UNSIGNED NOT NULL COMMENT '1–14',
    `school_type` VARCHAR(20) NOT NULL DEFAULT 'sm',
    `is_boarding` TINYINT(1) NOT NULL DEFAULT 0,
    `label`       VARCHAR(100) NOT NULL,
    UNIQUE KEY `uq_day_type` (`day_number`, `school_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `menu_items` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `template_id` INT UNSIGNED NOT NULL,
    `meal_type`   ENUM('breakfast','breakfast2','lunch','afternoon_snack','dinner','dinner2') NOT NULL,
    `section`     VARCHAR(50)   DEFAULT NULL COMMENT 'гор.блюдо, хлеб и т.д.',
    `recipe_num`  VARCHAR(30)   DEFAULT NULL,
    `dish_name`   VARCHAR(255)  DEFAULT NULL,
    `grams`       DECIMAL(8,1)  DEFAULT NULL,
    `price`       DECIMAL(8,2)  DEFAULT NULL,
    `kcal`        DECIMAL(8,2)  DEFAULT NULL,
    `protein`     DECIMAL(8,2)  DEFAULT NULL,
    `fat`         DECIMAL(8,2)  DEFAULT NULL,
    `carbs`       DECIMAL(8,2)  DEFAULT NULL,
    `sort_order`  SMALLINT      DEFAULT 0,
    FOREIGN KEY (`template_id`) REFERENCES `menu_templates`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `calendar` (
    `date`           DATE         NOT NULL,
    `school_type`    VARCHAR(20) NOT NULL DEFAULT 'sm',
    `template_id`    INT UNSIGNED DEFAULT NULL COMMENT 'NULL = выходной/каникулы',
    `school`         VARCHAR(100) DEFAULT NULL COMMENT 'Название школы',
    `dept`           VARCHAR(50)  DEFAULT NULL COMMENT 'Отделение/корпус',
    `is_cycle_start` TINYINT(1)   NOT NULL DEFAULT 0,
    PRIMARY KEY (`date`, `school_type`),
    FOREIGN KEY (`template_id`) REFERENCES `menu_templates`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 14 шаблонов × 3 типа школ = 42 шаблона
INSERT IGNORE INTO `menu_templates` (`day_number`, `school_type`, `label`) VALUES
(1,'sm','День 1'),(2,'sm','День 2'),(3,'sm','День 3'),(4,'sm','День 4'),
(5,'sm','День 5'),(6,'sm','День 6'),(7,'sm','День 7'),(8,'sm','День 8'),
(9,'sm','День 9'),(10,'sm','День 10'),(11,'sm','День 11'),(12,'sm','День 12'),
(13,'sm','День 13'),(14,'sm','День 14'),
(1,'main','День 1'),(2,'main','День 2'),(3,'main','День 3'),(4,'main','День 4'),
(5,'main','День 5'),(6,'main','День 6'),(7,'main','День 7'),(8,'main','День 8'),
(9,'main','День 9'),(10,'main','День 10'),(11,'main','День 11'),(12,'main','День 12'),
(13,'main','День 13'),(14,'main','День 14'),
(1,'ss','День 1'),(2,'ss','День 2'),(3,'ss','День 3'),(4,'ss','День 4'),
(5,'ss','День 5'),(6,'ss','День 6'),(7,'ss','День 7'),(8,'ss','День 8'),
(9,'ss','День 9'),(10,'ss','День 10'),(11,'ss','День 11'),(12,'ss','День 12'),
(13,'ss','День 13'),(14,'ss','День 14');

-- ─── Настройки пищеблока ─────────────────────────────────────

CREATE TABLE IF NOT EXISTS `kitchen_settings` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `org_name`    VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Название образовательной организации',
    `academic_year_start` VARCHAR(5) NOT NULL DEFAULT '09-01' COMMENT 'MM-DD начало учебного года',
    `academic_year_end`   VARCHAR(5) NOT NULL DEFAULT '05-31' COMMENT 'MM-DD конец учебного года',
    `reset_cycle_after_vacation` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Сбрасывать цикл меню после каникул',
    `updated_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `kitchen_settings` (`id`, `org_name`) VALUES (1, '');

CREATE TABLE IF NOT EXISTS `departments` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code`          VARCHAR(20)  NOT NULL UNIQUE COMMENT 'Код отделения = school_type в шаблонах/календаре',
    `label`         VARCHAR(100) NOT NULL COMMENT 'Полное название',
    `label_short`   VARCHAR(30)  NOT NULL DEFAULT '' COMMENT 'Краткое название для вкладок',
    `dept_name`     VARCHAR(100) NOT NULL DEFAULT '' COMMENT 'Отд./корпус для Excel',
    `is_enabled`    TINYINT(1)   NOT NULL DEFAULT 0,
    `is_builtin`    TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '1=встроенное, 0=кастомное',
    `is_boarding`   TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Интернат (доп. приёмы пищи)',
    `workdays`      VARCHAR(20)  NOT NULL DEFAULT '1,2,3,4,5' COMMENT 'CSV ISO дней недели 1-7',
    `publish_xlsx`  TINYINT(1)   NOT NULL DEFAULT 1 COMMENT 'Публиковать ежедневные xlsx',
    `file_suffix`   VARCHAR(20)  NOT NULL DEFAULT '' COMMENT 'Постфикс файла, напр. -sm',
    `sort_order`    SMALLINT     NOT NULL DEFAULT 0,
    `note`          VARCHAR(255) DEFAULT NULL COMMENT 'Подсказка в UI настроек',
    `ignore_vacations` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = работает круглый год'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `departments`
    (`code`, `label`, `label_short`, `is_enabled`, `is_builtin`, `is_boarding`, `workdays`, `publish_xlsx`, `file_suffix`, `sort_order`, `note`, `ignore_vacations`)
VALUES
    ('preschool', 'Дошкольное отделение', 'Дошк.',   0, 1, 1, '1,2,3,4,5', 0, '-preschool', 10, 'Интернатный режим включён всегда', 1),
    ('sm',        'Начальная школа',      'Нач.',    1, 1, 0, '1,2,3,4,5', 1, '-sm',        20, NULL, 0),
    ('main',      'Основная школа',       'Стар-ки', 1, 1, 0, '1,2,3,4,5', 1, '',           30, NULL, 0),
    ('ss',        'Старшая школа',        'Стар.',   0, 1, 0, '1,2,3,4,5', 1, '-ss',        40, 'Включайте только если меню отличается от основной школы', 0);

-- ─── Каникулы / учебный график ───────────────────────────────

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

-- Первый администратор создаётся через setup.php
