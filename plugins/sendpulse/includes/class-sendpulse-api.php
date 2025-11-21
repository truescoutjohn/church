<?php

class SendPulse_API {
    private static $api_user_id;
    private static $api_secret;
    private static $sender_email;

    private static function loadSettings() {
        self::$api_user_id = get_option('sendpulse_api_user_id');
        self::$api_secret = get_option('sendpulse_api_secret');
        self::$sender_email = get_option('sendpulse_sender_email');
    }

    public static function sendEmail($to, $subject, $html, $from_email = '', $from_name = '') {
        self::loadSettings();

        if (!self::$api_user_id || !self::$api_secret) {
            return new WP_Error('missing_credentials', 'SendPulse API credentials are not set.');
        }

        $sender_email = $from_email ?: self::$sender_email;
        $sender_name = $from_name ?: get_bloginfo('name');

        $body = [
            'email' => [
                'from' => [
                    'name' => $sender_name,
                    'email' => $sender_email
                ],
                'subject' => $subject,
                'text' => strip_tags($html), // plain-text fallback
                'html' => '<html><body>' . nl2br($html) . '</body></html>',
                'to' => [
                    [
                        'email' => $to,
                        'name' => $to  // опціонально
                    ]
                ]
            ]
        ];

        $token = self::getAccessToken();
        if (!$token) {
            return new WP_Error('token_error', 'Unable to get access token from SendPulse.');
        }

        $response = wp_remote_post('https://api.sendpulse.com/smtp/emails', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json'
            ],
            'body' => json_encode($body)
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200 && $code !== 202) {
            return new WP_Error('sendpulse_error', 'SendPulse API error: ' . wp_remote_retrieve_body($response));
        }

        return true;
    }



    private static function getAccessToken() {
        $response = wp_remote_post('https://api.sendpulse.com/oauth/access_token', [
            'body' => [
                'grant_type' => 'client_credentials',
                'client_id' => self::$api_user_id,
                'client_secret' => self::$api_secret
            ]
        ]);

        if (is_wp_error($response)) return '';

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['access_token'] ?? '';
    }
}
