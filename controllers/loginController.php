<?php
require_once '../models/login.php';

class LoginController {
    private $login;

    public function __construct() {
        $this->login = new Login();
    }

    public function validate($username, $password, $rememberMe) {
        $result = $this->login->validate($username, $password, $rememberMe);
        echo json_encode($result);
        exit();
    }

    public function passwordRecovery($username) {
        $result = $this->login->passwordRecovery($username);
        echo json_encode($result);
        exit();
    }

    public function passwordUpdate($token, $newPassword, $confirmPassword) {
        $result = $this->login->passwordUpdate($token, $newPassword, $confirmPassword);
        echo json_encode($result);
        exit();
    }
}
?>