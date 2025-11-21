<?php
/**
 * Контроллер верификации
 */
require_once __DIR__ . '/../services/class-auth-logger.php';

class Auth_Verification_Controller {
    
    private static $instance = null;
    private $logger = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->logger = Auth_Logger::get_instance();
    }
    
    public function register_routes($namespace) {
        register_rest_route($namespace, '/verify-email', array(
            'methods' => 'POST',
            'callback' => array($this, 'verify_email'),
            'permission_callback' => '__return_true',
            'args' => array(
                'token' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));
    }
    
    public function verify_email($request) {
        $token = $request->get_param('token');
        
        // Поиск пользователя
        $users = get_users(array(
            'meta_key' => 'email_verification_token',
            'meta_value' => $token,
            'number' => 1
        ));
        
        if (empty($users)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Invalid verification token'
            ), 400);
        }
        
        $user = $users[0];
        
        // Обновление статуса
        update_user_meta($user->ID, 'email_verified', true);
        delete_user_meta($user->ID, 'email_verification_token');
        
        // Логирование
        $this->logger->log_user_action($user->ID, 'email_verified', $request);
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Email verified successfully'
        ), 200);
    }
}