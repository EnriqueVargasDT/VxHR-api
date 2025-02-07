<?php
require_once '../models/logout.php';

class LogoutController {
    public static function logout() {
        Logout::logout();
    }
}
?>