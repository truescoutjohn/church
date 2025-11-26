<?php
/**
 * Telegram Webhook Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class Telegram_Webhook_Handler {
    
    private static $instance = null;
    private $redis = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->redis = WP_Redis_Manager::get_instance();
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    public function register_routes() {
        register_rest_route('telegram/v1', '/webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_webhook'),
            'permission_callback' => '__return_true'
        ));
    }
    
    public function handle_webhook($request) {
        $data = $request->get_json_params();
        
        error_log('Telegram webhook: ' . json_encode($data));
        
        // Обработка обычных сообщений
        if (isset($data['message'])) {
            $this->handle_message($data['message']);
        }
        
        // Обработка callback от inline кнопок
        if (isset($data['callback_query'])) {
            $this->handle_callback_query($data['callback_query']);
        }
        
        return new WP_REST_Response(array('ok' => true), 200);
    }
    
    private function handle_message($message) {
        $chat_id = $message['chat']['id'];
        $text = isset($message['text']) ? $message['text'] : '';
        
        // Команда /start - привязка аккаунта
        if ($text === '/start' || strpos($text, '/start') === 0) {
            $this->handle_start_command($message);
            return;
        }
        
        // Команда /verify - получение кода для привязки
        if ($text === '/verify') {
            $this->handle_verify_command($message);
            return;
        }
        
        // Проверка на OTP код (6 цифр)
        if (preg_match('/^\d{6}$/', $text)) {
            $this->handle_otp_input($chat_id, $text);
            return;
        }
        
        // Проверка на код привязки (формат: LINK-XXXXX)
        if (preg_match('/^LINK-[A-Z0-9]{5}$/', strtoupper($text))) {
            $this->handle_link_code($chat_id, strtoupper($text));
            return;
        }
    }
    
    private function handle_start_command($message) {
        $chat_id = $message['chat']['id'];
        $telegram = Telegram_API::get_instance();
        
        // Проверяем, есть ли уже привязанный пользователь
        $user_id = $this->get_user_by_chat_id($chat_id);
        
        if ($user_id) {
            $user = get_userdata($user_id);
            $text = "✅ Ваш аккаунт уже привязан!\n\n";
            $text .= "📧 Email: <b>{$user->user_email}</b>\n\n";
            $text .= "Вы будете получать OTP коды и уведомления о входе.";
        } else {
            $text = "👋 <b>Добро пожаловать!</b>\n\n";
            $text .= "Для привязки аккаунта:\n\n";
            $text .= "1️⃣ Войдите на сайт\n";
            $text .= "2️⃣ Перейдите в настройки профиля\n";
            $text .= "3️⃣ Получите код привязки\n";
            $text .= "4️⃣ Отправьте код сюда\n\n";
            $text .= "Или используйте команду /verify для получения инструкций";
        }
        
        $telegram->send_message($chat_id, $text);
    }
    
    private function handle_verify_command($message) {
        $chat_id = $message['chat']['id'];
        $telegram = Telegram_API::get_instance();
        
        $text = "🔗 <b>Привязка аккаунта</b>\n\n";
        $text .= "Получите код привязки на сайте:\n";
        $text .= "👉 " . site_url('/my-account/telegram') . "\n\n";
        $text .= "Затем отправьте код в формате:\n";
        $text .= "<code>LINK-XXXXX</code>";
        
        $telegram->send_message($chat_id, $text);
    }
    
    private function handle_link_code($chat_id, $code) {
        $telegram = Telegram_API::get_instance();
        
        // Проверка кода в Redis
        $redis_key = 'telegram_link_code:' . $code;
        $user_id = $this->redis->get($redis_key);
        
        if (!$user_id) {
            $telegram->send_message(
                $chat_id, 
                "❌ Неверный или устаревший код!\n\nПолучите новый код на сайте."
            );
            return;
        }
        
        // Привязка аккаунта
        update_user_meta($user_id, 'telegram_chat_id', $chat_id);
        update_user_meta($user_id, 'telegram_linked_at', time());
        
        // Удаление использованного кода
        $this->redis->delete($redis_key);
        
        $user = get_userdata($user_id);
        
        $text = "✅ <b>Аккаунт успешно привязан!</b>\n\n";
        $text .= "📧 Email: <b>{$user->user_email}</b>\n\n";
        $text .= "Теперь вы будете получать:\n";
        $text .= "• OTP коды для входа\n";
        $text .= "• Уведомления о входе в систему\n";
        $text .= "• Другие важные уведомления";
        
        $telegram->send_message($chat_id, $text);
        
        error_log("Telegram account linked: user_id={$user_id}, chat_id={$chat_id}");
    }
    
    private function handle_otp_input($chat_id, $otp) {
        $telegram = Telegram_API::get_instance();
        
        // Здесь можно добавить логику проверки OTP
        // Например, для быстрого входа через Telegram
        
        $telegram->send_message(
            $chat_id,
            "ℹ️ Введите этот код на сайте для входа"
        );
    }
    
    private function handle_callback_query($callback_query) {
        $chat_id = $callback_query['message']['chat']['id'];
        $message_id = $callback_query['message']['message_id'];
        $data = $callback_query['data'];
        $callback_query_id = $callback_query['id'];
        
        $telegram = Telegram_API::get_instance();
        
        // Обработка подтверждения авторизации
        if (strpos($data, 'auth_confirm:') === 0) {
            $auth_code = str_replace('auth_confirm:', '', $data);
            $this->confirm_auth($chat_id, $message_id, $auth_code, $callback_query_id);
        }
        
        // Обработка отмены авторизации
        if (strpos($data, 'auth_cancel:') === 0) {
            $auth_code = str_replace('auth_cancel:', '', $data);
            $this->cancel_auth($chat_id, $message_id, $auth_code, $callback_query_id);
        }
    }
    
    private function confirm_auth($chat_id, $message_id, $auth_code, $callback_query_id) {
        $telegram = Telegram_API::get_instance();
        
        // Проверка кода авторизации
        $redis_key = 'telegram_auth_request:' . $auth_code;
        $auth_data = $this->redis->get($redis_key);
        
        if (!$auth_data) {
            $telegram->answer_callback_query(
                $callback_query_id,
                'Код авторизации истек или недействителен',
                true
            );
            return;
        }
        
        // Подтверждение авторизации
        $this->redis->set('telegram_auth_confirmed:' . $auth_code, '1', 300);
        $this->redis->delete($redis_key);
        
        $telegram->edit_message_text(
            $chat_id,
            $message_id,
            "✅ <b>Авторизация подтверждена!</b>\n\nВы можете вернуться на сайт."
        );
        
        $telegram->answer_callback_query(
            $callback_query_id,
            '✅ Авторизация подтверждена'
        );
    }
    
    private function cancel_auth($chat_id, $message_id, $auth_code, $callback_query_id) {
        $telegram = Telegram_API::get_instance();
        
        // Отмена авторизации
        $redis_key = 'telegram_auth_request:' . $auth_code;
        $this->redis->delete($redis_key);
        
        $telegram->edit_message_text(
            $chat_id,
            $message_id,
            "❌ <b>Авторизация отменена</b>"
        );
        
        $telegram->answer_callback_query(
            $callback_query_id,
            '❌ Авторизация отменена'
        );
    }
    
    private function get_user_by_chat_id($chat_id) {
        $users = get_users(array(
            'meta_key' => 'telegram_chat_id',
            'meta_value' => $chat_id,
            'number' => 1
        ));
        
        return !empty($users) ? $users[0]->ID : null;
    }
}