<?php
require_once '../config/config.php';

class User {
    private $dbConnection;

    public function __construct() {
        $this->dbConnection = dbConnection();
    }

    public function getAll() {
        try {
            $sql = "SELECT * FROM dbo.users";
            $stmt = $this->dbConnection->query($sql);
            $users = array();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $users[] = $row;
            }

            echo json_encode($users);
        }
        catch(Exception $error) {
            http_response_code(500);
            echo json_encode(array('error' => true, 'message' => $error), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        exit();
    }

    public function getById($pk_user_id) {
        try {
            $sql = "SELECT *, CONCAT(first_name, ' ' , last_name_1, ' ', last_name_2) AS full_name FROM dbo.users WHERE pk_user_id = $pk_user_id";
            $stmt = $this->dbConnection->query($sql);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode($user);
        }
        catch(Exception $error) {
            http_response_code(500);
            echo json_encode(array('error' => true, 'message' => $error), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        exit();
    }
}
?>