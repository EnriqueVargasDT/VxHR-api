<?php

require_once '../models/Comment.php';
require_once '../models/CommentImage.php';
require_once '../models/CommentLink.php';
require_once '../models/CommentMention.php';

class CommentController {
    public function index($post_id) {
        $comment = new Comment();
        $comments = $comment->getByPost($post_id);
         $comments = array_map(function($item) {
            // $reactionModel = new Reaction();
            // $rows = $reactionModel->getReactionsByPost($item['id']); // Ejecuta el query
            // $reactions = formatReactions($rows);
            // $item["reactions"] = $reactions;
            return transformToNested($item);
        }, $comments);
        jsonResponse($comments);
    }

    public function show($post_id, $comment_id) {
        $comment = new Comment();
        $main = $comment->getById($comment_id);

        if (!$main || $main['post_id'] != $post_id) {
            http_response_code(404);
            echo json_encode(['message' => 'Comment not found']);
            return;
        }

        $replies = $comment->getReplies($comment_id);
        $main['replies'] = $replies;

        echo json_encode($main);
    }

    public function store($post_id) {
        $body = json_decode(file_get_contents("php://input"), true);

        $comment = new Comment();
        $comment->post_id = $post_id;
        $comment->parent_comment_id = $body['parent_comment_id'] ?? null;
        $comment->user_id = $_SESSION['user']['pk_user_id'];
        $comment->content = $body['content'];

        if ($comment->create()) {
            echo json_encode(['message' => 'Comment created']);
        } else {
            http_response_code(500);
            echo json_encode(['message' => 'Error creating comment']);
        }
    }

    public function update($post_id, $comment_id) {
        $body = json_decode(file_get_contents("php://input"), true);

        $comment = new Comment();
        $comment->id = $comment_id;
        $comment->content = $body['content'];

        // No hay update en modelo, se puede agregar
        $sql = "UPDATE comments SET content = :content WHERE id = :id AND post_id = :post_id AND deleted = 0";
        $stmt = dbConnection()->prepare($sql);
        $stmt->bindParam(':content', $comment->content);
        $stmt->bindParam(':id', $comment_id);
        $stmt->bindParam(':post_id', $post_id);

        if ($stmt->execute()) {
            echo json_encode(['message' => 'Comment updated']);
        } else {
            http_response_code(500);
            echo json_encode(['message' => 'Error updating comment']);
        }
    }

    public function destroy($post_id, $comment_id) {
        $comment = new Comment();
        $comment->id = $comment_id;
        $sql = "UPDATE comments SET deleted = 1 WHERE id = :id AND post_id = :post_id";
        $stmt = dbConnection()->prepare($sql);
        $stmt->bindParam(':id', $comment_id);
        $stmt->bindParam(':post_id', $post_id);

        if ($stmt->execute()) {
            echo json_encode(['message' => 'Comment deleted']);
        } else {
            http_response_code(500);
            echo json_encode(['message' => 'Error deleting comment']);
        }
    }
}
