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
            $sql = 'SELECT * FROM [job_position].[positions] ORDER BY job_position_parent_id ASC';
            $result = $this->dbConnection->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            
            $positions = array();
            foreach ($result as $key => $value) {
                $positions[$value['pk_job_position_id']] = array (
                    'id' => $value['pk_job_position_id'],
                    'name' => $value['job_position'],
                    'title' => $value['job_position'],
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