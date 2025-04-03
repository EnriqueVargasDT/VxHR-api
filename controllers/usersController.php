<?php
require_once '../models/users.php';

class UsersController {
    private $users;

    public function __construct() {
        $this->users = new Users();
    }

    public function getAll() {
        $this->users->getAll();
    }
}
?>