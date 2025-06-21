<?php

require_once '../models/Post.php';
require_once '../models/PostType.php';
require_once '../models/PostImage.php';
require_once '../models/PostLink.php';
require_once '../models/PostTarget.php';
require_once '../models/Reaction.php';
require_once '../models/Comment.php';

class PostController {
    public function index() {
        $post = new Post();
        $posts = $post->getAll();
        $posts = array_map(function($item) {
            $reactionModel = new Reaction();
            $rows = $reactionModel->getReactionsByPost($item['id']); // Ejecuta el query
            $reactions = formatReactions($rows);
            $item["reactions"] = $reactions;

            $commentModel = new Comment();
            $comments = $commentModel->getByPost($item['id']);
            $item["comments"] = array_map(function($item) {
                return transformToNested($item);
            }, $comments);

            return transformToNested($item);
        }, $posts);
        jsonResponse($posts);
    }

    public function show($id) {
        $post = new Post();
        $data = $post->getById($id);
        if (!$data) {
            handleError(404, 'Post not found');
            return;
        }

        jsonResponse(transformToNested($data));
    }

    public function store() {
        $body = json_decode(file_get_contents("php://input"), true);

        $post = new Post();
        $post->author_id = $_SESSION['user']["pk_user_id"];
        $post->post_type_id = $body['post_type_id'] ?? 5;
        $post->content = $body['content'];
        $post->published_at = $body['published_at'] ?? date('Y-m-d H:i:s');

        $id = $post->create();

        if ($id) {
            // Guardar target user si aplica
            if (!empty($body['target_user_id'])) {
                $target = new PostTarget();
                $target->post_id = $id;
                $target->target_user_id = $body['target_user_id'];
                $target->assign();
            }

            // Guardar imágenes si vienen
            if (!empty($body['images']) && is_array($body['images'])) {
                $imageModel = new PostImage();
                foreach ($body['images'] as $url) {
                    $imageModel->post_id = $id;
                    $imageModel->image_url = $url;
                    $imageModel->add();
                }
            }

            // Guardar links si vienen
            if (!empty($body['links']) && is_array($body['links'])) {
                $linkModel = new PostLink();
                foreach ($body['links'] as $link) {
                    $linkModel->post_id = $id;
                    $linkModel->external_url = $link['external_url'];
                    $linkModel->internal_redirect_path = $link['internal_redirect_path'] ?? null;
                    $linkModel->add();
                }
            }

            // Obtener post final con detalles
            $created = $post->getById($id);
            jsonResponse($created);
        } else {
            handleError(400, 'Error creating post');
        }
    }


    public function update($id) {
        $body = json_decode(file_get_contents("php://input"), true);
        $post = new Post();
        $post->id = $id;
        if (isset($body['title'])) $post->title = $body['title'];
        if (isset($body['content'])) $post->content = $body['content'];
        if (isset($body['post_type_id'])) $post->post_type_id = $body['post_type_id'];

        if ($post->update()) {
            $data = $post->getById($id);
            jsonResponse($data, 'Post updated: ' . $post->id);
        } else {
            handleError(400, 'Error updating post');
        }
    }

    public function destroy($id) {
        $post = new Post();
        $post->id = $id;
        if ($post->delete()) {
            jsonResponse('Post deleted');
        } else {
            handleError(400, 'Error deleting post');
        }
    }
}
