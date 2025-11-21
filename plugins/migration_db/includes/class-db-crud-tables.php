<?php
if(defined('ABSPATH') === false){
    exit;
}

define('START_TABLE', 'CREATE TABLE IF NOT EXISTS ');

class DB_Insertion{
    private $file;

    public function __construct() {
        $file_path = plugin_dir_path(__FILE__) . '/../data/tables.sql';
        $this->file =file_get_contents($file_path);
    }

    public function insert_tables() {
        // Validation file if not empty
        if (empty($this->file)) {
            wp_send_json_error(['message' => 'SQL файл пустой']);
            return;
        }

        $tables = explode( ';', $this->file );

        // Validation tables if not empty
        if (empty($tables)) {
            wp_send_json_error(['message' => 'Таблицы не найдены']);
            return;
        }

        global $wpdb;

        foreach ( $tables as $table ) {
            $trimmed_table = trim( $table );
            
            if ( empty( $trimmed_table ) ) {
                continue;
            }

            $wpdb->query($trimmed_table . ';');
        }
    }

    /**
     * Удаление таблиц
     */
    public function delete_tables() {
        global $wpdb;
        
        // Валидация
        if (empty($this->file)) {
            wp_send_json_error(['message' => 'SQL файл пустой']);
            return;
        }
        
        // Извлекаем названия таблиц
        preg_match_all('/CREATE TABLE(?:\s+IF NOT EXISTS)?\s+`?(\w+)`?/i', $this->file, $matches);
        $tables = array_unique($matches[1]);
        
        if (empty($tables)) {
            wp_send_json_error(['message' => 'Таблицы не найдены']);
            return;
        }
        
        // Удаляем таблицы
        $wpdb->query('SET FOREIGN_KEY_CHECKS = 0');

        $results = array_map(function($table) use ($wpdb) {
            return $wpdb->query("DROP TABLE IF EXISTS `{$table}`") !== false;
        }, $tables);
        
        $wpdb->query('SET FOREIGN_KEY_CHECKS = 1');
        
        // Результат
        $success_count = count(array_filter($results));
    }
}