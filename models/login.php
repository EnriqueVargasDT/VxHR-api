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
            $sql1 = "SELECT TOP 1 * FROM dbo.users_auth WHERE username = '$username'";
            $stmt = $this->dbConnection->query($sql1);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                $decryptedPassword = $this->decryptedPassword($password);
                if (password_verify($decryptedPassword, $result['password'])) {
                    $expTime = $rememberMe ? time() + (30 * 24 * 60 * 60) : time() + (60 * 60);
                    $payload = array(
                        'iat' => time(),
                        'exp' => $rememberMe ? time() + (3 * 24 * 60 * 60) /* Token válido por 3 días */ : time() + 3600 /* Token válido por 1 hora */,
                        'sub' => $username,
                        'role' => $result['pk_role_id'], 
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

                    // Actualizar la fecha de último inicio de sesión:
                    $sql2 = 'UPDATE [dbo].[users_auth] SET last_access_at = GETDATE() WHERE pk_user_auth_id = :pk_user_auth_id AND pk_user_id = :pk_user_id;';
                    $stmt2 = $this->dbConnection->prepare($sql2);
                    $stmt2->bindParam(':pk_user_auth_id', $result['pk_user_auth_id'], PDO::PARAM_INT);
                    $stmt2->bindParam(':pk_user_id', $result['pk_user_id'], PDO::PARAM_INT);
                    if ($stmt2->execute()) {
                        echo json_encode(array('ok' => true, 'pk_user_id' => $result['pk_user_id'], 'pk_role_id' => $result['pk_role_id'], ));
                    }
                    else {
                        http_response_code(500);
                        echo json_encode(array('error' => true, 'message' => 'Error al intentar actualizar la fecha de inicio de sesión.'), JSON_UNESCAPED_UNICODE, JSON_UNESCAPED_SLASHES);
                    }
                }
                else {
                    http_response_code(401);
                    echo json_encode(array('error' => true, 'type' => 'password', 'message' => 'Contraseña inválida.'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }
            else {
                http_response_code(401);
                echo json_encode(array('error' => true, 'type' => 'username', 'message' => 'Usuario no encontrado.'));
            }
        }
        catch(Exception $error) {
            http_response_code(500);
            echo json_encode(array('error' => true, 'message' => $error), JSON_UNESCAPED_UNICODE, JSON_UNESCAPED_SLASHES);
        }

        exit();
    }

    private function decryptedPassword($password) {
        $dataBase64 = base64_decode($password);
        $iv = substr($dataBase64, 0, 16);
        $encrypted = substr($dataBase64, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $this->secretKey, OPENSSL_RAW_DATA, $iv);
    }

    public function passwordRecovery($username) {
        try {
            if (trim(isset($username))) {
                $sql1 = "SELECT UsersAuth.*, CONCAT(Users.first_name, ' ', Users.last_name_1, ' ', Users.last_name_2) AS user_full_name FROM dbo.users_auth UsersAuth JOIN dbo.users ON UsersAuth.pk_user_id = Users.pk_user_id WHERE UsersAuth.username = '$username'";
                $stmt1 = $this->dbConnection->query($sql1);
                $result = $stmt1->fetch(PDO::FETCH_ASSOC);
                if (isset($result['pk_user_auth_id'])) {
                    $sql2 = 'DELETE FROM dbo.password_resets WHERE username = :username;';
                    $stmt2 = $this->dbConnection->prepare($sql2);
                    $stmt2->bindParam(':username', $username, PDO::PARAM_STR);
                    if ($stmt2->execute()) {
                        $token = password_hash($username, PASSWORD_BCRYPT);
                        $sql3 = 'INSERT INTO dbo.password_resets (username, token, created_at) VALUES(:username, :token, GETDATE());';
                        $stmt3 = $this->dbConnection->prepare($sql3);
                        $stmt3->bindParam(':username', $username, PDO::PARAM_STR);
                        $stmt3->bindParam(':token', $token, PDO::PARAM_STR);
                        $stmt3->execute();
                        if ($stmt3->rowCount() > 0) {
                            // Enviar correo de confirmación
                            require_once '../models/email.php';
                            $email = new Email();
                            $subject = 'Solicitud de restablecimiento de contraseña';
                            $template = file_get_contents("../templates/password_recovery_email.html");
                            $template = str_replace('{{username}}', $result['user_full_name'], $template);
                            $template = str_replace('{{email}}', $username, $template);
                            $template = str_replace('{{reset_link}}', $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'].":3000/restablecer-contraseña?token=$token", $template);
                            $message = $template;
                            $send = $email->send($username, $subject, $message);
                            if ($send) {
                                echo json_encode(array('ok' => true, 'message' => 'Correo electrónico enviado correctamente.'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                            }
                        }
                        else {
                            http_response_code(500);
                            echo json_encode(array('error' => true, 'message' => 'El correo electrónico no pudo ser generado. Intentar nuevamente.'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        }
                    }
                }
                else {
                    http_response_code(500);
                    echo json_encode(array('error' => true, 'message' => 'El correo electrónico proporcionado no esta registrado en la plataforma.'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }
            else {
                http_response_code(500);
                echo json_encode(array('error' => true, 'message' => 'No se recibió un correo electrónico válido.'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
        catch(Exception $error) {
            http_response_code(500);
            echo json_encode(array('error' => true, 'message' => $error), JSON_UNESCAPED_UNICODE, JSON_UNESCAPED_SLASHES);
        }

        exit();
    }

    public function passwordUpdate($token, $newPassword, $confirmPassword) {
        try {
            if (isset($token)) {
                $sql1 = "SELECT TOP 1 * FROM dbo.password_resets WHERE token = '$token' AND DATEADD(HOUR, 1, created_at) > GETDATE()";
                $stmt1 = $this->dbConnection->query($sql1);
                $result = $stmt1->fetch(PDO::FETCH_ASSOC);
                if (isset($result['pk_password_reset_id'])) {
                    if (isset($newPassword) && isset($confirmPassword)) {
                        if ($newPassword === $confirmPassword) {
                            $encryptedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
                            $sql2 = 'UPDATE dbo.users_auth SET password = :password WHERE username = :username';
                            $stmt2 = $this->dbConnection->prepare($sql2);
                            $stmt2->bindParam(':password', $encryptedPassword, PDO::PARAM_STR);
                            $stmt2->bindParam(':username', $result['username'], PDO::PARAM_STR);
                            $stmt2->execute();
                            if ($stmt2->rowCount() > 0) {
                                $sql3 = 'DELETE FROM dbo.password_resets WHERE username = :username;';
                                $stmt3 = $this->dbConnection->prepare($sql3);
                                $stmt3->bindParam(':username', $result['username'], PDO::PARAM_STR);
                                $stmt3->execute();
                                echo json_encode(array('ok' => true, 'message' => 'La contraseña ha sido actualizada correctamente.'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                            }
                            else {
                                http_response_code(500);
                                echo json_encode(array('error' => true, 'message' => 'No pudo ser actualizada la contraseña. Intentar nuevamente.'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                            }
                        }
                        else {
                            http_response_code(500);
                            echo json_encode(array('error' => true, 'message' => 'La contraseña no coincide.'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        }
                    }
                    else {
                        http_response_code(500);
                        echo json_encode(array('error' => true, 'message' => 'La contraseña proporcionada no es válida.'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }
                }
                else {
                    http_response_code(500);
                    echo json_encode(array('error' => true, 'message' => 'El token ha caducado.'));
                }
            }
            else {
                http_response_code(500);
                echo json_encode(array('error' => true, 'message' => 'El token es inválido.'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
        catch(Exception $error) {
            http_response_code(500);
            echo json_encode(array('error' => true, 'message' => $error), JSON_UNESCAPED_UNICODE, JSON_UNESCAPED_SLASHES);
        }

        exit();
    }
}
?>