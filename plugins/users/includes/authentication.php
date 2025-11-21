<?php
require_once WP_CONTENT_DIR . '/mu-plugins/redis/class-redis-wp-manager.php';
require_once WP_CONTENT_DIR . '/mu-plugins/redis/class-redis-wp-queue.php';
require_once WP_CONTENT_DIR . '/mu-plugins/email-notification/class-email-notification.php';

add_action('plugins_loaded', function(){
    // $email_notification = new Email_Notification(WP_Redis_Queue::get_instance());
    // $email_notification->send_email(
    //     'sashatkachenkobusiness@gmail.com',
    //     'Добро пожаловать!123123!',
    //     'Спасибо за регистрацию...',
    //     ['Content-Type: text/html; charset=UTF-8']
    // );
    
    // $worker = new Email_Worker($email_notification);
    // $processed = $worker->process_one();
    // echo "Обработано писем: $processed\n";

});