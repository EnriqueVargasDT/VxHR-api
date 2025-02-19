<?php
require_once '../config/config.php';

class User {
    private $dbConnection;

    public function __construct() {
        $this->dbConnection = dbConnection();
    }

    public function getAll() {
        try {
            $sql = 'SELECT * FROM [user].[users]';
            $stmt = $this->dbConnection->query($sql);
            $users = array();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $users[] = $row;
            }

            sendJsonResponse(200, array('ok' => true, 'users' => $users));
        }
        catch(Exception $error) {
            handleExceptionError($error);
        }

        exit();
    }

    public function getById($pk_user_id) {
        try {
            $sql = "
                SELECT u.*,
                CONCAT(u.first_name, ' ' , u.last_name_1, ' ', u.last_name_2) AS full_name,
                ums.marital_status,
                urs.relationship AS emergency_relationship,
                jpp.job_position_name,
                jpa.job_position_area,
                jpd.job_position_department,
                jpo.job_position_office
                FROM [user].[users] u
                LEFT JOIN [user].[marital_status] ums ON u.fk_marital_status_id = ums.pk_marital_status_id
                LEFT JOIN [user].[relationships] urs ON u.fk_emergency_relationship_id = urs.pk_relationship_id
                LEFT JOIN [job_position].[positions] jpp ON u.fk_job_position_id = jpp.pk_job_position_id
                LEFT JOIN [job_position].[area] jpa ON u.fk_job_position_area_id = jpa.pk_job_position_area_id
                LEFT JOIN [job_position].[department] jpd ON u.fk_job_position_department_id = jpd.pk_job_position_department_id
                LEFT JOIN [job_position].[office] jpo ON u.fk_job_position_office_id = jpo.pk_job_position_office_id
                WHERE u.pk_user_id = %s
            " . PHP_EOL;
            $sql = sprintf($sql, $pk_user_id);
            $stmt = $this->dbConnection->query($sql);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                sendJsonResponse(200, array('ok' => true, 'user' => $user));
            }
            else {
                handleError(500, 'No se encontró al usuario en la plataforma');
            }
        }
        catch(Exception $error) {
            handleExceptionError($error);
        }

        exit();
    }
}
?>