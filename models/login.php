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
            $sql1 = "SELECT TOP 1 UA.* FROM [user].[users_auth] UA JOIN [user].[users] U ON UA.[fk_user_id] = U.[pk_user_id] WHERE UA.[username] = '$username' AND U.[is_active] = 1";
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
                        'role' => $result['fk_role_id'], 
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
                    $this->dbConnection->beginTransaction();
                    $sql2 = 'UPDATE [user].[users_auth] SET [last_access_at] = GETDATE() WHERE [pk_user_auth_id] = :pk_user_auth_id AND [fk_user_id] = :fk_user_id;';
                    $stmt2 = $this->dbConnection->prepare($sql2);
                    $stmt2->bindParam(':pk_user_auth_id', $result['pk_user_auth_id'], PDO::PARAM_INT);
                    $stmt2->bindParam(':fk_user_id', $result['fk_user_id'], PDO::PARAM_INT);
                    if ($stmt2->execute()) {
                        if ($stmt2->rowCount() > 0) {
                            $this->dbConnection->commit();
                            $_SESSION['pk_user_id'] = $result['fk_user_id'];
                            sendJsonResponse(200, array('ok' => true, 'pk_user_id' => $result['fk_user_id'], 'pk_role_id' => $result['fk_role_id'], 'message' => 'Registro actualizado correctamente.', ));
                        }
                        else {
                            throw new Exception('Error: No se realizaron cambios en el registro.');
                        }
                    }
                    else {
                        throw new Exception('Error: Falló la instrucción de actualización del registro.');
                    }
                }
                else {
                    handleError(401, array('error' => true, 'type' => 'password', 'message' => 'Error: Contraseña inválida.'));
                }
            }
            else {
                handleError(401, array('error' => true, 'type' => 'username', 'message' => 'Error: Usuario no encontrado.'));
            }
        }
        catch(Exception $error) {
            if ($this->dbConnection->inTransaction()) {
                $this->dbConnection->rollBack();
            }
            handleExceptionError($error);
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
                $sql1 = "SELECT UsersAuth.*, CONCAT(Users.first_name, ' ', Users.last_name_1, ' ', Users.last_name_2) AS user_full_name FROM [user].[users_auth] UsersAuth JOIN [user].[users] ON UsersAuth.fk_user_id = Users.pk_user_id WHERE UsersAuth.username = '$username'";
                $stmt1 = $this->dbConnection->query($sql1);
                $result = $stmt1->fetch(PDO::FETCH_ASSOC);
                if (isset($result['pk_user_auth_id'])) {
                    $sql2 = 'DELETE FROM [user].[password_resets] WHERE username = :username;';
                    $stmt2 = $this->dbConnection->prepare($sql2);
                    $stmt2->bindParam(':username', $username, PDO::PARAM_STR);
                    if ($stmt2->execute()) {
                        $this->dbConnection->beginTransaction();
                        $token = password_hash($username, PASSWORD_BCRYPT);
                        $sql3 = 'INSERT INTO [user].[password_resets] ([username], [token], [created_at]) VALUES(:username, :token, GETDATE());';
                        $stmt3 = $this->dbConnection->prepare($sql3);
                        $stmt3->bindParam(':username', $username, PDO::PARAM_STR);
                        $stmt3->bindParam(':token', $token, PDO::PARAM_STR);
                        if ($stmt3->execute()) {
                            if ($stmt3->rowCount() > 0) {
                                // Enviar correo de recuperación de contraseña
                                require_once '../models/email.php';
                                $email = new Email();
                                $subject = 'Solicitud de restablecimiento de contraseña';
                                $template = file_get_contents('../templates/password_recovery_email.html');
                                $template = str_replace('{{username}}', $result['user_full_name'], $template);
                                $template = str_replace('{{email}}', $username, $template);
                                $template = str_replace('{{reset_link}}', $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'].":3000/restablecer-contraseña?token=$token", $template);
                                $message = $template;
                                $send = $email->send($username, $subject, $message);
                                if ($send) {
                                    $this->dbConnection->commit();
                                    sendJsonResponse(200, array('ok' => true, 'message' => 'Correo electrónico enviado correctamente.'));
                                }
                                else {
                                    throw new Exception('Error: No se realizó el envío del correo electrónico.');
                                }
                            }
                            else {
                                throw new Exception('Error: No se pudo crear el registro.');
                            }
                        }
                        else {
                            throw new Exception('Error: Falló la instrucción de creación del registro.');
                        }
                    }
                    else {
                        throw new Exception('Error: Falló la instrucción de eliminación de registros.');
                    }
                }
                else {
                    handleError(500, 'El correo electrónico proporcionado no esta registrado en la plataforma.');
                }
            }
            else {
                handleError(500, 'No se recibió un correo electrónico válido.');
            }
        }
        catch(Exception $error) {
            if ($this->dbConnection->inTransaction()) {
                $this->dbConnection->rollBack();
            }
            handleExceptionError($error);
        }

        exit();
    }

    public function passwordUpdate($token, $newPassword, $confirmPassword) {
        try {
            if (isset($token)) {
                $sql1 = "SELECT TOP 1 * FROM [user].[password_resets] WHERE [token] = '$token' AND DATEADD(HOUR, 1, [created_at]) > GETDATE()";
                $stmt1 = $this->dbConnection->query($sql1);
                $result = $stmt1->fetch(PDO::FETCH_ASSOC);
                if (isset($result['pk_password_reset_id'])) {
                    if (isset($newPassword) && isset($confirmPassword)) {
                        if ($newPassword === $confirmPassword) {
                            $this->dbConnection->beginTransaction();
                            $encryptedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
                            $sql2 = 'UPDATE [user].[users_auth] SET [password] = :password WHERE [username] = :username';
                            $stmt2 = $this->dbConnection->prepare($sql2);
                            $stmt2->bindParam(':password', $encryptedPassword, PDO::PARAM_STR);
                            $stmt2->bindParam(':username', $result['username'], PDO::PARAM_STR);
                            if ($stmt2->execute()) {
                                if ($stmt2->rowCount() > 0) {
                                    $this->dbConnection->commit();
                                    $sql3 = 'DELETE FROM [user].[password_resets] WHERE [username] = :username;';
                                    $stmt3 = $this->dbConnection->prepare($sql3);
                                    $stmt3->bindParam(':username', $result['username'], PDO::PARAM_STR);
                                    if ($stmt3->execute()) {
                                        sendJsonResponse(200, array('ok' => true, 'message' => 'La contraseña ha sido actualizada correctamente.'));
                                    }
                                    else {
                                        throw new Exception('Error: Falló la instrucción de eliminación de registro.');
                                    }
                                }
                                else {
                                    throw new Exception('Error: No se realizaron cambios en el registro.');
                                }
                            }
                            else {
                                throw new Exception('Error: Falló la instrucción de actualización del registro.');
                            }
                        }
                        else {
                            handleError(500, 'La contraseña no coincide.');
                        }
                    }
                    else {
                        handleError(500, 'La contraseña proporcionada no es válida.');
                    }
                }
                else {
                    handleError(500, 'El token ha caducado.');
                }
            }
            else {
                handleError(500, 'El token es inválido.');
            }
        }
        catch(Exception $error) {
            if ($this->dbConnection->inTransaction()) {
                $this->dbConnection->rollBack();
            }
            handleExceptionError($error);
        }

        exit();
    }
}
?>