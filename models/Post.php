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

    public function getById(int $id): ?array {
        $sql = "
            WITH page_posts AS (
                SELECT p.id, p.author_id, p.post_type_id, p.title, p.content,
                    p.created_at, p.published_at, p.updated_at, p.deleted
                FROM posts p
                WHERE p.deleted = 0 AND p.id = :id
            ),
            comments_agg AS (
                SELECT c.post_id, COUNT(*) AS comments_count
                FROM comments c
                WHERE c.deleted = 0 AND c.post_id IN (SELECT id FROM page_posts)
                GROUP BY c.post_id
            ),
            reactions_total AS (
                SELECT pr.post_id, COUNT(*) AS reactions__total
                FROM post_reactions pr
                WHERE pr.post_id IN (SELECT id FROM page_posts)
                GROUP BY pr.post_id
            )
            SELECT
                -- ==== campos base ====
                pp.id,
                pp.author_id,
                pp.post_type_id,
                pt.code AS type,
                pp.title,
                pp.content,

                CONVERT(varchar(50), ((pp.created_at AT TIME ZONE 'UTC') AT TIME ZONE 'Central Standard Time')) AS created_at,
                CONVERT(varchar(23), CAST(((pp.published_at AT TIME ZONE 'UTC') AT TIME ZONE 'Central Standard Time') AS datetime2(3)), 121) AS published_at,
                pp.updated_at,
                pp.deleted,

                -- ==== autor ====
                u.pk_user_id AS created_by__id,
                CONCAT(u.first_name,' ',u.last_name_1,
                    CASE WHEN u.last_name_2 IS NULL OR u.last_name_2='' THEN '' ELSE ' '+u.last_name_2 END)
                    AS created_by__display_name,
                jpp.job_position AS created_by__position,
                uf.profile_picture AS created_by__profile_picture,

                -- ==== target opcional ====
                tgt.target_user_id AS user__id,
                CASE WHEN tu.pk_user_id IS NULL THEN NULL ELSE
                    CONCAT(tu.first_name,' ',tu.last_name_1,
                        CASE WHEN tu.last_name_2 IS NULL OR tu.last_name_2='' THEN '' ELSE ' '+tu.last_name_2 END)
                END AS user__display_name,
                jpt.job_position AS user__position,
                tuf.profile_picture AS user__profile_picture,
                CASE WHEN tu.pk_user_id IS NULL THEN NULL ELSE DATEDIFF(YEAR, tu.date_of_hire, GETDATE()) END AS user__years,

                -- ==== conteos ====
                COALESCE(ca.comments_count, 0) AS comments_count,
                COALESCE(rt.reactions__total, 0) AS reactions__total,

                -- ==== JSON auxiliares ====
                rcount.reactions__count_json,
                ritems.reactions__items_json,
                cjson.comments__items_json,
                ajson.attachments__items_json

            FROM page_posts pp
            LEFT JOIN post_types pt              ON pt.id = pp.post_type_id

            -- autor
            LEFT JOIN [user].users u             ON u.pk_user_id = pp.author_id
            LEFT JOIN [job_position].[positions] jpp ON jpp.pk_job_position_id = u.fk_job_position_id
            OUTER APPLY (
                SELECT TOP(1) f.[file] AS profile_picture
                FROM [user].[files] f
                WHERE f.fk_user_id = u.pk_user_id AND f.type_file = 1
                ORDER BY f.pk_file_id DESC
            ) uf

            -- target
            LEFT JOIN post_targets tgt           ON tgt.post_id = pp.id
            LEFT JOIN [user].users tu            ON tu.pk_user_id = tgt.target_user_id
            LEFT JOIN [job_position].[positions] jpt ON jpt.pk_job_position_id = tu.fk_job_position_id
            OUTER APPLY (
                SELECT TOP(1) f.[file] AS profile_picture
                FROM [user].[files] f
                WHERE f.fk_user_id = tu.pk_user_id AND f.type_file = 1
                ORDER BY f.pk_file_id DESC
            ) tuf

            -- agregados
            LEFT JOIN comments_agg   ca          ON ca.post_id = pp.id
            LEFT JOIN reactions_total rt         ON rt.post_id = pp.id

            -- ====== Reactions: count por código ======
            OUTER APPLY (
                SELECT (
                    SELECT rc.code, COUNT(pr.reaction_id) AS qty
                    FROM reactions_catalog rc
                    LEFT JOIN post_reactions pr
                        ON pr.post_id = pp.id AND pr.reaction_id = rc.id
                    GROUP BY rc.id, rc.code
                    ORDER BY rc.id
                    FOR JSON PATH
                ) AS reactions__count_json
            ) rcount

            -- ====== Reactions: items ======
            OUTER APPLY (
                SELECT (
                    SELECT
                        CONVERT(varchar(23), CAST(((pr.created_at AT TIME ZONE 'UTC') AT TIME ZONE 'Central Standard Time') AS datetime2(3)), 121) AS created_at,
                        rc.code AS type,
                        pr.user_id                  AS [created_by.id],
                        CONCAT(ru.first_name,' ',ru.last_name_1,
                            CASE WHEN ru.last_name_2 IS NULL OR ru.last_name_2='' THEN '' ELSE ' '+ru.last_name_2 END)
                                                AS [created_by.display_name],
                        ruf.profile_picture         AS [created_by.profile_picture]
                    FROM post_reactions pr
                    JOIN reactions_catalog rc ON rc.id = pr.reaction_id
                    LEFT JOIN [user].users ru  ON ru.pk_user_id = pr.user_id
                    OUTER APPLY (
                        SELECT TOP(1) f.[file] AS profile_picture
                        FROM [user].[files] f
                        WHERE f.fk_user_id = ru.pk_user_id AND f.type_file = 1
                        ORDER BY f.pk_file_id DESC
                    ) ruf
                    WHERE pr.post_id = pp.id
                    ORDER BY pr.created_at DESC
                    FOR JSON PATH
                ) AS reactions__items_json
            ) ritems

            -- ====== Comments: items ======
            OUTER APPLY (
                SELECT (
                    SELECT
                        c.id, c.post_id, c.parent_comment_id, c.user_id, c.content,
                        CONVERT(varchar(50), ((c.created_at AT TIME ZONE 'UTC') AT TIME ZONE 'Central Standard Time')) AS created_at,
                        c.deleted,
                        c.user_id                    AS [created_by.id],
                        CONCAT(cu.first_name,' ',cu.last_name_1,
                            CASE WHEN cu.last_name_2 IS NULL OR cu.last_name_2='' THEN '' ELSE ' '+cu.last_name_2 END)
                                                AS [created_by.display_name],
                        jpc.job_position             AS [created_by.position],
                        cuf.profile_picture          AS [created_by.profile_picture]
                    FROM comments c
                    LEFT JOIN [user].users cu      ON cu.pk_user_id = c.user_id
                    LEFT JOIN [job_position].[positions] jpc ON jpc.pk_job_position_id = cu.fk_job_position_id
                    OUTER APPLY (
                        SELECT TOP(1) f.[file] AS profile_picture
                        FROM [user].[files] f
                        WHERE f.fk_user_id = cu.pk_user_id AND f.type_file = 1
                        ORDER BY f.pk_file_id DESC
                    ) cuf
                    WHERE c.deleted = 0 AND c.post_id = pp.id
                    ORDER BY c.created_at DESC
                    FOR JSON PATH
                ) AS comments__items_json
            ) cjson

            -- ====== Attachments ======
            OUTER APPLY (
                SELECT (
                    SELECT pl.id, pl.post_id, pl.src, pl.title, pl.description
                    FROM post_links pl
                    WHERE pl.post_id = pp.id
                    ORDER BY pl.id DESC
                    FOR JSON PATH
                ) AS attachments__items_json
            ) ajson
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
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
                (SELECT COUNT(*) FROM post_reactions r WHERE r.post_id = p.id) AS reactions__total

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
            
            WHERE p.deleted = 0

            ORDER BY p.published_at DESC
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countAll(): int {
        $sql = "SELECT COUNT(*) AS total FROM posts WHERE deleted = 0;";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Obtiene la página de posts con joins “seguros” (sin multiplicar filas) y agregados en lote.
     * Solo devuelve columnas necesarias (ajusta si ocupas más).
     */
    public function getPage(int $limit, int $offset): array {
        $sql = "
            WITH page_posts AS (
                SELECT p.id, p.author_id, p.post_type_id, p.title, p.content,
                    p.created_at, p.published_at, p.updated_at, p.deleted
                FROM posts p
                WHERE p.deleted = 0
                ORDER BY p.published_at DESC
                OFFSET :offset ROWS FETCH NEXT :limit ROWS ONLY
            ),
            comments_agg AS (
                SELECT c.post_id, COUNT(*) AS comments_count
                FROM comments c
                WHERE c.deleted = 0 AND c.post_id IN (SELECT id FROM page_posts)
                GROUP BY c.post_id
            ),
            reactions_total AS (
                SELECT pr.post_id, COUNT(*) AS reactions__total
                FROM post_reactions pr
                WHERE pr.post_id IN (SELECT id FROM page_posts)
                GROUP BY pr.post_id
            )
            SELECT
                -- ==== campos base ====
                pp.id,
                pp.author_id,
                pp.post_type_id,
                pt.code AS type,
                pp.title,
                pp.content,

                -- created_at con offset; published_at sin offset (como tu ejemplo)
                CONVERT(varchar(50), ((pp.created_at AT TIME ZONE 'UTC') AT TIME ZONE 'Central Standard Time')) AS created_at,
                CONVERT(varchar(23), CAST(((pp.published_at AT TIME ZONE 'UTC') AT TIME ZONE 'Central Standard Time') AS datetime2(3)), 121) AS published_at,
                pp.updated_at,
                pp.deleted,

                -- ==== autor ====
                u.pk_user_id AS created_by__id,
                CONCAT(u.first_name,' ',u.last_name_1,
                    CASE WHEN u.last_name_2 IS NULL OR u.last_name_2='' THEN '' ELSE ' '+u.last_name_2 END)
                    AS created_by__display_name,
                jpp.job_position AS created_by__position,
                uf.profile_picture AS created_by__profile_picture,

                -- ==== target opcional ====
                tgt.target_user_id AS user__id,
                CASE WHEN tu.pk_user_id IS NULL THEN NULL ELSE
                    CONCAT(tu.first_name,' ',tu.last_name_1,
                        CASE WHEN tu.last_name_2 IS NULL OR tu.last_name_2='' THEN '' ELSE ' '+tu.last_name_2 END)
                END AS user__display_name,
                jpt.job_position AS user__position,
                tuf.profile_picture AS user__profile_picture,
                CASE WHEN tu.pk_user_id IS NULL THEN NULL ELSE DATEDIFF(YEAR, tu.date_of_hire, GETDATE()) END AS user__years,

                -- ==== conteos ====
                COALESCE(ca.comments_count, 0) AS comments_count,
                COALESCE(rt.reactions__total, 0) AS reactions__total,

                -- ==== JSON auxiliares ====
                rcount.reactions__count_json,
                ritems.reactions__items_json,
                cjson.comments__items_json,
                ajson.attachments__items_json

            FROM page_posts pp
            LEFT JOIN post_types pt              ON pt.id = pp.post_type_id

            -- autor
            LEFT JOIN [user].users u             ON u.pk_user_id = pp.author_id
            LEFT JOIN [job_position].[positions] jpp ON jpp.pk_job_position_id = u.fk_job_position_id
            OUTER APPLY (
                SELECT TOP(1) f.[file] AS profile_picture
                FROM [user].[files] f
                WHERE f.fk_user_id = u.pk_user_id AND f.type_file = 1
                ORDER BY f.pk_file_id DESC
            ) uf

            -- target (tu PK actual permite 1 target por post)
            LEFT JOIN post_targets tgt           ON tgt.post_id = pp.id
            LEFT JOIN [user].users tu            ON tu.pk_user_id = tgt.target_user_id
            LEFT JOIN [job_position].[positions] jpt ON jpt.pk_job_position_id = tu.fk_job_position_id
            OUTER APPLY (
                SELECT TOP(1) f.[file] AS profile_picture
                FROM [user].[files] f
                WHERE f.fk_user_id = tu.pk_user_id AND f.type_file = 1
                ORDER BY f.pk_file_id DESC
            ) tuf

            -- agregados
            LEFT JOIN comments_agg   ca          ON ca.post_id = pp.id
            LEFT JOIN reactions_total rt         ON rt.post_id = pp.id

            -- ====== Reactions: count por código (incluye ceros) ======
            OUTER APPLY (
                SELECT (
                    SELECT rc.code, COUNT(pr.reaction_id) AS qty
                    FROM reactions_catalog rc
                    LEFT JOIN post_reactions pr
                        ON pr.post_id = pp.id AND pr.reaction_id = rc.id
                    GROUP BY rc.id, rc.code         -- <--- rc.id agregado
                    ORDER BY rc.id                  -- <--- ya es válido
                    FOR JSON PATH
                ) AS reactions__count_json
            ) rcount

            -- ====== Reactions: items ======
            OUTER APPLY (
                SELECT (
                    SELECT
                        CONVERT(varchar(23), CAST(((pr.created_at AT TIME ZONE 'UTC') AT TIME ZONE 'Central Standard Time') AS datetime2(3)), 121) AS created_at,
                        rc.code AS type,
                        pr.user_id                  AS [created_by.id],
                        CONCAT(ru.first_name,' ',ru.last_name_1,
                            CASE WHEN ru.last_name_2 IS NULL OR ru.last_name_2='' THEN '' ELSE ' '+ru.last_name_2 END)
                                                AS [created_by.display_name],
                        ruf.profile_picture         AS [created_by.profile_picture]
                    FROM post_reactions pr
                    JOIN reactions_catalog rc ON rc.id = pr.reaction_id
                    LEFT JOIN [user].users ru  ON ru.pk_user_id = pr.user_id
                    OUTER APPLY (
                        SELECT TOP(1) f.[file] AS profile_picture
                        FROM [user].[files] f
                        WHERE f.fk_user_id = ru.pk_user_id AND f.type_file = 1
                        ORDER BY f.pk_file_id DESC
                    ) ruf
                    WHERE pr.post_id = pp.id
                    ORDER BY pr.created_at DESC
                    FOR JSON PATH
                ) AS reactions__items_json
            ) ritems

            -- ====== Comments: items ======
            OUTER APPLY (
                SELECT (
                    SELECT
                        c.id, c.post_id, c.parent_comment_id, c.user_id, c.content,
                        CONVERT(varchar(50), ((c.created_at AT TIME ZONE 'UTC') AT TIME ZONE 'Central Standard Time')) AS created_at,
                        c.deleted,
                        c.user_id                    AS [created_by.id],
                        CONCAT(cu.first_name,' ',cu.last_name_1,
                            CASE WHEN cu.last_name_2 IS NULL OR cu.last_name_2='' THEN '' ELSE ' '+cu.last_name_2 END)
                                                AS [created_by.display_name],
                        jpc.job_position             AS [created_by.position],
                        cuf.profile_picture          AS [created_by.profile_picture]
                    FROM comments c
                    LEFT JOIN [user].users cu      ON cu.pk_user_id = c.user_id
                    LEFT JOIN [job_position].[positions] jpc ON jpc.pk_job_position_id = cu.fk_job_position_id
                    OUTER APPLY (
                        SELECT TOP(1) f.[file] AS profile_picture
                        FROM [user].[files] f
                        WHERE f.fk_user_id = cu.pk_user_id AND f.type_file = 1
                        ORDER BY f.pk_file_id DESC
                    ) cuf
                    WHERE c.deleted = 0 AND c.post_id = pp.id
                    ORDER BY c.created_at DESC
                    FOR JSON PATH
                ) AS comments__items_json
            ) cjson

            -- ====== Attachments (post_links) ======
            OUTER APPLY (
                SELECT (
                    SELECT pl.id, pl.post_id, pl.src, pl.title, pl.description
                    FROM post_links pl
                    WHERE pl.post_id = pp.id
                    ORDER BY pl.id DESC
                    FOR JSON PATH
                ) AS attachments__items_json
            ) ajson

            ORDER BY pp.published_at DESC;
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


}
