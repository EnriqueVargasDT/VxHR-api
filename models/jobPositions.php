<?php
require_once '../config/config.php';

class JobPositions {
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

    public function getAll() {
        try {
            $sql = "
                    SELECT
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
                    ORDER BY jpp.created_at DESC;
                ";
            $result = $this->dbConnection->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            sendJsonResponse(200, ['ok' => true, 'data' => $result]);
        }
        catch (Exception $error) {
            handleExceptionError($error);
        }

        exit();
    }    
}
?>