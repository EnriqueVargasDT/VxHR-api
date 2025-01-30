<?php
require_once '../models/login.php';

class LoginController {
    public function validate($username, $password, $iv) {
        $login = new Login();
        $result = $login->validate($username, $password, $iv);
        echo json_encode($result);
        exit();
    }
}
?>