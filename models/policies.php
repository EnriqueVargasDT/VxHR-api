<?php
require_once '../config/config.php';

class Policies {
    private $dbConnection;

    public function __construct() {
        $this->dbConnection = dbConnection();
    }

    public function getAll() {
        try {
            $sql = 'SELECT * FROM [dbo].[policies] ORDER BY created_at DESC';
            $result = $this->dbConnection->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            sendJsonResponse(200, array('ok' => true, 'data' => $result, ));
        }
        catch (Exception $error) {
            handleExceptionError($error);
        }

        exit();
    }
}
?>