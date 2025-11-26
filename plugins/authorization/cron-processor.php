<?php
/**
 * Cron обработчик очередей
 */

$wp_load = '/var/www/html/wp-load.php';
$log_file = '/tmp/queue-detailed.log';

function log_message($message) {
    global $log_file;
    $time = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[{$time}] {$message}\n", FILE_APPEND);
}

log_message("=== Starting cron ===");

if (!file_exists($wp_load)) {
    log_message("ERROR: WordPress not found at {$wp_load}");
    exit(1);
}

require_once $wp_load;
log_message("WordPress loaded");

// Проверка Redis
if (!class_exists('WP_Redis_Manager')) {
    log_message("ERROR: WP_Redis_Manager not found");
    exit(1);
}

$redis = WP_Redis_Manager::get_instance();
if (!$redis->is_connected()) {
    log_message("ERROR: Redis not connected");
    exit(1);
}
log_message("Redis: connected");

// Обработка очередей
if (class_exists('Auth_Email_Queue_Handler') && class_exists('Telegram_Notification_Handler')) {
    $start = microtime(true);
    $email_handler= Auth_Email_Queue_Handler::get_instance();
    $telegram_handler = Telegram_Notification_Handler::get_instance();
    $processed = $email_handler->process_all(100);
    $processed += $telegram_handler->process_all(100);
    $duration = round(microtime(true) - $start, 2);
    
    log_message("Processed: {$processed} items in {$duration}s");
    
    $stats = $processor->get_queue_stats();
    log_message("Remaining - emails: {$stats['emails']}, total: {$stats['total']}");
} else {
    log_message("ERROR: Auth Email Queue Handler not found");
}

log_message("=== Completed ===\n");