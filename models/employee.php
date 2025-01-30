<?php
require_once '../config/config.php';

class Employee {
    private $dbConnection;

    public function __construct() {
        $this->dbConnection = dbConnection();
    }

    public function getAll() {
        try {
            $sql = "SELECT * FROM dbo.employees";
            $stmt = $this->dbConnection->query($sql);
            $employees = array();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $employees[] = $row;
            }

            echo json_encode($employees);
        }
        catch(error) {
            echo json_encode(array('error' => true, 'message' => $error), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        exit();
    }

    public function getById($pk_employee_id) {
        try {
            $sql = "SELECT * FROM dbo.employees WHERE pk_employee_id = $pk_employee_id";
            $stmt = $this->dbConnection->query($sql);
            $employee = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode($employee);
        }
        catch(error) {
            echo json_encode(array('error' => true, 'message' => $error), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        exit();
    }
}
?>