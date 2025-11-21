-- ============================================================================
-- СТРУКТУРА БАЗЫ ДАННЫХ ДЛЯ WORDPRESS
-- Многоязычный сайт с каталогом товаров/услуг
-- ============================================================================

-- Отключаем проверку внешних ключей при создании таблиц
-- SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- 1. ЯЗЫКИ И ЛОКАЛИЗАЦИЯ
-- ============================================================================

-- Таблица языков
CREATE TABLE IF NOT EXISTS `wp_languages` (
  `language_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(10) NOT NULL COMMENT 'Код языка (ru, en, uk)',
  `name` VARCHAR(100) NOT NULL COMMENT 'Название языка',
  `native_name` VARCHAR(100) NOT NULL COMMENT 'Название на родном языке',
  `is_default` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Язык по умолчанию',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Активен ли язык',
  `sort_order` INT NOT NULL DEFAULT 0 COMMENT 'Порядок сортировки',
  `flag_icon` VARCHAR(255) DEFAULT NULL COMMENT 'Путь к иконке флага',
  `locale` VARCHAR(20) DEFAULT NULL COMMENT 'Локаль (ru_RU, en_US)',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`language_id`),
  UNIQUE KEY `code` (`code`),
  KEY `is_active` (`is_active`),
  KEY `is_default` (`is_default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. ПОЛЬЗОВАТЕЛИ
-- ============================================================================

-- Расширенная таблица пользователей (дополняет стандартную wp_users)
CREATE TABLE IF NOT EXISTS `wp_users_extended` (
  `user_extended_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL COMMENT 'ID из стандартной таблицы wp_users',
  `phone` VARCHAR(20) DEFAULT NULL COMMENT 'Телефон',
  `email` VARCHAR(20) DEFAULT NULL COMMENT 'Email'
  `avatar_url` VARCHAR(500) DEFAULT NULL COMMENT 'URL аватара',
  `preferred_language` INT UNSIGNED DEFAULT NULL COMMENT 'Предпочитаемый язык',
  `timezone` VARCHAR(50) DEFAULT 'UTC' COMMENT 'Часовой пояс',
  `receive_notifications` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Получать уведомления',
  `last_activity` DATETIME DEFAULT NULL COMMENT 'Последняя активность',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_extended_id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `preferred_language` (`preferred_language`),
  KEY `last_activity` (`last_activity`),
  CONSTRAINT `fk_users_extended_language` FOREIGN KEY (`preferred_language`) 
    REFERENCES `wp_languages` (`language_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3. КАТЕГОРИИ
-- ============================================================================

-- Основная таблица категорий
CREATE TABLE IF NOT EXISTS `wp_categories` (
  `category_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Родительская категория',
  `slug` VARCHAR(200) NOT NULL COMMENT 'URL-friendly название',
  `image_url` VARCHAR(500) DEFAULT NULL COMMENT 'Изображение категории',
  `icon_class` VARCHAR(100) DEFAULT NULL COMMENT 'CSS класс иконки',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Активна ли категория',
  `sort_order` INT NOT NULL DEFAULT 0 COMMENT 'Порядок сортировки',
  `level` INT NOT NULL DEFAULT 0 COMMENT 'Уровень вложенности',
  `path` VARCHAR(500) DEFAULT NULL COMMENT 'Путь в иерархии (1/2/3)',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `parent_id` (`parent_id`),
  KEY `is_active` (`is_active`),
  KEY `sort_order` (`sort_order`),
  KEY `level` (`level`),
  CONSTRAINT `fk_categories_parent` FOREIGN KEY (`parent_id`) 
    REFERENCES `wp_categories` (`category_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Переводы категорий
CREATE TABLE IF NOT EXISTS `wp_categories_i18n` (
  `category_i18n_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` BIGINT UNSIGNED NOT NULL,
  `language_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL COMMENT 'Название категории',
  `description` TEXT DEFAULT NULL COMMENT 'Описание категории',
  `meta_title` VARCHAR(255) DEFAULT NULL COMMENT 'SEO заголовок',
  `meta_description` TEXT DEFAULT NULL COMMENT 'SEO описание',
  `meta_keywords` TEXT DEFAULT NULL COMMENT 'SEO ключевые слова',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`category_i18n_id`),
  UNIQUE KEY `category_language` (`category_id`, `language_id`),
  KEY `language_id` (`language_id`),
  CONSTRAINT `fk_categories_i18n_category` FOREIGN KEY (`category_id`) 
    REFERENCES `wp_categories` (`category_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_categories_i18n_language` FOREIGN KEY (`language_id`) 
    REFERENCES `wp_languages` (`language_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 4. ТОВАРЫ/УСЛУГИ
-- ============================================================================

-- Основная таблица товаров/услуг
CREATE TABLE IF NOT EXISTS `wp_products` (
  `product_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sku` VARCHAR(100) DEFAULT NULL COMMENT 'Артикул',
  `slug` VARCHAR(200) NOT NULL COMMENT 'URL-friendly название',
  `type` ENUM('product', 'service', 'digital', 'subscription') NOT NULL DEFAULT 'product',
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Цена',
  `old_price` DECIMAL(10,2) DEFAULT NULL COMMENT 'Старая цена для скидок',
  `currency` VARCHAR(3) NOT NULL DEFAULT 'USD' COMMENT 'Валюта',
  `stock_quantity` INT DEFAULT 0 COMMENT 'Количество на складе',
  `is_in_stock` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'В наличии',
  `featured_image` VARCHAR(500) DEFAULT NULL COMMENT 'Главное изображение',
  `gallery_images` TEXT DEFAULT NULL COMMENT 'JSON массив изображений галереи',
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Рекомендуемый товар',
  `is_new` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Новинка',
  `is_sale` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Акция',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Активен',
  `views_count` INT NOT NULL DEFAULT 0 COMMENT 'Количество просмотров',
  `rating` DECIMAL(3,2) DEFAULT 0.00 COMMENT 'Средний рейтинг (0-5)',
  `reviews_count` INT NOT NULL DEFAULT 0 COMMENT 'Количество отзывов',
  `sort_order` INT NOT NULL DEFAULT 0 COMMENT 'Порядок сортировки',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`product_id`),
  UNIQUE KEY `slug` (`slug`),
  UNIQUE KEY `sku` (`sku`),
  KEY `type` (`type`),
  KEY `price` (`price`),
  KEY `is_active` (`is_active`),
  KEY `is_featured` (`is_featured`),
  KEY `rating` (`rating`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Переводы товаров/услуг
CREATE TABLE IF NOT EXISTS `wp_products_i18n` (
  `product_i18n_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `language_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL COMMENT 'Название товара',
  `short_description` TEXT DEFAULT NULL COMMENT 'Краткое описание',
  `description` LONGTEXT DEFAULT NULL COMMENT 'Полное описание',
  `specifications` TEXT DEFAULT NULL COMMENT 'Характеристики (JSON)',
  `meta_title` VARCHAR(255) DEFAULT NULL COMMENT 'SEO заголовок',
  `meta_description` TEXT DEFAULT NULL COMMENT 'SEO описание',
  `meta_keywords` TEXT DEFAULT NULL COMMENT 'SEO ключевые слова',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`product_i18n_id`),
  UNIQUE KEY `product_language` (`product_id`, `language_id`),
  KEY `language_id` (`language_id`),
  FULLTEXT KEY `search_content` (`name`, `short_description`, `description`),
  CONSTRAINT `fk_products_i18n_product` FOREIGN KEY (`product_id`) 
    REFERENCES `wp_products` (`product_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_products_i18n_language` FOREIGN KEY (`language_id`) 
    REFERENCES `wp_languages` (`language_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Связь товаров с категориями (Many-to-Many)
CREATE TABLE IF NOT EXISTS `wp_product_categories` (
  `product_category_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `category_id` BIGINT UNSIGNED NOT NULL,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Основная категория',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`product_category_id`),
  UNIQUE KEY `product_category` (`product_id`, `category_id`),
  KEY `category_id` (`category_id`),
  KEY `is_primary` (`is_primary`),
  CONSTRAINT `fk_product_categories_product` FOREIGN KEY (`product_id`) 
    REFERENCES `wp_products` (`product_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_product_categories_category` FOREIGN KEY (`category_id`) 
    REFERENCES `wp_categories` (`category_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 5. АТРИБУТЫ И ВАРИАЦИИ
-- ============================================================================

-- Таблица атрибутов (цвет, размер и т.д.)
CREATE TABLE IF NOT EXISTS `wp_attributes` (
  `attribute_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(100) NOT NULL COMMENT 'Идентификатор атрибута',
  `type` ENUM('select', 'color', 'text', 'number') NOT NULL DEFAULT 'select',
  `is_filterable` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Использовать в фильтрах',
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`attribute_id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Переводы атрибутов
CREATE TABLE IF NOT EXISTS `wp_attributes_i18n` (
  `attribute_i18n_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attribute_id` BIGINT UNSIGNED NOT NULL,
  `language_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL COMMENT 'Название атрибута',
  PRIMARY KEY (`attribute_i18n_id`),
  UNIQUE KEY `attribute_language` (`attribute_id`, `language_id`),
  KEY `language_id` (`language_id`),
  CONSTRAINT `fk_attributes_i18n_attribute` FOREIGN KEY (`attribute_id`) 
    REFERENCES `wp_attributes` (`attribute_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_attributes_i18n_language` FOREIGN KEY (`language_id`) 
    REFERENCES `wp_languages` (`language_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Значения атрибутов
CREATE TABLE IF NOT EXISTS `wp_attribute_values` (
  `attribute_value_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attribute_id` BIGINT UNSIGNED NOT NULL,
  `value` VARCHAR(255) NOT NULL COMMENT 'Значение (для color - hex код)',
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`attribute_value_id`),
  KEY `attribute_id` (`attribute_id`),
  CONSTRAINT `fk_attribute_values_attribute` FOREIGN KEY (`attribute_id`) 
    REFERENCES `wp_attributes` (`attribute_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Переводы значений атрибутов
CREATE TABLE IF NOT EXISTS `wp_attribute_values_i18n` (
  `attribute_value_i18n_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attribute_value_id` BIGINT UNSIGNED NOT NULL,
  `language_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL COMMENT 'Название значения',
  PRIMARY KEY (`attribute_value_i18n_id`),
  UNIQUE KEY `attribute_value_language` (`attribute_value_id`, `language_id`),
  KEY `language_id` (`language_id`),
  CONSTRAINT `fk_attribute_values_i18n_value` FOREIGN KEY (`attribute_value_id`) 
    REFERENCES `wp_attribute_values` (`attribute_value_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_attribute_values_i18n_language` FOREIGN KEY (`language_id`) 
    REFERENCES `wp_languages` (`language_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Связь товаров с атрибутами
CREATE TABLE IF NOT EXISTS `wp_product_attributes` (
  `product_attribute_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `attribute_id` BIGINT UNSIGNED NOT NULL,
  `attribute_value_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`product_attribute_id`),
  UNIQUE KEY `product_attribute_value` (`product_id`, `attribute_id`, `attribute_value_id`),
  KEY `attribute_id` (`attribute_id`),
  KEY `attribute_value_id` (`attribute_value_id`),
  CONSTRAINT `fk_product_attributes_product` FOREIGN KEY (`product_id`) 
    REFERENCES `wp_products` (`product_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_product_attributes_attribute` FOREIGN KEY (`attribute_id`) 
    REFERENCES `wp_attributes` (`attribute_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_product_attributes_value` FOREIGN KEY (`attribute_value_id`) 
    REFERENCES `wp_attribute_values` (`attribute_value_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 6. ОТЗЫВЫ И РЕЙТИНГИ
-- ============================================================================

-- Таблица отзывов
CREATE TABLE IF NOT EXISTS `wp_reviews` (
  `review_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'ID пользователя (NULL для гостей)',
  `author_name` VARCHAR(255) NOT NULL COMMENT 'Имя автора',
  `author_email` VARCHAR(255) NOT NULL COMMENT 'Email автора',
  `rating` TINYINT UNSIGNED NOT NULL COMMENT 'Оценка 1-5',
  `title` VARCHAR(255) DEFAULT NULL COMMENT 'Заголовок отзыва',
  `content` TEXT NOT NULL COMMENT 'Текст отзыва',
  `pros` TEXT DEFAULT NULL COMMENT 'Достоинства',
  `cons` TEXT DEFAULT NULL COMMENT 'Недостатки',
  `is_verified_purchase` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Подтвержденная покупка',
  `is_approved` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Одобрен модератором',
  `helpful_count` INT NOT NULL DEFAULT 0 COMMENT 'Количество "полезно"',
  `not_helpful_count` INT NOT NULL DEFAULT 0 COMMENT 'Количество "не полезно"',
  `language_id` INT UNSIGNED NOT NULL COMMENT 'Язык отзыва',
  `ip_address` VARCHAR(45) DEFAULT NULL COMMENT 'IP адрес',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`review_id`),
  KEY `product_id` (`product_id`),
  KEY `user_id` (`user_id`),
  KEY `rating` (`rating`),
  KEY `is_approved` (`is_approved`),
  KEY `language_id` (`language_id`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `fk_reviews_product` FOREIGN KEY (`product_id`) 
    REFERENCES `wp_products` (`product_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reviews_language` FOREIGN KEY (`language_id`) 
    REFERENCES `wp_languages` (`language_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица полезности отзывов (кто отметил отзыв полезным)
CREATE TABLE IF NOT EXISTS `wp_review_helpfulness` (
  `helpfulness_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `review_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'ID пользователя',
  `is_helpful` TINYINT(1) NOT NULL COMMENT '1 - полезно, 0 - не полезно',
  `ip_address` VARCHAR(45) DEFAULT NULL COMMENT 'IP адрес для гостей',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`helpfulness_id`),
  UNIQUE KEY `review_user` (`review_id`, `user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_helpfulness_review` FOREIGN KEY (`review_id`) 
    REFERENCES `wp_reviews` (`review_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 7. ТЕГИ
-- ============================================================================

-- Основная таблица тегов
CREATE TABLE IF NOT EXISTS `wp_tags` (
  `tag_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(200) NOT NULL COMMENT 'URL-friendly название',
  `usage_count` INT NOT NULL DEFAULT 0 COMMENT 'Количество использований',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`tag_id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `usage_count` (`usage_count`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Переводы тегов
CREATE TABLE IF NOT EXISTS `wp_tags_i18n` (
  `tag_i18n_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tag_id` BIGINT UNSIGNED NOT NULL,
  `language_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL COMMENT 'Название тега',
  PRIMARY KEY (`tag_i18n_id`),
  UNIQUE KEY `tag_language` (`tag_id`, `language_id`),
  KEY `language_id` (`language_id`),
  CONSTRAINT `fk_tags_i18n_tag` FOREIGN KEY (`tag_id`) 
    REFERENCES `wp_tags` (`tag_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tags_i18n_language` FOREIGN KEY (`language_id`) 
    REFERENCES `wp_languages` (`language_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Связь товаров с тегами
CREATE TABLE IF NOT EXISTS `wp_product_tags` (
  `product_tag_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `tag_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`product_tag_id`),
  UNIQUE KEY `product_tag` (`product_id`, `tag_id`),
  KEY `tag_id` (`tag_id`),
  CONSTRAINT `fk_product_tags_product` FOREIGN KEY (`product_id`) 
    REFERENCES `wp_products` (`product_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_product_tags_tag` FOREIGN KEY (`tag_id`) 
    REFERENCES `wp_tags` (`tag_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 8. СТРАНИЦЫ И КОНТЕНТ
-- ============================================================================

-- Основная таблица страниц
CREATE TABLE IF NOT EXISTS `wp_pages` (
  `page_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Родительская страница',
  `slug` VARCHAR(200) NOT NULL COMMENT 'URL-friendly название',
  `template` VARCHAR(100) DEFAULT 'default' COMMENT 'Шаблон страницы',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Активна ли страница',
  `show_in_menu` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Показывать в меню',
  `sort_order` INT NOT NULL DEFAULT 0 COMMENT 'Порядок в меню',
  `featured_image` VARCHAR(500) DEFAULT NULL COMMENT 'Главное изображение',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`page_id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `parent_id` (`parent_id`),
  KEY `is_active` (`is_active`),
  KEY `show_in_menu` (`show_in_menu`),
  CONSTRAINT `fk_pages_parent` FOREIGN KEY (`parent_id`) 
    REFERENCES `wp_pages` (`page_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Переводы страниц
CREATE TABLE IF NOT EXISTS `wp_pages_i18n` (
  `page_i18n_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_id` BIGINT UNSIGNED NOT NULL,
  `language_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL COMMENT 'Заголовок страницы',
  `content` LONGTEXT DEFAULT NULL COMMENT 'Содержимое страницы',
  `excerpt` TEXT DEFAULT NULL COMMENT 'Краткое описание',
  `meta_title` VARCHAR(255) DEFAULT NULL COMMENT 'SEO заголовок',
  `meta_description` TEXT DEFAULT NULL COMMENT 'SEO описание',
  `meta_keywords` TEXT DEFAULT NULL COMMENT 'SEO ключевые слова',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`page_i18n_id`),
  UNIQUE KEY `page_language` (`page_id`, `language_id`),
  KEY `language_id` (`language_id`),
  FULLTEXT KEY `search_content` (`title`, `content`),
  CONSTRAINT `fk_pages_i18n_page` FOREIGN KEY (`page_id`) 
    REFERENCES `wp_pages` (`page_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pages_i18n_language` FOREIGN KEY (`language_id`) 
    REFERENCES `wp_languages` (`language_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 9. МЕНЮ
-- ============================================================================

-- Таблица меню
CREATE TABLE IF NOT EXISTS `wp_menus` (
  `menu_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(100) NOT NULL COMMENT 'Идентификатор меню',
  `location` VARCHAR(100) NOT NULL COMMENT 'Расположение (header, footer, etc)',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`menu_id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Переводы названий меню
CREATE TABLE IF NOT EXISTS `wp_menus_i18n` (
  `menu_i18n_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `menu_id` INT UNSIGNED NOT NULL,
  `language_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL COMMENT 'Название меню',
  PRIMARY KEY (`menu_i18n_id`),
  UNIQUE KEY `menu_language` (`menu_id`, `language_id`),
  KEY `language_id` (`language_id`),
  CONSTRAINT `fk_menus_i18n_menu` FOREIGN KEY (`menu_id`) 
    REFERENCES `wp_menus` (`menu_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_menus_i18n_language` FOREIGN KEY (`language_id`) 
    REFERENCES `wp_languages` (`language_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Элементы меню
CREATE TABLE IF NOT EXISTS `wp_menu_items` (
  `menu_item_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `menu_id` INT UNSIGNED NOT NULL,
  `parent_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Родительский элемент',
  `type` ENUM('page', 'category', 'product', 'custom', 'external') NOT NULL DEFAULT 'custom',
  `object_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'ID связанного объекта',
  `url` VARCHAR(500) DEFAULT NULL COMMENT 'Пользовательский URL',
  `target` VARCHAR(20) DEFAULT '_self' COMMENT 'Цель ссылки (_self, _blank)',
  `icon_class` VARCHAR(100) DEFAULT NULL COMMENT 'CSS класс иконки',
  `css_classes` VARCHAR(255) DEFAULT NULL COMMENT 'Дополнительные CSS классы',
  `sort_order` INT NOT NULL DEFAULT 0 COMMENT 'Порядок сортировки',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`menu_item_id`),
  KEY `menu_id` (`menu_id`),
  KEY `parent_id` (`parent_id`),
  KEY `type` (`type`),
  KEY `sort_order` (`sort_order`),
  CONSTRAINT `fk_menu_items_menu` FOREIGN KEY (`menu_id`) 
    REFERENCES `wp_menus` (`menu_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_menu_items_parent` FOREIGN KEY (`parent_id`) 
    REFERENCES `wp_menu_items` (`menu_item_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Переводы элементов меню
CREATE TABLE IF NOT EXISTS `wp_menu_items_i18n` (
  `menu_item_i18n_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `menu_item_id` BIGINT UNSIGNED NOT NULL,
  `language_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL COMMENT 'Название элемента',
  `description` TEXT DEFAULT NULL COMMENT 'Описание (для всплывающих подсказок)',
  PRIMARY KEY (`menu_item_i18n_id`),
  UNIQUE KEY `menu_item_language` (`menu_item_id`, `language_id`),
  KEY `language_id` (`language_id`),
  CONSTRAINT `fk_menu_items_i18n_item` FOREIGN KEY (`menu_item_id`) 
    REFERENCES `wp_menu_items` (`menu_item_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_menu_items_i18n_language` FOREIGN KEY (`language_id`) 
    REFERENCES `wp_languages` (`language_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 10. МЕДИА ФАЙЛЫ
-- ============================================================================

-- Таблица медиа файлов
CREATE TABLE IF NOT EXISTS `wp_media` (
  `media_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Кто загрузил',
  `file_name` VARCHAR(255) NOT NULL COMMENT 'Имя файла',
  `file_path` VARCHAR(500) NOT NULL COMMENT 'Путь к файлу',
  `file_url` VARCHAR(500) NOT NULL COMMENT 'URL файла',
  `mime_type` VARCHAR(100) NOT NULL COMMENT 'MIME тип',
  `file_size` INT NOT NULL COMMENT 'Размер в байтах',
  `width` INT DEFAULT NULL COMMENT 'Ширина (для изображений)',
  `height` INT DEFAULT NULL COMMENT 'Высота (для изображений)',
  `alt_text` VARCHAR(255) DEFAULT NULL COMMENT 'Альтернативный текст',
  `caption` TEXT DEFAULT NULL COMMENT 'Подпись',
  `description` TEXT DEFAULT NULL COMMENT 'Описание',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`media_id`),
  KEY `user_id` (`user_id`),
  KEY `mime_type` (`mime_type`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 11. НАСТРОЙКИ
-- ============================================================================

-- Таблица настроек сайта
CREATE TABLE IF NOT EXISTS `wp_settings` (
  `setting_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL COMMENT 'Ключ настройки',
  `setting_value` LONGTEXT DEFAULT NULL COMMENT 'Значение настройки',
  `setting_type` VARCHAR(50) NOT NULL DEFAULT 'text' COMMENT 'Тип данных',
  `group_name` VARCHAR(100) DEFAULT NULL COMMENT 'Группа настроек',
  `is_autoload` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Загружать автоматически',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `group_name` (`group_name`),
  KEY `is_autoload` (`is_autoload`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 12. ЛОГИ И АНАЛИТИКА
-- ============================================================================

-- Таблица логов активности
CREATE TABLE IF NOT EXISTS `wp_activity_logs` (
  `log_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'ID пользователя',
  `action` VARCHAR(100) NOT NULL COMMENT 'Действие (view, add_to_cart, purchase, etc)',
  `entity_type` VARCHAR(50) DEFAULT NULL COMMENT 'Тип сущности (product, category, page)',
  `entity_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'ID сущности',
  `details` TEXT DEFAULT NULL COMMENT 'Дополнительные данные (JSON)',
  `ip_address` VARCHAR(45) DEFAULT NULL COMMENT 'IP адрес',
  `user_agent` VARCHAR(500) DEFAULT NULL COMMENT 'User Agent',
  `referrer` VARCHAR(500) DEFAULT NULL COMMENT 'Реферер',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `user_id` (`user_id`),
  KEY `action` (`action`),
  KEY `entity_type` (`entity_type`),
  KEY `entity_id` (`entity_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица зафейленных работ
CREATE TABLE IF NOT EXISTS wp_failed_jobs (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  queue varchar(255) NOT NULL,
  payload longtext NOT NULL,
  exception longtext NOT NULL,
  failed_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  attempts int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY queue (queue),
  KEY failed_at (failed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица избранного
CREATE TABLE IF NOT EXISTS `wp_favorites` (
  `favorite_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL COMMENT 'ID пользователя',
  `product_id` BIGINT UNSIGNED NOT NULL COMMENT 'ID товара',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`favorite_id`),
  UNIQUE KEY `user_product` (`user_id`, `product_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `fk_favorites_product` FOREIGN KEY (`product_id`) 
    REFERENCES `wp_products` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица сравнения товаров
CREATE TABLE IF NOT EXISTS `wp_comparisons` (
  `comparison_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'ID пользователя (NULL для гостей)',
  `session_id` VARCHAR(255) DEFAULT NULL COMMENT 'ID сессии для гостей',
  `product_id` BIGINT UNSIGNED NOT NULL COMMENT 'ID товара',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`comparison_id`),
  KEY `user_id` (`user_id`),
  KEY `session_id` (`session_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `fk_comparisons_product` FOREIGN KEY (`product_id`) 
    REFERENCES `wp_products` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 13. ФОРМЫ И ЗАЯВКИ
-- ============================================================================

-- Таблица форм
CREATE TABLE IF NOT EXISTS `wp_forms` (
  `form_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(100) NOT NULL COMMENT 'Идентификатор формы',
  `form_fields` TEXT NOT NULL COMMENT 'Структура полей (JSON)',
  `email_to` VARCHAR(500) DEFAULT NULL COMMENT 'Email для уведомлений',
  `success_message` TEXT DEFAULT NULL COMMENT 'Сообщение об успехе',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`form_id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Переводы форм
CREATE TABLE IF NOT EXISTS `wp_forms_i18n` (
  `form_i18n_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `form_id` INT UNSIGNED NOT NULL,
  `language_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL COMMENT 'Название формы',
  `description` TEXT DEFAULT NULL COMMENT 'Описание формы',
  PRIMARY KEY (`form_i18n_id`),
  UNIQUE KEY `form_language` (`form_id`, `language_id`),
  KEY `language_id` (`language_id`),
  CONSTRAINT `fk_forms_i18n_form` FOREIGN KEY (`form_id`) 
    REFERENCES `wp_forms` (`form_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_forms_i18n_language` FOREIGN KEY (`language_id`) 
    REFERENCES `wp_languages` (`language_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица заявок с форм
CREATE TABLE IF NOT EXISTS `wp_form_submissions` (
  `submission_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `form_id` INT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'ID пользователя',
  `language_id` INT UNSIGNED NOT NULL COMMENT 'Язык заявки',
  `form_data` TEXT NOT NULL COMMENT 'Данные формы (JSON)',
  `status` ENUM('new', 'read', 'in_progress', 'completed', 'spam') NOT NULL DEFAULT 'new',
  `ip_address` VARCHAR(45) DEFAULT NULL COMMENT 'IP адрес',
  `user_agent` VARCHAR(500) DEFAULT NULL COMMENT 'User Agent',
  `referrer` VARCHAR(500) DEFAULT NULL COMMENT 'Реферер',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`submission_id`),
  KEY `form_id` (`form_id`),
  KEY `user_id` (`user_id`),
  KEY `language_id` (`language_id`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `fk_submissions_form` FOREIGN KEY (`form_id`) 
    REFERENCES `wp_forms` (`form_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_submissions_language` FOREIGN KEY (`language_id`) 
    REFERENCES `wp_languages` (`language_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 14. SEO И РЕДИРЕКТЫ
-- ============================================================================

-- Таблица редиректов
CREATE TABLE IF NOT EXISTS `wp_redirects` (
  `redirect_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `from_url` VARCHAR(500) NOT NULL COMMENT 'Исходный URL',
  `to_url` VARCHAR(500) NOT NULL COMMENT 'Целевой URL',
  `redirect_type` ENUM('301', '302', '307') NOT NULL DEFAULT '301',
  `hits_count` INT NOT NULL DEFAULT 0 COMMENT 'Количество переходов',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`redirect_id`),
  UNIQUE KEY `from_url` (`from_url`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- ВСТАВКА НАЧАЛЬНЫХ ДАННЫХ
-- ============================================================================

-- Языки
INSERT INTO `wp_languages` (`code`, `name`, `native_name`, `is_default`, `is_active`, `sort_order`, `locale`) VALUES
('ru', 'Russian', 'Русский', 1, 1, 1, 'ru_RU'),
('uk', 'Ukrainian', 'Українська', 0, 1, 2, 'uk_UA'),
('en', 'English', 'English', 0, 1, 3, 'en_US');

-- Настройки по умолчанию
INSERT INTO `wp_settings` (`setting_key`, `setting_value`, `setting_type`, `group_name`, `is_autoload`) VALUES
('site_title', 'My WordPress Site', 'text', 'general', 1),
('site_description', 'Just another WordPress site', 'text', 'general', 1),
('products_per_page', '12', 'number', 'catalog', 1),
('enable_reviews', '1', 'boolean', 'catalog', 1),
('currency_symbol', '$', 'text', 'catalog', 1),
('currency_position', 'before', 'text', 'catalog', 1);

-- Меню по умолчанию
INSERT INTO `wp_menus` (`slug`, `location`, `is_active`) VALUES
('main-menu', 'header', 1),
('footer-menu', 'footer', 1);

-- Переводы меню
INSERT INTO `wp_menus_i18n` (`menu_id`, `language_id`, `name`)
SELECT 1, language_id, 
  CASE 
    WHEN code = 'ru' THEN 'Главное меню'
    WHEN code = 'uk' THEN 'Головне меню'
    ELSE 'Main Menu'
  END
FROM `wp_languages`;

INSERT INTO `wp_menus_i18n` (`menu_id`, `language_id`, `name`)
SELECT 2, language_id, 
  CASE 
    WHEN code = 'ru' THEN 'Меню в подвале'
    WHEN code = 'uk' THEN 'Меню в футері'
    ELSE 'Footer Menu'
  END
FROM `wp_languages`;

-- Включаем проверку внешних ключей
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- ПОЛЕЗНЫЕ ПРЕДСТАВЛЕНИЯ (VIEWS)
-- ============================================================================

-- Представление для получения товаров с переводами
-- CREATE OR REPLACE VIEW `vw_products_full` AS
-- SELECT 
--   p.*,
--   pi.language_id,
--   pi.name,
--   pi.short_description,
--   pi.description,
--   l.code AS language_code
-- FROM `wp_products` p
-- LEFT JOIN `wp_products_i18n` pi ON p.product_id = pi.product_id
-- LEFT JOIN `wp_languages` l ON pi.language_id = l.language_id;

-- -- Представление для получения категорий с переводами
-- CREATE OR REPLACE VIEW `vw_categories_full` AS
-- SELECT 
--   c.*,
--   ci.language_id,
--   ci.name,
--   ci.description,
--   l.code AS language_code
-- FROM `wp_categories` c
-- LEFT JOIN `wp_categories_i18n` ci ON c.category_id = ci.category_id
-- LEFT JOIN `wp_languages` l ON ci.language_id = l.language_id;

-- -- Представление для получения страниц с переводами
-- CREATE OR REPLACE VIEW `vw_pages_full` AS
-- SELECT 
--   p.*,
--   pi.language_id,
--   pi.title,
--   pi.content,
--   l.code AS language_code
-- FROM `wp_pages` p
-- LEFT JOIN `wp_pages_i18n` pi ON p.page_id = pi.page_id
-- LEFT JOIN `wp_languages` l ON pi.language_id = l.language_id;

-- ============================================================================
-- ПОЛЕЗНЫЕ ИНДЕКСЫ ДЛЯ ОПТИМИЗАЦИИ
-- ============================================================================

-- Дополнительные индексы для производительности
ALTER TABLE `wp_products` ADD INDEX `idx_price_active` (`price`, `is_active`);
ALTER TABLE `wp_products` ADD INDEX `idx_featured_active` (`is_featured`, `is_active`);
ALTER TABLE `wp_reviews` ADD INDEX `idx_product_approved` (`product_id`, `is_approved`);
ALTER TABLE `wp_activity_logs` ADD INDEX `idx_user_action_date` (`user_id`, `action`, `created_at`);

-- ============================================================================
-- КОНЕЦ СКРИПТА
-- ============================================================================
