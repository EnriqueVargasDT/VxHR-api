<?php
class Temperature {
    
    public static function get($latitude, $longitude) {
        try {
            if (isset($latitude) && isset($longitude)) {
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
                    handleError(500, 'Error al intentar obtener los datos de la ciudad de acuerdo a las coordenadas proporcionadas.');
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
                        handleError(500, 'Error al intentar obtener la temperatura de la ciudad.');
                    }

                    curl_close($initTemperaturWeatherAPI);
                    sendJsonResponse(200, ['ok' => true, 'data' => json_decode($responseTemperature)]);
                }
            }
            else {
                handleError(500, 'Error al intentar obtener la temperatura: latitud/longitud no válida.');
            }
        }
        catch(Exception $error) {
            handleExceptionError($error);
        }

        exit();
    }
}
?>