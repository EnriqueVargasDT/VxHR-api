<?php
require_once '../models/policies.php';

class PoliciesController {
    private $policies;

    public function __construct() {
        $this->policies = new Policies();
    }

    public function getAll($available = null) {
        $this->policies->getAll($available);
    }

    public function getById($id) {
        $this->policies->getById($id);
    }

    public function getAllUsersById($id, $page) {
        $this->policies->getAllUsersById($id, $page);
    }

    public function save($data) {
        $this->policies->save($data);
    }

    public function update($id, $data) {
        $this->policies->update($id, $data);
    }

    public function updateStatus($id, $status) {
        $this->policies->updateStatus($id, $status);
    }
}
?>