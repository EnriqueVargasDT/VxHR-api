<?php

require_once '../config/config.php';

class CommentMention {
    private $conn;
    private $table = "comment_mentions";

    public $comment_id;
    public $mentioned_user_id;

    public function __construct() {
        $this->conn = dbConnection();
    }

    public function addMention() {
        $sql = "IF NOT EXISTS (
                    SELECT 1 FROM {$this->table}
                    WHERE comment_id = :comment_id AND mentioned_user_id = :mentioned_user_id
                )
                BEGIN
                    INSERT INTO {$this->table} (comment_id, mentioned_user_id)
                    VALUES (:comment_id, :mentioned_user_id)
                END";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':comment_id', $this->comment_id);
        $stmt->bindParam(':mentioned_user_id', $this->mentioned_user_id);

        return $stmt->execute();
    }

    public function removeMention() {
        $sql = "DELETE FROM {$this->table}
                WHERE comment_id = :comment_id AND mentioned_user_id = :mentioned_user_id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':comment_id', $this->comment_id);
        $stmt->bindParam(':mentioned_user_id', $this->mentioned_user_id);

        return $stmt->execute();
    }

    public function getMentionsByComment($comment_id) {
        $sql = "SELECT mentioned_user_id
                FROM {$this->table}
                WHERE comment_id = :comment_id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':comment_id', $comment_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
