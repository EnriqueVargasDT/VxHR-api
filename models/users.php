<?php
require_once '../config/config.php';
require_once 'userFiles.php';

class Users {
    private $dbConnection;

    public function __construct() {
        $this->dbConnection = dbConnection();
    }

    public function getAll() {
        try {
            $sql = sprintf("
                SELECT
                    u.*,
                    CONCAT(u.first_name, ' ' , u.last_name_1, ' ', u.last_name_2) AS full_name,
                    ums.marital_status,
                    urs.relationship AS emergency_relationship,
                    jpp.job_position,
                    jpa.job_position_area,
                    jpd.job_position_department,
                    jpo.job_position_office,
                    ua.username,
                    uf.[file] AS profile_picture,
                    ua.last_access_at,
                    jpb.job_position AS boss_position,
                    CONCAT(ub.first_name, ' ' , ub.last_name_1, ' ', ub.last_name_2) AS boss,
                    ur.role AS user_type,
                    jt.job_position_type AS job_type
                FROM [user].[users] u
                LEFT JOIN [user].[users_auth] ua ON u.pk_user_id = ua.fk_user_id
                LEFT JOIN [user].[marital_status] ums ON u.fk_marital_status_id = ums.pk_marital_status_id
                LEFT JOIN [user].[relationships] urs ON u.fk_emergency_relationship_id = urs.pk_relationship_id
                LEFT JOIN [user].[files] uf ON u.pk_user_id = uf.fk_user_id AND uf.type_file = 1
                LEFT JOIN [job_position].[positions] jpp ON u.fk_job_position_id = jpp.pk_job_position_id
                LEFT JOIN [job_position].[area] jpa ON jpp.fk_job_position_area_id = jpa.pk_job_position_area_id
                LEFT JOIN [job_position].[department] jpd ON jpp.fk_job_position_department_id = jpd.pk_job_position_department_id
                LEFT JOIN [job_position].[office] jpo ON jpp.fk_job_position_office_id = jpo.pk_job_position_office_id
                LEFT JOIN [job_position].[positions] jpb ON jpp.job_position_parent_id = jpb.pk_job_position_id
                LEFT JOIN [user].[users] ub ON jpb.pk_job_position_id = ub.fk_job_position_id
                LEFT JOIN [user].[roles] ur ON ua.fk_role_id = ur.pk_role_id
                LEFT JOIN [job_position].[type] jt ON jpp.fk_job_position_type_id = jt.pk_job_position_type_id
                ORDER BY u.first_name;
            ", UserFiles::TYPE_PROFILE_PICTURE);
            $stmt = $this->dbConnection->query($sql);
            $users = [];
            $i = 1;
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

    public function getUsersEmails() {
        try {
            $sql = sprintf("
                SELECT
                    CONCAT(u.first_name, ' ', u.last_name_1, ' ', u.last_name_2) AS full_name,
                    CASE
                        WHEN u.institutional_email IS NOT NULL AND u.institutional_email NOT IN ('', '-') THEN u.institutional_email
                        WHEN u.personal_email IS NOT NULL AND u.personal_email NOT IN ('', '-') THEN u.personal_email
                        ELSE NULL
                    END AS email,
                    o.job_position_office_short AS office_name,
                    u.birth_date,
                    f.[file] AS profile_picture
                FROM [user].[users] u
                INNER JOIN [user].[genders] g ON u.fk_gender_id = g.pk_gender_id
                INNER JOIN [job_position].[positions] p ON u.fk_job_position_id = p.pk_job_position_id
                INNER JOIN [job_position].[office] o ON p.fk_job_position_office_id = o.pk_job_position_office_id
                LEFT JOIN [user].[files] f 
                    ON f.fk_user_id = u.pk_user_id 
                    AND f.type_file = 1
                WHERE u.is_active = 1
                ORDER BY full_name;
            ");
            $stmt = $this->dbConnection->query($sql);
            $users = [];
            $i = 1;
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $users[] = $row;
            }

            return $users;
        }
        catch(Exception $error) {
            handleExceptionError($error);
        }
    }

    public function getUsersWithAnniversary($startDate = null, $endDate = null) {
        $startDate = $startDate ? date('Y-m-d', strtotime($startDate)) : date('Y-m-d', strtotime('last Monday'));
        $endDate = $endDate ? date('Y-m-d', strtotime($endDate)) : date('Y-m-d', strtotime('next Sunday'));
    
        try {
            if (!$startDate || !$endDate) {
                throw new Exception('Start date and end date are required.');
            }
    
            if ($startDate > $endDate) {
                throw new Exception('Start date cannot be greater than end date.');
            }
    
            $sql = "
                SET DATEFIRST 1;
                DECLARE @startDate DATE = :start_date;
                DECLARE @endDate DATE = :end_date;
    
                SELECT
                    CONCAT(u.first_name, ' ', u.last_name_1, ' ', u.last_name_2) AS full_name,
                    g.gender AS gender,
                    CASE
                        WHEN u.institutional_email IS NOT NULL AND u.institutional_email NOT IN ('', '-') THEN u.institutional_email
                        WHEN u.personal_email IS NOT NULL AND u.personal_email NOT IN ('', '-') THEN u.personal_email
                        ELSE NULL
                    END AS email,
                    p.job_position AS job_position,
                    o.job_position_office_short AS office_name,
                    u.date_of_hire,
                    DATEDIFF(YEAR, u.date_of_hire, GETDATE()) AS years_completed,
                    CONCAT(DATEDIFF(YEAR, u.date_of_hire, GETDATE()), '° Aniversario') AS anniversary,
                    f.[file] AS profile_picture
                FROM [user].[users] u
                INNER JOIN [user].[genders] g ON u.fk_gender_id = g.pk_gender_id
                INNER JOIN [job_position].[positions] p ON u.fk_job_position_id = p.pk_job_position_id
                INNER JOIN [job_position].[office] o ON p.fk_job_position_office_id = o.pk_job_position_office_id
                LEFT JOIN [user].[files] f 
                    ON f.fk_user_id = u.pk_user_id 
                    AND f.type_file = 1
                WHERE
                    u.is_active = 1
                    AND u.date_of_hire <= DATEADD(YEAR, -1, DATEADD(DAY, 7 - DATEPART(WEEKDAY, GETDATE()), CAST(GETDATE() AS DATE)))
                    AND DATEFROMPARTS(YEAR(GETDATE()), MONTH(u.date_of_hire), DAY(u.date_of_hire))
                        BETWEEN @startDate AND @endDate
                ORDER BY
                    years_completed DESC,
                    u.date_of_hire;
            ";
    
            $stmt = $this->dbConnection->prepare($sql);
            $stmt->bindParam(':start_date', $startDate);
            $stmt->bindParam(':end_date', $endDate);
            $stmt->execute();
    
            $users = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $users[] = $row;
            }
    
            return $users;
        } catch (Exception $error) {
            handleExceptionError($error);
        }
    
    }
    

    /*
     * This function is used to get users with birthdays.
     * It retrieves users whose birthday is today or on a specific date.
     * @param string|null $date Format date 'Y-m-d' (optional).
     * @return void
     */
    public function getUsersWithBirthday($date = null) {
        $date = $date ? date('Y-m-d', strtotime($date)) : date('Y-m-d');
        $currentDay = $date ? date('d', strtotime($date)) : date('d');
        $currentMonth = $date ? date('m', strtotime($date)) : date('m');

        try {
            $sql = sprintf("
                SELECT
                    CONCAT(u.first_name, ' ', u.last_name_1, ' ', u.last_name_2) AS full_name,
                    g.gender AS gender,
                    -- Email combinado
                    CASE
                        WHEN u.institutional_email IS NOT NULL AND u.institutional_email NOT IN ('', '-') THEN u.institutional_email
                        WHEN u.personal_email IS NOT NULL AND u.personal_email NOT IN ('', '-') THEN u.personal_email
                        ELSE NULL
                    END AS email,
                    p.job_position AS job_position,
                    o.job_position_office_short AS office_name,
                    u.birth_date,
                    f.[file] AS profile_picture
                FROM [user].[users] u
                INNER JOIN [user].[genders] g ON u.fk_gender_id = g.pk_gender_id
                INNER JOIN [job_position].[positions] p ON u.fk_job_position_id = p.pk_job_position_id
                INNER JOIN [job_position].[office] o ON p.fk_job_position_office_id = o.pk_job_position_office_id
                LEFT JOIN [user].[files] f 
                    ON f.fk_user_id = u.pk_user_id 
                    AND f.type_file = 1
                WHERE
                    u.is_active = 1
                    AND u.birth_date IS NOT NULL
                    AND MONTH(u.birth_date) = %s
                    AND DAY(u.birth_date) = %s
                ORDER BY
                    full_name;
            ", $currentMonth, $currentDay);
            $stmt = $this->dbConnection->query($sql);
            $users = [];
            $i = 1;
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $users[] = $row;
            }

            return $users;
        }
        catch(Exception $error) {
            handleExceptionError($error);
        }

    }

}
?>