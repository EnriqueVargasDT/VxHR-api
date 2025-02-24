<?php
require_once '../config/config.php';

class JobPosition {
    private $dbConnection;

    public function __construct() {
        $this->dbConnection = dbConnection();
    }

    public function getAll() {
        try {
            $sql = "
                SELECT
                    jpp.pk_job_position_id AS id,
                    jpp.job_position_name AS description,
                    jpa.job_position_area AS area,
                    jpd.job_position_department AS department,
                    jpo.job_position_office AS office,
                    ur.role,
                    jpt.job_position_type,
                    jps.job_position_status,
                    jpp.fk_job_position_status_id AS job_position_status_id,
                    jpp.publish_date,
                    jpp.fk_job_position_area_id AS parent_id,
                    CONCAT(u.first_name, ' ', u.last_name_1, ' ', u.last_name_2) AS created_by
                FROM [job_position].[positions] jpp
                LEFT JOIN [job_position].[area] jpa ON jpp.fk_job_position_area_id = jpa.pk_job_position_area_id
                LEFT JOIN [job_position].[department] jpd ON jpp.fk_job_position_department_id = jpd.pk_job_position_department_id
                LEFT JOIN [job_position].[office] jpo ON jpp.fk_job_position_office_id = jpo.pk_job_position_office_id
                LEFT JOIN [job_position].[type] jpt ON jpp.fk_job_position_type_id = jpt.pk_job_position_type_id
                LEFT JOIN [job_position].[status] jps ON jpp.fk_job_position_status_id = jps.pk_job_position_status_id
                LEFT JOIN [user].[roles] ur ON jpp.fk_role_id = ur.pk_role_id
                LEFT JOIN [user].[users] u ON jpp.created_by = u.pk_user_id
                WHERE jpp.publish_date <= GETDATE()
                ORDER BY jpp.created_at DESC
            ;" . PHP_EOL;
            $stmt = $this->dbConnection->query($sql);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sendJsonResponse(200, array('ok' => true, 'data' => $result));
        }
        catch (Exception $error) {
            handleExceptionError($error);
        }

        exit();
    }

    public function getDataById($id) {
        try {
            $sql = '
                SELECT
                    job_position_name,
                    fk_job_position_area_id AS job_position_area_id,
                    fk_job_position_department_id AS job_position_department_id,
                    fk_job_position_office_id AS job_position_office_id,
                    fk_job_position_type_id AS job_position_type_id,
                    fk_job_position_status_id AS job_position_status_id,
                    fk_role_id AS user_role_id,
                    job_position_parent_id,
                    publish_date
                FROM [job_position].[positions]
                WHERE pk_job_position_id = :id
            ;' . PHP_EOL;
            $stmt = $this->dbConnection->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sendJsonResponse(200, array('ok' => true, 'data' => $result));
        }
        catch (Exception $error) {
            handleExceptionError($error);
        }

        exit();
    }

    public function save($data) {
        try {
            $fields = '[job_position_name], [fk_job_position_area_id], [fk_job_position_department_id], [fk_job_position_office_id], [fk_job_position_type_id], [fk_job_position_status_id], [fk_role_id], [job_position_parent_id], [publish_date], [created_by]';
            $values = ':job_position_name, :job_position_area_id, :job_position_department_id, :job_position_office_id, :job_position_type_id, :job_position_status_id, :user_role_id, :job_position_parent_id, :publish_date, :created_by';
            $sql = sprintf('INSERT INTO [job_position].[positions] (%s) VALUES(%s)', $fields, $values);
            $stmt = $this->dbConnection->prepare($sql);
            $stmt->bindParam(':job_position_name', $data['job_position_name'], PDO::PARAM_STR);
            $stmt->bindParam(':job_position_area_id', $data['job_position_area_id'], PDO::PARAM_INT);
            $stmt->bindParam(':job_position_department_id', $data['job_position_department_id'], PDO::PARAM_INT);
            $stmt->bindParam(':job_position_office_id', $data['job_position_office_id'], PDO::PARAM_INT);
            $stmt->bindParam(':job_position_type_id', $data['job_position_type_id'], PDO::PARAM_INT);
            $stmt->bindParam(':job_position_status_id', $data['job_position_status_id'], PDO::PARAM_INT);
            $stmt->bindParam(':user_role_id', $data['user_role_id'], PDO::PARAM_INT);
            $stmt->bindParam(':job_position_parent_id', $data['job_position_parent_id'], PDO::PARAM_INT);
            $stmt->bindParam(':publish_date', $data['publish_date'], PDO::PARAM_STR);
            $stmt->bindValue(':created_by', $_SESSION['pk_user_id'], PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                sendJsonResponse(200, array('ok' => true, 'message' => 'Registro agregado correctamente.'));
            }
            else {
                handleError(500, 'No se realizo la creación del registro.');
            }
        }
        catch (Exception $error) {
            handleExceptionError($error);
        }

        exit();
    }

    public function update($id, $data) {
        try {
            $sql = '
                UPDATE [job_position].[positions]
                SET 
                    [job_position_name] = :job_position_name,
                    [fk_job_position_area_id] = :job_position_area_id,
                    [fk_job_position_department_id] = :job_position_department_id,
                    [fk_job_position_office_id] = :job_position_office_id,
                    [fk_job_position_type_id] = :job_position_type_id,
                    [fk_job_position_status_id] = :job_position_status_id,
                    [fk_role_id] = :user_role_id,
                    [job_position_parent_id] = :job_position_parent_id,
                    [publish_date] = :publish_date
                WHERE [pk_job_position_id] = :id
            ';
    
            $stmt = $this->dbConnection->prepare($sql);
            $stmt->bindParam(':job_position_name', $data['job_position_name'], PDO::PARAM_STR);
            $stmt->bindParam(':job_position_area_id', $data['job_position_area_id'], PDO::PARAM_INT);
            $stmt->bindParam(':job_position_department_id', $data['job_position_department_id'], PDO::PARAM_INT);
            $stmt->bindParam(':job_position_office_id', $data['job_position_office_id'], PDO::PARAM_INT);
            $stmt->bindParam(':job_position_type_id', $data['job_position_type_id'], PDO::PARAM_INT);
            $stmt->bindParam(':job_position_status_id', $data['job_position_status_id'], PDO::PARAM_INT);
            $stmt->bindParam(':user_role_id', $data['user_role_id'], PDO::PARAM_INT);
            $stmt->bindParam(':job_position_parent_id', $data['job_position_parent_id'], PDO::PARAM_INT);
            $stmt->bindParam(':publish_date', $data['publish_date'], PDO::PARAM_STR);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                sendJsonResponse(200, array('ok' => true, 'message' => 'Registro actualizado correctamente.'));
            }
            else {
                handleError(500, 'No se realizaron cambios en el registro.');
            }
        } 
        catch (Exception $error) {
            handleExceptionError($error);
        }
    
        exit();
    }    
}
?>