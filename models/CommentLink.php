<?php

require_once '../config/config.php';

class CommentLink {
    private $conn;
    private $table = "comment_links";

    public $id;
    public $comment_id;
    public $external_url;
    public $internal_redirect_path;

    public function __construct() {
        $this->conn = dbConnection();
    }

    public function add() {
        $sql = "INSERT INTO {$this->table} (comment_id, external_url, internal_redirect_path)
                VALUES (:comment_id, :external_url, :internal_redirect_path)";
        $stmt = $this->conn->prepare($sql);

        $stmt->bindParam(':comment_id', $this->comment_id);
        $stmt->bindParam(':external_url', $this->external_url);
        $stmt->bindParam(':internal_redirect_path', $this->internal_redirect_path);

        return $stmt->execute();
    }

    public function getByComment($comment_id) {
        $sql = "SELECT * FROM {$this->table}
                WHERE comment_id = :comment_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':comment_id', $comment_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteById($id) {
        $sql = "DELETE FROM {$this->table}
                WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);

        return $stmt->execute();
    }

    public function deleteAllByComment($comment_id) {
        $sql = "DELETE FROM {$this->table}
                WHERE comment_id = :comment_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':comment_id', $comment_id);

        return $stmt->execute();
    }
}
