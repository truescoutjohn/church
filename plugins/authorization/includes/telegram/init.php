<?php
/**
 * Plugin Name: Telegram Auth & Notifications
 * Description: Telegram OTP и авторизация через Telegram
 * Version: 1.0
 * Author: Your Name
 */

if (!defined('ABSPATH')) {
    exit;
}

define('TELEGRAM_AUTH_DIR', plugin_dir_path(__FILE__));

// Загрузка файлов
add_action('plugins_loaded', function() {
    if (!class_exists('WP_Redis_Manager') || !class_exists('WP_Redis_Queue')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>Telegram Auth требует WP Redis Manager и WP Redis Queue!</p></div>';
        });
        return;
    }
    
    $includes = array(
        'class-telegram-api.php',
        'class-telegram-settings.php',
        'class-telegram-webhook-handler.php',
        'class-telegram-otp-sender.php',
        '../managers/class-telegram-user-manager.php',
        '../handlers/class-telegram-handler.php',
        '../controllers/class-telegram-auth-controller.php',
    );
    
    foreach ($includes as $file) {
        $filepath = TELEGRAM_AUTH_DIR . $file;
        if (file_exists($filepath)) {
            require_once $filepath;
        }
    }
    
    // Инициализация
    Telegram_Settings::get_instance();
    Telegram_Webhook_Handler::get_instance();
    Telegram_Auth_Controller::get_instance();
    
    // Регистрация Cron для обработки уведомлений
    if (!wp_next_scheduled('telegram_process_notifications')) {
        wp_schedule_event(time(), 'every_minute', 'telegram_process_notifications');
    }
});

// // Обработчик Cron для отправки уведомлений
// add_action('telegram_process_notifications', function() {
//     if (class_exists('Telegram_Notification_Worker')) {
//         $worker = Telegram_Notification_Worker::get_instance();
//         $sent = $worker->process_queue(50);
        
//         if ($sent > 0) {
//             error_log("Telegram: Sent {$sent} notifications");
//         }
//     }
// });

// Очистка при деактивации
register_deactivation_hook(__FILE__, function() {
    $timestamp = wp_next_scheduled('telegram_process_notifications');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'telegram_process_notifications');
    }
});