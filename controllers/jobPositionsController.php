<?php
require_once '../models/jobPositions.php';

class JobPositionsController {
    private $jobPositions;

    public function __construct() {
        $this->jobPositions = new JobPositions();
    }

    public function getAll($page) {
        $this->jobPositions->getAll($page);
    }
}
?>