<?php

function posts($method, $subroutes, $body) {
    $postController = new PostController();
    $reactionController = new ReactionController();
    $commentController = new CommentController();

    $id = $subroutes[0] ?? null;
    $subId = $subroutes[1] ?? null;
    $subSubId = $subroutes[2] ?? null;

    // /posts
    if (!$id) {
        if ($method === 'GET') $postController->index();
        elseif ($method === 'POST') $postController->store();
        else methodNotAllowed();
        return;
    }

    // /posts/{id}
    if (!$subId) {
        if ($method === 'GET') $postController->show($id);
        elseif ($method === 'PUT') $postController->update($id);
        elseif ($method === 'DELETE') $postController->destroy($id);
        else methodNotAllowed();
        return;
    }

    // Subroutes
    switch ($subId) {
        case 'reactions':
            if ($subSubId) {
                if ($method === 'GET') $reactionController->show($id, $subSubId);
                elseif ($method === 'DELETE') $reactionController->destroy($id, $subSubId);
                else methodNotAllowed();
            } else {
                if ($method === 'GET') $reactionController->index($id);
                elseif ($method === 'POST') $reactionController->store($id);
                else methodNotAllowed();
            }
            break;

        case 'comments':
            if ($subSubId) {
                if ($method === 'GET') $commentController->show($id, $subSubId);
                elseif ($method === 'PUT') $commentController->update($id, $subSubId);
                elseif ($method === 'DELETE') $commentController->destroy($id, $subSubId);
                else methodNotAllowed();
            } else {
                if ($method === 'GET') $commentController->index($id);
                elseif ($method === 'POST') $commentController->store($id);
                else methodNotAllowed();
            }
            break;

        default:
            pathNotFound();
            break;
    }
}
