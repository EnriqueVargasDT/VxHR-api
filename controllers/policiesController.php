<?php
require_once '../models/policies.php';

class PoliciesController {
    private $policies;

    public function __construct() {
        $this->policies = new Policies();
    }

    public function getAll() {
        $this->policies->getAll();
    }

    public function updateStatus($id, $status) {
        $this->policies->updateStatus($id, $status);
    }
}
?>