<?php
require_once '../controllers/temperatureController.php';
require_once '../controllers/loginController.php';
require_once '../controllers/employeeController.php';

$method = $_SERVER['REQUEST_METHOD'];
$urlParts = explode('/', trim($_SERVER['REQUEST_URI'], '/'));

if ($urlParts[0] === 'api' && strpos($urlParts[1], 'login') !== false) {
    $loginController = new LoginController();
    switch ($method) {
        case 'POST':
            $body = json_decode(file_get_contents('php://input'), true);
            if (isset($body['username']) && isset($body['password'])) {
                echo $loginController->validate($body['username'], $body['password'], $body['iv']);
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
else if ($urlParts[0] === 'api' && strpos($urlParts[1], 'employee') !== false) {
    $employeeController = new EmployeeController();
    switch ($method) {
        case 'GET':
            if (isset($urlParts[2])) {
                if (is_numeric($requestUri[2])) {
                    echo $employeeController->getById($requestUri[2]);
                }
                else {
                    echo json_encode(['error' => true, 'message' => 'No se recibió un id de empleado válido.']);
                }
            }
            else {
                echo $employeeController->getAll();
            }
            break;
        default:
            // ...
            break;
    }
}
else if ($urlParts[0] === 'api' && strpos($urlParts[1], 'temperature') !== false) {
    echo TemperatureController::get();
}
else {
    echo json_encode(['error' => true, 'message' => 'Ruta no encontrada.']);
}
?>