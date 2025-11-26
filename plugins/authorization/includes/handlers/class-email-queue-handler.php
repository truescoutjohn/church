<?php
/**
 * Обработчик email очередей для Auth
 * ОДНА ОЧЕРЕДЬ: 'emails' для всего процесса
 */

if (!defined('ABSPATH')) {
    exit;
}

class Auth_Email_Queue_Handler {
    
    private static $instance = null;
    private $queue = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->queue = WP_Redis_Queue::get_instance();
        $this->register_handlers();
    }
    
    /**
     * Регистрация обработчиков для разных типов email
     */
    public function register_handlers() {
        add_action('redis_queue_job_send_welcome_email', [$this, 'handle_welcome_email'], 10, 2);
        add_action('redis_queue_job_send_verification_email', [$this, 'handle_verification_email'], 10, 2);
        add_action('redis_queue_job_send_password_reset', [$this, 'handle_password_reset'], 10, 2);
        add_action('redis_queue_job_send_otp', [$this, 'handle_send_otp'], 10, 2);
        add_action('redis_queue_job_login_notification', [$this, 'handle_login_notification'], 10, 2);
        
        // НОВЫЙ: обработчик для готовых email
        add_action('redis_queue_job_ready_email', [$this, 'handle_ready_email'], 10, 2);
    }
    
    /**
     * ЕДИНСТВЕННЫЙ МЕТОД для обработки очереди 'emails'
     */
    public function process_all($limit = 100) {
        $processed = 0;
        
        while ($processed < $limit && $this->queue->size('emails') > 0) {
            $job = $this->queue->pop('emails');
            
            if (!$job) {
                break;
            }
            
            $job_data = json_decode($job['data'], true);
            
            if (!isset($job_data['type'])) {
                error_log("Job type not specified in emails queue");
                $this->queue->complete($job);
                continue;
            }
            
            try {
                // Вызываем соответствующий обработчик через хук
                $hook_name = 'redis_queue_job_' . $job_data['type'];
                do_action($hook_name, $job_data, ['queue' => 'emails', 'job' => $job]);
                
                // Задача успешно обработана
                $this->queue->complete($job);
                $processed++;
                
                error_log("Processed email job: {$job_data['type']}");
                
            } catch (Exception $e) {
                error_log("Error processing email job: " . $e->getMessage());
                // Вернуть в очередь для повтора
                $this->queue->fail($job, $e->getMessage());
            }
            
            usleep(10000); // 0.01 сек
        }
        
        return $processed;
    }
    
    /**
     * Отправка через SendPulse API
     */
    private function send_via_sendpulse($to, $subject, $message) {
        if (!class_exists('SendPulse_API')) {
            error_log('SendPulse_API class not found');
            return false;
        }
        
        try {
            $result = SendPulse_API::sendEmail($to, $subject, $message);
            
            if (is_wp_error($result)) {
                error_log('SendPulse error: ' . $result->get_error_message());
                return false;
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log('SendPulse exception: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Добавить готовое письмо в ту же очередь 'emails' с типом 'ready_email'
     */
    private function queue_email($to, $subject, $message) {
        // Добавляем готовый email в ту же очередь 'emails' с типом 'ready_email'
        $result = $this->queue->push('emails', json_encode([
            'type' => 'ready_email',  // Специальный тип для готовых писем
            'to' => $to,
            'subject' => $subject,
            'message' => $message,
            'attempts' => 0,
            'created_at' => time()
        ]), 100); // Высокий приоритет для отправки
        
        if ($result) {
            error_log("Ready email queued for sending to: {$to}, subject: {$subject}");
        } else {
            error_log("Failed to queue ready email to: {$to}");
        }
        
        return $result;
    }
    
    /**
     * НОВЫЙ ОБРАБОТЧИК: Отправка готового письма
     */
    public function handle_ready_email($job_data, $context) {
        $to = $job_data['to'];
        $subject = $job_data['subject'];
        $message = $job_data['message'];
        
        error_log("Sending ready email to: {$to}");
        
        $success = $this->send_via_sendpulse($to, $subject, $message);
        
        if ($success) {
            error_log("Email sent successfully to {$to}: {$subject}");
        } else {
            throw new Exception("Failed to send email to {$to}");
        }
    }
    
    public function handle_welcome_email($job_data, $context) {
        $email = $job_data['email'];
        $first_name = $job_data['first_name'] ?? '';
        $name = !empty($first_name) ? $first_name : $email;
        
        $subject = 'Добро пожаловать!';
        $message = "
            <h2>Здравствуйте, {$name}!</h2>
            <p>Спасибо за регистрацию на нашем сайте.</p>
            <p>Мы рады видеть вас в нашем сообществе!</p>
        ";
        
        $this->queue_email($email, $subject, $message);
    }
    
    public function handle_verification_email($job_data, $context) {
        $email = $job_data['email'];
        $token = $job_data['token'];
        $verification_url = site_url("/wp-json/auth/v1/verify-email?token={$token}");
        
        $subject = 'Подтверждение email';
        $message = "
            <h2>Подтвердите ваш email</h2>
            <p>Для завершения регистрации, пожалуйста, подтвердите ваш email адрес:</p>
            <p><a href='{$verification_url}' style='display: inline-block; padding: 10px 20px; background-color: #0073aa; color: white; text-decoration: none; border-radius: 5px;'>Подтвердить email</a></p>
        ";
        
        $this->queue_email($email, $subject, $message);
    }
    
    public function handle_password_reset($job_data, $context) {
        $user_id = $job_data['user_id'];
        $email = $job_data['email'];
        $token = $job_data['token'];
        
        $user = get_userdata($user_id);
        $reset_url = site_url("/wp-login.php?action=rp&key={$token}&login=" . rawurlencode($user->user_login));
        
        $subject = 'Сброс пароля';
        $message = "
            <h2>Сброс пароля</h2>
            <p>Вы запросили сброс пароля для вашей учетной записи.</p>
            <p><a href='{$reset_url}' style='display: inline-block; padding: 10px 20px; background-color: #0073aa; color: white; text-decoration: none; border-radius: 5px;'>Сбросить пароль</a></p>
            <p style='margin-top: 20px; color: #d63638;'><strong>Важно:</strong> Ссылка действительна в течение 1 часа.</p>
        ";
        
        $this->queue_email($email, $subject, $message);
    }
    
    public function handle_send_otp($job_data, $context) {
        $email = $job_data['email'];
        $otp = $job_data['otp'];
        
        $subject = 'Ваш код подтверждения';
        $message = "
            <div style='text-align: center; font-family: Arial, sans-serif;'>
                <h2>Код подтверждения входа</h2>
                <p>Введите этот одноразовый код для входа в систему:</p>
                <div style='background-color: #f0f0f0; padding: 20px; margin: 20px 0; border-radius: 5px;'>
                    <h1 style='font-size: 36px; letter-spacing: 8px; margin: 0; color: #0073aa;'>{$otp}</h1>
                </div>
                <p style='color: #d63638;'><strong>Код действителен в течение 10 минуты.</strong></p>
            </div>
        ";
        
        $this->queue_email($email, $subject, $message);
    }
    
    public function handle_login_notification($job_data, $context) {
        $user_id = $job_data['user_id'];
        $ip = $job_data['ip'];
        $user_agent = $job_data['user_agent'];
        
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }
        
        $login_time = current_time('mysql');
        
        $subject = 'Новый вход в систему';
        $message = "
            <h2>Новый вход в вашу учетную запись</h2>
            <p>Здравствуйте, {$user->display_name}!</p>
            <p>Был выполнен вход в вашу учетную запись:</p>
            <ul style='list-style: none; padding: 0;'>
                <li><strong>Время:</strong> {$login_time}</li>
                <li><strong>IP-адрес:</strong> {$ip}</li>
                <li><strong>Устройство:</strong> {$user_agent}</li>
            </ul>
        ";
        
        $this->queue_email($user->user_email, $subject, $message);
    }
    
    /**
     * Статистика очередей
     */
    public function get_queue_stats() {
        return [
            'emails' => $this->queue->size('emails'),
            'total' => $this->queue->size('emails')
        ];
    }
}