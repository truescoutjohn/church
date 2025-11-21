<?php
/**
 * Клас для роботи з Zoom OAuth
 * Server-to-Server OAuth App
 */

class Zoom_OAuth {
    
    private static $token = null;
    private static $token_expires = 0;
    
    /**
     * Отримання OAuth токена
     */
    public static function get_access_token() {
        // Перевіряємо кеш в пам'яті
        if (self::$token && time() < self::$token_expires) {
            return self::$token;
        }
        
        // Перевіряємо transient
        $cached_token = get_transient('zoom_access_token');
        if ($cached_token) {
            self::$token = $cached_token['token'];
            self::$token_expires = $cached_token['expires'];
            return self::$token;
        }
        
        // Запитуємо новий токен
        $url = 'https://zoom.us/oauth/token';
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode(ZOOM_CLIENT_ID . ':' . ZOOM_CLIENT_SECRET),
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'body' => array(
                'grant_type' => 'account_credentials',
                'account_id' => ZOOM_ACCOUNT_ID
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('Zoom OAuth error: ' . $response->get_error_message());
            return false;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($code !== 200 || !isset($body['access_token'])) {
            error_log('Zoom OAuth failed: ' . print_r($body, true));
            return false;
        }
        
        self::$token = $body['access_token'];
        self::$token_expires = time() + $body['expires_in'] - 300; // -5 хвилин запасу
        
        // Зберігаємо в transient
        set_transient('zoom_access_token', array(
            'token' => self::$token,
            'expires' => self::$token_expires
        ), $body['expires_in'] - 300);
        
        error_log('Zoom OAuth: New token received, expires in ' . $body['expires_in'] . ' seconds');
        
        return self::$token;
    }
    
    /**
     * Створення зустрічі через Zoom API
     */
    public static function create_meeting($data) {
        $token = self::get_access_token();
        
        if (!$token) {
            return new WP_Error('no_token', 'Failed to get access token');
        }
        
        $url = 'https://api.zoom.us/v2/users/me/meetings';
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($code !== 201) {
            error_log('Zoom create meeting failed: ' . print_r($body, true));
            return new WP_Error('create_failed', $body['message'] ?? 'Failed to create meeting');
        }
        
        return $body;
    }
    
    /**
     * Отримання інформації про зустріч
     */
    public static function get_meeting($meeting_id) {
        $token = self::get_access_token();
        
        if (!$token) {
            return new WP_Error('no_token', 'Failed to get access token');
        }
        
        $url = "https://api.zoom.us/v2/meetings/{$meeting_id}";
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return json_decode(wp_remote_retrieve_body($response), true);
    }
    
    /**
     * Видалення зустрічі
     */
    public static function delete_meeting($meeting_id) {
        $token = self::get_access_token();
        
        if (!$token) {
            return new WP_Error('no_token', 'Failed to get access token');
        }
        
        $url = "https://api.zoom.us/v2/meetings/{$meeting_id}";
        
        $response = wp_remote_request($url, array(
            'method' => 'DELETE',
            'headers' => array(
                'Authorization' => 'Bearer ' . $token
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        
        return $code === 204;
    }
    
    /**
     * Отримання списку зустрічей
     */
    public static function list_meetings($type = 'scheduled') {
        $token = self::get_access_token();
        
        if (!$token) {
            return new WP_Error('no_token', 'Failed to get access token');
        }
        
        $url = add_query_arg(array(
            'type' => $type,
            'page_size' => 30
        ), 'https://api.zoom.us/v2/users/me/meetings');
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return json_decode(wp_remote_retrieve_body($response), true);
    }
}