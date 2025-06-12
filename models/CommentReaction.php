<?php

require_once '../config/config.php';

class CommentReaction {
    private $conn;
    private $table = "comment_reactions";

    public $comment_id;
    public $user_id;
    public $reaction_id;

    public function __construct() {
        $this->conn = dbConnection();
    }

    public function setReaction() {
        $sql = "
            IF EXISTS (
                SELECT 1 FROM {$this->table}
                WHERE comment_id = :comment_id AND user_id = :user_id
            )
            BEGIN
                UPDATE {$this->table}
                SET reaction_id = :reaction_id
                WHERE comment_id = :comment_id AND user_id = :user_id
            END
            ELSE
            BEGIN
                INSERT INTO {$this->table} (comment_id, user_id, reaction_id)
                VALUES (:comment_id, :user_id, :reaction_id)
            END
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':comment_id', $this->comment_id);
        $stmt->bindParam(':user_id', $this->user_id);
        $stmt->bindParam(':reaction_id', $this->reaction_id);

        return $stmt->execute();
    }

    public function getReactionsByComment($comment_id) {
        $sql = "SELECT reaction_id, COUNT(*) AS total
                FROM {$this->table}
                WHERE comment_id = :comment_id
                GROUP BY reaction_id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':comment_id', $comment_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUserReaction($comment_id, $user_id) {
        $sql = "SELECT reaction_id
                FROM {$this->table}
                WHERE comment_id = :comment_id AND user_id = :user_id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':comment_id', $comment_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
