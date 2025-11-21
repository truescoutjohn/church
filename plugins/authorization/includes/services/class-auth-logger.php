<?php
/**
 * Логгер действий пользователей
 */
class Auth_Logger {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function log_user_action($user_id, $action, $request) {
        $log_data = array(
            'user_id' => $user_id,
            'action' => $action,
            'timestamp' => time(),
            'ip' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT']
        );
        
        wp_redis_queue_push('logs', array(
            'type' => 'user_action',
            'data' => $log_data
        ), 0);
    }
}