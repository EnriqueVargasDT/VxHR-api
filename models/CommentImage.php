<?php

require_once '../config/config.php';

class CommentImage {
    private $conn;
    private $table = "comment_images";

    public $id;
    public $comment_id;
    public $image_url;

    public function __construct() {
        $this->conn = dbConnection();
    }

    public function add() {
        $sql = "INSERT INTO {$this->table} (comment_id, image_url)
                VALUES (:comment_id, :image_url)";
        $stmt = $this->conn->prepare($sql);

        $stmt->bindParam(':comment_id', $this->comment_id);
        $stmt->bindParam(':image_url', $this->image_url);

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
