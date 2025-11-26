<?php
/**
 * Telegram Notification Worker
 */

if (!defined('ABSPATH')) {
    exit;
}

class Telegram_Notification_Handler {
    
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
     * Обработка очереди уведомлений
     */
    public function process_queue($limit = 50) {
        $processed = 0;
        
        while ($processed < $limit && $this->queue->size('telegram_notifications') > 0) {
            $job = $this->queue->pop('telegram_notifications');
            
            if (!$job) {
                break;
            }
            
            $job_data = json_decode($job['data'], true);
            
            if (!isset($job_data['type'])) {
                error_log("Telegram: Job type not specified");
                $this->queue->complete($job);
                continue;
            }
            
            try {
                // Вызов соответствующего обработчика
                $hook_name = 'redis_queue_job_' . $job_data['type'];
                do_action($hook_name, $job_data, array('queue' => 'telegram_notifications'));
                
                $this->queue->complete($job);
                $processed++;
                
            } catch (Exception $e) {
                error_log("Telegram notification error: " . $e->getMessage());
                $this->queue->fail($job, $e->getMessage());
            }
            
            usleep(50000); // 0.05 сек между отправками
        }
        
        return $processed;
    }
    
    /**
     * Статистика очереди
     */
    public function get_queue_stats() {
        return array(
            'pending' => $this->queue->size('telegram_notifications'),
            'processing' => 0 // можно добавить счетчик обрабатываемых
        );
    }
}