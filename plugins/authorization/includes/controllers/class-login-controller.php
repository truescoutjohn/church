<?php
/**
 * Контроллер входа в систему
 */

require_once __DIR__ . '/../managers/class-auth-token-manager.php';
require_once __DIR__ . '/../services/class-auth-logger.php';
require_once __DIR__ . '/../services/class-auth-rate-limit.php';

class Auth_Login_Controller {
    
    private static $instance = null;
    private $token_manager = null;
    private $logger = null;
    private $rate_limiter = null;
    private $redis = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->redis = WP_Redis_Manager::get_instance();
        $this->token_manager = Auth_Token_Manager::get_instance();
        $this->logger = Auth_Logger::get_instance();
        $this->rate_limiter = Auth_Rate_Limiter::get_instance();
    }
    
    public function register_routes($namespace) {
        // Login
        register_rest_route($namespace, '/login', array(
            'methods' => 'POST',
            'callback' => array($this, 'login_user'),
            'permission_callback' => '__return_true',
            'args' => array(
                'email' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_email'
                ),
                'password' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'remember' => array(
                    'required' => false,
                    'type' => 'boolean',
                    'default' => false
                )
            )
        ));

        register_rest_route($namespace, '/finally-login', array(
            'methods' => 'POST',
            'callback' => array($this, 'finally_login'),
            'permission_callback' => '__return_true'
        ));
        
        // Logout
        register_rest_route($namespace, '/logout', array(
            'methods' => 'POST',
            'callback' => array($this, 'logout_user'),
            'permission_callback' => array($this->token_manager, 'check_authentication')
        ));
        
        // Current user
        register_rest_route($namespace, '/me', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_current_user'),
            'permission_callback' => array($this->token_manager, 'check_authentication')
        ));
    }

    public function finally_login($request){
        if(!$this->is_checked_otp($request)){
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Invalid email or wrong otp'
            ), 400);
        }
        $email = $request->get_json_params()['email'];
        $user = get_user_by('email', $email);
        // Сброс счетчика попыток
        $this->rate_limiter->reset_login_attempts($email);
        
        // Генерация токена
        $auth_token = $this->token_manager->generate_auth_token($user->ID, $remember);
        
        // Логирование
        $this->logger->log_user_action($user->ID, 'login', $request);
        
        // Уведомление о входе
        $this->queue_login_notification($user->ID);
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Login successful',
            'data' => array(
                'user_id' => $user->ID,
                'username' => $user->user_login,
                'email' => $user->user_email,
                'display_name' => $user->display_name,
                'token' => $auth_token,
                'email_verified' => (bool) get_user_meta($user->ID, 'email_verified', true)
            )
        ), 200);
    }

    public function login_user($request) {
        $email = $request->get_param('email');
        $password = $request->get_param('password');
        $remember = $request->get_param('remember');
        
        // Проверка rate limit
        if (!$this->rate_limiter->check_login_attempts($email)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Too many login attempts. Please try again later.'
            ), 429);
        }
        
        // Получение пользователя
        $user = get_user_by('email', $email);
        
        if (!$user || !wp_check_password($password, $user->user_pass, $user->ID)) {
            $this->rate_limiter->increment_login_attempts($email);
            
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Invalid email or password'
            ), 401);
        }
        
        $otp = $this->get_otp();

        $this->redis->set('user: ' . $email, $otp, 600);

        $this->queue_send_otp($user->ID, $email, $otp);

        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Otp was sent'
        ), 200);
    }
    
    public function logout_user($request) {
        $user_id = $this->token_manager->get_user_id_from_token($request);
        
        if (!$user_id) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Invalid token'
            ), 401);
        }
        
        // Удаление токена
        $this->token_manager->revoke_token($request);
        
        // Логирование
        $this->logger->log_user_action($user_id, 'logout', $request);
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Logout successful'
        ), 200);
    }
    
    public function get_current_user($request) {
        $user_id = $this->token_manager->get_user_id_from_token($request);
        
        if (!$user_id) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Invalid token'
            ), 401);
        }
        
        $user = get_userdata($user_id);
        
        if (!$user) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'User not found'
            ), 404);
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'data' => array(
                'id' => $user->ID,
                'username' => $user->user_login,
                'email' => $user->user_email,
                'display_name' => $user->display_name,
                'first_name' => get_user_meta($user->ID, 'first_name', true),
                'last_name' => get_user_meta($user->ID, 'last_name', true),
                'email_verified' => (bool) get_user_meta($user->ID, 'email_verified', true),
                'registered' => $user->user_registered,
                'roles' => $user->roles
            )
        ), 200);
    }
    
    private function queue_login_notification($user_id) {
        wp_redis_queue_push_delayed('notifications', array(
            'type' => 'login_notification',
            'user_id' => $user_id,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT']
        ), 60, 1);
    }

    private function queue_send_otp($user_id, $email, $otp) {
        wp_redis_queue_push('emails', array(
            'type' => 'send_otp',
            'user_id' => $user_id,
            'otp' => $otp,
            'email' => $email
        ));
    }

    private function get_otp(){
        return rand(pow(10, 5), pow(10, 6) - 1);
    }

    private function is_checked_otp($request){
        $data = $request->get_json_params($request);
        if(empty($data['email']) || empty($data['otp'])){
            return false;
        }

        $key = 'user: ' . $data['email']; 
        $otp = $this->redis->get($key);
        
        if(empty($otp)){
            return false;
        }
        
        return $otp === $data['otp'];
    }
}