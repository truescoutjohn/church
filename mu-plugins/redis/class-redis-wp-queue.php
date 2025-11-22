<?php
/**
 * Redis Queue System для WordPress
 * Файл: wp-content/mu-plugins/redis-queue.php
 */
class WP_Redis_Queue {
    
    private static $instance = null;
    private $redis = null;
    
    const QUEUE_PREFIX = 'queue:';
    const PROCESSING_PREFIX = 'processing:';
    const FAILED_PREFIX = 'failed:';
    const JOBS_PREFIX = 'job:';
    const RETRY_PREFIX = 'retry:';
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->redis = WP_Redis_Manager::get_instance()->get_client();
        
        // Регистрация worker через WP-Cron
        add_action('init', [$this, 'register_worker_cron']);
        add_action('redis_queue_worker', [$this, 'process_queue']);
        
        // CLI команда для запуска worker
        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::add_command('redis-queue', [$this, 'cli_commands']);
        }
    }
    
    /**
     * Регистрация cron задачи для обработки очереди
     */
    public function register_worker_cron() {
        if (!wp_next_scheduled('redis_queue_worker')) {
            wp_schedule_event(time(), 'every_minute', 'redis_queue_worker');
        }
    }
    
    /**
     * Добавление задачи в очередь
     */
    public function push($queue_name, $job_data, $priority = 0) {
        if (!$this->redis) {
            error_log('Redis not connected');
            return false;
        }
        
        try {
            $job_id = uniqid('job_', true);
            
            $job = [
                'id' => $job_id,
                'queue' => $queue_name,
                'data' => $job_data,
                'attempts' => 0,
                'max_attempts' => 3,
                'created_at' => time(),
                'priority' => $priority
            ];
            
            // Сохраняем данные задачи
            $job_key = self::JOBS_PREFIX . $job_id;
            $this->redis->setex($job_key, 86400, json_encode($job)); // TTL 24 часа
            
            // Добавляем в очередь с приоритетом
            $queue_key = self::QUEUE_PREFIX . $queue_name;
            $this->redis->zAdd($queue_key, $priority, $job_id);
            
            do_action('redis_queue_job_pushed', $job_id, $queue_name, $job_data);
            
            return $job_id;
            
        } catch (Exception $e) {
            error_log('Redis queue push error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Добавление задачи с задержкой
     */
    public function push_delayed($queue_name, $job_data, $delay_seconds, $priority = 0) {
        if (!$this->redis) {
            return false;
        }
        
        try {
            $job_id = uniqid('job_', true);
            
            $job = [
                'id' => $job_id,
                'queue' => $queue_name,
                'data' => $job_data,
                'attempts' => 0,
                'max_attempts' => 3,
                'created_at' => time(),
                'priority' => $priority,
                'available_at' => time() + $delay_seconds
            ];
            
            // Сохраняем данные задачи
            $job_key = self::JOBS_PREFIX . $job_id;
            $this->redis->setex($job_key, 86400, json_encode($job));
            
            // Добавляем в очередь отложенных задач
            $delayed_key = self::QUEUE_PREFIX . $queue_name . ':delayed';
            $this->redis->zAdd($delayed_key, time() + $delay_seconds, $job_id);
            
            return $job_id;
            
        } catch (Exception $e) {
            error_log('Redis delayed queue push error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Получение задачи из очереди
     */
    public function pop($queue_name, $timeout = 0) {
        if (!$this->redis) {
            return null;
        }
        
        try {
            // Перемещаем готовые отложенные задачи в основную очередь
            $this->move_delayed_jobs($queue_name);
            
            $queue_key = self::QUEUE_PREFIX . $queue_name;
            
            if ($timeout > 0) {
                // Блокирующее получение с таймаутом
                $result = $this->redis->bzPopMin($queue_key, $timeout);
            } else {
                // Неблокирующее получение
                $result = $this->redis->zPopMin($queue_key);
            }
            
            if (empty($result)) {
                return null;
            }
            
            $job_id = is_array($result) ? array_keys($result)[0] : $result;
            
            // Получаем данные задачи
            $job_key = self::JOBS_PREFIX . $job_id;
            $job_data = $this->redis->get($job_key);
            
            if (!$job_data) {
                return null;
            }
            
            $job = json_decode($job_data, true);
            
            // Перемещаем в "обрабатывается"
            $processing_key = self::PROCESSING_PREFIX . $queue_name;
            $this->redis->zAdd($processing_key, time() + 300, $job_id); // TTL 5 минут
            
            return $job;
            
        } catch (Exception $e) {
            error_log('Redis queue pop error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Перемещение отложенных задач
     */
    private function move_delayed_jobs($queue_name) {
        try {
            $delayed_key = self::QUEUE_PREFIX . $queue_name . ':delayed';
            $queue_key = self::QUEUE_PREFIX . $queue_name;
            $current_time = time();
            
            // Получаем готовые задачи
            $ready_jobs = $this->redis->zRangeByScore($delayed_key, 0, $current_time);
            
            foreach ($ready_jobs as $job_id) {
                // Получаем приоритет из данных задачи
                $job_key = self::JOBS_PREFIX . $job_id;
                $job_data = $this->redis->get($job_key);
                
                if ($job_data) {
                    $job = json_decode($job_data, true);
                    $priority = $job['priority'] ?? 0;
                    
                    // Перемещаем в основную очередь
                    $this->redis->zAdd($queue_key, $priority, $job_id);
                    $this->redis->zRem($delayed_key, $job_id);
                }
            }
            
        } catch (Exception $e) {
            error_log('Redis move delayed jobs error: ' . $e->getMessage());
        }
    }
    
    /**
     * Завершение задачи (успешно)
     */
    public function complete($job) {
        if (!$this->redis || !isset($job['id'])) {
            return false;
        }
        
        try {
            $job_id = $job['id'];
            $queue_name = $job['queue'];
            
            // Удаляем из "обрабатывается"
            $processing_key = self::PROCESSING_PREFIX . $queue_name;
            $this->redis->zRem($processing_key, $job_id);
            
            // Удаляем данные задачи
            $job_key = self::JOBS_PREFIX . $job_id;
            $this->redis->del($job_key);
            
            do_action('redis_queue_job_completed', $job_id, $queue_name);
            
            return true;
            
        } catch (Exception $e) {
            error_log('Redis queue complete error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Провал задачи с возможностью повтора
     */
    public function fail($job, $error_message = '') {
        if (!$this->redis || !isset($job['id'])) {
            return false;
        }
        
        try {
            $job_id = $job['id'];
            $queue_name = $job['queue'];
            
            // Увеличиваем счетчик попыток
            $job['attempts']++;
            $job['last_error'] = $error_message;
            $job['last_attempt_at'] = time();
            
            // Удаляем из "обрабатывается"
            $processing_key = self::PROCESSING_PREFIX . $queue_name;
            $this->redis->zRem($processing_key, $job_id);
            
            // Проверяем количество попыток
            if ($job['attempts'] >= $job['max_attempts']) {
                // Перемещаем в провалившиеся
                $failed_key = self::FAILED_PREFIX . $queue_name;
                $this->redis->zAdd($failed_key, time(), $job_id);
                
                // Обновляем данные задачи
                $job_key = self::JOBS_PREFIX . $job_id;
                $this->redis->setex($job_key, 604800, json_encode($job)); // TTL 7 дней
                
                do_action('redis_queue_job_failed', $job_id, $queue_name, $error_message);
            } else {
                // Возвращаем в очередь с задержкой (exponential backoff)
                $delay = pow(2, $job['attempts']) * 60; // 2min, 4min, 8min...
                
                // Обновляем данные задачи
                $job_key = self::JOBS_PREFIX . $job_id;
                $this->redis->setex($job_key, 86400, json_encode($job));
                
                // Добавляем в отложенные
                $delayed_key = self::QUEUE_PREFIX . $queue_name . ':delayed';
                $this->redis->zAdd($delayed_key, time() + $delay, $job_id);
                
                do_action('redis_queue_job_retrying', $job_id, $queue_name, $job['attempts']);
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log('Redis queue fail error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Обработать задачу из очереди
     */
    public function process_job($queue_name, $job_data) {
        // Получаем тип задачи
        $job_type = isset($job_data['type']) ? $job_data['type'] : null;
        
        if (!$job_type) {
            error_log("Job type not specified in queue {$queue_name}");
            return false;
        }
        
        // Формируем имя хука: redis_queue_job_{type}
        $hook_name = 'redis_queue_job_' . $job_type;
        
        // Вызываем хук
        do_action($hook_name, $job_data, array(
            'queue' => $queue_name,
            'processed_at' => time()
        ));
        
        return true;
    }
    
    /**
     * Обработать все задачи из очереди
     */
    public function process_queue($queue_name, $limit = 10) {
        $processed = 0;
        
        while ($processed < $limit) {
            $job = $this->pop($queue_name);
            
            if (!$job) {
                break; // Очередь пуста
            }
            
            $job_data = json_decode($job['data'], true);
            
            try {
                $this->process_job($queue_name, $job_data);
                $processed++;
            } catch (Exception $e) {
                error_log("Queue processing error: " . $e->getMessage());
                
                // Можно вернуть задачу в очередь для повтора
                if (isset($job_data['attempts']) && $job_data['attempts'] < 3) {
                    $job_data['attempts']++;
                    $this->push($queue_name, json_encode($job_data));
                }
            }
        }
        
        return $processed;
    }
    
    /**
     * Получение размера очереди
     */
    public function size($queue_name) {
        if (!$this->redis) {
            return 0;
        }
        
        try {
            $queue_key = self::QUEUE_PREFIX . $queue_name;
            return $this->redis->zCard($queue_key);
            
        } catch (Exception $e) {
            error_log('Redis queue size error: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Очистка очереди
     */
    public function clear($queue_name) {
        if (!$this->redis) {
            return false;
        }
        
        try {
            $queue_key = self::QUEUE_PREFIX . $queue_name;
            $delayed_key = self::QUEUE_PREFIX . $queue_name . ':delayed';
            $processing_key = self::PROCESSING_PREFIX . $queue_name;
            $failed_key = self::FAILED_PREFIX . $queue_name;
            
            $this->redis->del($queue_key);
            $this->redis->del($delayed_key);
            $this->redis->del($processing_key);
            $this->redis->del($failed_key);
            
            return true;
            
        } catch (Exception $e) {
            error_log('Redis queue clear error: ' . $e->getMessage());
            return false;
        }
    }
}

// Инициализация
WP_Redis_Queue::get_instance();

/**
 * Helper функции
 */
function wp_redis_queue_push($queue, $job_data, $priority = 0) {
    return WP_Redis_Queue::get_instance()->push($queue, json_encode($job_data), $priority);
}

function wp_redis_queue_push_delayed($queue, $job_data, $delay, $priority = 0) {
    return WP_Redis_Queue::get_instance()->push_delayed($queue, json_encode($job_data), $delay, $priority);
}

function wp_redis_queue_size($queue) {
    return WP_Redis_Queue::get_instance()->size($queue);
}