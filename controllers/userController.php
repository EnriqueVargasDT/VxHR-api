<?php
require_once '../models/user.php';

class UserController {
    public function getAll() {
        $user = new User();
        $result = $user->getAll();
        echo json_encode($result);
        exit();
    }

    public function getById($pk_user_id) {
        $user = new User();
        $result = $user->getById($pk_user_id);
        echo json_encode($result);
        exit();
    }
}
?>