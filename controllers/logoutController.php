<?php
require_once '../models/logout.php';

class LogoutController {
    public static function logout() {
        $result = Logout::logout();
        echo json_encode($result);
        exit();
    }
}
?>