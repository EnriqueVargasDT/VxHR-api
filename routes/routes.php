<?php
session_start();
require_once '../utils/response.php';
spl_autoload_register(function ($className) {
    $controllerPath = __DIR__ . '/../controllers/' . $className . '.php';
    if (file_exists($controllerPath)) {
        require_once $controllerPath;
    }
});

$method = $_SERVER['REQUEST_METHOD'];
$requestUriParts = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
$body = json_decode(file_get_contents('php://input'), true);

$main = $requestUriParts[0] ?? '';
if ($main !== 'api') {
    pathNotFound();
}

$route = $requestUriParts[1] ?? null;
$subroutes = array_slice($requestUriParts, 2);

if (str_starts_with($route, 'login')) {
    login($method, $subroutes, $body);
}
else if (str_starts_with($route, 'logout')) {
    logout($method);
}
else {
    $tokenController = new TokenController();
    $validateToken = $tokenController->validate();
    if (isset($validateToken['ok'])) {
        switch (true) {
            case str_starts_with($route, 'role'):
                role($method, $subroutes, $body);
                break;
            case str_starts_with($route, 'policies'):
                policies($method, $subroutes, $body);
                break;
            case str_starts_with($route, 'user_policies'):
                user_policies($method, $subroutes, $body);
                break;
            case str_starts_with($route, 'user_files'):
                user_files($method, $subroutes, $body);
                break;
            case str_starts_with($route, 'user'):
                user($method, $subroutes, $body);
                break;
            case str_starts_with($route, 'temperature'):
                temperature($method);
                break;
            case str_starts_with($route, 'catalog'):
                catalog($method, $subroutes, $body);
                break;
            case str_starts_with($route, 'job_position'):
                job_position($method, $subroutes, $body);
                break;
            case str_starts_with($route, 'organization'):
                organization($method);
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
                    if (str_starts_with($subroutes[0], 'password_recovery')) {
                        $loginController->passwordRecovery($body['username']);
                    }
                    else if (str_starts_with($subroutes[0], 'password_update')) {
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
                    if (str_starts_with($subroutes[0], 'catalog')) {
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
            if (isset($_GET['id'])) {
                $userController->getById($_GET['id']);
            }

            if (count($subroutes) > 0) {
                if (isset($subroutes[0])) {
                    if (str_starts_with($subroutes[0], 'has_signed_policies')) {
                        $userController->hasSignedPolicies();
                    }
                }
            }

            $userController->getAll();
            break;
        case 'POST':
            $userController->save($body);
            break;
        case 'PUT':
            if (count($subroutes) > 0) {
                if (isset($subroutes[0])) {
                    if (str_starts_with($subroutes[0], 'status')) {
                        $userController->updateStatus($body['id'], $body['status']);
                    }
                    else if (is_numeric($subroutes[0])) {
                        $userController->update($subroutes[0], $body);
                    }

                    pathNotFound();
                }
            }

            pathNotFound();
            break;
        default:
            methodNotAllowed();
            break;
    }
}

function user_policies($method, $subroutes, $body) {
    $userPoliciesController = new UserPoliciesController();
    switch ($method) {
        case 'GET':
            if (isset($_GET['user_id']) && isset($_GET['signed'])) {
                $userPoliciesController->getAll($_GET['user_id'], $_GET['signed']);
            }

            $userPoliciesController->getAll();
            break;
        case 'POST':
            $userPoliciesController->save($body);
            break;
        case 'PUT':
            if (count($subroutes) > 0) {
                if (isset($subroutes[0])) {
                    if (str_contains($subroutes[0], 'status')) {
                        $userPoliciesController->updateStatus($body);
                    }

                    pathNotFound();
                }
            }

            pathNotFound();
            break;
        default:
            methodNotAllowed();
            break;
    }
}

function user_files($method, $subroutes, $body) {
    $userFilesController = new UserFilesController();
    switch ($method) {
        case 'GET':
            if (isset($_GET['user_id']) && isset($_GET['type_file'])) {
                $userFilesController->getByType($_GET['user_id'], $_GET['type_file']);
            }
            
            pathNotFound();
            break;
        case 'POST':
            if (isset($_POST['type_file']) && $_POST['type_file'] == UserFiles::TYPE_PROFILE_PICTURE) {
                $userFilesController->upload($_POST['user_id'], UserFiles::TYPE_PROFILE_PICTURE, null);
            }
            else if (isset($body['type_file']) && $body['type_file'] == UserFiles::TYPE_SIGNATURE) {
                $userFilesController->upload($body['user_id'], UserFiles::TYPE_SIGNATURE, (isset($body['file']) ? $body['file'] : null));
            }

            pathNotFound();
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
                            $catalogController->getItemById($subroutes[0], $subroutes[1], $_GET['id']);
                        }

                        $catalogController->getAll($subroutes[0], $subroutes[1], isset($_GET['available']) ? $_GET['available'] : null);
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
                            $catalogController->saveItem($subroutes[0], $subroutes[1], $body);
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
                        if (isset($subroutes[2])) {
                            if (str_starts_with($subroutes[2], 'status')) {
                                $catalogController->updateItemStatus($subroutes[0], $subroutes[1], $body);
                            }

                            pathNotFound();
                        }
                        else {
                            if (isset($body['id'])) {
                                $catalogController->updateItem($subroutes[0], $subroutes[1], $body);
                            }

                            internalServerError('No se recibió el id del elemento de catálogo para actualizar.');
                        }
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

function job_position($method, $subroutes, $body) {
    $jobPositionController = new JobPositionController();
    switch ($method) {
        case 'GET':
            if (count($subroutes) > 0) {
                if (isset($subroutes[0])) {
                    if (str_starts_with($subroutes[0], 'positions')) {
                        if (isset($_GET['id'])) {
                            $jobPositionController->getDataById($_GET['id']);
                        }

                        $jobPositionController->getAll(isset($_GET['available']) ? $_GET['available'] : null);
                    }

                    pathNotFound();
                }
            }

            pathNotFound();
            break;
        case 'POST':
            $jobPositionController->save($body);
            break;
        case 'PUT':
            if (count($subroutes) > 0) {
                if (isset($subroutes[0])) {
                    if (is_numeric($subroutes[0])) {
                        $jobPositionController->update($subroutes[0], $body);
                    }

                    pathNotFound();
                }
            }

            pathNotFound();
            break;
        default:
            methodNotAllowed();
            break;
    }
}

function organization($method) {
    $organizationController = new OrganizationController();
    switch ($method) {
        case 'GET':
            $organizationController->getData();
            break;
        default:
            methodNotAllowed();
            break;
    }
}

function policies($method, $subroutes, $body) {
    $policiesController = new PoliciesController();
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                if (str_starts_with($subroutes[0], 'all_users')) {
                    $policiesController->getAllUsers($_GET['id']);
                }
                
                $policiesController->getById($_GET['id']);
            }

            $policiesController->getAll();
            break;
        case 'POST':
            $policiesController->save($body);
            break;
        case 'PUT':
            if (count($subroutes) > 0) {
                if (isset($subroutes[0])) {
                    if (str_starts_with($subroutes[0], 'status')) {
                        $policiesController->updateStatus($body['id'], $body['status']);
                    }
                    elseif (is_numeric($subroutes[0])) {
                        $policiesController->update($subroutes[0], $body);
                    }
                }
            }

            pathNotFound();
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
    handleError(404, 'Ruta no encontrada.');
    exit();
}

function methodNotAllowed() {
    handleError(405, 'Método no permitido.');
    exit();
}

function unAuthorized() {
    handleError(401, 'No autorizado.');
    exit();
}

function internalServerError($message = null) {
    handleError(500, $message ?? 'Error interno de servidor.');
    exit();
}
?>