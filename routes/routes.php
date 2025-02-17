<?php
require_once '../controllers/tokenController.php';
require_once '../controllers/loginController.php';
require_once '../controllers/roleController.php';
require_once '../controllers/logoutController.php';
require_once '../controllers/userController.php';
require_once '../controllers/temperatureController.php';
require_once '../controllers/catalogController.php';

$method = $_SERVER['REQUEST_METHOD'];
$requestUriParts = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
$body = json_decode(file_get_contents('php://input'), true);

$main = $requestUriParts[0] ?? '';
if ($main !== 'api') {
    pathNotFound();
}

$route = $requestUriParts[1] ?? null;
$subroutes = array_slice($requestUriParts, 2);

if (str_contains($route, 'login')) {
    login($method, $subroutes, $body);
}
else if (str_contains($route, 'logout')) {
    logout($method);
}
else {
    $tokenController = new TokenController();
    $validateToken = $tokenController->validate();

    if (isset($validateToken['ok'])) {
        switch ($route) {
            case str_contains($route, 'role'):
                role($method, $subroutes, $body);
                break;
            case str_contains($route, 'user'):
                user($method, $subroutes, $body);
                break;
            case str_contains($route, 'temperature'):
                temperature($method);
                break;
            case str_contains($route, 'catalog'):
                catalog($method, $subroutes, $body);
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

function login($method, $subroutes, $body) {
    $loginController = new LoginController();
    switch ($method) {
        case 'POST':
            if (count($subroutes) > 0) {
                if (isset($subroutes[0])) {
                    if (str_contains($subroutes[0], 'password_recovery')) {
                        $loginController->passwordRecovery($body['username']);
                    }
                    else if (str_contains($subroutess[0], 'password_update')) {
                        $loginController->passwordUpdate($body['token'], $body['newPassword'], $body['confirmPassword']);
                    }
                    
                    pathNotFound();
                }
            }
            else {
                if (isset($body['username']) && isset($body['password'])) {
                    $loginController->validate($body['username'], $body['password'], $body['rememberMe']);
                }

                internalServerError('No se recibió un usuario y/o contraseña.');
            }
            break;
        default:
            methodNotAllowed();
            break;
    }
}

function role($method, $subroutes, $body) {
    $roleController = new RoleController();
    switch ($method) {
        case 'GET':
            if (count($subroutes) > 0) {
                if (isset($subroutes[0])) {
                    if (str_contains($subroutes[0], 'catalog')) {
                        $roleController->getAll();    
                    }
                    
                    pathNotFound();
                }
            }

            $roleController->getBySession();
            break;
        default:
            methodNotAllowed();
            break;
    }
}

function user($method, $subroutes, $body) {
    $userController = new UserController();
    switch ($method) {
        case 'GET':
            if (count($subroutes) > 0) {
                if (isset($subroutes[0])) {
                    if (is_numeric($subroutes[0])) {
                        $userController->getById($subroutes[0]);
                    }
                    
                    pathNotFound();
                }
            }
            
            $userController->getAll();
            break;
        default:
            methodNotAllowed();
            break;
    }
}

function temperature($method) {
    switch ($method) {
        case 'GET':
            TemperatureController::get($_GET['latitude'], $_GET['longitude']);
            break;
        default:
            methodNotAllowed();
            break;
    }
}

function catalog($method, $subroutes, $body) {
    $catalogController = new CatalogController();
    switch ($method) {
        case 'GET':
            if (count($subroutes) > 0) {
                if (isset($subroutes[0])) {
                    if (isset($subroutes[1])) {
                        if (isset($_GET['id'])) {
                            $catalogController->getItemDataById($subroutes[0], $subroutes[1], $_GET['id']);
                        }
                        else {
                            $catalogController->getDataByName($subroutes[0], $subroutes[1]);
                        }
                    }
                    
                    internalServerError('No se recibió un nombre de catálogo válido.');
                }
            }
            
            pathNotFound();
            break;
        case 'POST':
            if (count($subroutes) > 0) {
                if (isset($subroutes[0])) {
                    if (isset($subroutes[1])) {
                        if (isset($body['description'])) {
                            $catalogController->saveNewItem($subroutes[0], $subroutes[1], $body);
                        }

                        internalServerError('No se recibió una descripción válida para crear elemento de catálogo.');    
                    }
                    
                    internalServerError('No se recibió un nombre de catálogo válido.');
                }
            }
            
            pathNotFound();
            break;
        case 'PUT':
            if (count($subroutes) > 0) {
                if (isset($subroutes[0])) {
                    if (isset($subroutes[1])) {
                        if (isset($body['id'])) {
                            $catalogController->updateItem($subroutes[0], $subroutes[1], $body);
                        }

                        internalServerError('No se recibió el id del elemento de catálogo para actualizar.');
                    }

                    internalServerError('No se recibió un nombre de catálogo válido.');
                }
            }
            else {
                pathNotFound();
            }
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