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
            sendJsonResponse(200, ['ok' => true, 'role' => $decoded['role'], ]);
        }
        catch (Exception $error) {
            handleExceptionError($error);
        }
    }

    public function getAll() {
        try {
            $sql = 'SELECT * FROM [user].[roles]';
            $result = $this->dbConnection->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            sendJsonResponse(200, ['ok' => true, 'roles' => $result, ]);
        }
        catch (Exception $error) {
            handleExceptionError($error);
        }
    }
}
?>