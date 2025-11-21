<?php
/**
 * Plugin Name: Migration DB
 * Description: An migration tool for Church App
 * Version: 1.0.0
 * Author: Sponge
 * Author URI: https://spng.space/
 * Text Domain: migration_db
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;

// Подключаем классы
require_once __DIR__ . '/includes/class-migration.php';

// ============================================
// ИНИЦИАЛИЗАЦИЯ ПЛАГИНА
// ============================================

add_action( 'plugins_loaded', 'migration_db_init' );

function migration_db_init() {
    // Инициализируем плагин
    Migration::get_instance();
}

