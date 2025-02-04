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
                echo json_encode(['error' => true, 'message' => 'No se recibió un usuario y/o contraseña.']);
            }
            break;
        default:
            // ...
            break;
    }
}
else if ($requestUriParts[0] === 'api' && strpos($requestUriParts[1], 'user') !== false) {
    validateToken();
    $userController = new UserController();
    switch ($method) {
        case 'GET':
            if (isset($requestUriParts[2])) {
                if (is_numeric($requestUriParts[2])) {
                    echo $userController->getById($requestUriParts[2]);
                }
                else {
                    echo json_encode(['error' => true, 'message' => 'No se recibió un id de empleado válido.']);
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
    validateToken();
    echo TemperatureController::get();
}
else {
    echo json_encode(['error' => true, 'message' => 'Ruta no encontrada.']);
}

function validateToken () {
    if (!isset($_COOKIE['token'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Usuario no autenticado', 'cookie' => $_COOKIE]);
        exit();
    }
}
?>