<?php

require_once '../models/ReactionsCatalog.php';

class ReactionsCatalogController {
    public function index() {
        $catalog = new ReactionsCatalog();
        echo json_encode($catalog->getAll());
    }

    public function show($code) {
        $catalog = new ReactionsCatalog();
        $data = $catalog->getByCode($code);
        if ($data) {
            echo json_encode($data);
        } else {
            http_response_code(404);
            echo json_encode(['message' => 'Reaction not found']);
        }
    }

    public function store() {
        $body = json_decode(file_get_contents("php://input"), true);
        $catalog = new ReactionsCatalog();
        $catalog->code = $body['code'];
        $catalog->icon = $body['icon'];
        $catalog->label = $body['label'];

        if ($catalog->create()) {
            echo json_encode(['message' => 'Reaction created']);
        } else {
            http_response_code(500);
            echo json_encode(['message' => 'Error creating reaction']);
        }
    }

    public function update($code) {
        $body = json_decode(file_get_contents("php://input"), true);
        $catalog = new ReactionsCatalog();
        $catalog->code = $code;
        $catalog->icon = $body['icon'];
        $catalog->label = $body['label'];

        if ($catalog->update()) {
            echo json_encode(['message' => 'Reaction updated']);
        } else {
            http_response_code(500);
            echo json_encode(['message' => 'Error updating reaction']);
        }
    }

    public function destroy($code) {
        $catalog = new ReactionsCatalog();
        $catalog->code = $code;

        if ($catalog->deleteByCode()) {
            echo json_encode(['message' => 'Reaction deleted']);
        } else {
            http_response_code(500);
            echo json_encode(['message' => 'Error deleting reaction']);
        }
    }
}
