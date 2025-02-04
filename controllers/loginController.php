<?php
require_once '../models/login.php';

class LoginController {
    public function validate($username, $password, $rememberMe) {
        $login = new Login();
        $result = $login->validate($username, $password, $rememberMe);
        echo json_encode($result);
        exit();
    }
}
?>