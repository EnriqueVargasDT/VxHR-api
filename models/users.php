<?php
require_once '../config/config.php';
require_once 'userFiles.php';

class Users {
    private $dbConnection;

    public function __construct() {
        $this->dbConnection = dbConnection();
    }

    public function getAll($page = 1) {
        try {
            $sql = sprintf("
                SELECT
                    (SELECT COUNT(*) FROM [user].[users]) AS total_rows,
                    CEILING(CAST(COUNT(*) OVER() AS FLOAT) / 10) AS total_pages,
                    u.*,
                    CONCAT(u.first_name, ' ' , u.last_name_1, ' ', u.last_name_2) AS full_name,
                    ums.marital_status,
                    urs.relationship AS emergency_relationship,
                    jpp.job_position,
                    jpa.job_position_area,
                    jpd.job_position_department,
                    jpo.job_position_office,
                    ua.username,
                    CONCAT('data:image/', uf.file_extension, ';base64,', uf.[file]) AS profile_picture,
                    ua.last_access_at
                FROM [user].[users] u
                LEFT JOIN [user].[users_auth] ua ON u.pk_user_id = ua.fk_user_id
                LEFT JOIN [user].[marital_status] ums ON u.fk_marital_status_id = ums.pk_marital_status_id
                LEFT JOIN [user].[relationships] urs ON u.fk_emergency_relationship_id = urs.pk_relationship_id
                LEFT JOIN [user].[files] uf ON u.pk_user_id = uf.fk_user_id AND uf.type_file = %s
                LEFT JOIN [job_position].[positions] jpp ON u.fk_job_position_id = jpp.pk_job_position_id
                LEFT JOIN [job_position].[area] jpa ON jpp.fk_job_position_area_id = jpa.pk_job_position_area_id
                LEFT JOIN [job_position].[department] jpd ON jpp.fk_job_position_department_id = jpd.pk_job_position_department_id
                LEFT JOIN [job_position].[office] jpo ON jpp.fk_job_position_office_id = jpo.pk_job_position_office_id
                ORDER BY u.created_at DESC
                OFFSET (%s - 1) * 10 ROWS 
                FETCH NEXT 10 ROWS ONLY;
            ", $page, UserFiles::TYPE_PROFILE_PICTURE);
            $stmt = $this->dbConnection->query($sql);
            $users = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $users[] = $row;
            }

            sendJsonResponse(200, ['ok' => true, 'users' => $users]);
        }
        catch(Exception $error) {
            handleExceptionError($error);
        }

        exit();
    }
}
?>