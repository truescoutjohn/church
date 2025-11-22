<?php
/**
 * Redis Manager для WordPress
 * Файл: wp-content/mu-plugins/redis-manager.php или в functions.php
 */

class WP_Redis_Manager {
    
    private static $instance = null;
    private $redis = null;
    private $connected = false;
    
    // Конфигурация
    private $config = [
        'host' => 'redis',
        'port' => 6379,
        'password' => null,
        'database' => 0,
        'timeout' => 2.5,
        'read_timeout' => 2.5,
        'prefix' => 'wp_'
    ];
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Загрузка конфигурации из wp-config.php
        if (defined('WP_REDIS_HOST')) {
            $this->config['host'] = WP_REDIS_HOST;
        }
        if (defined('WP_REDIS_PORT')) {
            $this->config['port'] = WP_REDIS_PORT;
        }
        if (defined('WP_REDIS_PASSWORD')) {
            $this->config['password'] = WP_REDIS_PASSWORD;
        }
        if (defined('WP_REDIS_DATABASE')) {
            $this->config['database'] = WP_REDIS_DATABASE;
        }
        if (defined('WP_REDIS_PREFIX')) {
            $this->config['prefix'] = WP_REDIS_PREFIX;
        }
        
        $this->connect();
    }
    
    /**
     * Подключение к Redis
     */
    private function connect() {
        if (!class_exists('Redis')) {
            error_log('Redis PHP extension is not installed');
            return false;
        }
        
        try {
            $this->redis = new Redis();
            
            $connected = $this->redis->connect(
                $this->config['host'],
                $this->config['port'],
                $this->config['timeout']
            );
            
            if (!$connected) {
                throw new Exception('Could not connect to Redis');
            }
            
            // Установка таймаута чтения
            $this->redis->setOption(Redis::OPT_READ_TIMEOUT, $this->config['read_timeout']);
            
            // Аутентификация
            if ($this->config['password']) {
                if (!$this->redis->auth($this->config['password'])) {
                    throw new Exception('Redis authentication failed');
                }
            }
            
            // Выбор базы данных
            $this->redis->select($this->config['database']);
            
            $this->connected = true;
            
            return true;
            
        } catch (Exception $e) {
            error_log('Redis connection error: ' . $e->getMessage());
            $this->connected = false;
            return false;
        }
    }
    
    /**
     * Проверка подключения
     */
    public function is_connected() {
        return $this->connected && $this->redis !== null;
    }
    
    /**
     * Получение Redis клиента
     */
    public function get_client() {
        return $this->is_connected() ? $this->redis : null;
    }
    
    /**
     * Добавление префикса к ключу
     */
    private function prefix_key($key) {
        return $this->config['prefix'] . $key;
    }
    
    /**
     * Установка значения
     */
    public function set($key, $value, $expiration = 0) {
        if (!$this->is_connected()) {
            return false;
        }
        
        try {
            $key = $this->prefix_key($key);
            
            if ($expiration > 0) {
                return $this->redis->setex($key, $expiration, $value);
            }
            
            return $this->redis->set($key, $value);
            
        } catch (Exception $e) {
            error_log('Redis set error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Получение значения
     */
    public function get($key = null) {
        if (!$this->is_connected() || !$key) {
            return false;
        }
        
        try {
            $key = $this->prefix_key($key);
            return $this->redis->get($key);
            
        } catch (Exception $e) {
            error_log('Redis get error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Удаление ключа
     */
    public function delete($key) {
        if (!$this->is_connected()) {
            return false;
        }
        
        try {
            $key = $this->prefix_key($key);
            return $this->redis->del($key);
            
        } catch (Exception $e) {
            error_log('Redis delete error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Проверка существования ключа
     */
    public function exists($key) {
        if (!$this->is_connected()) {
            return false;
        }
        
        try {
            $key = $this->prefix_key($key);
            return $this->redis->exists($key);
            
        } catch (Exception $e) {
            error_log('Redis exists error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Инкремент
     */
    public function increment($key, $by = 1) {
        if (!$this->is_connected()) {
            return false;
        }
        
        try {
            $key = $this->prefix_key($key);
            return $this->redis->incrBy($key, $by);
            
        } catch (Exception $e) {
            error_log('Redis increment error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Декремент
     */
    public function decrement($key, $by = 1) {
        if (!$this->is_connected()) {
            return false;
        }
        
        try {
            $key = $this->prefix_key($key);
            return $this->redis->decrBy($key, $by);
            
        } catch (Exception $e) {
            error_log('Redis decrement error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Очистка базы данных
     */
    public function flush_db() {
        if (!$this->is_connected()) {
            return false;
        }
        
        try {
            return $this->redis->flushDB();
            
        } catch (Exception $e) {
            error_log('Redis flush error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Информация о Redis
     */
    public function info() {
        if (!$this->is_connected()) {
            return false;
        }
        
        try {
            return $this->redis->info();
            
        } catch (Exception $e) {
            error_log('Redis info error: ' . $e->getMessage());
            return false;
        }
    }
}

// Инициализация
WP_Redis_Manager::get_instance();

/**
 * Helper функции
 */
function wp_redis() {
    return WP_Redis_Manager::get_instance();
}

function wp_redis_set($key, $value, $expiration = 0) {
    return wp_redis()->set($key, json_encode($value), $expiration);
}

function wp_redis_get($key) {
    return wp_redis()->get($key);
}

function wp_redis_delete($key) {
    return wp_redis()->delete($key);
}

wp_redis();