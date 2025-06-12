<?php

require_once '../models/PostType.php';

class PostTypeController {
    public function index() {
        $type = new PostType();
        jsonResponse($type->getAll());
    }

    public function show($id) {
        $type = new PostType();
        $data = $type->getById($id);
        if ($data) {
            jsonResponse($data);
        } else {
            handleError(404, 'Post type not found');
        }
    }

    public function store() {
        $body = json_decode(file_get_contents("php://input"), true);
        $type = new PostType();
        $type->code = $body['code'];
        $type->name = $body['name'];
        $type->description = $body['description'];

        $id = $type->create();

        if ($id) {
             $created = $type->getById($id);
            jsonResponse($created, ['message' => 'Post type created', 'id' => $id]);
        } else {
            handleError(400, 'Error creating post type');
        }
    }

    public function update($id) {
        $body = json_decode(file_get_contents("php://input"), true);
        $type = new PostType();
        $type->id = $id;
        if (isset($body['code'])) $type->code =  $body['code'];
        if (isset($body['name'])) $type->name =  $body['name'];
        if (isset($body['description'])) $type->description =  $body['description'];

        if ($type->update()) {
            $data = $type->getById($id);
            jsonResponse($data, 'Post type updated: ' . $type->id);
        } else {
            handleError(400, 'Error updating post type');
        }
    }

    public function destroy($id) {
        $type = new PostType();
        $type->id = $id;
        if ($type->delete()) {
            jsonResponse('Post type deleted');
        } else {
            handleError(500, 'Error deleting post type');
        }
    }
}
