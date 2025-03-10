<?php
require_once '../models/userPolicies.php';

class UserPoliciesController {
    private $userPolicies;

    public function __construct() {
        $this->userPolicies = new UserPolicies();
    }

    public function getAll($userId) {
        $this->userPolicies->getAll($userId);
    }

    public function save($data) {
        $this->userPolicies->save($data);
    }
}
?>