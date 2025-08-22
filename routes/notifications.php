<?php
require_once __DIR__ . '/../controllers/NotificationController.php';

function notifications($method, $subroutes, $body) {
    $controller = new NotificationController();
    $id = $subroutes[0] ?? null;
    $action = $subroutes[1] ?? null;
    
    // /notifications
    if (!$id) {
        if ($method === 'GET') $controller->index();
        elseif ($method === 'POST') $controller->store($body);
        else methodNotAllowed();
        return;
    }
    
    // /notifications/{id}/publish
    if ($action === 'publish') {
        if ($method === 'POST') $controller->publish((int)$id, $body);
        else methodNotAllowed();
        return;
    }
    
    if($id === "me") {
        require_once '../routes/me_notifications.php';
        me_notifications($method, array_slice($subroutes, 1), $body);
    } else {
        // /notifications/{id}
        if ($method === 'GET') $controller->show((int)$id);
        elseif ($method === 'PUT') $controller->update((int)$id, $body);
        elseif ($method === 'DELETE') $controller->destroy((int)$id);
        else methodNotAllowed();
    }

}
