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

    public function save($data) {
        $this->user->save($data);
    }

    public function update($id, $data) {
        $this->user->update($id, $data);
    }

    public function updateStatus($id, $status) {
        $this->user->updateStatus($id, $status);
    }

    public function uploadProfilePicture($userId) {
        $this->user->uploadProfilePicture($userId);
    }

    public function uploadSignature($userId, $fileBase64) {
        $this->user->uploadSignature($userId, $fileBase64);
    }

    public function getSignature($userId) {
        $this->user->getSignature($userId);
    }

    public function getSignedPolicies() {
        $this->user->getSignedPolicies();
    }
}
?>