<?php

require_once '../config/config.php';

class ReactionsCatalog {
    private $conn;
    private $table = "reactions_catalog";

    public $id;
    public $code;
    public $icon;
    public $label;

    public function __construct() {
        $this->conn = dbConnection();
    }

    public function getAll() {
        $sql = "SELECT * FROM {$this->table} ORDER BY label ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getByCode($code) {
        $sql = "SELECT * FROM {$this->table} WHERE code = :code";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':code', $code);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create() {
        $data = [
            'code' => $this->code,
            'icon' => $this->icon,
            'label' => $this->label
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
            'icon' => $this->icon,
            'label' => $this->label
        ];

         $result = buildUpdateQuery($this->table, 'id', $this->id, $data);
        if (!$result) return false;

        $stmt = $this->conn->prepare($result['sql']);

        foreach ($result['params'] as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        return $stmt->execute();
    }

    public function deleteByCode() {
        $sql = "DELETE FROM {$this->table} WHERE code = :code";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':code', $this->code);

        return $stmt->execute();
    }
}
