<?php
/**
 * Telegram API класс
 */

if (!defined('ABSPATH')) {
    exit;
}

class Telegram_API {
    
    private static $instance = null;
    private $bot_token = null;
    private $api_url = 'https://api.telegram.org/bot';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->bot_token = get_option('telegram_bot_token');
    }
    
    /**
     * Отправка сообщения
     */
    public function send_message($chat_id, $text, $parse_mode = 'HTML', $reply_markup = null) {
        if (empty($this->bot_token)) {
            error_log('Telegram: Bot token not configured');
            return false;
        }
        
        $data = array(
            'chat_id' => $chat_id,
            'text' => $text,
            'parse_mode' => $parse_mode
        );
        
        if ($reply_markup) {
            $data['reply_markup'] = json_encode($reply_markup);
        }
        
        return $this->make_request('sendMessage', $data);
    }
    
    /**
     * Отправка OTP кода
     */
    public function send_otp($chat_id, $otp, $action = 'login') {
        $action_text = array(
            'login' => '🔐 Вход в систему',
            'register' => '✅ Регистрация',
            'reset_password' => '🔑 Сброс пароля'
        );
        
        $message = "<b>{$action_text[$action]}</b>\n\n";
        $message .= "Ваш одноразовый код:\n";
        $message .= "<code>{$otp}</code>\n\n";
        $message .= "⏱ Код действителен 5 минут\n";
        $message .= "❌ Если это были не вы, проигнорируйте это сообщение";
        
        return $this->send_message($chat_id, $message);
    }
    
    /**
     * Отправка уведомления о входе
     */
    public function send_login_notification($chat_id, $ip, $user_agent, $location = '') {
        $message = "🔔 <b>Новый вход в систему</b>\n\n";
        $message .= "📍 IP: <code>{$ip}</code>\n";
        $message .= "🖥 Устройство: {$user_agent}\n";
        
        if ($location) {
            $message .= "🌍 Местоположение: {$location}\n";
        }
        
        $message .= "\n⏰ " . current_time('d.m.Y H:i:s');
        
        return $this->send_message($chat_id, $message);
    }
    
    /**
     * Установка webhook
     */
    public function set_webhook($url) {
        return $this->make_request('setWebhook', array(
            'url' => $url,
            'allowed_updates' => json_encode(['message', 'callback_query'])
        ));
    }
    
    /**
     * Удаление webhook
     */
    public function delete_webhook() {
        return $this->make_request('deleteWebhook');
    }
    
    /**
     * Получить информацию о боте
     */
    public function get_me() {
        return $this->make_request('getMe');
    }
    
    /**
     * Создание inline клавиатуры для авторизации
     */
    public function create_auth_keyboard($auth_code) {
        return array(
            'inline_keyboard' => array(
                array(
                    array(
                        'text' => '✅ Подтвердить вход',
                        'callback_data' => 'auth_confirm:' . $auth_code
                    )
                ),
                array(
                    array(
                        'text' => '❌ Отменить',
                        'callback_data' => 'auth_cancel:' . $auth_code
                    )
                )
            )
        );
    }
    
    /**
     * Ответ на callback query
     */
    public function answer_callback_query($callback_query_id, $text = '', $show_alert = false) {
        return $this->make_request('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $text,
            'show_alert' => $show_alert
        ));
    }
    
    /**
     * Редактирование сообщения
     */
    public function edit_message_text($chat_id, $message_id, $text, $parse_mode = 'HTML') {
        return $this->make_request('editMessageText', array(
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => $text,
            'parse_mode' => $parse_mode
        ));
    }
    
    /**
     * Выполнение API запроса
     */
    private function make_request($method, $data = array()) {
        $url = $this->api_url . $this->bot_token . '/' . $method;
        
        $response = wp_remote_post($url, array(
            'body' => $data,
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('Telegram API Error: ' . $response->get_error_message());
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($body['ok']) || !$body['ok']) {
            error_log('Telegram API Error: ' . json_encode($body));
            return false;
        }
        
        return $body['result'];
    }
}