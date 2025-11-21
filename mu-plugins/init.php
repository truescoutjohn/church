<?php


// add_action('plugins_loaded', function(){
    require_once __DIR__ . '/redis/class-redis-wp-manager.php';
    require_once __DIR__ . '/redis/class-redis-wp-queue.php';
    require_once __DIR__ . '/email-notification/class-email-notification.php';
// });