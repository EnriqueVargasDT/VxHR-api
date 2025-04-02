<?php
require_once '../config/config.php';

class UserPolicies {
    private $dbConnection;

    public function __construct() {
        $this->dbConnection = dbConnection();
    }

    public function getAll($userId, $signed) {
        try {
            if ($signed) {
                $sql = 'SELECT p.pk_policy_id, p.policy, p.content, up.*
                        FROM [user].[policies] up
                        LEFT JOIN [dbo].[policies] p ON up.fk_policy_id = p.pk_policy_id AND p.[status] = 1
                        WHERE up.fk_user_id = :fk_user_id;';
                $stmt = $this->dbConnection->prepare($sql);
                $stmt->bindParam('fk_user_id', $userId, PDO::PARAM_INT);
                $stmt->execute();
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                sendJsonResponse(200, ['ok' => true, 'data' => $result, ]);
            }
            else {
                $sql = "SELECT p.*
                        FROM [dbo].[policies] p
                        WHERE NOT EXISTS(
                            SELECT 1
                            FROM [user].[policies] up
                            WHERE up.fk_policy_id = p.pk_policy_id
                            AND up.fk_user_id = $userId
                        )
                        AND p.fk_job_position_type_id IN(
                            (
                                SELECT jp.fk_job_position_type_id
                                FROM [user].[users] u
                                INNER JOIN [job_position].[positions] jp ON u.fk_job_position_id = jp.pk_job_position_id
                                WHERE u.pk_user_id = $userId
                            ),
                            3
                        );";
                $result = $this->dbConnection->query($sql)->fetchAll(PDO::FETCH_ASSOC);
                sendJsonResponse(200, ['ok' => true, 'data' => $result, ]);
            }
        }
        catch (Exception $error) {
            handleExceptionError($error);
        }

        exit();
    }

    public function save($data) {
        try {
            $this->dbConnection->beginTransaction();
            $sql = 'INSERT INTO [user].[policies] ([fk_user_id], [fk_policy_id], [signing_date], [signature_file]) VALUES (:fk_user_id, :fk_policy_id, :signing_date, :signature_file)';
            $stmt = $this->dbConnection->prepare($sql);
            $stmt->bindParam(':fk_user_id', $data['fk_user_id'], PDO::PARAM_INT);
            $stmt->bindParam(':fk_policy_id', $data['fk_policy_id'], PDO::PARAM_INT);
            $stmt->bindParam(':signing_date', $data['signing_date'], PDO::PARAM_STR);
            $stmt->bindParam(':signature_file', $data['signature_file'], PDO::PARAM_STR);
            if (!$stmt->execute() || $stmt->rowCount() === 0) {
                throw new Exception('Error: No se pudo registrar las políticas firmadas por el usuario.');
            }

            $this->dbConnection->commit();
            sendJsonResponse(200, ['ok' => true, 'message' => 'Política firmada y registrada exitosamente.', ]);
        }
        catch (Exception $error) {
            if ($this->dbConnection->inTransaction()) {
                $this->dbConnection->rollBack();
            }
            handleExceptionError($error);
        }

        exit();
    }

    public function updateStatus($data) {
        try {
            $this->dbConnection->beginTransaction();
            $sql = 'UPDATE [user].[users] SET [has_signed_policies] = :has_signed_policies WHERE pk_user_id = :pk_user_id;';
            $stmt = $this->dbConnection->prepare($sql);
            $stmt->bindParam(':has_signed_policies', $data['signed'], PDO::PARAM_INT);
            $stmt->bindParam(':pk_user_id', $data['fk_user_id'], PDO::PARAM_INT);
            if (!$stmt->execute() || $stmt->rowCount() === 0) {
                throw new Exception('Error: No se pudo actualizar el estado del usuario.');
            }

            $this->dbConnection->commit();
            sendJsonResponse(200, ['ok' => true, 'message' => 'Estado del usuario actualizado exitosamente.', ]);
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