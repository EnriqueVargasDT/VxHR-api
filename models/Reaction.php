<?php

require_once '../config/config.php';

class Reaction {
    private $conn;
    private $table = "post_reactions";

    public $post_id;
    public $user_id;
    public $reaction_id;

    public function __construct() {
        $this->conn = dbConnection();
    }

    public function setReaction() {
        // Verifica si ya existe una reacción
        $checkSql = "SELECT COUNT(*) FROM {$this->table} WHERE post_id = :post_id AND user_id = :user_id";
        $checkStmt = $this->conn->prepare($checkSql);
        $checkStmt->bindParam(':post_id', $this->post_id);
        $checkStmt->bindParam(':user_id', $this->user_id);
        $checkStmt->execute();
        $exists = $checkStmt->fetchColumn() > 0;

        if ($exists) {
            // Actualizar reacción existente
            $updateSql = "UPDATE {$this->table} SET reaction_id = :reaction_id WHERE post_id = :post_id AND user_id = :user_id";
            $updateStmt = $this->conn->prepare($updateSql);
            $updateStmt->bindParam(':reaction_id', $this->reaction_id);
            $updateStmt->bindParam(':post_id', $this->post_id);
            $updateStmt->bindParam(':user_id', $this->user_id);
            return $updateStmt->execute();
        } else {
            // Insertar nueva reacción
            $insertSql = "INSERT INTO {$this->table} (post_id, user_id, reaction_id) VALUES (:post_id, :user_id, :reaction_id)";
            $insertStmt = $this->conn->prepare($insertSql);
            $insertStmt->bindParam(':post_id', $this->post_id);
            $insertStmt->bindParam(':user_id', $this->user_id);
            $insertStmt->bindParam(':reaction_id', $this->reaction_id);
            return $insertStmt->execute();
        }
    }


    public function getReactionsByPost($post_id) {
        $sql = "SELECT
            r.created_at,
            rc.code AS type,
            u.pk_user_id AS created_by__id,
            CONCAT(u.first_name, ' ', u.last_name_1) AS created_by__display_name,
            uf.[file] AS created_by__profile_picture
        FROM post_reactions r
        JOIN reactions_catalog rc ON r.reaction_id = rc.id
        JOIN [user].users u ON u.pk_user_id = r.user_id
        LEFT JOIN [user].files uf ON uf.fk_user_id = u.pk_user_id AND uf.type_file = 1
        WHERE r.post_id = :post_id
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':post_id', $post_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUserReaction($post_id, $user_id) {
        $sql = "SELECT reaction_id
                FROM {$this->table}
                WHERE post_id = :post_id AND user_id = :user_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':post_id', $post_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
