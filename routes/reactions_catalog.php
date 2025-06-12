<?php

function reactions_catalog($method, $subroutes, $body) {
    $controller = new ReactionsCatalogController();
    $code = $subroutes[0] ?? null;

    if (!$code) {
        if ($method === 'GET') $controller->index();
        elseif ($method === 'POST') $controller->store();
        else methodNotAllowed();
        return;
    }

    if ($method === 'GET') $controller->show($code);
    elseif ($method === 'PUT') $controller->update($code);
    elseif ($method === 'DELETE') $controller->destroy($code);
    else methodNotAllowed();
}
