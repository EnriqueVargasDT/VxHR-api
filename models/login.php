<?php
require_once '../config/config.php';
require_once '../libs/php-jwt/src/JWT.php';
use \Firebase\JWT\JWT;

class Login {
    private $dbConnection;
    private $secretKey;

    public function __construct() {
        $this->dbConnection = dbConnection();
        $this->secretKey = "clave_super_secreta";
    }

    public function validate($username, $password) {
        try {
            $sql = "SELECT * FROM dbo.users_auth WHERE username = '$username'";
            $stmt = $this->dbConnection->query($sql);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if (password_verify($password, $result['password'])) {
                $payload = [
                    'iat' => time(),
                    'exp' => time() + 3600, // Token válido por 1 hora
                    'sub' => $username
                ];
                
                // Generar el JWT con la librería JWT
                $jwt = JWT::encode($payload, $this->secretKey, 'HS256');
                setcookie('token', $jwt, [
                    "expires" => $payload['exp'],
                    "path" => "/",
                    "secure" => false, // https o  http
                    "httponly" => false,
                    "samesite" => "Strict",
                ]);
                echo json_encode([
                    'ok' => true,
                    'pk_user_id' => $result['pk_user_id']
                ]);
            }
            else {
                http_response_code(401);
                echo json_encode(['error' => true, 'message' => 'Credenciales inválidas.']);
            }
        }
        catch(error) {
            http_response_code(500);
            echo json_encode(array('error' => true, 'message' => 'Ha ocurrido un error no conocido.'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        exit();
    }
}
?>