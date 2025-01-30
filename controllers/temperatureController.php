<?php
require_once '../models/temperature.php';

class TemperatureController {
    public static function get() {
        $result = Temperature::get();
        echo json_encode($result);
        exit();
    }
}
?>
