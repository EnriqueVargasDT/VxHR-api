<?php
require_once '../config/config.php';

class UserPolicies {
    private $dbConnection;

    public function __construct() {
        $this->dbConnection = dbConnection();
    }

    public function getAll($userId) {
        try {
            $sql = 'SELECT * FROM [dbo].[policies] WHERE pk_policy_id NOT IN(SELECT fk_policy_id FROM [user].[policies] WHERE fk_user_id = :fk_user_id)';
            $stmt = $this->dbConnection->prepare($sql);
            $stmt->bindParam(':fk_user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sendJsonResponse(200, array('ok' => true, 'data' => $result, ));
        }
        catch (Exception $error) {
            handleExceptionError($error);
        }

        exit();
    }

    public function save($data) {
        try {
            $this->dbConnection->beginTransaction();
            $sql1 = 'INSERT INTO [user].[policies] ([fk_user_id], [fk_policy_id], [signed_date], [signed_file]) VALUES (:fk_user_id, :fk_policy_id, :signed_date, :signed_file)';
            $stmt1 = $this->dbConnection->prepare($sql1);
            $stmt1->bindParam(':fk_user_id', $data['fk_user_id'], PDO::PARAM_INT);
            $stmt1->bindParam(':fk_policy_id', $data['fk_policy_id'], PDO::PARAM_INT);
            $stmt1->bindParam(':signed_date', $data['signed_date'], PDO::PARAM_STR);
            $stmt1->bindParam(':signed_file', $data['signed_file'], PDO::PARAM_STR);
            if (!$stmt1->execute() || $stmt1->rowCount() === 0) {
                throw new Exception('Error: No se pudo registrar las políticas firmadas por el usuario.');
            }

            $this->dbConnection->commit();
            sendJsonResponse(200, array('ok' => true, 'message' => 'Política firmada y registrada exitosamente.', ));
        }
        catch (Exception $error) {
            if ($this->dbConnection->inTransaction()) {
                $this->dbConnection->rollBack();
            }
            handleExceptionError($error);
        }

        exit();
    }
}
?>