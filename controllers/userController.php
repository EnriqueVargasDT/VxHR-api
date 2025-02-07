<?php
require_once '../models/user.php';

class UserController {
    private $user;

    public function __construct() {
        $this->user = new User();
    }

    public function getAll() {
        $this->user->getAll();
    }

    public function getById($pk_user_id) {
        $this->user->getById($pk_user_id);
    }
}
?>