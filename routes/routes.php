<?php
require_once '../controllers/tokenController.php';
require_once '../controllers/loginController.php';
require_once '../controllers/logoutController.php';
require_once '../controllers/userController.php';
require_once '../controllers/temperatureController.php';

$method = $_SERVER['REQUEST_METHOD'];
$requestUriParts = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
$body = json_decode(file_get_contents('php://input'), true);

if ($requestUriParts[0] === 'api') {
    if (strpos($requestUriParts[1], 'login') !== false) {
        $loginController = new LoginController();
        switch ($method) {
            case 'POST':
                if (isset($requestUriParts[2])) {
                    if (is_numeric($requestUriParts[2])) {
                        echo $userController->getById($requestUriParts[2]);
                    }
                    else {
                        if (strpos($requestUriParts[2], 'password_recovery') !== false) {
                            echo $loginController->passwordRecovery($body['username']);
                        }
                        else if (strpos($requestUriParts[2], 'password_update') !== false) {
                            echo $loginController->passwordUpdate($body['token'], $body['newPassword'], $body['confirmPassword']);
                        }
                        else {
                            resourceNotAllowed();
                        }
                    }
                }
                else {
                    if (isset($body['username']) && isset($body['password'])) {
                        echo $loginController->validate($body['username'], $body['password'], $body['rememberMe']);
                    }
                    else {
                        http_response_code(500);
                        echo json_encode(array('error' => true, 'message' => 'No se recibió un usuario y/o contraseña.'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }
                }
                break;
            default:
                resourceNotAllowed();
                break;
        }
    }
    else {
        $tokenController = new TokenController();
        $validateToken = $tokenController->validate();

        if (isset($validateToken['ok'])) {
            if (strpos($requestUriParts[1], 'user') !== false) {
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
                        resourceNotAllowed();
                        break;
                }
            }
            else if (strpos($requestUriParts[1], 'temperature') !== false) {
                switch ($method) {
                    case 'GET':
                        echo TemperatureController::get();
                        break;
                    default:
                        resourceNotAllowed();
                        break;
                }
            }
            else if(strpos($requestUriParts[1], 'logout') !== false) {
                switch ($method) {
                    case 'POST':
                        echo LogoutController::logout();
                        break;
                    default:
                        resourceNotAllowed();
                        break;
                }
            }
            else {
                pathNotFound();
            }
        }
        else {
            http_response_code(401);
            echo json_encode($validateToken, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }
}
else {
    pathNotFound();
}

function pathNotFound() {
    http_response_code(404);
    echo json_encode(array('error' => true, 'message' => 'Ruta no encontrada.'));
}

function resourceNotAllowed() {
    http_response_code(405);
    echo json_encode(array('error' => true, 'message' => 'Método no permitido.'));
}
?>