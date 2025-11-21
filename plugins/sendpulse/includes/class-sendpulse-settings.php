<?php

class SendPulse_Settings {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    public static function add_menu() {
        add_options_page(
            'SendPulse Налаштування',
            'SendPulse',
            'manage_options',
            'sendpulse-settings',
            [__CLASS__, 'settings_page']
        );
    }

    public static function register_settings() {
        register_setting('sendpulse_settings_group', 'sendpulse_api_user_id');
        register_setting('sendpulse_settings_group', 'sendpulse_api_secret');
        register_setting('sendpulse_settings_group', 'sendpulse_sender_email');
    }

    public static function get_templates() {
        $templates = get_option('spn_email_templates', []);

        if (!isset($templates['reset_password'])) {
            $templates['reset_password'] = [
                'subject' => 'Відновлення паролю',
                'body' => "Для відновлення паролю перейдіть за посиланням: {{reset_link}}"
            ];
            update_option('spn_email_templates', $templates);
        }

        if (!isset($templates['password_changed'])) {
            $templates['password_changed'] = [
                'subject' => 'Зміна паролю',
                'body' => "Ваш паролю успішно змінено!"
            ];
            update_option('spn_email_templates', $templates);
        }

        if (!isset($templates['register_success'])) {
            $templates['register_success'] = [
                'subject' => 'Ласкаво просимо!',
                'body' => "
                    <p>Привіт, {{user_email}}!</p>
                    <p>Дякуємо за реєстрацію на сайті {{site_url}}.</p>
                    <p>Ваш пароль: <strong>{{password}}</strong></p>
                    <p>Для входу перейдіть за посиланням: <a href='{{login_url}}'>{{login_url}}</a></p>
                "
            ];
            update_option('spn_email_templates', $templates);
        }

        if (!isset($templates['company_created'])) {
            $templates['company_created'] = [
                'subject' => 'Новий проєкт для вас',
                'body' => "
                    <p>Привіт, {{user_email}}!</p>
                    <p>Для вас створено новий проєкт на сайті {{site_url}}.</p>
                    <p>Переглянути проєкт ви можете за посиланням: <a href='{{company_url}}'>{{company_url}}</a></p>
                "
            ];
            update_option('spn_email_templates', $templates);
        }

        return $templates;
    }

    public static function get_template($key) {
        $templates = self::get_templates();
        return isset($templates[$key]) ? $templates[$key] : null;
    }

    public static function settings_page() {
        // Збереження шаблонів
        if (isset($_POST['spn_save_templates']) && check_admin_referer('spn_save_templates')) {
            $templates = self::get_templates();
            foreach ($templates as $key => &$tpl) {
                $tpl['subject'] = sanitize_text_field($_POST[$key . '_subject']);
                $tpl['body'] = wp_kses_post($_POST[$key . '_body']);
            }
            update_option('spn_email_templates', $templates);
            echo '<div class="updated"><p>Шаблони збережено.</p></div>';
        }

        $templates = self::get_templates();
        ?>
        <div class="wrap">
            <h1>Налаштування SendPulse</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('sendpulse_settings_group');
                do_settings_sections('sendpulse_settings_group');
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">API User ID</th>
                        <td><input type="text" name="sendpulse_api_user_id" value="<?php echo esc_attr(get_option('sendpulse_api_user_id')); ?>" size="50" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">API Secret</th>
                        <td><input type="text" name="sendpulse_api_secret" value="<?php echo esc_attr(get_option('sendpulse_api_secret')); ?>" size="50" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Email Відправника</th>
                        <td><input type="email" name="sendpulse_sender_email" value="<?php echo esc_attr(get_option('sendpulse_sender_email')); ?>" size="50" /></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>

            <hr>
            <h2>Шаблони листів</h2>
            <form method="post">
                <?php wp_nonce_field('spn_save_templates'); ?>
                <?php foreach ($templates as $key => $tpl): ?>
                    <h3><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="<?php echo $key; ?>_subject">Тема листа</label></th>
                            <td><input type="text" name="<?php echo $key; ?>_subject" value="<?php echo esc_attr($tpl['subject']); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="<?php echo $key; ?>_body">Тіло листа</label></th>
                            <td><textarea name="<?php echo $key; ?>_body" rows="6" class="large-text"><?php echo esc_textarea($tpl['body']); ?></textarea></td>
                        </tr>
                    </table>
                    <hr>
                <?php endforeach; ?>
                <p><input type="submit" name="spn_save_templates" class="button-primary" value="Зберегти шаблони"></p>
            </form>
        </div>
        <?php
    }
}

SendPulse_Settings::init();
