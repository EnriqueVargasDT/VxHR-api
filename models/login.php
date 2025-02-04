<?php
require_once '../config/config.php';
require_once '../libs/php-jwt/src/JWT.php';
use \Firebase\JWT\JWT;

class Login {
    private $dbConnection;
    private $secretKey;

    public function __construct() {
        $this->dbConnection = dbConnection();
        $this->secretKey = getenv('APP_SECRET_KEY');
    }

    public function validate($username, $password, $rememberMe) {
        try {
            $sql = "SELECT * FROM dbo.users_auth WHERE username = '$username'";
            $stmt = $this->dbConnection->query($sql);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                $decryptedPassword = $this->decryptedPassword($password);
                if (password_verify($decryptedPassword, $result['password'])) {
                    $expTime = $rememberMe ? time() + (30 * 24 * 60 * 60) : time() + (60 * 60);
                    $payload = array(
                        'iat' => time(),
                        'exp' => $rememberMe ? time() + (3 * 24 * 60 * 60) /* Token válido por 3 días */ : time() + 3600 /* Token válido por 1 hora */,
                        'sub' => $username
                    );
                    
                    // Generar el JWT con la librería JWT
                    $jwt = JWT::encode($payload, $this->secretKey, 'HS256');
                    setcookie('token', $jwt, [
                        'expires' => $payload['exp'],
                        'path' => '/',
                        'secure' => true, // https o  http
                        'httponly' => true,
                        'samesite' => 'Strict',
                    ]);
                    echo json_encode(array('ok' => true, 'pk_user_id' => $result['pk_user_id']));
                }
                else {
                    http_response_code(401);
                    echo json_encode(array('error' => true, 'type' => 'password', 'message' => 'Contraseña inválida.'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }
            else {
                http_response_code(401);
                echo json_encode(array('error' => true, 'type' => 'username', 'message' => 'Usuario no encontrado.'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
        catch(Exception $error) {
            http_response_code(500);
            echo json_encode(array('error' => true, 'message' => $error), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        exit();
    }

    private function decryptedPassword($password) {
        $dataBase64 = base64_decode($password);
        $iv = substr($dataBase64, 0, 16);
        $encrypted = substr($dataBase64, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $this->secretKey, OPENSSL_RAW_DATA, $iv);
    }
}
?>