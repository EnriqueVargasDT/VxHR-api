<?php
require_once '../models/temperature.php';

class TemperatureController {
    public static function get($latitude, $longitude) {
        Temperature::get($latitude, $longitude);
    }
}
?>
