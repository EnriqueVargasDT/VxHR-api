<?php

require_once '../config/config.php';

class Comment {
    private $conn;
    private $table = "comments";

    public $id;
    public $post_id;
    public $parent_comment_id;
    public $user_id;
    public $content;
    public $created_at;
    public $deleted;

    public function __construct() {
        $this->conn = dbConnection();
    }

    public function create() {
        $sql = "INSERT INTO {$this->table} (post_id, parent_comment_id, user_id, content)
                VALUES (:post_id, :parent_comment_id, :user_id, :content)";
        $stmt = $this->conn->prepare($sql);

        $stmt->bindParam(':post_id', $this->post_id);
        $stmt->bindParam(':parent_comment_id', $this->parent_comment_id);
        $stmt->bindParam(':user_id', $this->user_id);
        $stmt->bindParam(':content', $this->content);

        return $stmt->execute();
    }

    public function getById($id) {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id AND deleted = 0";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getByPost($post_id) {
        $sql = "SELECT
                    c.*,
                    SWITCHOFFSET(CONVERT(datetimeoffset, c.created_at), '-06:00') AS created_at,

                    -- Datos del autor
                    u.pk_user_id AS created_by__id,
                    CONCAT(u.first_name, ' ', u.last_name_1) AS created_by__display_name,
                    jpp.job_position AS created_by__position,
                    uf.[file] AS created_by__profile_picture

                FROM {$this->table} c
                
                -- JOIN autor
                LEFT JOIN [user].users u ON u.pk_user_id = c.user_id
                LEFT JOIN [job_position].[positions] jpp ON u.fk_job_position_id = jpp.pk_job_position_id
                LEFT JOIN [user].[files] uf ON uf.fk_user_id = u.pk_user_id AND uf.type_file = 1
                
                WHERE post_id = :post_id AND parent_comment_id IS NULL AND deleted = 0
                ORDER BY c.created_at DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':post_id', $post_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getReplies($comment_id) {
        $sql = "SELECT * FROM {$this->table}
                WHERE parent_comment_id = :comment_id AND deleted = 0
                ORDER BY created_at ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':comment_id', $comment_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function delete() {
        $sql = "UPDATE {$this->table} SET deleted = 1 WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }
}
