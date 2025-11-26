<?php
/**
 * Telegram Auth Controller - REST API endpoints
 */

if (!defined('ABSPATH')) {
    exit;
}

class Telegram_Auth_Controller {
    
    private static $instance = null;
    private $redis = null;
    private $user_manager = null;
    private $telegram = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->redis = WP_Redis_Manager::get_instance();
        $this->user_manager = Telegram_User_Manager::get_instance();
        $this->telegram = Telegram_API::get_instance();
        
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    public function register_routes() {
        // Генерация кода привязки
        register_rest_route('telegram/v1', '/link/generate', array(
            'methods' => 'POST',
            'callback' => array($this, 'generate_link_code'),
            'permission_callback' => function($request) {
                return is_user_logged_in();
            }
        ));
        
        // Проверка статуса привязки
        register_rest_route('telegram/v1', '/link/status', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_link_status'),
            'permission_callback' => function($request) {
                return is_user_logged_in();
            }
        ));
        
        // Отвязка аккаунта
        register_rest_route('telegram/v1', '/link/unlink', array(
            'methods' => 'POST',
            'callback' => array($this, 'unlink_account'),
            'permission_callback' => function($request) {
                return is_user_logged_in();
            }
        ));
        
        // Инициация авторизации через Telegram
        register_rest_route('telegram/v1', '/auth/init', array(
            'methods' => 'POST',
            'callback' => array($this, 'init_telegram_auth'),
            'permission_callback' => '__return_true',
            'args' => array(
                'email' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_email'
                )
            )
        ));
        
        // Проверка статуса авторизации
        register_rest_route('telegram/v1', '/auth/check', array(
            'methods' => 'POST',
            'callback' => array($this, 'check_auth_status'),
            'permission_callback' => '__return_true',
            'args' => array(
                'auth_code' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));
    }
    
    /**
     * Генерация кода привязки
     */
    public function generate_link_code($request) {
        $user_id = get_current_user_id();
        
        if ($this->user_manager->is_telegram_linked($user_id)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Telegram уже привязан к этому аккаунту'
            ), 400);
        }
        
        $code = $this->user_manager->generate_link_code($user_id);
        
        return new WP_REST_Response(array(
            'success' => true,
            'data' => array(
                'code' => $code,
                'expires_in' => 900, // 15 минут
                'bot_username' => $this->get_bot_username()
            )
        ), 200);
    }
    
    /**
     * Получение статуса привязки
     */
    public function get_link_status($request) {
        $user_id = get_current_user_id();
        $is_linked = $this->user_manager->is_telegram_linked($user_id);
        
        $response = array(
            'success' => true,
            'data' => array(
                'is_linked' => $is_linked,
                'bot_username' => $this->get_bot_username()
            )
        );
        
        if ($is_linked) {
            $link_info = $this->user_manager->get_link_info($user_id);
            $response['data']['linked_at'] = $link_info['linked_at'];
        }
        
        return new WP_REST_Response($response, 200);
    }
    
    /**
     * Отвязка аккаунта
     */
    public function unlink_account($request) {
        $user_id = get_current_user_id();
        
        if (!$this->user_manager->is_telegram_linked($user_id)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Telegram не привязан к этому аккаунту'
            ), 400);
        }
        
        $this->user_manager->unlink_telegram($user_id);
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Telegram успешно отвязан'
        ), 200);
    }
    
    /**
     * Инициация авторизации через Telegram
     */
    public function init_telegram_auth($request) {
        $email = $request->get_param('email');
        
        $user = get_user_by('email', $email);
        
        if (!$user) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Пользователь не найден'
            ), 404);
        }
        
        // Проверка привязки Telegram
        if (!$this->user_manager->is_telegram_linked($user->ID)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Telegram не привязан к этому аккаунту'
            ), 400);
        }
        
        // Генерация кода авторизации
        $auth_code = $this->generate_auth_code();
        
        // Сохранение запроса на авторизацию
        $this->redis->set('telegram_auth_request:' . $auth_code, json_encode(array(
            'user_id' => $user->ID,
            'email' => $email,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'timestamp' => time()
        )), 300); // 5 минут
        
        // Отправка уведомления в Telegram
        $chat_id = $this->user_manager->get_user_chat_id($user->ID);
        
        if ($chat_id) {
            $message = "🔐 <b>Запрос на вход</b>\n\n";
            $message .= "📧 Email: <b>{$email}</b>\n";
            $message .= "📍 IP: <code>{$_SERVER['REMOTE_ADDR']}</code>\n\n";
            $message .= "Это вы пытаетесь войти?";
            
            $keyboard = $this->telegram->create_auth_keyboard($auth_code);
            $this->telegram->send_message($chat_id, $message, 'HTML', $keyboard);
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'data' => array(
                'auth_code' => $auth_code,
                'message' => 'Проверьте Telegram для подтверждения входа'
            )
        ), 200);
    }
    
    /**
     * Проверка статуса авторизации
     */
    public function check_auth_status($request) {
        $auth_code = $request->get_param('auth_code');
        
        // Проверка подтверждения
        $confirmed = $this->redis->get('telegram_auth_confirmed:' . $auth_code);
        
        if ($confirmed) {
            // Получение данных пользователя
            $redis_key = 'telegram_auth_request:' . $auth_code;
            $auth_data = $this->redis->get($redis_key);
            
            if (!$auth_data) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => 'Код авторизации истек'
                ), 400);
            }
            
            $data = json_decode($auth_data, true);
            $user = get_userdata($data['user_id']);
            
            // Генерация токена (используем существующий Auth_Token_Manager)
            if (class_exists('Auth_Token_Manager')) {
                $token_manager = Auth_Token_Manager::get_instance();
                $auth_token = $token_manager->generate_auth_token($user->ID);
            } else {
                $auth_token = wp_generate_password(32, false);
            }
            
            // Очистка
            $this->redis->delete('telegram_auth_confirmed:' . $auth_code);
            $this->redis->delete($redis_key);
            
            return new WP_REST_Response(array(
                'success' => true,
                'data' => array(
                    'user_id' => $user->ID,
                    'email' => $user->user_email,
                    'token' => $auth_token,
                    'confirmed' => true
                )
            ), 200);
        }
        
        // Проверка, существует ли запрос
        $request_exists = $this->redis->get('telegram_auth_request:' . $auth_code);
        
        if (!$request_exists) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Код авторизации не найден или истек'
            ), 404);
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'data' => array(
                'confirmed' => false,
                'message' => 'Ожидание подтверждения'
            )
        ), 200);
    }
    
    private function generate_auth_code() {
        return bin2hex(random_bytes(16));
    }
    
    private function get_bot_username() {
        $bot_info = $this->telegram->get_me();
        return isset($bot_info['username']) ? '@' . $bot_info['username'] : '';
    }
}