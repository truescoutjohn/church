<?php
/**
 * Plugin Name: Authorization API
 */

if (!defined('ABSPATH')) {
    exit;
}

define('AUTH_API_DIR', plugin_dir_path(__FILE__));
require_once AUTH_API_DIR . 'includes/telegram/init.php';

add_action('plugins_loaded', function() {
    if (!class_exists('WP_Redis_Manager') || !class_exists('WP_Redis_Queue')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>Authorization API требует WP Redis Manager и WP Redis Queue!</p></div>';
        });
        return;
    }
    
    // Загрузка файлов
    $includes = array(
        'includes/services/class-auth-rate-limiter.php',
        'includes/services/class-auth-logger.php',
        'includes/services/class-auth-validator.php',
        'includes/services/class-email-notification.php',
        'includes/services/class-email-worker.php',
        'includes/services/class-queue-processor.php',  // ← НОВЫЙ (если используете вариант 2)
        'includes/managers/class-auth-token-manager.php',
        'includes/handlers/class-auth-email-queue-handler.php',
        'includes/controllers/class-auth-registration-controller.php',
        'includes/controllers/class-auth-login-controller.php',
        'includes/controllers/class-auth-token-controller.php',
        'includes/controllers/class-auth-password-controller.php',
        'includes/controllers/class-auth-verification-controller.php',
        'includes/class-auth-rest-api.php',
    );
    
    foreach ($includes as $file) {
        $filepath = AUTH_API_DIR . $file;
        if (file_exists($filepath)) {
            require_once $filepath;
        }
    }
    
    // Инициализация
    Auth_REST_API::get_instance();
    
    // Регистрация Cron задачи
    if (!wp_next_scheduled('auth_process_queues')) {
        wp_schedule_event(time(), 'every_minute', 'auth_process_queues');
    }
});

// Обработчик Cron - ОБРАБАТЫВАЕТ ОЧЕРЕДИ И ВЫЗЫВАЕТ ХУКИ
add_action('auth_process_queues', function() {
    // Вариант 1: Через Auth_Email_Queue_Handler
    if (class_exists('Auth_Email_Queue_Handler')) {
        $handler = Auth_Email_Queue_Handler::get_instance();
        $processed_emails = $handler->process_emails_queue(10);
        
        if ($processed_emails > 0) {
            error_log("Processed {$processed_emails} email jobs");
        }
    }
    
    // Обработка email очереди (отправка самих писем)
    if (class_exists('Email_Worker')) {
        $worker = Email_Worker::get_instance();
        $sent_emails = $worker->process_all();
        
        if ($sent_emails > 0) {
            error_log("Sent {$sent_emails} emails");
        }
    }
});

// Кастомный интервал
add_filter('cron_schedules', function($schedules) {
    $schedules['every_minute'] = array(
        'interval' => 60,
        'display' => __('Every Minute')
    );
    return $schedules;
});

// Очистка при деактивации
register_deactivation_hook(__FILE__, function() {
    $timestamp = wp_next_scheduled('auth_process_queues');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'auth_process_queues');
    }
});