<?php

require_once '../models/Post.php';
require_once '../models/PostType.php';
require_once '../models/PostImage.php';
require_once '../models/PostLink.php';
require_once '../models/PostTarget.php';
require_once '../models/Reaction.php';
require_once '../models/Comment.php';



class PostController {
    private $reactionCodes = [
        'like','love','applause','birthday-cake','smile',
        'trophy','idea','appreciation','sympathy','star'
    ];

    public function index(): void {
        $page  = max(1, (int)($_GET['page']  ?? 1));
        $limit = max(1, min((int)($_GET['limit'] ?? 20), 50));
        $offset = ($page - 1) * $limit;

        $postModel = new Post();
        $total = $postModel->countAll();
        $rows  = $postModel->getPage($limit, $offset);

        $data = [];
        foreach ($rows as $r) {
        
            $nested = transformToNested($r);

        
            $countArr = json_decode($nested['reactions']['count_json'] ?? '[]', true) ?: [];
            $countMap = array_fill_keys($this->reactionCodes, 0);
            foreach ($countArr as $it) {
                if (isset($it['code'])) $countMap[$it['code']] = (int)($it['qty'] ?? 0);
            }
            unset($nested['reactions']['count_json']);
            $nested['reactions']['count']  = $countMap;
            $nested['reactions']['total']  = (int)($nested['reactions']['total'] ?? 0);

        
            $nested['reactions']['items'] =
                json_decode($nested['reactions']['items_json'] ?? '[]', true) ?: [];
            unset($nested['reactions']['items_json']);

        
            $nested['comments'] =
                json_decode($nested['comments']['items_json'] ?? '[]', true) ?: [];
            unset($nested['comments']['items_json']);

        
            $nested['attachments'] =
                json_decode($nested['attachments']['items_json'] ?? '[]', true) ?: [];
            unset($nested['attachments']['items_json']);

            $data[] = $nested;
        }

        jsonResponse($data, null, 200, [
            'pagination' => [
                'page'     => $page,
                'limit'    => $limit,
                'total'    => $total,
                'has_more' => ($offset + count($data)) < $total,
            ],
        ]);
    }

    public function show($id) {
        $post = new Post();
        $response = $post->getById($id);
        if (!$response) {
            handleError(404, 'Post not found');
            return;
        }

        $nested = transformToNested($response);
        $countArr = json_decode($nested['reactions']['count_json'] ?? '[]', true) ?: [];
        $countMap = array_fill_keys($this->reactionCodes, 0);
        foreach ($countArr as $it) {
            if (isset($it['code'])) $countMap[$it['code']] = (int)($it['qty'] ?? 0);
        }
        unset($nested['reactions']['count_json']);
        $nested['reactions']['count']  = $countMap;
        $nested['reactions']['total']  = (int)($nested['reactions']['total'] ?? 0);

    
        $nested['reactions']['items'] =
            json_decode($nested['reactions']['items_json'] ?? '[]', true) ?: [];
        unset($nested['reactions']['items_json']);

    
        $nested['comments'] =
            json_decode($nested['comments']['items_json'] ?? '[]', true) ?: [];
        unset($nested['comments']['items_json']);

    
        $nested['attachments'] =
            json_decode($nested['attachments']['items_json'] ?? '[]', true) ?: [];
        unset($nested['attachments']['items_json']);

        jsonResponse($nested);
    }

    public function store() {
        $body = json_decode(file_get_contents("php://input"), true);

        $post = new Post();
        $post->author_id = $_SESSION['user']["pk_user_id"];
        $post->post_type_id = isset($body['post_type_id']) ? $body['post_type_id'] : 5;
        $post->published_at = isset($body['published_at']) ? $body['published_at'] : date('Y-m-d H:i:s');
        $post->content = $body['content'];

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
                    $linkModel->src = $link['src'];
                    $linkModel->title = $link['title'];
                    $linkModel->description = isset($link['description']) ? $link['description'] : null;
                    $linkModel->add();
                }
            }

            // Obtener post final con detalles
            $created = $post->getById($id);
            jsonResponse(transformToNested($created));
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
