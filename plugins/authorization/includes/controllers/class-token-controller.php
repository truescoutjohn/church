<?php
/**
 * Контроллер управления токенами
 */
class Auth_Token_Controller {
    
    private static $instance = null;
    private $token_manager = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->token_manager = Auth_Token_Manager::get_instance();
    }
    
    public function register_routes($namespace) {
        register_rest_route($namespace, '/refresh', array(
            'methods' => 'POST',
            'callback' => array($this, 'refresh_token'),
            'permission_callback' => array($this->token_manager, 'check_authentication')
        ));
    }
    
    public function refresh_token($request) {
        $user_id = $this->token_manager->get_user_id_from_token($request);
        
        if (!$user_id) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Invalid token'
            ), 401);
        }
        
        // Удаление старого токена
        $this->token_manager->revoke_token($request);
        
        // Генерация нового токена
        $new_token = $this->token_manager->generate_auth_token($user_id);
        
        return new WP_REST_Response(array(
            'success' => true,
            'data' => array(
                'token' => $new_token
            )
        ), 200);
    }
}