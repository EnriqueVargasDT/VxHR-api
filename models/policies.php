<?php
require_once '../config/config.php';
require_once 'email.php';

class Policies {
    private $dbConnection;

    public function __construct() {
        $this->dbConnection = dbConnection();
    }

    public function getAll() {
        try {
            $sql = "SELECT
                    p.*,
                    CONCAT(u.first_name, ' ' , u.last_name_1, ' ', u.last_name_2) AS created_by_full_name
                    FROM [dbo].[policies] p
                    LEFT JOIN [user].[users] u ON p.created_by = u.pk_user_id
                    ORDER BY created_at DESC";
            $result = $this->dbConnection->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            sendJsonResponse(200, ['ok' => true, 'data' => $result, ]);
        }
        catch (Exception $error) {
            handleExceptionError($error);
        }

        exit();
    }

    public function getById($id) {
        try {
            $sql = 'SELECT * FROM [dbo].[policies] WHERE pk_policy_id = :pk_policy_id';
            $stmt = $this->dbConnection->prepare($sql);
            $stmt->bindParam(':pk_policy_id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sendJsonResponse(200, ['ok' => true, 'data' => $result]);
        }
        catch (Exception $error) {
            handleExceptionError($error);
        }

        exit();
    }

    public function getAllUsersById($id) {
        try {
            $sql = "
                SELECT
                    p.pk_policy_id,
                    p.policy,
                    CASE WHEN TRIM(CONCAT(u.first_name, ' ', u.last_name_1, ' ', u.last_name_2)) = '' THEN '~Sin Asignar' ELSE TRIM(CONCAT(u.first_name, ' ', u.last_name_1, ' ', u.last_name_2))
                    END AS user_full_name,
                    jp.job_position,
                    CASE WHEN institutional_email IS NULL THEN '~Sin Asignar' ELSE institutional_email
                    END AS username,
                    p.content,
                    p.created_at,
                    up.signing_date,
                    up.signature_file
                FROM [dbo].[policies] p
                LEFT JOIN [job_position].[positions] jp ON p.fk_job_position_type_id = jp.fk_job_position_type_id
                LEFT JOIN [user].[users] u ON jp.pk_job_position_id = u.fk_job_position_id
                LEFT JOIN [user].[policies] up ON p.pk_policy_id = up.fk_policy_id AND up.fk_user_id = u.pk_user_id
                WHERE p.pk_policy_id = :pk_policy_id";
            $stmt = $this->dbConnection->prepare($sql);
            $stmt->bindParam(':pk_policy_id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sendJsonResponse(200, ['ok' => true, 'data' => $result]);
        }
        catch (Exception $error) {
            handleExceptionError($error);
        }

        exit();
    }

    public function save($data) {
        try {
            $this->dbConnection->beginTransaction();
            $sql = 'INSERT INTO [dbo].[policies] ([policy], [nom_iso], [fk_job_position_type_id], [content], [created_by])
                    VALUES(:policy, :nom_iso, :fk_job_position_type_id, :content, :created_by)';
            $stmt = $this->dbConnection->prepare($sql);
            $stmt->bindParam(':policy', $data['policy'], PDO::PARAM_STR);
            $stmt->bindParam(':nom_iso', $data['nom_iso'], PDO::PARAM_STR);
            $stmt->bindParam(':fk_job_position_type_id', $data['fk_job_position_type_id'], PDO::PARAM_INT);
            $stmt->bindParam(':content', $data['content'], PDO::PARAM_STR);
            $stmt->bindParam(':created_by', $_SESSION['pk_user_id'], PDO::PARAM_INT);
            if (!$stmt->execute() && $stmt->rowCount() === 0) {
                throw new Exception('Error: No se pudo crear la política.');
            }

            // Envío de notificación:
            $sql2 = "
                SELECT u.pk_user_id, ua.username AS email, CONCAT(u.first_name, ' ' , u.last_name_1, ' ', u.last_name_2) AS full_name
                FROM [user].[users] u
                LEFT JOIN [job_position].[positions] jp ON u.fk_job_position_id = jp.pk_job_position_id
                LEFT JOIN [user].[users_auth] ua ON u.pk_user_id = ua.fk_user_id
                WHERE jp.fk_job_position_type_id = :fk_job_position_type_id
            ";
            $stmt2 = $this->dbConnection->prepare($sql2);
            $stmt2->bindParam(':fk_job_position_type_id', $data['fk_job_position_type_id'], PDO::PARAM_INT);
            $stmt2->execute();
            $result = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($result)) {
                $sql3 = "UPDATE [user].[users] SET has_signed_policies = 0 WHERE pk_user_id IN (SELECT CAST(value AS INT) FROM STRING_SPLIT(:pk_user_ids, ','))";
                $stmt3 = $this->dbConnection->prepare($sql3);
                $stmt3->bindValue(':pk_user_ids', implode(',', array_column($result, 'pk_user_id')), PDO::PARAM_INT);
                if (!$stmt3->execute() && $stmt3->rowCount() === 0) {
                    throw new Exception('No se pudo realizar la asignación de la nueva política a los usuarios dentro del alcance.');
                }

                require_once '../models/email.php';
                $email = new Email();
                $subject = 'Notificación de asignación de nueva política.';
                $template = file_get_contents('../templates/new_policies_notification.html');
                $link = $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'].":3000";

                foreach ($result as $row) {
                    $message = str_replace('{{username}}', $row['full_name'], $template);
                    $message = str_replace('{{link}}', $link, $message);
                    $send = $email->send($row['email'], $subject, $message);
                    if (!$send) {
                        throw new Exception('Error: No se pudo realizar el envío del correo electrónico a ' . $row['email']);
                    }
                }
            }

            $this->dbConnection->commit();
            sendJsonResponse(200, ['ok' => true, 'message' => 'La nueva política fue creada exitosamente.']);
        }
        catch(Exception $error) {
            if ($this->dbConnection->inTransaction()) {
                $this->dbConnection->rollBack();
            }
            handleExceptionError($error);
        }

        exit();
    }

    public function update($id, $data) {
        try {
            $this->dbConnection->beginTransaction();
            $sql = 'UPDATE [dbo].[policies] SET [policy] = :policy, [nom_iso] = :nom_iso, [fk_job_position_type_id] = :fk_job_position_type_id, [content] = :content WHERE [pk_policy_id] = :pk_policy_id';
            $stmt = $this->dbConnection->prepare($sql);
            $stmt->bindParam(':policy', $data['policy'], PDO::PARAM_STR);
            $stmt->bindParam(':nom_iso', $data['nom_iso'], PDO::PARAM_STR);
            $stmt->bindParam(':fk_job_position_type_id', $data['fk_job_position_type_id'], PDO::PARAM_INT);
            $stmt->bindParam(':content', $data['content'], PDO::PARAM_STR);
            $stmt->bindParam(':pk_policy_id', $id, PDO::PARAM_INT);
            if (!$stmt->execute() && $stmt->rowCount() === 0) {
                throw new Exception('Error: No se realizaron cambios en los datos de la política.');
            }

            $this->dbConnection->commit();
            sendJsonResponse(200, ['ok' => true, 'message' => 'Los datos de la política fueron actualizados exitosamente.']);
        }
        catch(Exception $error) {
            if ($this->dbConnection->inTransaction()) {
                $this->dbConnection->rollBack();
            }
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