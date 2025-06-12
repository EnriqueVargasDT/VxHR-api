<?php

function post_types($method, $subroutes, $body) {
    $controller = new PostTypeController();
    $id = $subroutes[0] ?? null;

    if (!$id) {
        if ($method === 'GET') $controller->index();
        elseif ($method === 'POST') $controller->store();
        else methodNotAllowed();
        return;
    }

    if ($method === 'GET') $controller->show($id);
    elseif ($method === 'PUT') $controller->update($id);
    elseif ($method === 'DELETE') $controller->destroy($id);
    else methodNotAllowed();
}
