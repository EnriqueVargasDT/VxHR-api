<?php
require_once '../models/role.php';

class RoleController {
    private $role;

    public function __construct() {
        $this->role = new Role();
    }

    public function getBySession() {
        $this->role->getBySession();
    }

    public function getAll() {
        $this->role->getAll();
    }
}
?>