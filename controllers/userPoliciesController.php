<?php
require_once '../models/userPolicies.php';

class UserPoliciesController {
    private $userPolicies;

    public function __construct() {
        $this->userPolicies = new UserPolicies();
    }

    public function getAll($userId, $signed) {
        $this->userPolicies->getAll($userId, $signed);
    }

    public function save($data) {
        $this->userPolicies->save($data);
    }

    public function updateStatus($data) {
        $this->userPolicies->updateStatus($data);
    }
}
?>