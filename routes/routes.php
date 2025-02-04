<?php
require_once '../controllers/loginController.php';
require_once '../controllers/userController.php';
require_once '../controllers/temperatureController.php';

$method = $_SERVER['REQUEST_METHOD'];
$requestUriParts = explode('/', trim($_SERVER['REQUEST_URI'], '/'));

if ($requestUriParts[0] === 'api' && strpos($requestUriParts[1], 'login') !== false) {
    $loginController = new LoginController();
    switch ($method) {
        case 'POST':
            $body = json_decode(file_get_contents('php://input'), true);
            if (isset($body['username']) && isset($body['password'])) {
                echo $loginController->validate($body['username'], $body['password']);
            }
            else {
                http_response_code(500);
                echo json_encode(array('error' => true, 'message' => 'No se recibió un usuario y/o contraseña.'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            break;
        default:
            // ...
            break;
    }
}
else if ($requestUriParts[0] === 'api' && strpos($requestUriParts[1], 'user') !== false) {
    $userController = new UserController();
    switch ($method) {
        case 'GET':
            if (isset($requestUriParts[2])) {
                if (is_numeric($requestUriParts[2])) {
                    echo $userController->getById($requestUriParts[2]);
                }
                else {
                    http_response_code(500);
                    echo json_encode(array('error' => true, 'message' => 'No se recibió un id de empleado válido.'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }
            else {
                echo $userController->getAll();
            }
            break;
        default:
            // ...
            break;
    }
}
else if ($requestUriParts[0] === 'api' && strpos($requestUriParts[1], 'temperature') !== false) {
    echo TemperatureController::get();
}
else {
    http_response_code(404);
    echo json_encode(array('error' => true, 'message' => 'Ruta no encontrada.'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
?>