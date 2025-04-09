<?php
require_once '../config/config.php';
require_once '../models/userFiles.php';

class Organization {
    private $dbConnection;

    public function __construct() {
        $this->dbConnection = dbConnection();
    }

    public function getData() {
        try {
            $sql = sprintf("
                SELECT
                    jp.*,
                    jpd.job_position_department,
                    jpd.job_position_department_short,
                    jpo.job_position_office,
                    jpo.job_position_office_short,
                    CONCAT(CASE WHEN CHARINDEX(' ', first_name) > 0 THEN LEFT(first_name, CHARINDEX(' ', first_name) - 1) ELSE first_name END, ' ', u.last_name_1) AS full_name,
                    uf.[file] AS profile_picture
                FROM [job_position].[positions] jp
                LEFT JOIN [job_position].[office] jpo ON jp.fk_job_position_office_id = jpo.pk_job_position_office_id
                LEFT JOIN [job_position].[department] jpd ON jp.fk_job_position_department_id = jpd.pk_job_position_department_id
                LEFT JOIN [user].[users] u ON jp.pk_job_position_id = u.fk_job_position_id
                LEFT JOIN [user].[files] uf ON u.pk_user_id = uf.fk_user_id AND uf.type_file = %s
                ORDER BY jp.job_position_parent_id ASC
            ", UserFiles::TYPE_PROFILE_PICTURE);
            $result = $this->dbConnection->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            $positions = [];
            foreach ($result as $key => $value) {
                $positions[$value['pk_job_position_id']] = array (
                    'id' => $value['pk_job_position_id'],
                    'name' => $value['full_name'],
                    'profile_picture' => $value['profile_picture'],
                    'position' => $value['job_position'],
                    'full_department' => $value['job_position_department'],
                    'department' => $value['job_position_department_short'],
                    'full_location' => $value['job_position_office'],
                    'location' => $value['job_position_office_short'],
                    'parent_id' => $value['job_position_parent_id'],
                    'children' => [],
                );
            }
            
            $data = null;
            foreach ($positions as &$node) {
                if ($node['parent_id'] == 0) {
                    $data = &$node;
                }
                else {
                    $positions[$node['parent_id']]['children'][] = &$node;
                }
            }
            sendJsonResponse(200, ['ok' => true, 'data' => $data, ]);
        }
        catch (Exception $error) {
            handleExceptionError($error);
        }

        exit();
    }
}
?>