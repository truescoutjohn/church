
<?php
/**
 * Plugin Name: Zoom REST API Integration
 * Plugin URI: https://example.com
 * Description: Інтеграція Zoom Web SDK через REST API з Server-to-Server OAuth
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: zoom-rest-api
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

// Константи плагіна
define('ZOOM_REST_VERSION', '1.0.0');
define('ZOOM_REST_DIR', plugin_dir_path(__FILE__));
define('ZOOM_REST_URL', plugin_dir_url(__FILE__));

// ✅ ЗАМІНІТЬ НА СВОЇ CREDENTIALS з Zoom Marketplace
define('ZOOM_ACCOUNT_ID', '0lt89ShzT6m8tMQxBi-3dw');
define('ZOOM_CLIENT_ID', 'bDjigIAvQyiKRDoXwHioCQ');
define('ZOOM_CLIENT_SECRET', '1DIHaKc09ozxaQE5vTxf16qzrCmASpDe');

define('ZOOM_SDK_KEY', 'ai6jXCMUm733lSiDpj4mPGq0fKjBfq4arVQA');
define('ZOOM_SDK_SECRET', 'hLpewKrT9f2uInMoRPk4Y9OzmxIz8yy3CwVg');

// Підключаємо класи
require_once ZOOM_REST_DIR . 'includes/class-zoom-oauth.php';
require_once ZOOM_REST_DIR . 'includes/class-zoom-jwt.php';
require_once ZOOM_REST_DIR . 'includes/class-zoom-rest-api.php';
require_once ZOOM_REST_DIR . 'frontend/meetings.php';

/**
 * Ініціалізація плагіна
 */
function zoom_rest_api_init() {
    return Zoom_REST_API::get_instance();
}
add_action('plugins_loaded', 'zoom_rest_api_init');

/**
 * Активація плагіна
 */
register_activation_hook(__FILE__, function() {
    // Перевіряємо credentials
    if (ZOOM_ACCOUNT_ID !== '0lt89ShzT6m8tMQxBi-3dw') {
        wp_die("Будь ласка, налаштуйте Zoom credentials у файлі zoom-rest-api.php " . ZOOM_ACCOUNT_ID);
    }
    
    // Очищуємо permalinks
    flush_rewrite_rules();
});

/**
 * Деактивація плагіна
 */
register_deactivation_hook(__FILE__, function() {
    delete_transient('zoom_access_token');
    flush_rewrite_rules();
});