<?php
/*
Plugin Name: SendPulse Notifications
Description: Надсилання системних повідомлень через SendPulse.
Version: 1.0
Author: Doxx
*/

// Підключення класу API
require_once plugin_dir_path(__FILE__) . 'includes/class-sendpulse-api.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-sendpulse-settings.php';


// // Хук для реєстрації
// add_action('user_register', 'spn_send_registration_email', 10, 1);
// function spn_send_registration_email($user_id) {
//     $user = get_userdata($user_id);
//     $email = $user->user_email;
//     $name = $user->display_name;

//     $subject = "Ласкаво просимо, $name!";
//     $message = "Дякуємо за реєстрацію на нашому сайті.";

//     SendPulse_API::sendEmail($email, $subject, $message);
// }

// // Хук для відновлення паролю
// add_filter('retrieve_password_message', 'spn_override_password_reset_message', 10, 4);
// function spn_override_password_reset_message($message, $key, $user_login, $user_data, $url_recovery = 'wp-login.php?action=rp') {
//     $reset_url = network_site_url("{$url_recovery}&key=$key&login=" . rawurlencode($user_login), 'login');
//     $template = SendPulse_Settings::get_template('reset_password');

//     $subject = $template['subject'];
//     $body = str_replace(
//         ['{{user_email}}', '{{reset_link}}'],
//         [$user_data->user_email, $reset_url],
//         $template['body']
//     );

//     SendPulse_API::sendEmail(
//         $user_data->user_email,
//         $subject,
//         $body,
//         get_option('sendpulse_sender_email'), // from email
//         get_bloginfo('name')                  // from name
//     );


//     // Повертаємо порожній рядок, щоб стандартний лист не відправлявся
//     return '';
// }

