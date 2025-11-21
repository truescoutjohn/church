<?php
/**
 * Клас для генерації JWT підписів для Zoom Video SDK
 */
class Zoom_Video_SDK_JWT {
    
    /**
     * Генерація JWT підпису для Video SDK
     * 
     * @param string $sdk_key SDK Key
     * @param string $sdk_secret SDK Secret
     * @param string $session_name Назва сесії
     * @param int $role 0 = participant, 1 = host
     * @param string $user_identity Ім'я користувача
     * @return string JWT signature
     */
    public static function generate_signature($sdk_key, $sdk_secret, $session_name, $role = 0, $user_identity = '') {
        $iat = time() - 30;
        $exp = $iat + 60 * 60 * 2; // 2 години
        
        $payload = array(
            'app_key' => $sdk_key,
            'tpc' => $session_name,
            'role_type' => (int)$role,
            'user_identity' => $user_identity,
            'version' => 1,
            'iat' => $iat,
            'exp' => $exp
        );
        
        return self::encode_jwt($payload, $sdk_secret);
    }
    
    /**
     * Кодування JWT токена
     */
    private static function encode_jwt($payload, $secret) {
        $header = array(
            'alg' => 'HS256',
            'typ' => 'JWT'
        );
        
        $segments = array();
        $segments[] = self::base64url_encode(json_encode($header));
        $segments[] = self::base64url_encode(json_encode($payload));
        
        $signing_input = implode('.', $segments);
        $signature = hash_hmac('sha256', $signing_input, $secret, true);
        $segments[] = self::base64url_encode($signature);
        
        return implode('.', $segments);
    }
    
    /**
     * Base64 URL encode
     */
    private static function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

/**
 * Клас для генерації Meeting Signature для Zoom Meeting SDK v4.x
 * Використовує Client ID та Client Secret з General App (після активації SDK Scopes).
 */

class Zoom_JWT { // Я рекомендую использовать это имя вместо Zoom_JWT
    
    /**
     * Генерація Meeting Signature
     * * @param string $sdk_key Ваш SDK Key (Client ID)
     * @param string $sdk_secret Ваш SDK Secret (Client Secret)
     * @param string $meeting_number Meeting ID (число)
     * @param int $role 0 = учасник, 1 = хост
     * @return string JWT Signature (токен)
     */
    public static function generate_signature($sdk_key, $sdk_secret, $meeting_number, $role = 0) {
        
        // Время выдачи токена (Issued At Time)
        // Устанавливаем на 30 секунд раньше, чтобы избежать проблем с синхронизацией
        $iat = time() - 30; 
        
        // Время истечения токена (Expiration Time) - 2 часа
        $exp = $iat + 60 * 60 * 2; 
        
        // 1. Формируем строку данных для хэширования
        // Порядок полей КРИТИЧЕСКИ ВАЖЕН: sdkKey, mn, iat, exp, role
        $data_to_hash = $sdk_key . $meeting_number . $iat . $exp . $role;
        
        // 2. Генерируем хэш SHA256 от этой строки, используя SDK Secret
        // ЭТОТ ХЭШ ЯВЛЯЕТСЯ ЗНАЧЕНИЕМ ПОЛЯ 'hash' В PAYLOAD
        $hash_signature = hash_hmac('sha256', $data_to_hash, $sdk_secret);
        
        // 3. Формируем PAYLOAD (полезную нагрузку) JWT
        $payload = array(
            'sdkKey' => $sdk_key, // Ваш Client ID
            'mn' => $meeting_number,
            'role' => (int)$role,
            'iat' => $iat,
            'exp' => $exp,
            // Обязательное поле для Meeting SDK v4.x:
            'hash' => $hash_signature 
        );
        
        // 4. Кодируем и подписываем JWT целиком с использованием SDK Secret
        return self::encode_jwt($payload, $sdk_secret);
    }
    
    /**
     * Кодування JWT токена (стандартний метод)
     */
    private static function encode_jwt($payload, $secret) {
        $header = array(
            'alg' => 'HS256',
            'typ' => 'JWT'
        );
        
        $segments = array();
        $segments[] = self::base64url_encode(json_encode($header));
        $segments[] = self::base64url_encode(json_encode($payload));
        
        $signing_input = implode('.', $segments);
        
        // true: возвращает бинарный результат для Base64 URL кодирования
        $signature = hash_hmac('sha256', $signing_input, $secret, true);
        $segments[] = self::base64url_encode($signature);
        
        return implode('.', $segments);
    }
    
    /**
     * Base64 URL encode (стандартный метод)
     */
    private static function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}