<?php
require_once '../models/organization.php';

class OrganizationController {
    private $organization;

    public function __construct() {
        $this->organization = new Organization();
    }

    public function getData() {
        $this->organization->getData();
    }
}
?>