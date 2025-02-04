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
            
            echo json_encode(array('ok' => true, 'message' => 'Sesión cerrada.'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        catch(Exception $error) {
            echo json_encode(array('error' => true, 'message' => $error), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        exit();
    }
}
?>