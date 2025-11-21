<?php
/**
 * Контроллер управления паролями
 */

require_once __DIR__ . '/../managers/class-auth-token-manager.php';
require_once __DIR__ . '/../services/class-auth-logger.php';

class Auth_Password_Controller {
    
    private static $instance = null;
    private $token_manager = null;
    private $logger = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->token_manager = Auth_Token_Manager::get_instance();
        $this->logger = Auth_Logger::get_instance();
    }
    
    public function register_routes($namespace) {
        // Forgot password
        register_rest_route($namespace, '/forgot-password', array(
            'methods' => 'POST',
            'callback' => array($this, 'forgot_password'),
            'permission_callback' => '__return_true',
            'args' => array(
                'email' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_email'
                )
            )
        ));
        
        // Reset password
        register_rest_route($namespace, '/reset-password', array(
            'methods' => 'POST',
            'callback' => array($this, 'reset_password'),
            'permission_callback' => '__return_true',
            'args' => array(
                'token' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'password' => array(
                    'required' => true,
                    'type' => 'string',
                    'minLength' => 8,
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));
    }
    
    public function forgot_password($request) {
        $email = $request->get_param('email');
        
        $user = get_user_by('email', $email);
        
        if (!$user) {
            // Возвращаем успех даже если пользователь не найден
            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'If this email exists, you will receive a password reset link'
            ), 200);
        }
        
        // Генерация токена сброса
        $reset_token = $this->token_manager->generate_password_reset_token($user->ID);
        
        // Добавление в очередь
        wp_redis_queue_push('emails', array(
            'type' => 'send_password_reset',
            'user_id' => $user->ID,
            'email' => $email,
            'token' => $reset_token
        ), 10);
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'If this email exists, you will receive a password reset link'
        ), 200);
    }
    
    public function reset_password($request) {
        $token = $request->get_param('token');
        $new_password = $request->get_param('password');
        
        // Поиск пользователя
        $users = get_users(array(
            'meta_key' => 'password_reset_token',
            'meta_value' => $token,
            'number' => 1
        ));
        
        if (empty($users)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Invalid reset token'
            ), 400);
        }
        
        $user = $users[0];
        
        // Проверка срока действия
        $expires = get_user_meta($user->ID, 'password_reset_expires', true);
        
        if (time() > $expires) {
            delete_user_meta($user->ID, 'password_reset_token');
            delete_user_meta($user->ID, 'password_reset_expires');
            
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Reset token has expired'
            ), 400);
        }
        
        // Сброс пароля
        wp_set_password($new_password, $user->ID);
        
        // Удаление токена
        delete_user_meta($user->ID, 'password_reset_token');
        delete_user_meta($user->ID, 'password_reset_expires');
        
        // Логирование
        $this->logger->log_user_action($user->ID, 'password_reset', $request);
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Password reset successfully'
        ), 200);
    }
}