<?php
require_once '../models/token.php';

class Role {
    private $secretKey;
    private $token;

    public function __construct() {
        $this->secretKey = getenv('APP_SECRET_KEY');
        $this->token = new Token();
    }

    public function get() {
        try {
            $decoded = $this->token->validate();
            echo json_encode(array('ok' => true, 'message' => 'Rol encontrado', 'role' => $decoded['role'], ), JSON_UNESCAPED_UNICODE, JSON_UNESCAPED_SLASHES);
        }
        catch (Exception $error) {
            http_response_code(500);
            echo json_encode(array('error' => true, 'message' => $error), JSON_UNESCAPED_UNICODE, JSON_UNESCAPED_SLASHES);
        }
    }
}
?>