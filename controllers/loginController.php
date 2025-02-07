<?php
require_once '../models/login.php';

class LoginController {
    private $login;

    public function __construct() {
        $this->login = new Login();
    }

    public function validate($username, $password, $rememberMe) {
        $this->login->validate($username, $password, $rememberMe);
    }

    public function passwordRecovery($username) {
        $this->login->passwordRecovery($username);
    }

    public function passwordUpdate($token, $newPassword, $confirmPassword) {
        $this->login->passwordUpdate($token, $newPassword, $confirmPassword);
    }
}
?>