<?php
require_once '../config/config.php';

Class UserFiles {
    private $dbConnection;

    public function __construct() {
        $this->dbConnection = dbConnection();
    }

    public function upload($userId, $fileBase64, $type) {
        try {
            
        }
        catch (Exception $error) {
            
        }
    }

    public function get() {
        try {
            
        }
        catch (Exception $error) {
            
        }
    }
}

?>