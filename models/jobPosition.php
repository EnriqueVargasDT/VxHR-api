<?php
require_once '../config/config.php';

class JobPosition {
    private $dbConnection;
    
    // User status
    const STATUS_AVAILABLE = 1;
    const STATUS_BUSY = 2;
    const STATUS_INACTIVE = 3;

    // Admin status
    const ADMIN_STATUS_CREATED = 1;
    const ADMIN_STATUS_IN_SEARCH = 2;
    const ADMIN_STATUS_IN_SELECTION = 3;
    const ADMIN_STATUS_BUSY = 4;
    const ADMIN_STATUS_INACTIVE = 5;

    public function __construct() {
        $this->dbConnection = dbConnection();
    }

    public function getAll($available) {
        try {
            $WHERE = 'WHERE jpp.publish_date <= GETDATE() ';
            if ($available) {
                $WHERE .= '
                    AND jpp.fk_job_position_status_id IN(' . self::STATUS_AVAILABLE . ')
                    AND jpp.fk_job_position_admin_status_id IN(' . self::ADMIN_STATUS_CREATED . ', ' . self::ADMIN_STATUS_IN_SEARCH . ')
                ';
            }
            $sql = "SELECT
                        jpp.pk_job_position_id,
                        jpp.job_position,
                        jpp.fk_job_position_area_id,
                        jpa.job_position_area,
                        jpp.fk_job_position_department_id,
                        jpd.job_position_department,
                        jpp.fk_job_position_office_id,
                        jpo.job_position_office,
                        jpt.job_position_type,
                        jpp.fk_job_position_status_id,
                        jps.job_position_status,
                        jpp.fk_job_position_admin_status_id,
                        jpas.job_position_admin_status,
                        jpp.publish_date,
                        jpp.fk_job_position_area_id AS parent_id,
                        jpp.created_by,
                        CONCAT('VC-#', RIGHT('00000' + CAST(jpp.pk_job_position_id AS VARCHAR), 5), ' - ', jpp.job_position, ' - ', CASE WHEN pu.first_name = '' OR pu.first_name IS NULL THEN '[Vacante]' ELSE CONCAT(pu.first_name, ' ', pu.last_name_1, ' ', pu.last_name_2) END) AS inmediate_supervisor_full_name,
                        CONCAT(cu.first_name, ' ', cu.last_name_1, ' ', cu.last_name_2) AS created_by_full_name
                    FROM [job_position].[positions] jpp
                    LEFT JOIN [job_position].[area] jpa ON jpp.fk_job_position_area_id = jpa.pk_job_position_area_id
                    LEFT JOIN [job_position].[department] jpd ON jpp.fk_job_position_department_id = jpd.pk_job_position_department_id
                    LEFT JOIN [job_position].[office] jpo ON jpp.fk_job_position_office_id = jpo.pk_job_position_office_id
                    LEFT JOIN [job_position].[type] jpt ON jpp.fk_job_position_type_id = jpt.pk_job_position_type_id
                    LEFT JOIN [job_position].[status] jps ON jpp.fk_job_position_status_id = jps.pk_job_position_status_id
                    LEFT JOIN [job_position].[admin_status] jpas ON jpp.fk_job_position_admin_status_id = jpas.pk_job_position_admin_status_id
                    LEFT JOIN [user].[users] pu ON jpp.pk_job_position_id = pu.fk_job_position_id
                    LEFT JOIN [user].[users] cu ON jpp.created_by = cu.pk_user_id
                    $WHERE
                    ORDER BY jpp.created_at DESC;";
            $result = $this->dbConnection->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            sendJsonResponse(200, ['ok' => true, 'data' => $result]);
        }
        catch (Exception $error) {
            handleExceptionError($error);
        }

        exit();
    }

    public function getDataById($id) {
        try {
            $sql = "SELECT
                        job_position,
                        fk_job_position_area_id AS job_position_area_id,
                        fk_job_position_department_id AS job_position_department_id,
                        fk_job_position_office_id AS job_position_office_id,
                        fk_job_position_type_id AS job_position_type_id,
                        fk_job_position_status_id AS job_position_status_id,
                        fk_job_position_admin_status_id AS job_position_admin_status_id,
                        job_position_parent_id,
                        publish_date
                    FROM [job_position].[positions]
                    WHERE pk_job_position_id = :id;";
            $stmt = $this->dbConnection->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
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
            if (isset($data['job_position_parent_id']) && intval($data['job_position_parent_id']) === 0) {
                throw new Exception('Error: No se puede crear la vacante, se requiere un Jefe Inmediato.');
            }
            $this->dbConnection->beginTransaction();
            $fields = '[job_position], [fk_job_position_area_id], [fk_job_position_department_id], [fk_job_position_office_id], [fk_job_position_type_id], [fk_job_position_status_id], [fk_job_position_admin_status_id], [job_position_parent_id], [publish_date], [created_by]';
            $values = ':job_position, :job_position_area_id, :job_position_department_id, :job_position_office_id, :job_position_type_id, :job_position_status_id, :job_position_admin_status_id, :job_position_parent_id, :publish_date, :created_by';
            $sql = sprintf('INSERT INTO [job_position].[positions] (%s) VALUES(%s)', $fields, $values);
            $stmt = $this->dbConnection->prepare($sql);
            $stmt->bindParam(':job_position', $data['job_position'], PDO::PARAM_STR);
            $stmt->bindParam(':job_position_area_id', $data['job_position_area_id'], PDO::PARAM_INT);
            $stmt->bindParam(':job_position_department_id', $data['job_position_department_id'], PDO::PARAM_INT);
            $stmt->bindParam(':job_position_office_id', $data['job_position_office_id'], PDO::PARAM_INT);
            $stmt->bindParam(':job_position_type_id', $data['job_position_type_id'], PDO::PARAM_INT);
            $stmt->bindParam(':job_position_status_id', $data['job_position_status_id'], PDO::PARAM_INT);
            $stmt->bindParam(':job_position_admin_status_id', $data['job_position_admin_status_id'], PDO::PARAM_INT);
            $stmt->bindParam(':job_position_parent_id', $data['job_position_parent_id'], PDO::PARAM_INT);
            $stmt->bindParam(':publish_date', $data['publish_date'], PDO::PARAM_STR);
            $stmt->bindValue(':created_by', $_SESSION['pk_user_id'], PDO::PARAM_INT);
            if (!$stmt->execute() && $stmt->rowCount() === 0) {
                throw new Exception('Error: No se pudo crear la vacante.');
            }
            
            $this->dbConnection->commit();
            sendJsonResponse(200, ['ok' => true, 'message' => 'La nueva vacante fue creada exitosamente.']);
        }
        catch (Exception $error) {
            if ($this->dbConnection->inTransaction()) {
                $this->dbConnection->rollBack();
            }
            handleExceptionError($error);
        }

        exit();
    }

    public function update($id, $data) {
        try {
            if (isset($data['job_position_parent_id']) && intval($data['job_position_parent_id']) === 0) {
                throw new Exception('Error: No se puede editar la vacante, se requiere un Jefe Inmediato.');
            }
            $this->dbConnection->beginTransaction();
            $sql = "UPDATE [job_position].[positions]
                    SET 
                        [job_position] = :job_position,
                        [fk_job_position_area_id] = :job_position_area_id,
                        [fk_job_position_department_id] = :job_position_department_id,
                        [fk_job_position_office_id] = :job_position_office_id,
                        [fk_job_position_type_id] = :job_position_type_id,
                        [fk_job_position_admin_status_id] = :job_position_admin_status_id,
                        [job_position_parent_id] = :job_position_parent_id,
                        [publish_date] = :publish_date
                    WHERE [pk_job_position_id] = :id";
            $stmt = $this->dbConnection->prepare($sql);
            $stmt->bindParam(':job_position', $data['job_position'], PDO::PARAM_STR);
            $stmt->bindParam(':job_position_area_id', $data['job_position_area_id'], PDO::PARAM_INT);
            $stmt->bindParam(':job_position_department_id', $data['job_position_department_id'], PDO::PARAM_INT);
            $stmt->bindParam(':job_position_office_id', $data['job_position_office_id'], PDO::PARAM_INT);
            $stmt->bindParam(':job_position_type_id', $data['job_position_type_id'], PDO::PARAM_INT);
            $stmt->bindParam(':job_position_admin_status_id', $data['job_position_admin_status_id'], PDO::PARAM_INT);
            $stmt->bindParam(':job_position_parent_id', $data['job_position_parent_id'], PDO::PARAM_INT);
            $stmt->bindParam(':publish_date', $data['publish_date'], PDO::PARAM_STR);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            if (!$stmt->execute() && $stmt->rowCount() === 0) {
                throw new Exception('Error: No se realizaron cambios en los datos de la vacante.');
            }
            
            $this->dbConnection->commit();
            sendJsonResponse(200, ['ok' => true, 'message' => 'Los datos de la vacante fueron actualizados exitosamente.']);
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