<?php
require_once '../models/role.php';

class RoleController {
    private $role;

    public function __construct() {
        $this->role = new Role();
    }

    public function get() {
        $this->role->get();
    }
}
?>