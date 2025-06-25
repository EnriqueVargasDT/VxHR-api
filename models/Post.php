<?php

require_once '../config/config.php';

class Post {
    private $conn;
    private $table = "posts";

    public $id;
    public $author_id;
    public $post_type_id;
    public $title;
    public $content;
    public $created_at;
    public $published_at;
    public $updated_at;
    public $deleted;

    public function __construct() {
        $this->conn = dbConnection();
    }

    public function create() {
        $data = [
            'author_id' => $this->author_id,
            'post_type_id' => $this->post_type_id,
            'title' => $this->title,
            'content' => $this->content
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
            'title' => $this->title,
            'content' => $this->content,
            'post_type_id' => $this->post_type_id,
            'updated_at' => date('Y-m-d H:i:s')
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
        $sql = "UPDATE {$this->table} SET deleted = 1 WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $this->id);
        return $stmt->execute();
    }

    public function getById($id) {
        $sql = "SELECT
                p.*,
                SWITCHOFFSET(CONVERT(datetimeoffset, p.created_at), '-06:00') AS created_at,

                -- Datos del autor
                u.pk_user_id AS created_by__id,
                CONCAT(u.first_name, ' ', u.last_name_1) AS created_by__display_name,
                jpp.job_position AS created_by__position,
                uf.[file] AS created_by__profile_picture,

                -- Tipo de publicación
                pt.code AS type,

                -- Usuario objetivo (cumpleaños, aniversario, etc.)
                tu.pk_user_id AS user__id,
                CONCAT(tu.first_name, ' ', tu.last_name_1) AS user__display_name,
                jpt.job_position AS user__position,
                tuf.[file] AS user__profile_picture,
                DATEDIFF(YEAR, tu.date_of_hire, GETDATE()) AS user__years,

                -- Conteo de comentarios hasta tercer nivel
                (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id AND c.deleted = 0) AS comments_count,

                -- Conteo de reacciones por tipo
                (SELECT COUNT(*) FROM post_reactions r WHERE r.post_id = p.id) AS reactions_count

            FROM posts p

            -- JOIN autor
            LEFT JOIN [user].users u ON u.pk_user_id = p.author_id
            LEFT JOIN [job_position].[positions] jpp ON u.fk_job_position_id = jpp.pk_job_position_id
            LEFT JOIN [user].[files] uf ON uf.fk_user_id = u.pk_user_id AND uf.type_file = 1

            -- JOIN tipo de publicación
            LEFT JOIN post_types pt ON pt.id = p.post_type_id

            -- JOIN usuario objetivo (si aplica)
            LEFT JOIN post_targets tgt ON tgt.post_id = p.id
            LEFT JOIN [user].users tu ON tu.pk_user_id = tgt.target_user_id
            LEFT JOIN [job_position].[positions] jpt ON tu.fk_job_position_id = jpt.pk_job_position_id
            LEFT JOIN [user].[files] tuf ON tuf.fk_user_id = tu.pk_user_id AND tuf.type_file = 1

            WHERE p.id = :id AND p.deleted = 0
            ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getAll() {
        $sql = "SELECT
                p.*,
                SWITCHOFFSET(CONVERT(datetimeoffset, p.created_at), '-06:00') AS created_at,

                -- Datos del autor
                u.pk_user_id AS created_by__id,
                CONCAT(u.first_name, ' ', u.last_name_1) AS created_by__display_name,
                jpp.job_position AS created_by__position,
                uf.[file] AS created_by__profile_picture,

                -- Tipo de publicación
                pt.code AS type,

                -- Usuario objetivo (cumpleaños, aniversario, etc.)
                tu.pk_user_id AS user__id,
                CONCAT(tu.first_name, ' ', tu.last_name_1) AS user__display_name,
                jpt.job_position AS user__position,
                tuf.[file] AS user__profile_picture,
                DATEDIFF(YEAR, tu.date_of_hire, GETDATE()) AS user__years,

                -- Conteo de comentarios hasta tercer nivel
                (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id AND c.deleted = 0) AS comments_count,

                -- Conteo de reacciones por tipo
                (SELECT COUNT(*) FROM post_reactions r WHERE r.post_id = p.id) AS reactions__total,

                -- Link realacionado
                tl.id as attachments_id,
                tl.src as attachments_src,
                tl.title as attachments_title,
                tl.description as attachments_description

            FROM posts p

            -- JOIN autor
            LEFT JOIN [user].users u ON u.pk_user_id = p.author_id
            LEFT JOIN [job_position].[positions] jpp ON u.fk_job_position_id = jpp.pk_job_position_id
            LEFT JOIN [user].[files] uf ON uf.fk_user_id = u.pk_user_id AND uf.type_file = 1

            -- JOIN tipo de publicación
            LEFT JOIN post_types pt ON pt.id = p.post_type_id

            -- JOIN usuario objetivo (si aplica)
            LEFT JOIN post_targets tgt ON tgt.post_id = p.id
            LEFT JOIN [user].users tu ON tu.pk_user_id = tgt.target_user_id
            LEFT JOIN [job_position].[positions] jpt ON tu.fk_job_position_id = jpt.pk_job_position_id
            LEFT JOIN [user].[files] tuf ON tuf.fk_user_id = tu.pk_user_id AND tuf.type_file = 1

            -- JOIN links
            LEFT JOIN post_links pl ON p.id = pl.post_id
            
            WHERE p.deleted = 0

            ORDER BY p.published_at DESC
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
