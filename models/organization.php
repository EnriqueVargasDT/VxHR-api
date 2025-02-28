<?php
require_once '../config/config.php';

/*
const data = {
    id: 1,
    name: 'John Doe',
    title: 'CEO',
    children: [
        {
            id: 2,
            name: 'Jane Smith',
            title: 'CTO',
            children: [
                { id: 3, name: 'Jake White', title: 'Dev Lead' },
                { id: 4, name: 'Sally Brown', title: 'QA Lead' },
            ],
        },
        {
            id: 5,
            name: 'Mark Johnson',
            title: 'CFO',
            children: [
                { id: 6, name: 'Emma Davis', title: 'Accounting Manager' },
                { id: 7, name: 'Liam Clark', title: 'Financial Analyst' },
            ],
        },
    ],
};
*/

class Organization {
    private $dbConnection;

    public function __construct() {
        $this->dbConnection = dbConnection();
    }

    public function getData() {
        try {
            $sql = "
                SELECT
                jp.*,
                jpd.job_position_department,
                jpd.job_position_department_short,
                jpo.job_position_office,
                jpo.job_position_office_short,
                CONCAT(CASE WHEN CHARINDEX(' ', first_name) > 0 THEN LEFT(first_name, CHARINDEX(' ', first_name) - 1) ELSE first_name END, ' ', u.last_name_1) AS full_name
                FROM [job_position].[positions] jp
                LEFT JOIN [job_position].[office] jpo ON jp.fk_job_position_office_id = jpo.pk_job_position_office_id
                LEFT JOIN [job_position].[department] jpd ON jp.fk_job_position_department_id = jpd.pk_job_position_department_id
                LEFT JOIN [user].[users] u ON jp.pk_job_position_id = u.fk_job_position_id
                ORDER BY jp.job_position_parent_id ASC
            ";
            $result = $this->dbConnection->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            $positions = array();
            foreach ($result as $key => $value) {
                $positions[$value['pk_job_position_id']] = array (
                    'id' => $value['pk_job_position_id'],
                    'name' => $value['full_name'],
                    'position' => $value['job_position'],
                    'full_department' => $value['job_position_department'],
                    'department' => $value['job_position_department_short'],
                    'full_location' => $value['job_position_office'],
                    'location' => $value['job_position_office_short'],
                    'parent_id' => $value['job_position_parent_id'],
                    'children' => array(),
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
            sendJsonResponse(200, array('ok' => true, 'data' => $data, ));
        }
        catch (Exception $error) {
            handleExceptionError($error);
        }

        exit();
    }
}
?>