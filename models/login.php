<?php
require_once '../config/config.php';
require_once '../libs/php-jwt/src/JWT.php';
use \Firebase\JWT\JWT;

class Login {
    private $dbConnection;
    private $secretKey;

    public function __construct() {
        $this->dbConnection = dbConnection();
        $this->secretKey = getenv('ENCRYPT_PASSWORD_KEY');
    }

    public function validate($username, $password, $rememberMe) {
        try {
            $sql1 = "SELECT TOP 1 ua.*, u.has_signed_policies FROM [user].[users_auth] ua JOIN [user].[users] u ON ua.[fk_user_id] = u.[pk_user_id] WHERE ua.[username] = '$username' AND u.[is_active] = 1";
            $result = $this->dbConnection->query($sql1)->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                if ($_SERVER['HTTP_ORIGIN'] === 'https://vica.vittilog.com') {
                    $allowedUsers = [1, 10, 11, 531, 553, 84, 403, 513, 440,];
                    if (!in_array($result['fk_user_id'], $allowedUsers)) {
                        handleError(401, ['error' => true, 'type' => 'username', 'message' => 'La plataforma se encuentra en mantenimiento. Favor de intentar más tarde.']);
                        exit();
                    }
                }

                $decryptedPassword = $this->decryptedPassword($password);
                if (password_verify($decryptedPassword, $result['password'])) {
                    $expTime = $rememberMe ? time() + (30 * 24 * 60 * 60) : time() + (60 * 60);
                    $payload = [
                        'iat' => time(),
                        'exp' => $rememberMe ? time() + (5 * 24 * 60 * 60) /* Token válido por 5 días */ : time() + 28800 /* Token válido por 8 horas */,
                        'sub' => $username,
                        'role' => $result['fk_role_id'], 
                    ];
                    
                    // Generar el JWT con la librería JWT
                    $jwt = JWT::encode($payload, $this->secretKey, 'HS256');
                    setcookie('token', $jwt, [
                        'expires' => $payload['exp'],
                        'path' => '/',
                        'secure' => true, // https o  http
                        'httponly' => true,
                        'samesite' => 'None',
                    ]);

                    // Actualizar la fecha de último inicio de sesión:
                    $this->dbConnection->beginTransaction();

                    $sql2 = 'UPDATE [user].[users_auth] SET [last_access_at] = GETDATE() WHERE [pk_user_auth_id] = :pk_user_auth_id AND [fk_user_id] = :fk_user_id;';
                    $stmt2 = $this->dbConnection->prepare($sql2);
                    $stmt2->bindParam(':pk_user_auth_id', $result['pk_user_auth_id'], PDO::PARAM_INT);
                    $stmt2->bindParam(':fk_user_id', $result['fk_user_id'], PDO::PARAM_INT);
                    if (!$stmt2->execute() || $stmt2->rowCount() === 0) {
                        throw new Exception('Error: No se realizaron cambios en la fecha de último inicio de sesión.');
                    }
                    
                    $this->dbConnection->commit();
                    $_SESSION['pk_user_id'] = $result['fk_user_id'];
                    sendJsonResponse(200, ['ok' => true, 'pk_user_id' => $result['fk_user_id'], 'pk_role_id' => $result['fk_role_id'], 'has_signed_policies' => $result['has_signed_policies'], 'message' => 'Registro actualizado exitosamente.', ]);
                }
                else {
                    handleError(401, ['error' => true, 'type' => 'password', 'message' => 'Contraseña inválida.']);
                }
            }
            else {
                handleError(401, ['error' => true, 'type' => 'username', 'message' => 'Usuario no encontrado.']);
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
                $sql1 = "
                    SELECT
                        UsersAuth.*,
                        CASE 
                            WHEN Users.institutional_email = '-' THEN Users.personal_email 
                            ELSE Users.institutional_email 
                        END AS email,
                        CONCAT(Users.first_name, ' ', Users.last_name_1, ' ', Users.last_name_2) AS user_full_name
                    FROM [user].[users_auth] UsersAuth
                    INNER JOIN [user].[users] Users ON UsersAuth.fk_user_id = Users.pk_user_id
                    WHERE UsersAuth.username = '$username'
                ";
                $result = $this->dbConnection->query($sql1)->fetch(PDO::FETCH_ASSOC);
                if (isset($result['pk_user_auth_id'])) {
                    $this->dbConnection->beginTransaction();

                    $sql2 = 'DELETE FROM [user].[password_resets] WHERE username = :username;';
                    $stmt2 = $this->dbConnection->prepare($sql2);
                    $stmt2->bindParam(':username', $username, PDO::PARAM_STR);
                    $stmt2->execute();
                    
                    // Crear el token de recuperación de contraseña.
                    $token = password_hash($username, PASSWORD_BCRYPT);
                    $sql3 = 'INSERT INTO [user].[password_resets] ([username], [token], [created_at]) VALUES(:username, :token, GETDATE());';
                    $stmt3 = $this->dbConnection->prepare($sql3);
                    $stmt3->bindParam(':username', $username, PDO::PARAM_STR);
                    $stmt3->bindParam(':token', $token, PDO::PARAM_STR);
                    if (!$stmt3->execute() || $stmt3->rowCount() === 0) {
                        throw new Exception('Error: No se pudo crear el token de recuperación de contraseña.');
                    }

                    // Enviar correo de recuperación de contraseña
                    require_once '../models/email.php';
                    $email = new Email();
                    $subject = 'Solicitud de restablecimiento de contraseña';
                    $template = file_get_contents('../templates/password_recovery_email.html');
                    $template = str_replace('{{user_full_name}}', $result['user_full_name'], $template);
                    $HTTP_HOST = null;
                    if ($_SERVER['HTTP_HOST'] === 'localhost') {
                        $HTTP_HOST = 'http://localhost:3000';
                    }
                    else {
                        $HTTP_HOST = $_SERVER['HTTP_ORIGIN'];
                    }
                    $template = str_replace('{{reset_link}}', $HTTP_HOST."/restablecer-contraseña?token=$token", $template);
                    $message = $template;
                    $send = $email->send($result['email'], $subject, $message);
                    if (!$send) {
                        throw new Exception('Error: No se pudo realizar el envío del correo electrónico.');
                    }
                    
                    $this->dbConnection->commit();
                    sendJsonResponse(200, ['ok' => true, 'message' => 'Correo electrónico enviado exitosamente.']);
                }
                else {
                    handleError(500, 'El usuario proporcionado no esta registrado en la plataforma.');
                }
            }
            else {
                handleError(500, 'No se recibió un usuario válido.');
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
                $result = $this->dbConnection->query($sql1)->fetch(PDO::FETCH_ASSOC);
                if (isset($result['pk_password_reset_id'])) {
                    if (isset($newPassword) && isset($confirmPassword)) {
                        if ($newPassword === $confirmPassword) {
                            $this->dbConnection->beginTransaction();

                            $encryptedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
                            $sql2 = 'UPDATE [user].[users_auth] SET [password] = :password WHERE [username] = :username';
                            $stmt2 = $this->dbConnection->prepare($sql2);
                            $stmt2->bindParam(':password', $encryptedPassword, PDO::PARAM_STR);
                            $stmt2->bindParam(':username', $result['username'], PDO::PARAM_STR);
                            if (!$stmt2->execute() || $stmt2->rowCount() === 0) {
                                throw new Exception('Error: No se realizaron cambios en el contraseña.');
                            }
                            
                            $sql3 = 'DELETE FROM [user].[password_resets] WHERE [username] = :username;';
                            $stmt3 = $this->dbConnection->prepare($sql3);
                            $stmt3->bindParam(':username', $result['username'], PDO::PARAM_STR);
                            $stmt3->execute();

                            $this->dbConnection->commit();
                            sendJsonResponse(200, ['ok' => true, 'message' => 'La contraseña ha sido actualizada exitosamente.']);
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