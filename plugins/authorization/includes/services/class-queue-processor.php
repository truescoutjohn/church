<?php
/**
 * Обработчик Redis очередей
 */

if (!defined('ABSPATH')) {
    exit;
}

class Queue_Processor {
    
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
    }
    
    /**
     * Обработать очередь emails
     */
    public function process_emails_queue($limit = 10) {
        return $this->process_queue('emails', $limit);
    }
    
    /**
     * Обработать очередь notifications
     */
    public function process_notifications_queue($limit = 10) {
        return $this->process_queue('notifications', $limit);
    }
    
    /**
     * Обработать очередь logs
     */
    public function process_logs_queue($limit = 10) {
        return $this->process_queue('logs', $limit);
    }
    
    /**
     * Обработать очередь email_notification
     */
    public function process_email_notification_queue($limit = 100) {
        $email_worker = Email_Worker::get_instance();
        return $email_worker->process_all();
    }
    
    /**
     * Универсальный обработчик очереди
     */
    private function process_queue($queue_name, $limit = 10) {
        $processed = 0;
        
        while ($processed < $limit && $this->queue->size($queue_name) > 0) {
            $job = $this->queue->pop($queue_name);
            
            if (!$job) {
                break;
            }
            
            $job_data = json_decode($job['data'], true);
            
            try {
                $this->dispatch_job($job_data, $queue_name);
                $processed++;
            } catch (Exception $e) {
                error_log("Queue {$queue_name} processing error: " . $e->getMessage());
                
                // Повторная попытка
                if (isset($job_data['attempts']) && $job_data['attempts'] < 3) {
                    $job_data['attempts'] = isset($job_data['attempts']) ? $job_data['attempts'] + 1 : 1;
                    $this->queue->push($queue_name, json_encode($job_data));
                }
            }
            
            // Пауза между задачами
            usleep(10000); // 0.01 секунды
        }
        
        return $processed;
    }
    
    /**
     * Диспетчеризация задачи по типу
     */
    private function dispatch_job($job_data, $queue_name) {
        if (!isset($job_data['type'])) {
            throw new Exception("Job type not specified");
        }
        
        $job_type = $job_data['type'];
        
        // Формируем имя хука
        $hook_name = 'redis_queue_job_' . $job_type;
        
        // Вызываем хук с данными задачи
        do_action($hook_name, $job_data, array(
            'queue' => $queue_name,
            'processed_at' => time()
        ));
        
        error_log("Processed job: {$job_type} from queue: {$queue_name}");
    }
    
    /**
     * Обработать все очереди
     */
    public function process_all_queues() {
        $total = 0;
        
        $total += $this->process_emails_queue(10);
        $total += $this->process_notifications_queue(10);
        $total += $this->process_logs_queue(10);
        $total += $this->process_email_notification_queue(100);
        
        return $total;
    }
}