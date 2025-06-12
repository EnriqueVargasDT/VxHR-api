<?php

require_once '../config/config.php';

class PostType {
    private $conn;
    private $table = "post_types";

    public $id;
    public $code;
    public $name;
    public $description;

    public function __construct() {
        $this->conn = dbConnection();
    }

    public function getAll() {
        $sql = "SELECT * FROM {$this->table} ORDER BY code ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create() {
        $data = [
            'code' => $this->code,
            'name' => $this->name,
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

    public function update() {
        $data = [
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
        ];

        $result = buildUpdateQuery($this->table, 'id', $this->id, $data);
        if (!$result) return false;

        $stmt = $this->conn->prepare($result['sql']);

        foreach ($result['params'] as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        return $stmt->execute();
    }

    public function delete() {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }
}
