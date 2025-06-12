<?php

require_once '../config/config.php';

class PostTarget {
    private $conn;
    private $table = "post_targets";

    public $id;
    public $post_id;
    public $target_user_id;

    public function __construct() {
        $this->conn = dbConnection();
    }

    public function assign() {
        $data = [
            'post_id' => $this->post_id,
            'target_user_id' => $this->target_user_id
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
            'target_user_id' => $this->target_user_id,
            'post_id' => $this->post_id
        ];

        $result = buildUpdateQuery($this->table, 'id', $this->id, $data);
        if (!$result) return false;

        $stmt = $this->conn->prepare($result['sql']);

        foreach ($result['params'] as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        return $stmt->execute();
    }

    public function getByPost($post_id) {
        $sql = "SELECT
                u.pk_user_id AS id,
                CONCAT(u.first_name, ' ', u.last_name_1) AS display_name,
                jpp.job_position AS position,
                uf.[file] AS profile_picture,
                DATEDIFF(YEAR, u.date_of_hire, GETDATE()) AS years
            FROM post_targets pt
            LEFT JOIN [user].users u ON u.pk_user_id = pt.target_user_id
            LEFT JOIN [job_position].[positions] jpp ON u.fk_job_position_id = jpp.pk_job_position_id
            LEFT JOIN [user].[files] uf ON u.pk_user_id = uf.fk_user_id AND uf.type_file = 1
            WHERE pt.post_id = :post_id
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':post_id', $post_id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    public function delete() {
        $sql = "DELETE FROM {$this->table}
                WHERE post_id = :post_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':post_id', $this->post_id);

        return $stmt->execute();
    }
}
