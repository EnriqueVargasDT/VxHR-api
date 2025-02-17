<?php
require_once '../config/config.php';
require_once '../models/token.php';

class Role {
    private $dbConnection;
    private $secretKey;
    private $token;

    public function __construct() {
        $this->dbConnection = dbConnection();
        $this->secretKey = getenv('APP_SECRET_KEY');
        $this->token = new Token();
    }

    public function getBySession() {
        try {
            $decoded = $this->token->validate();
            echo json_encode(array('ok' => true, 'role' => $decoded['role'], ), JSON_UNESCAPED_UNICODE, JSON_UNESCAPED_SLASHES);
        }
        catch (Exception $error) {
            http_response_code(500);
            echo json_encode(array('error' => true, 'message' => $error), JSON_UNESCAPED_UNICODE, JSON_UNESCAPED_SLASHES);
        }
    }

    public function getAll() {
        try {
            $sql = 'SELECT * FROM [user].[roles]';
            $stmt = $this->dbConnection->query($sql);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(array('ok' => true, 'roles' => $result, ));
        }
        catch (Exception $error) {
            http_response_code(500);
            echo json_encode(array('error' => true, 'message' => $error), JSON_UNESCAPED_UNICODE, JSON_UNESCAPED_SLASHES);
        }
    }
}
?>