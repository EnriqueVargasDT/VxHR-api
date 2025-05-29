<?php
require_once '../libs/php-jwt/src/JWTExceptionWithPayloadInterface.php';
require_once '../libs/php-jwt/src/ExpiredException.php';
require_once '../libs/php-jwt/src/JWT.php';
require_once '../libs/php-jwt/src/Key.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;


class Token {
    private $secretKey;

    public function __construct() {
        $this->secretKey = getenv('ENCRYPT_PASSWORD_KEY');
    }

    public function validate() {
        $token = $_COOKIE['token'] ?? null;
        $headers = apache_request_headers();
        if (isset($headers['Authorization'])) {
            $token = str_replace('Bearer ', '', $headers['Authorization']);
        }

        try {
            if (!isset($token)) {
                return ['error' => true, 'message' => 'Usuario no autenticado.'];
            }
            
            $decoded = JWT::decode($token, new Key($this->secretKey, 'HS256'));
            return ['ok' => true, 'message' => 'Token válido.', 'role' => $decoded->role, "sub" => $decoded->sub ];
        }
        catch (Exception $error) {
            return ['error' => true, 'message' => 'Token inválido.'];
        }
    }
}
?>
