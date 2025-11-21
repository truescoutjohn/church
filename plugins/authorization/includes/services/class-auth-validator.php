<?php
/**
 * Валидатор данных
 */
class Auth_Validator {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function validate_registration($email, $password, $username) {
        // Проверка email
        if (email_exists($email)) {
            return new WP_Error('email_exists', 'Email already exists');
        }
        
        // Проверка username
        if (!empty($username) && username_exists($username)) {
            return new WP_Error('username_exists', 'Username already exists');
        }
        
        // Проверка пароля
        if (strlen($password) < 8) {
            return new WP_Error('weak_password', 'Password must be at least 8 characters');
        }
        
        return true;
    }
}