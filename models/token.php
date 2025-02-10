<?php
require_once '../libs/php-jwt/src/JWT.php';
require_once '../libs/php-jwt/src/Key.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Token {
    private $secretKey;

    public function __construct() {
        $this->secretKey = getenv('APP_SECRET_KEY');
    }

    public function validate() {
        try {
            if (!isset($_COOKIE['token'])) {
                return array('error' => true, 'message' => 'Usuario no autenticado.');
            }
            
            $decoded = JWT::decode($_COOKIE['token'], new Key($this->secretKey, 'HS256'));
            return array('ok' => true, 'message' => 'Token válido.', 'role' => $decoded->role, );
        }
        catch (Exception $error) {
            return array('error' => true, 'message' => 'Token inválido.');
        }
    }
}
?>