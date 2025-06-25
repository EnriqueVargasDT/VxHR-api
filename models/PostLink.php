<?php

require_once '../config/config.php';

class PostLink {
    private $conn;
    private $table = "post_links";

    public $id;
    public $post_id;
    public $src;
    public $title;
    public $description;

    public function __construct() {
        $this->conn = dbConnection();
    }

    public function add() {
        $data = [
            'post_id' => $this->post_id,
            'src' => $this->src,
            'title' => $this->title,
            'description' => $this->description
        ];

        $result = buildInsertQuery($this->table, $data);
        if (!$result) return false;

        $stmt = $this->conn->prepare($result['sql']);
        foreach ($result['params'] as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        // Execute the prepared statement and return the last inserted ID
        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return $this->id;
        }

        return false;
    }

    public function getByPost($post_id) {
        $sql = "SELECT * FROM {$this->table}
                WHERE post_id = :post_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':post_id', $post_id);
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

    public function deleteAllByPost($post_id) {
        $sql = "DELETE FROM {$this->table}
                WHERE post_id = :post_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':post_id', $post_id);

        return $stmt->execute();
    }
}
