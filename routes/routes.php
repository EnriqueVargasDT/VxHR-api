<?php
require_once '../controllers/tokenController.php';
require_once '../controllers/loginController.php';
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
    http_response_code(404);
    echo json_encode(array('error' => true, 'message' => 'Ruta no encontrada.'));
    exit();
}

function methodNotAllowed() {
    http_response_code(405);
    echo json_encode(array('error' => true, 'message' => 'Método no permitido.'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

function unAuthorized() {
    http_response_code(401);
    echo json_encode(array('error' => true, 'message' => 'No autorizado.'));
    exit();
}

function internalServerError($message = null) {
    http_response_code(500);
    echo json_encode(array('error' => true, 'message' => $message ?? 'Error interno de servidor.'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}
?>