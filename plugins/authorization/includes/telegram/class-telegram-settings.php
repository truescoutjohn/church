<?php
/**
 * Telegram Settings
 */

if (!defined('ABSPATH')) {
    exit;
}

class Telegram_Settings {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    public function add_menu() {
        add_options_page(
            'Telegram Settings',
            'Telegram Auth',
            'manage_options',
            'telegram-auth-settings',
            array($this, 'settings_page')
        );
    }
    
    public function register_settings() {
        register_setting('telegram_auth_settings', 'telegram_bot_token');
        register_setting('telegram_auth_settings', 'telegram_webhook_url');
        register_setting('telegram_auth_settings', 'telegram_otp_enabled');
        register_setting('telegram_auth_settings', 'telegram_login_notifications');
    }
    
    public function settings_page() {
        if (isset($_POST['telegram_setup_webhook']) && check_admin_referer('telegram_setup_webhook')) {
            $this->setup_webhook();
        }
        
        if (isset($_POST['telegram_delete_webhook']) && check_admin_referer('telegram_delete_webhook')) {
            $this->delete_webhook();
        }
        
        $bot_info = $this->get_bot_info();
        ?>
        <div class="wrap">
            <h1>Настройки Telegram</h1>
            
            <?php if ($bot_info): ?>
                <div class="notice notice-success">
                    <p>
                        <strong>✅ Бот подключен:</strong> 
                        @<?php echo esc_html($bot_info['username']); ?> 
                        (<?php echo esc_html($bot_info['first_name']); ?>)
                    </p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('telegram_auth_settings');
                do_settings_sections('telegram_auth_settings');
                ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="telegram_bot_token">Bot Token</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="telegram_bot_token" 
                                   name="telegram_bot_token" 
                                   value="<?php echo esc_attr(get_option('telegram_bot_token')); ?>" 
                                   class="regular-text" 
                                   placeholder="1234567890:ABCdefGHIjklMNOpqrsTUVwxyz" />
                            <p class="description">
                                Получите токен у <a href="https://t.me/botfather" target="_blank">@BotFather</a>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Webhook URL</th>
                        <td>
                            <code><?php echo esc_html(site_url('/wp-json/telegram/v1/webhook')); ?></code>
                            <p class="description">Этот URL будет использоваться для приема сообщений от Telegram</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="telegram_otp_enabled">OTP через Telegram</label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       id="telegram_otp_enabled" 
                                       name="telegram_otp_enabled" 
                                       value="1" 
                                       <?php checked(get_option('telegram_otp_enabled'), '1'); ?> />
                                Включить OTP коды через Telegram
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="telegram_login_notifications">Уведомления о входе</label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       id="telegram_login_notifications" 
                                       name="telegram_login_notifications" 
                                       value="1" 
                                       <?php checked(get_option('telegram_login_notifications'), '1'); ?> />
                                Отправлять уведомления о входе в Telegram
                            </label>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Сохранить настройки'); ?>
            </form>
            
            <hr>
            
            <h2>Управление Webhook</h2>
            <form method="post">
                <?php wp_nonce_field('telegram_setup_webhook'); ?>
                <p>
                    <input type="submit" 
                           name="telegram_setup_webhook" 
                           class="button button-primary" 
                           value="Установить Webhook" />
                    
                    <input type="submit" 
                           name="telegram_delete_webhook" 
                           class="button" 
                           value="Удалить Webhook" />
                </p>
            </form>
            
            <hr>
            
            <h2>Инструкции</h2>
            <ol>
                <li>Создайте бота через <a href="https://t.me/botfather" target="_blank">@BotFather</a></li>
                <li>Скопируйте токен и вставьте его в поле выше</li>
                <li>Сохраните настройки</li>
                <li>Нажмите "Установить Webhook"</li>
                <li>Пользователи должны написать боту команду <code>/start</code> для привязки аккаунта</li>
            </ol>
        </div>
        <?php
    }
    
    private function setup_webhook() {
        $telegram = Telegram_API::get_instance();
        $webhook_url = site_url('/wp-json/telegram/v1/webhook');
        
        $result = $telegram->set_webhook($webhook_url);
        
        if ($result) {
            echo '<div class="notice notice-success"><p>Webhook успешно установлен!</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Ошибка при установке webhook</p></div>';
        }
    }
    
    private function delete_webhook() {
        $telegram = Telegram_API::get_instance();
        $result = $telegram->delete_webhook();
        
        if ($result) {
            echo '<div class="notice notice-success"><p>Webhook удален!</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Ошибка при удалении webhook</p></div>';
        }
    }
    
    private function get_bot_info() {
        $telegram = Telegram_API::get_instance();
        return $telegram->get_me();
    }
}