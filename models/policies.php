<?php
require_once '../config/config.php';

class Policies {
    private $dbConnection;

    public function __construct() {
        $this->dbConnection = dbConnection();
    }

    public function getAll() {
        try {
            $sql = "
                SELECT
                p.*,
                CONCAT(u.first_name, ' ' , u.last_name_1, ' ', u.last_name_2) AS created_by_full_name
                FROM [dbo].[policies] p
                LEFT JOIN [user].[users] u ON p.created_by = u.pk_user_id
                ORDER BY created_at DESC
            ";
            $result = $this->dbConnection->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            sendJsonResponse(200, array('ok' => true, 'data' => $result, ));
        }
        catch (Exception $error) {
            handleExceptionError($error);
        }

        exit();
    }

    public function updateStatus($id, $status) {
        try {
            $this->dbConnection->beginTransaction();
            
            $sql = 'UPDATE [dbo].[policies] SET [status] = :status WHERE [pk_policy_id] = :id;';
            $stmt = $this->dbConnection->prepare($sql);
            $stmt->bindParam(':status', $status, PDO::PARAM_INT);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            if (!$stmt->execute() || $stmt->rowCount() === 0) {
                throw new Exception('Error: No se realizaron cambios en el estado de la política.');
            }
            
            $this->dbConnection->commit();
            sendJsonResponse(200, ['ok' => true, 'message' => 'El estado de la política fue actualizado exitosamente.']);
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