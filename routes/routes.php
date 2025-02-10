<?php
require_once '../controllers/tokenController.php';
require_once '../controllers/loginController.php';
require_once '../controllers/roleController.php';
require_once '../controllers/logoutController.php';
require_once '../controllers/userController.php';
require_once '../controllers/temperatureController.php';

$method = $_SERVER['REQUEST_METHOD'];
$requestUriParts = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
$body = json_decode(file_get_contents('php://input'), true);

$main = $requestUriParts[0] ?? '';
if ($main !== 'api') {
    pathNotFound();
    exit();
}

$route = $requestUriParts[1] ?? null;
$subroute = $requestUriParts[2] ?? null;

if (strpos($route, 'login') !== false) {
    login($method, $subroute, $body);
}
else {
    $tokenController = new TokenController();
    $validateToken = $tokenController->validate();

    if (isset($validateToken['ok'])) {
        switch ($route) {
            case strpos($route, 'role') !== false:
                role($method, $subroute, $body);
                break;
            case strpos($route, 'user') !== false:
                user($method, $subroute, $body);
                break;
            case strpos($route, 'temperature') !== false:
                temperature($method);
                break;
            case strpos($route, 'logout') !== false:
                logout($method);
                break;
            default:
                pathNotFound();
                break;
        }
    }
    else {
        unAuthorized();
    }
}

function login($method, $subroute, $body) {
    $loginController = new LoginController();
    switch ($method) {
        case 'POST':
            if (isset($subroute)) {
                if (is_numeric($subroute)) {
                    $userController->getById($subroute);
                }
                else {
                    if (strpos($subroute, 'password_recovery') !== false) {
                        $loginController->passwordRecovery($body['username']);
                    }
                    else if (strpos($subroute, 'password_update') !== false) {
                        $loginController->passwordUpdate($body['token'], $body['newPassword'], $body['confirmPassword']);
                    }
                    else {
                        pathNotFound();
                    }
                }
            }
            else {
                if (isset($body['username']) && isset($body['password'])) {
                    $loginController->validate($body['username'], $body['password'], $body['rememberMe']);
                }
                else {
                    internalServerError('No se recibió un usuario y/o contraseña.');
                }
            }
            break;
        default:
            methodNotAllowed();
            break;
    }
}

function role($method, $subroute, $body) {
    $roleController = new RoleController();
    switch ($method) {
        case 'GET':
            if (isset($subroute)) {
                pathNotFound();
            }
            else {
                $roleController->get();
            }
            break;
        default:
            methodNotAllowed();
            break;
    }
}

function user($method, $subroute, $body) {
    $userController = new UserController();
    switch ($method) {
        case 'GET':
            if (isset($subroute)) {
                if (is_numeric($subroute)) {
                    $userController->getById($subroute);
                }
                else {
                    internalServerError('No se recibió un id de empleado válido.');
                }
            }
            else {
                $userController->getAll();
            }
            break;
        default:
            methodNotAllowed();
            break;
    }
}

function temperature($method) {
    switch ($method) {
        case 'GET':
            TemperatureController::get();
            break;
        default:
            methodNotAllowed();
            break;
    }
}

function logout($method) {
    switch ($method) {
        case 'POST':
            LogoutController::logout();
            break;
        default:
            methodNotAllowed();
            break;
    }
}

function pathNotFound() {
    sendJsonResponse(404, 'Ruta no encontrada.');
}

function methodNotAllowed() {
    sendJsonResponse(405, 'Método no permitido.');
}

function unAuthorized() {
    sendJsonResponse(401, 'No autorizado.');
}

function internalServerError($message = null) {
    sendJsonResponse(500, $message ?? 'Error interno de servidor.');
}

function sendJsonResponse($statusCode, $message) {
    http_response_code($statusCode);
    echo json_encode(array('error' => true, 'message' => $message), JSON_UNESCAPED_UNICODE, JSON_UNESCAPED_SLASHES);
    exit();
}
?>