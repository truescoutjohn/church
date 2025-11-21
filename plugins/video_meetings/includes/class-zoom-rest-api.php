<?php
/**
 * REST API endpoints для Zoom
 */

class Zoom_REST_API {
    
    private static $instance = null;
    private $namespace = 'zoom/v1';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
        
        // CORS headers
        add_action('rest_api_init', function() {
            remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
            add_filter('rest_pre_serve_request', function($value) {
                header('Access-Control-Allow-Origin: *');
                header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
                header('Access-Control-Allow-Credentials: true');
                header('Access-Control-Allow-Headers: Content-Type, Authorization');
                return $value;
            });
        }, 15);
    }
    
    /**
     * Реєстрація REST endpoints
     */
    public function register_routes() {
        // ✅ GET /wp-json/zoom/v1/config
        // Публічна конфігурація (SDK Key)
        register_rest_route($this->namespace, '/config', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_config'),
            'permission_callback' => '__return_true'
        ));
        
        // ✅ POST /wp-json/zoom/v1/signature
        // Генерація JWT підпису для Web SDK
        register_rest_route($this->namespace, '/signature', array(
            'methods' => 'POST',
            'callback' => array($this, 'get_signature'),
            'permission_callback' => '__return_true',
            'args' => array(
                'meeting_number' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function($param) {
                        return !empty($param) && preg_match('/^\d+$/', $param);
                    }
                ),
                'role' => array(
                    'required' => false,
                    'type' => 'integer',
                    'default' => 0,
                    'sanitize_callback' => 'absint'
                )
            )
        ));

        register_rest_route($this->namespace, '/video-sdk-signature', array(
            'methods' => 'POST',
            'callback' => array($this, 'get_video_sdk_signature'),
            'permission_callback' => '__return_true',
            'args' => array(
                'sessionName' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'role' => array(
                    'required' => false,
                    'type' => 'integer',
                    'default' => 0,
                    'sanitize_callback' => 'absint'
                ),
                'userIdentity' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));
                
        // ✅ POST /wp-json/zoom/v1/meetings
        // Створення зустрічі
        register_rest_route($this->namespace, '/meetings', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_meeting'),
            'permission_callback' => '__return_true', // Або додайте перевірку авторизації
            'args' => array(
                'topic' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'start_time' => array(
                    'required' => false,
                    'type' => 'string'
                ),
                'duration' => array(
                    'required' => false,
                    'type' => 'integer',
                    'default' => 60
                ),
                'password' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));
        
        // ✅ GET /wp-json/zoom/v1/meetings
        // Список зустрічей
        register_rest_route($this->namespace, '/meetings', array(
            'methods' => 'GET',
            'callback' => array($this, 'list_meetings'),
            'permission_callback' => '__return_true'
        ));
        
        // ✅ GET /wp-json/zoom/v1/meetings/{id}
        // Інформація про зустріч
        register_rest_route($this->namespace, '/meetings/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_meeting'),
            'permission_callback' => '__return_true'
        ));
        
        // ✅ DELETE /wp-json/zoom/v1/meetings/{id}
        // Видалення зустрічі
        register_rest_route($this->namespace, '/meetings/(?P<id>\d+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'delete_meeting'),
            'permission_callback' => '__return_true'
        ));

        register_rest_route($this->namespace, '/test-signature', array(
            'methods' => 'POST',
            'callback' => array($this, 'test_signature'),
            'permission_callback' => '__return_true',
            'args' => array(
                'meeting_number' => array(
                    'required' => true,
                    'type' => 'string'
                )
            )
        ));
    }
    
    /**
     * POST /wp-json/zoom/v1/test-signature
     * Тестирование генерации подписи
     */
    public function test_signature($request) {
        $meeting_number = $request->get_param('meeting_number');
        $role = 0;
        
        error_log('=== TESTING SIGNATURE GENERATION ===');
        error_log('Meeting Number: ' . $meeting_number);
        error_log('SDK Key (Client ID): ' . ZOOM_CLIENT_ID);
        error_log('SDK Secret length: ' . strlen(ZOOM_CLIENT_SECRET));
        
        $signature = Zoom_JWT::generate_signature(ZOOM_SDK_KEY, ZOOM_SDK_SECRET, $meeting_number, $role);
        
        // Декодируем JWT для проверки
        $parts = explode('.', $signature);
        
        $header = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        
        return new WP_REST_Response(array(
            'success' => true,
            'data' => array(
                'signature' => $signature,
                'signature_length' => strlen($signature),
                'header' => $header,
                'payload' => $payload,
                'credentials' => array(
                    'sdk_key' => ZOOM_CLIENT_ID,
                    'sdk_secret_set' => !empty(ZOOM_CLIENT_SECRET),
                    'account_id_set' => !empty(ZOOM_ACCOUNT_ID)
                )
            )
        ), 200);
    }

    /**
     * POST /wp-json/zoom/v1/video-sdk-signature
     * Генерация подписи для Video SDK
     */
    public function get_video_sdk_signature($request) {
        $session_name = $request->get_param('sessionName');
        $role = $request->get_param('role');
        $user_identity = $request->get_param('userIdentity');
        
        try {
            $signature = Zoom_Video_SDK_JWT::generate_signature(
                ZOOM_SDK_KEY, 
                ZOOM_SDK_SECRET, 
                $session_name, 
                $role,
                $user_identity
            );
            
            return new WP_REST_Response(array(
                'success' => true,
                'data' => array(
                    'signature' => $signature,
                    'sessionName' => $session_name
                )
            ), 200);
            
        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Failed to generate signature',
                'error' => $e->getMessage()
            ), 500);
        }
    }

    /**
     * GET /wp-json/zoom/v1/config
     */
    public function get_config($request) {
        return new WP_REST_Response(array(
            'success' => true,
            'data' => array(
                'sdk_key' => ZOOM_SDK_KEY,
                'sdk_version' => '2.18.2'
            )
        ), 200);
    }
    
    /**
     * POST /wp-json/zoom/v1/signature
     */
    public function get_signature($request) {
        $meeting_number = $request->get_param('meeting_number');
        $role = $request->get_param('role');
        
        try {
            $signature = Zoom_JWT::generate_signature(ZOOM_SDK_KEY, ZOOM_SDK_SECRET, $meeting_number, $role);
            
            return new WP_REST_Response(array(
                'success' => true,
                'data' => array(
                    'signature' => $signature,
                    'meeting_number' => $meeting_number
                )
            ), 200);
            
        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Failed to generate signature',
                'error' => $e->getMessage()
            ), 500);
        }
    }
    
    /**
     * POST /wp-json/zoom/v1/meetings
     */
    // В class-zoom-rest-api.php, метод create_meeting

    public function create_meeting($request) {
        $topic = $request->get_param('topic');
        $start_time = $request->get_param('start_time');
        $duration = $request->get_param('duration');
        $password = $request->get_param('password');
        
        $meeting_data = array(
            'topic' => $topic,
            'type' => $start_time ? 2 : 1,
            'duration' => $duration,
            'timezone' => 'Europe/Kiev',
            'settings' => array(
                'host_video' => true,
                'participant_video' => true,
                'join_before_host' => true, // ✅ Разрешить присоединение до хоста
                'mute_upon_entry' => true,
                'waiting_room' => false, // ✅ Отключить комнату ожидания
                'audio' => 'both',
                'auto_recording' => 'none',
                'meeting_authentication' => false
            )
        );
        
        if ($start_time) {
            $meeting_data['start_time'] = $start_time;
        }
        
        if ($password) {
            $meeting_data['password'] = $password;
        }
        
        $result = Zoom_OAuth::create_meeting($meeting_data);
        
        if (is_wp_error($result)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => $result->get_error_message()
            ), 500);
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'data' => array(
                'id' => $result['id'],
                'topic' => $result['topic'],
                'join_url' => $result['join_url'],
                'start_url' => $result['start_url'],
                'password' => $result['password'] ?? '',
                'start_time' => $result['start_time'] ?? null
            )
        ), 201);
    }
    
    /**
     * GET /wp-json/zoom/v1/meetings
     */
    public function list_meetings($request) {
        $result = Zoom_OAuth::list_meetings();
        
        if (is_wp_error($result)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => $result->get_error_message()
            ), 500);
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'data' => $result['meetings'] ?? array()
        ), 200);
    }
    
    /**
     * GET /wp-json/zoom/v1/meetings/{id}
     */
    public function get_meeting($request) {
        $meeting_id = $request->get_param('id');
        
        $result = Zoom_OAuth::get_meeting($meeting_id);
        
        if (is_wp_error($result)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => $result->get_error_message()
            ), 500);
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'data' => $result
        ), 200);
    }
    
    /**
     * DELETE /wp-json/zoom/v1/meetings/{id}
     */
    public function delete_meeting($request) {
        $meeting_id = $request->get_param('id');
        
        $result = Zoom_OAuth::delete_meeting($meeting_id);
        
        if (is_wp_error($result)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => $result->get_error_message()
            ), 500);
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Meeting deleted'
        ), 200);
    }
}