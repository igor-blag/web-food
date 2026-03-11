-- Миграция: Настройки пищеблока + динамические отделения
-- Выполнить один раз на существующей БД

SET NAMES utf8mb4;

-- 1. Расширяем school_type с ENUM до VARCHAR (данные сохраняются)
ALTER TABLE `menu_templates` MODIFY `school_type` VARCHAR(20) NOT NULL DEFAULT 'sm';
ALTER TABLE `calendar`       MODIFY `school_type` VARCHAR(20) NOT NULL DEFAULT 'sm';

-- 2. Глобальные настройки пищеблока (одна строка)
CREATE TABLE IF NOT EXISTS `kitchen_settings` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `org_name`    VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Название образовательной организации',
    `updated_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `kitchen_settings` (`id`, `org_name`) VALUES (1, '');

-- 3. Отделения
CREATE TABLE IF NOT EXISTS `departments` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code`          VARCHAR(20)  NOT NULL UNIQUE COMMENT 'Код отделения = school_type в шаблонах/календаре',
    `label`         VARCHAR(100) NOT NULL COMMENT 'Полное название для страницы Шаблоны',
    `label_short`   VARCHAR(30)  NOT NULL DEFAULT '' COMMENT 'Краткое название для вкладок календаря',
    `dept_name`     VARCHAR(100) NOT NULL DEFAULT '' COMMENT 'Отд./корпус для Excel',
    `is_enabled`    TINYINT(1)   NOT NULL DEFAULT 0,
    `is_builtin`    TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '1=встроенное, 0=кастомное',
    `is_boarding`   TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Интернат (доп. приёмы пищи)',
    `workdays`      VARCHAR(20)  NOT NULL DEFAULT '1,2,3,4,5' COMMENT 'CSV ISO дней недели 1-7',
    `publish_xlsx`  TINYINT(1)   NOT NULL DEFAULT 1 COMMENT 'Публиковать ежедневные xlsx',
    `file_suffix`   VARCHAR(20)  NOT NULL DEFAULT '' COMMENT 'Постфикс файла, напр. -sm',
    `sort_order`    SMALLINT     NOT NULL DEFAULT 0,
    `note`          VARCHAR(255) DEFAULT NULL COMMENT 'Подсказка в UI настроек'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `departments`
    (`code`, `label`, `label_short`, `is_enabled`, `is_builtin`, `is_boarding`, `workdays`, `publish_xlsx`, `file_suffix`, `sort_order`, `note`)
VALUES
    ('preschool', 'Дошкольное отделение', 'Дошк.',   0, 1, 1, '1,2,3,4,5', 0, '-preschool', 10, 'Интернатный режим включён всегда'),
    ('sm',        'Начальная школа',      'Нач.',    1, 1, 0, '1,2,3,4,5', 1, '-sm',        20, NULL),
    ('main',      'Основная школа',       'Стар-ки', 1, 1, 0, '1,2,3,4,5', 1, '',           30, NULL),
    ('ss',        'Старшая школа',        'Стар.',   0, 1, 0, '1,2,3,4,5', 1, '-ss',        40, 'Включайте только если меню отличается от основной школы');
