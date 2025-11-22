<?php
/**
 * Обработчик email очередей для Auth
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
    }
    
    /**
     * ЕДИНСТВЕННЫЙ МЕТОД для обработки всех email очередей
     * Обрабатывает обе очереди: 'emails' и 'email_notification'
     */
    public function process_all($limit = 100) {
        $total = 0;
        
        // Шаг 1: Обработать задачи из 'emails' (превратить в готовые письма)
        $total += $this->process_email_jobs($limit / 2);
        
        return $total;
    }
    
    /**
     * Обработка очереди 'emails' - превращает задачи в готовые письма
     */
    private function process_email_jobs($limit = 50) {
        $processed = 0;
        
        while ($processed < $limit && $this->queue->size('emails') > 0) {
            $job = $this->queue->pop('emails');
            var_dump($job);
            if (!$job) {
                break;
            }
            
            $job_data = json_decode($job['data'], true);
            
            if (!isset($job_data['type'])) {
                error_log("jobs " . json_encode($job_data));
                error_log("Job type not specified in emails queue");
                $this->queue->complete($job);
                continue;
            }
            
            try {
                // Вызываем соответствующий обработчик через хук
                $hook_name = 'redis_queue_job_' . $job_data['type'];
                do_action($hook_name, $job_data, ['queue' => 'emails']);
                
                // Задача успешно обработана
                $this->queue->complete($job);
                $processed++;
                
            } catch (Exception $e) {
                error_log("Error processing email job: " . $e->getMessage());
                // Вернуть в очередь для повтора
                $this->queue->fail($job, $e->getMessage());
            }
            
            usleep(10000); // 0.01 сек
        }
        
        return $processed;
    }
    
    // /**
    //  * Обработка очереди 'email_notification' - отправка готовых писем
    //  */
    // private function process_email_sending($limit = 50) {
    //     $sent = 0;
        
    //     while ($sent < $limit && $this->queue->size('emails') > 0) {
    //         $job = $this->queue->pop('emails');
            
    //         if (!$job) {
    //             break;
    //         }
            
    //         $email_data = json_decode($job['data'], true);
            
    //         try {
    //             // Отправка через SendPulse
    //             $success = $this->send_via_sendpulse(
    //                 $email_data['to'],
    //                 $email_data['subject'],
    //                 $email_data['message']
    //             );
                
    //             if ($success) {
    //                 $this->queue->complete($job);
    //                 $sent++;
    //                 error_log("Email sent to {$email_data['to']}: {$email_data['subject']}");
    //             } else {
    //                 throw new Exception('SendPulse API failed');
    //             }
                
    //         } catch (Exception $e) {
    //             error_log("Error sending email: " . $e->getMessage());
    //             $this->queue->fail($job, $e->getMessage());
    //         }
            
    //         usleep(100000); // 0.1 сек между отправками
    //     }
        
    //     return $sent;
    // }
    
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
     * Добавить готовое письмо в очередь отправки
     */
    private function queue_email($to, $subject, $message) {
        return $this->queue->push('emails', json_encode([
            'to' => $to,
            'subject' => $subject,
            'message' => $message,
            'attempts' => 0,
            'created_at' => time()
        ]));
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
                <p style='color: #d63638;'><strong>Код действителен в течение 1 минуты.</strong></p>
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
            'email_jobs' => $this->queue->size('emails'),
            'email_sending' => $this->queue->size('email_notification'),
            'total' => $this->queue->size('emails') + $this->queue->size('email_notification')
        ];
    }
}
// class Auth_Email_Queue_Handler {
    
//     private static $instance = null;
//     private $email_notification = null;
//     private $queue = null;
    
//     public static function get_instance() {
//         if (null === self::$instance) {
//             self::$instance = new self();
//         }
//         return self::$instance;
//     }
    
//     private function __construct() {
//         $this->queue = WP_Redis_Queue::get_instance();
//         $this->email_notification = Email_Notification::get_instance($this->queue);
//     }
    
//     /**
//      * Регистрация хуков для обработки задач
//      */
//     public function register_handlers() {
//         add_action('redis_queue_job_send_welcome_email', array($this, 'handle_welcome_email'), 10, 2);
//         add_action('redis_queue_job_send_verification_email', array($this, 'handle_verification_email'), 10, 2);
//         add_action('redis_queue_job_send_password_reset', array($this, 'handle_password_reset_email'), 10, 2);
//         add_action('redis_queue_job_send_otp', array($this, 'handle_send_otp'), 10, 2);
//     }
    
//     /**
//      * НОВЫЙ МЕТОД: Обработка очереди emails
//      * Этот метод достает задачи из очереди и вызывает соответствующие хуки
//      */
//     public function process_emails_queue($limit = 10) {
//         $processed = 0;
        
//         while ($processed < $limit && $this->queue->size('emails') > 0) {
//             $job = $this->queue->pop('emails');
            
//             if (!$job) {
//                 break;
//             }
            
//             $job_data = json_decode($job['data'], true);
            
//             if (!isset($job_data['type'])) {
//                 error_log("Job type not specified in emails queue");
//                 continue;
//             }
            
//             try {
//                 // Формируем имя хука и вызываем его
//                 $hook_name = 'redis_queue_job_' . $job_data['type'];
//                 do_action($hook_name, $job_data, array(
//                     'queue' => 'emails',
//                     'processed_at' => time()
//                 ));
                
//                 $processed++;
//             } catch (Exception $e) {
//                 error_log("Error processing email job: " . $e->getMessage());
                
//                 // Повторная попытка
//                 if (!isset($job_data['attempts'])) {
//                     $job_data['attempts'] = 0;
//                 }
                
//                 if ($job_data['attempts'] < 3) {
//                     $job_data['attempts']++;
//                     $this->queue->push('emails', json_encode($job_data));
//                 }
//             }
//         }
        
//         return $processed;
//     }
    
//     public function handle_welcome_email($job_data, $job) {
//         $user_id = $job_data['user_id'];
//         $email = $job_data['email'];
//         $first_name = $job_data['first_name'] ?? '';
        
//         $result = $this->email_notification->send_welcome_email($user_id, $email, $first_name);
        
//         if (!$result) {
//             error_log("Failed to queue welcome email for user {$user_id}");
//             throw new Exception('Failed to queue welcome email');
//         }
        
//         error_log("Welcome email queued for user {$user_id}");
//     }
    
//     public function handle_verification_email($job_data, $job) {
//         $user_id = $job_data['user_id'];
//         $email = $job_data['email'];
//         $token = $job_data['token'];
        
//         $result = $this->email_notification->send_verification_email($user_id, $email, $token);
        
//         if (!$result) {
//             error_log("Failed to queue verification email for user {$user_id}");
//             throw new Exception('Failed to queue verification email');
//         }
        
//         error_log("Verification email queued for user {$user_id}");
//     }
    
//     public function handle_password_reset_email($job_data, $job) {
//         $user_id = $job_data['user_id'];
//         $email = $job_data['email'];
//         $token = $job_data['token'];
        
//         $result = $this->email_notification->send_password_reset_email($user_id, $email, $token);
        
//         if (!$result) {
//             error_log("Failed to queue password reset email for user {$user_id}");
//             throw new Exception('Failed to queue password reset email');
//         }
        
//         error_log("Password reset email queued for user {$user_id}");
//     }

//     public function handle_send_otp($job_data, $job) {
//         $email = $job_data['email'];
//         $otp = $job_data['otp'];
        
//         $result = $this->email_notification->send_send_otp($email, $otp);
        
//         if (!$result) {
//             error_log("Failed to queue password reset email for user {$user_id}");
//             throw new Exception('Failed to queue password reset email');
//         }
        
//         error_log("Password reset email queued for user {$user_id}");
//     }
// }