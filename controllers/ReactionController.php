<?php

require_once '../models/Reaction.php';

class ReactionController {
    public function index($post_id) {
        $reaction = new Reaction();
        $reactions = $reaction->getReactionsByPost($post_id);
        $posts = array_map(function($item) {
            return transformToNested($item);
        }, $reactions);
        jsonResponse($posts);
    }

    public function show($post_id, $user_id) {
        $reaction = new Reaction();
        $data = $reaction->getUserReaction($post_id, $user_id);

        if ($data) {
            echo json_encode($data);
        } else {
            http_response_code(404);
            echo json_encode(['message' => 'Reaction not found']);
        }
    }

    public function store($post_id) {
        $body = json_decode(file_get_contents("php://input"), true);

        $reaction = new Reaction();
        $reaction->post_id = $post_id;
        $reaction->user_id = $_SESSION['user']['pk_user_id'];
        $reaction->reaction_id = $body['reaction_id'];

        if ($reaction->setReaction()) {
            echo json_encode(['message' => 'Reaction saved']);
        } else {
            http_response_code(500);
            echo json_encode(['message' => 'Error saving reaction']);
        }
    }

    public function destroy($post_id, $user_id) {
        $sql = "DELETE FROM post_reactions WHERE post_id = :post_id AND user_id = :user_id";
        $conn = dbConnection();
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':post_id', $post_id);
        $stmt->bindParam(':user_id', $user_id);

        if ($stmt->execute()) {
            echo json_encode(['message' => 'Reaction deleted']);
        } else {
            http_response_code(500);
            echo json_encode(['message' => 'Error deleting reaction']);
        }
    }
}
