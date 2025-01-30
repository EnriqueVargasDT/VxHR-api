<?php
require_once '../models/employee.php';

class EmployeeController {
    public function getAll() {
        $employee = new Employee();
        $result = $employee->getAll();
        echo json_encode($result);
        exit();
    }

    public function getById($pk_employee_id) {
        $employee = new Employee();
        $result = $employee->getById($pk_employee_id);
        echo json_encode($result);
        exit();
    }
}
?>