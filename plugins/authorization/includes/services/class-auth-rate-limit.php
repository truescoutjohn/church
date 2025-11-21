<?php
/**
 * Rate Limiter для защиты от брутфорса
 */
class Auth_Rate_Limiter {
    
    private static $instance = null;
    private $redis = null;
    private $max_attempts = 5;
    private $lockout_time = 900; // 15 минут
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->redis = WP_Redis_Manager::get_instance();
    }
    
    public function check_login_attempts($email) {
        $key = $this->get_rate_limit_key($email);
        $attempts = (int) $this->redis->get($key);
        
        return $attempts < $this->max_attempts;
    }
    
    public function increment_login_attempts($email) {
        $key = $this->get_rate_limit_key($email);
        $attempts = (int) $this->redis->get($key);
        
        $this->redis->set($key, $attempts + 1, $this->lockout_time);
    }
    
    public function reset_login_attempts($email) {
        $key = $this->get_rate_limit_key($email);
        $this->redis->delete($key);
    }
    
    private function get_rate_limit_key($email) {
        return 'login_attempts:' . md5($email . $_SERVER['REMOTE_ADDR']);
    }
}