<?php

require_once '../controllers/PostController.php';
require_once '../controllers/ReactionController.php';
require_once '../controllers/CommentController.php';
require_once '../controllers/PostTypeController.php';
require_once '../controllers/ReactionsCatalogController.php';

require_once '../routes/posts.php';
require_once '../routes/post_types.php';
require_once '../routes/reactions_catalog.php';
require_once '../utils/response.php';

// Simula la petición
$method = $_SERVER['REQUEST_METHOD'];
$requestUriParts = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
$body = json_decode(file_get_contents('php://input'), true);

$route = $requestUriParts[0] ?? null;
$subroutes = array_slice($requestUriParts, 1);

switch ($route) {
    case 'posts':
        posts($method, $subroutes, $body);
        break;
    case 'post_types':
        post_types($method, $subroutes, $body);
        break;
    case 'reactions_catalog':
        reactions_catalog($method, $subroutes, $body);
        break;
    default:
        pathNotFound();
        break;
}
