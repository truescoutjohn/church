<?php
//Отправка имейла в очередь
class Email_Notification {
    private $queue;
    private static $instance;

    public function __construct(WP_Redis_Queue $queue) {
        $this->queue = $queue;
    }

    public static function get_instance(WP_Redis_Queue $queue){
        if(empty(self::$instance)){
            self::$instance = new self($queue);
            return self::$instance;
        }

        return self::$instance;
    }

    // Добавить письмо в очередь
    public function send_email($to, $subject, $message, $headers = [], $attachments = []) {
        $job = [
            'to' => $to,
            'subject' => $subject,
            'message' => $message,
            'headers' => $headers,
            'attachments' => $attachments,
            'attempts' => 0,
            'created_at' => time()
        ];
        
        return $this->queue->push('email_notification', json_encode($job));
    }

    public function remove_email_from_queue(){
        return $this->queue->pop('email_notification');
    }

    public function get_email_count(){
        return $this->queue->size('email_notification');
    }
}
//Обработчик очереди
class Email_Worker {
    private $notificator;

    public function __construct(Email_Notification $notificator) {
        $this->notificator = $notificator;
    }

    public function process_one(){
        $job = $this->notificator->remove_email_from_queue();
        $data = json_decode($job['data'], true);

        //$success = SendPulse_API::sendEmail($data['to'], $data['subject'], $data['message']);
        
        // Если не отправилось - вернуть в очередь с увеличенным счётчиком
        if (!$success && $job['attempts'] < $this->maxAttempts) {
            $job['attempts']++;
            $this->notificator->send_email($data['to'],
                $data['subject'],
                $data['message'],
                $data['headers'],
                $data['attachments']);
        }
        
        return $success;
    }

    public function process_all() {
        $processed = 0;
        while ($this->notificator->get_email_count() > 0) {
            $this->process_one();
            $processed++;
            
            // Небольшая пауза, чтобы не перегрузить SMTP
            usleep(100000); // 0.1 секунды
        }
        return $processed;
    }
}