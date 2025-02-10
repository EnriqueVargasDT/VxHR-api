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

            echo json_encode(array('ok' => true, 'users' => $users));
        }
        catch(Exception $error) {
            http_response_code(500);
            echo json_encode(array('error' => true, 'message' => $error), JSON_UNESCAPED_UNICODE, JSON_UNESCAPED_SLASHES);
        }

        exit();
    }

    public function getById($pk_user_id) {
        try {
            $sql = "SELECT Users.*, CONCAT(Users.first_name, ' ' , Users.last_name_1, ' ', Users.last_name_2) AS full_name, MS.marital_status, RS.relationship AS emergency_relationship FROM dbo.users Users JOIN dbo.marital_status MS ON Users.pk_marital_status_id = MS.pk_marital_status_id JOIN dbo.relationships RS ON Users.pk_emergency_relationship_id = RS.pk_relationship_id WHERE Users.pk_user_id = $pk_user_id";
            $stmt = $this->dbConnection->query($sql);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                echo json_encode(array('ok' => true, 'user' => $user));
            }
            else {
                http_response_code(500);
                echo json_encode(array('error' => true, 'message' => 'No se encontró al usuario en la plataforma'), JSON_UNESCAPED_UNICODE, JSON_UNESCAPED_SLASHES);    
            }
        }
        catch(Exception $error) {
            http_response_code(500);
            echo json_encode(array('error' => true, 'message' => $error));
        }

        exit();
    }
}
?>