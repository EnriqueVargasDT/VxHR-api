<?php
require_once '../models/userFiles.php';

class UserFilesController {
    private $userFiles;

    public function __construct() {
        $this->userFiles = new UserFiles();
    }

    public function upload($userId, $typeFile, $file) {
        $this->userFiles->upload($userId, $typeFile, $file);
    }

    public function getByType($userId, $typeFile) {
        $this->userFiles->getByType($userId, $typeFile);
    }
}
?>