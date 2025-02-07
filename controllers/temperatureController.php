<?php
require_once '../models/temperature.php';

class TemperatureController {
    public static function get() {
        Temperature::get();
    }
}
?>
