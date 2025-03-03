<?php
require_once '../models/jobPosition.php';

class JobPositionController {
    private $jobPosition;

    public function __construct() {
        $this->jobPosition = new JobPosition();
    }

    public function getAll($available) {
        $this->jobPosition->getAll($available);
    }

    public function getDataById($id) {
        $this->jobPosition->getDataById($id);
    }

    public function save($data) {
        $this->jobPosition->save($data);
    }

    public function update($id, $data) {
        $this->jobPosition->update($id, $data);
    }
}
?>