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
            sendJsonResponse(200, array('ok' => true, 'role' => $decoded['role'], ));
        }
        catch (Exception $error) {
            handleExceptionError($error);
        }
    }

    public function getAll() {
        try {
            $sql = 'SELECT * FROM [user].[roles]';
            $stmt = $this->dbConnection->query($sql);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sendJsonResponse(200, array('ok' => true, 'roles' => $result, ));
        }
        catch (Exception $error) {
            handleExceptionError($error);
        }
    }
}
?>