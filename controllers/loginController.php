<?php
require_once '../models/login.php';

class LoginController {
    public function validate($username, $password) {
        $login = new Login();
        $result = $login->validate($username, $password);
        echo json_encode($result);
        exit();
    }
}
?>