<?php
/**
 * Telegram User Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class Telegram_User_Manager {
    
    private static $instance = null;
    private $redis = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->redis = WP_Redis_Manager::get_instance();
    }
    
    /**
     * Генерация кода привязки аккаунта
     */
    public function generate_link_code($user_id) {
        $code = 'LINK-' . strtoupper(substr(md5(uniqid($user_id, true)), 0, 5));
        
        // Сохранение кода в Redis на 15 минут
        $this->redis->set('telegram_link_code:' . $code, $user_id, 900);
        
        return $code;
    }
    
    /**
     * Проверка привязки Telegram
     */
    public function is_telegram_linked($user_id) {
        $chat_id = get_user_meta($user_id, 'telegram_chat_id', true);
        return !empty($chat_id);
    }
    
    /**
     * Получение chat_id пользователя
     */
    public function get_user_chat_id($user_id) {
        return get_user_meta($user_id, 'telegram_chat_id', true);
    }
    
    /**
     * Отвязка Telegram аккаунта
     */
    public function unlink_telegram($user_id) {
        delete_user_meta($user_id, 'telegram_chat_id');
        delete_user_meta($user_id, 'telegram_linked_at');
        
        return true;
    }
    
    /**
     * Получение информации о привязке
     */
    public function get_link_info($user_id) {
        if (!$this->is_telegram_linked($user_id)) {
            return null;
        }
        
        return array(
            'chat_id' => get_user_meta($user_id, 'telegram_chat_id', true),
            'linked_at' => get_user_meta($user_id, 'telegram_linked_at', true)
        );
    }
}