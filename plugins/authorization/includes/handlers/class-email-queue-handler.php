<?php
/**
 * Обработчик email очередей для Auth
 */

if (!defined('ABSPATH')) {
    exit;
}

class Auth_Email_Queue_Handler {
    
    private static $instance = null;
    private $email_notification = null;
    private $queue = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->queue = WP_Redis_Queue::get_instance();
        $this->email_notification = Email_Notification::get_instance($this->queue);
    }
    
    /**
     * Регистрация хуков для обработки задач
     */
    public function register_handlers() {
        add_action('redis_queue_job_send_welcome_email', array($this, 'handle_welcome_email'), 10, 2);
        add_action('redis_queue_job_send_verification_email', array($this, 'handle_verification_email'), 10, 2);
        add_action('redis_queue_job_send_password_reset', array($this, 'handle_password_reset_email'), 10, 2);
        add_action('redis_queue_job_send_otp', array($this, 'handle_send_otp'), 10, 2);
    }
    
    /**
     * НОВЫЙ МЕТОД: Обработка очереди emails
     * Этот метод достает задачи из очереди и вызывает соответствующие хуки
     */
    public function process_emails_queue($limit = 10) {
        $processed = 0;
        
        while ($processed < $limit && $this->queue->size('emails') > 0) {
            $job = $this->queue->pop('emails');
            
            if (!$job) {
                break;
            }
            
            $job_data = json_decode($job['data'], true);
            
            if (!isset($job_data['type'])) {
                error_log("Job type not specified in emails queue");
                continue;
            }
            
            try {
                // Формируем имя хука и вызываем его
                $hook_name = 'redis_queue_job_' . $job_data['type'];
                do_action($hook_name, $job_data, array(
                    'queue' => 'emails',
                    'processed_at' => time()
                ));
                
                $processed++;
            } catch (Exception $e) {
                error_log("Error processing email job: " . $e->getMessage());
                
                // Повторная попытка
                if (!isset($job_data['attempts'])) {
                    $job_data['attempts'] = 0;
                }
                
                if ($job_data['attempts'] < 3) {
                    $job_data['attempts']++;
                    $this->queue->push('emails', json_encode($job_data));
                }
            }
        }
        
        return $processed;
    }
    
    public function handle_welcome_email($job_data, $job) {
        $user_id = $job_data['user_id'];
        $email = $job_data['email'];
        $first_name = $job_data['first_name'] ?? '';
        
        $result = $this->email_notification->send_welcome_email($user_id, $email, $first_name);
        
        if (!$result) {
            error_log("Failed to queue welcome email for user {$user_id}");
            throw new Exception('Failed to queue welcome email');
        }
        
        error_log("Welcome email queued for user {$user_id}");
    }
    
    public function handle_verification_email($job_data, $job) {
        $user_id = $job_data['user_id'];
        $email = $job_data['email'];
        $token = $job_data['token'];
        
        $result = $this->email_notification->send_verification_email($user_id, $email, $token);
        
        if (!$result) {
            error_log("Failed to queue verification email for user {$user_id}");
            throw new Exception('Failed to queue verification email');
        }
        
        error_log("Verification email queued for user {$user_id}");
    }
    
    public function handle_password_reset_email($job_data, $job) {
        $user_id = $job_data['user_id'];
        $email = $job_data['email'];
        $token = $job_data['token'];
        
        $result = $this->email_notification->send_password_reset_email($user_id, $email, $token);
        
        if (!$result) {
            error_log("Failed to queue password reset email for user {$user_id}");
            throw new Exception('Failed to queue password reset email');
        }
        
        error_log("Password reset email queued for user {$user_id}");
    }

    public function handle_send_otp($job_data, $job) {
        $email = $job_data['email'];
        $otp = $job_data['otp'];
        
        $result = $this->email_notification->send_send_otp($email, $otp);
        
        if (!$result) {
            error_log("Failed to queue password reset email for user {$user_id}");
            throw new Exception('Failed to queue password reset email');
        }
        
        error_log("Password reset email queued for user {$user_id}");
    }
}