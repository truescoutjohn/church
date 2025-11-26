<?php
/**
 * Telegram OTP Sender
 */

if (!defined('ABSPATH')) {
    exit;
}

class Telegram_OTP_Sender {
    
    private static $instance = null;
    private $telegram = null;
    private $user_manager = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->telegram = Telegram_API::get_instance();
        $this->user_manager = Telegram_User_Manager::get_instance();
        
        // Регистрация обработчиков для очереди
        $this->register_handlers();
    }
    
    public function register_handlers() {
        add_action('redis_queue_job_telegram_send_otp', array($this, 'handle_send_otp'), 10, 2);
        add_action('redis_queue_job_telegram_login_notification', array($this, 'handle_login_notification'), 10, 2);
    }
    
    /**
     * Отправка OTP в очередь
     */
    public function queue_otp($user_id, $otp, $action = 'login') {
        // Проверка, включены ли OTP через Telegram
        if (!get_option('telegram_otp_enabled')) {
            return false;
        }
        
        // Проверка привязки Telegram
        if (!$this->user_manager->is_telegram_linked($user_id)) {
            return false;
        }
        
        $queue = WP_Redis_Queue::get_instance();
        return $queue->push('telegram_notifications', json_encode(array(
            'type' => 'telegram_send_otp',
            'user_id' => $user_id,
            'otp' => $otp,
            'action' => $action,
            'timestamp' => time()
        )));
    }
    
    /**
     * Отправка уведомления о входе в очередь
     */
    public function queue_login_notification($user_id, $ip, $user_agent) {
        // Проверка, включены ли уведомления
        if (!get_option('telegram_login_notifications')) {
            return false;
        }
        
        // Проверка привязки Telegram
        if (!$this->user_manager->is_telegram_linked($user_id)) {
            return false;
        }
        
        $queue = WP_Redis_Queue::get_instance();
        return $queue->push('telegram_notifications', json_encode(array(
            'type' => 'telegram_login_notification',
            'user_id' => $user_id,
            'ip' => $ip,
            'user_agent' => $user_agent,
            'timestamp' => time()
        )));
    }
    
    /**
     * Обработчик отправки OTP
     */
    public function handle_send_otp($job_data, $context) {
        $user_id = $job_data['user_id'];
        $otp = $job_data['otp'];
        $action = isset($job_data['action']) ? $job_data['action'] : 'login';
        
        $chat_id = $this->user_manager->get_user_chat_id($user_id);
        
        if (!$chat_id) {
            error_log("Telegram: No chat_id for user {$user_id}");
            return false;
        }
        
        $result = $this->telegram->send_otp($chat_id, $otp, $action);
        
        if ($result) {
            error_log("Telegram OTP sent to user {$user_id}");
        } else {
            error_log("Failed to send Telegram OTP to user {$user_id}");
        }
        
        return $result;
    }
    
    /**
     * Обработчик уведомления о входе
     */
    public function handle_login_notification($job_data, $context) {
        $user_id = $job_data['user_id'];
        $ip = $job_data['ip'];
        $user_agent = $job_data['user_agent'];
        
        $chat_id = $this->user_manager->get_user_chat_id($user_id);
        
        if (!$chat_id) {
            error_log("Telegram: No chat_id for user {$user_id}");
            return false;
        }
        
        // Определение местоположения по IP (опционально)
        $location = $this->get_location_by_ip($ip);
        
        $result = $this->telegram->send_login_notification($chat_id, $ip, $user_agent, $location);
        
        if ($result) {
            error_log("Telegram login notification sent to user {$user_id}");
        } else {
            error_log("Failed to send Telegram login notification to user {$user_id}");
        }
        
        return $result;
    }
    
    /**
     * Определение местоположения по IP (опционально)
     */
    private function get_location_by_ip($ip) {
        // Можно использовать сервисы геолокации
        // Например: ipapi.co, ip-api.com и т.д.
        
        $response = wp_remote_get("http://ip-api.com/json/{$ip}");
        
        if (is_wp_error($response)) {
            return '';
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($data['country']) && isset($data['city'])) {
            return $data['city'] . ', ' . $data['country'];
        }
        
        return '';
    }
}