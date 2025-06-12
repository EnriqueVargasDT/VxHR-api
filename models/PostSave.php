<?php

require_once '../config/config.php';

class PostSave {
    private $conn;
    private $table = "post_saves";

    public $post_id;
    public $user_id;
    public $saved_at;

    public function __construct() {
        $this->conn = dbConnection();
    }

    public function save() {
        $sql = "IF NOT EXISTS (
                    SELECT 1 FROM {$this->table}
                    WHERE post_id = :post_id AND user_id = :user_id
                )
                BEGIN
                    INSERT INTO {$this->table} (post_id, user_id)
                    VALUES (:post_id, :user_id)
                END";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':post_id', $this->post_id);
        $stmt->bindParam(':user_id', $this->user_id);

        return $stmt->execute();
    }

    public function unsave() {
        $sql = "DELETE FROM {$this->table}
                WHERE post_id = :post_id AND user_id = :user_id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':post_id', $this->post_id);
        $stmt->bindParam(':user_id', $this->user_id);

        return $stmt->execute();
    }

    public function isSaved($post_id, $user_id) {
        $sql = "SELECT COUNT(*) AS saved
                FROM {$this->table}
                WHERE post_id = :post_id AND user_id = :user_id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':post_id', $post_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['saved'] > 0;
    }

    public function getSavedPosts($user_id) {
        $sql = "SELECT post_id, saved_at
                FROM {$this->table}
                WHERE user_id = :user_id
                ORDER BY saved_at DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
