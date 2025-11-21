<?php
/**
 * Контроллер регистрации пользователей
 */

require_once __DIR__ . '/../managers/class-auth-token-manager.php';
require_once __DIR__ . '/../services/class-auth-logger.php';
require_once __DIR__ . '/../services/class-auth-validator.php';

class Auth_Registration_Controller {
    
    private static $instance = null;
    private $token_manager = null;
    private $logger = null;
    private $validator = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->token_manager = Auth_Token_Manager::get_instance();
        $this->logger = Auth_Logger::get_instance();
        $this->validator = Auth_Validator::get_instance();
    }
    
    public function register_routes($namespace) {
        register_rest_route($namespace, '/register', array(
            'methods' => 'POST',
            'callback' => array($this, 'register_user'),
            'permission_callback' => '__return_true',
            'args' => array(
                'email' => array(
                    'required' => true,
                    'type' => 'string',
                    'format' => 'email',
                    'sanitize_callback' => 'sanitize_email',
                    'validate_callback' => function($param) {
                        return is_email($param);
                    }
                ),
                'password' => array(
                    'required' => true,
                    'type' => 'string',
                    'minLength' => 8,
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'username' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_user'
                ),
                'first_name' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'last_name' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));
    }
    
    public function register_user($request) {
        $email = $request->get_param('email');
        $password = $request->get_param('password');
        $username = $request->get_param('username');
        $first_name = $request->get_param('first_name');
        $last_name = $request->get_param('last_name');
        
        // Валидация
        $validation = $this->validator->validate_registration($email, $password, $username);
        if (is_wp_error($validation)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => $validation->get_error_message()
            ), 400);
        }
        
        // Генерация username если не указан
        if (empty($username)) {
            $username = $this->generate_unique_username($email);
        }
        
        // Создание пользователя
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => $user_id->get_error_message()
            ), 500);
        }
        
        // Обновление дополнительных данных
        $this->update_user_metadata($user_id, $first_name, $last_name);
        
        // Генерация токена верификации
        $verification_token = $this->token_manager->generate_verification_token($user_id);
        
        // Добавление задач в очередь
        $this->queue_welcome_email($user_id, $email, $first_name);
        $this->queue_verification_email($user_id, $email, $verification_token);
        
        // Генерация токена авторизации
        $auth_token = $this->token_manager->generate_auth_token($user_id);
        
        // Логирование
        $this->logger->log_user_action($user_id, 'register', $request);
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'User registered successfully',
            'data' => array(
                'user_id' => $user_id,
                'username' => $username,
                'email' => $email,
                'token' => $auth_token,
                'email_verified' => false
            )
        ), 201);
    }
    
    private function generate_unique_username($email) {
        $username = sanitize_user(current(explode('@', $email)), true);
        
        $counter = 1;
        $temp_username = $username;
        while (username_exists($temp_username)) {
            $temp_username = $username . $counter;
            $counter++;
        }
        
        return $temp_username;
    }
    
    private function update_user_metadata($user_id, $first_name, $last_name) {
        if ($first_name) {
            update_user_meta($user_id, 'first_name', $first_name);
        }
        
        if ($last_name) {
            update_user_meta($user_id, 'last_name', $last_name);
        }
    }
    
    private function queue_welcome_email($user_id, $email, $first_name) {
        wp_redis_queue_push('emails', array(
            'type' => 'send_welcome_email',
            'user_id' => $user_id,
            'email' => $email,
            'first_name' => $first_name
        ), 5);
    }
    
    private function queue_verification_email($user_id, $email, $token) {
        wp_redis_queue_push('emails', array(
            'type' => 'send_verification_email',
            'user_id' => $user_id,
            'email' => $email,
            'token' => $token
        ), 10);
    }
}