<?php
/**
 * Базовый класс для REST API аутентификации
 */

require_once __DIR__ . '/handlers/class-email-queue-handler.php';
require_once __DIR__ . '/controllers/class-password-controller.php';
require_once __DIR__ . '/controllers/class-login-controller.php';
require_once __DIR__ . '/controllers/class-registration-controller.php';
require_once __DIR__ . '/controllers/class-token-controller.php';
require_once __DIR__ . '/controllers/class-verification-controller.php';

class Auth_REST_API {
    
    private static $instance = null;
    protected $namespace = 'auth/v1';
    protected $redis = null;
    protected $redis_queue = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->redis = WP_Redis_Manager::get_instance();
        $this->redis_queue = WP_Redis_Queue::get_instance();
        
        add_action('rest_api_init', array($this, 'init'));
    }
    
    public function init() {
        $this->setup_cors();
        $this->register_routes();
        $this->register_queue_handlers();
    }
    
    private function setup_cors() {
        remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
        add_filter('rest_pre_serve_request', function($value) {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token');
            return $value;
        }, 15);
    }
    
    private function register_routes() {
        // Регистрация маршрутов через подклассы
        Auth_Registration_Controller::get_instance()->register_routes($this->namespace);
        Auth_Login_Controller::get_instance()->register_routes($this->namespace);
        Auth_Token_Controller::get_instance()->register_routes($this->namespace);
        Auth_Password_Controller::get_instance()->register_routes($this->namespace);
        Auth_Verification_Controller::get_instance()->register_routes($this->namespace);
    }
    
    private function register_queue_handlers() {
        Auth_Email_Queue_Handler::get_instance()->register_handlers();
    }
}

// Инициализация
Auth_REST_API::get_instance();