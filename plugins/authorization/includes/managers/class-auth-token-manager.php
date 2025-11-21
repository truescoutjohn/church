<?php
/**
 * Менеджер токенов
 */
class Auth_Token_Manager {
    
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
     * Генерация токена авторизации
     */
    public function generate_auth_token($user_id, $remember = false) {
        $token = bin2hex(random_bytes(32));
        $expiration = $remember ? 2592000 : 86400; // 30 дней или 1 день
        
        $token_data = array(
            'user_id' => $user_id,
            'created_at' => time(),
            'ip' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT']
        );
        
        $this->redis->set('auth_token:' . $token, json_encode($token_data), $expiration);
        
        return $token;
    }
    
    /**
     * Генерация токена верификации email
     */
    public function generate_verification_token($user_id) {
        $token = bin2hex(random_bytes(32));
        update_user_meta($user_id, 'email_verification_token', $token);
        update_user_meta($user_id, 'email_verified', false);
        
        return $token;
    }
    
    /**
     * Генерация токена сброса пароля
     */
    public function generate_password_reset_token($user_id) {
        $token = bin2hex(random_bytes(32));
        update_user_meta($user_id, 'password_reset_token', $token);
        update_user_meta($user_id, 'password_reset_expires', time() + 3600); // 1 час
        
        return $token;
    }
    
    /**
     * Проверка аутентификации
     */
    public function check_authentication($request) {
        $user_id = $this->get_user_id_from_token($request);
        return $user_id !== null;
    }
    
    /**
     * Получение user_id из токена
     */
    public function get_user_id_from_token($request) {
        $token = $this->extract_token($request);
        
        if (!$token) {
            return null;
        }
        
        $token_data = $this->redis->get('auth_token:' . $token);
        
        if (!$token_data) {
            return null;
        }
        
        $data = json_decode($token_data, true);
        
        return isset($data['user_id']) ? $data['user_id'] : null;
    }
    
    /**
     * Отзыв токена
     */
    public function revoke_token($request) {
        $token = $this->extract_token($request);
        
        if ($token) {
            $this->redis->delete('auth_token:' . $token);
        }
    }
    
    /**
     * Извлечение токена из запроса
     */
    private function extract_token($request) {
        $auth_header = $request->get_header('authorization');
        
        if (!$auth_header) {
            $auth_header = $request->get_header('x-auth-token');
        }
        
        if (!$auth_header) {
            return null;
        }
        
        // Bearer token
        if (preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
            return $matches[1];
        }
        
        return $auth_header;
    }
}