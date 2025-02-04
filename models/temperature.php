<?php
class Temperature {
    
    public static function get() {
        if (isset($_GET['latitude']) && isset($_GET['longitude'])) {
            $latitude = $_GET['latitude']; // 25.7172319;
            $longitude = $_GET['longitude']; // -100.5408731;
            $API_URL = 'https://api.openweathermap.org/data/2.5/weather';
            $API_KEY = 'a01406997e51be1e45c9b504a64a9552'; // Api key personal Luis Muñoz
            $latLongURL = "$API_URL?lat=$latitude&lon=$longitude&appid=$API_KEY&lang=es";

            $initLatLongWeatherAPI = curl_init();
            curl_setopt($initLatLongWeatherAPI, CURLOPT_URL, $latLongURL);
            curl_setopt($initLatLongWeatherAPI, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($initLatLongWeatherAPI, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($initLatLongWeatherAPI, CURLOPT_HTTPHEADER, ['Accept: application/json']);
            $responseLatLong = curl_exec($initLatLongWeatherAPI);
            if ($responseLatLong === false) {
                http_response_code(500);
                echo json_encode(array('error' => true, 'message' => 'Error al intentar obtener los datos de la ciudad de acuerdo a las coordenadas proporcionadas.'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            curl_close($initLatLongWeatherAPI);
            $responseLatLongJson = json_decode($responseLatLong, true);
            if ($responseLatLongJson['name']) {
                $city = $responseLatLongJson['name'];
                
                $initTemperaturWeatherAPI = curl_init();
                $temperatureURL = "$API_URL?q=" . rawurlencode($city) . "&appid=$API_KEY&lang=es&units=metric";
                curl_setopt($initTemperaturWeatherAPI, CURLOPT_URL, $temperatureURL);
                curl_setopt($initTemperaturWeatherAPI, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($initTemperaturWeatherAPI, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($initTemperaturWeatherAPI, CURLOPT_HTTPHEADER, ['Accept: application/json']);
                $responseTemperature = curl_exec($initTemperaturWeatherAPI);
                if ($responseTemperature === false) {
                    http_response_code(500);
                    echo json_encode(array('error' => true, 'message' => 'Error al intentar obtener la temperatura de la ciudad.'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }

                curl_close($initTemperaturWeatherAPI);
                echo $responseTemperature;
            }
        }
        else {
            http_response_code(500);
            echo json_encode(array('error' => true, 'message' => 'Error al intentar obtener la temperatura: latitud/longitud no válida.'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        exit();
    }
}
?>