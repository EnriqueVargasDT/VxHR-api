<?php
$allowedOrigins = [
    'https://dev-vica.azurewebsites.net',
    'https://vica.vittilog.com',
    'https://production-vica.azurewebsites.net',
    'https://lively-glacier-076b0c40f.6.azurestaticapps.net'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

$isLocalhost = preg_match('/^http:\/\/localhost(:\d+)?$/', $origin);

// Si el origen está en la lista o es localhost con cualquier puerto
if (in_array($origin, $allowedOrigins) || $isLocalhost) {
    header("Access-Control-Allow-Origin: " . $origin);
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Access-Control-Allow-Credentials: true");
} else {
    error_log("Blocked origin: $origin");
}


if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}
