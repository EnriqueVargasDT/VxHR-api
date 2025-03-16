<?php
class Logout {
    public static function logout() {
        try {
            setcookie('token', '', [
                'expires' => time() - 3600, // Expirar inmediatamente
                'path' => '/',
                'secure' => false,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
            session_unset();
            session_destroy();
            sendJsonResponse(200, ['ok' => true, 'message' => 'Sesión cerrada exitosamente.']);
        }
        catch(Exception $error) {
            handleExceptionError($error);
        }

        exit();
    }
}
?>