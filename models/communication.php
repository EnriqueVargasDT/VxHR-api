<?php
require_once '../config/config.php';
require_once '../models/userFiles.php';

class Communication {
    private $dbConnection;
    
    public function __construct() {
        $this->dbConnection = dbConnection();
    }

    public function dashboard() {
        try {
            $data = ['posts' => [], 'events' => [], 'c4' => [], 'news' => []];

            // Comunicados, Eventos y Espacio C4.
            $sqlPosts = "
                SELECT *
                FROM [communication].[posts]
                WHERE CAST(publish_date AS DATE) <= CAST(GETDATE() AS DATE)
                AND [status] = 1
                ORDER BY publish_date DESC
            ";
            $resultPosts = $this->dbConnection->query($sqlPosts)->fetchAll(PDO::FETCH_ASSOC);
            if (count($resultPosts) > 0) {
                foreach ($resultPosts as $post) {
                    if (intval($post['fk_post_type_id']) === 1 && count($data['posts']) < 2) {
                        $data['posts'][] = $post;
                    }
                    else if (intval($post['fk_post_type_id']) === 2 && count($data['events']) < 2) {
                        $data['events'][] = $post;
                    }
                    else if (intval($post['fk_post_type_id']) === 3 && count($data['c4']) < 2) {
                        $data['c4'][] = $post;
                    }
                }
            }

            // Aniversarios Laborales.
            $sqlWorkAnniversaries = sprintf("
                SELECT 
                    CONCAT('Aniversarios Laborales Semana ', DATEPART(ISO_WEEK, u.date_of_hire)) AS 'title',
                    CONCAT(u.first_name, ' ' , u.last_name_1, ' ', u.last_name_2) AS user_full_name,
                    jp.job_position,
                    jpo.job_position_office_short,
                    DATEDIFF(YEAR, u.date_of_hire, GETDATE()) AS years_worked,
                    uf.[file],
                    DATEPART(YEAR, u.date_of_hire) AS hire_year,
                    DATEPART(ISO_WEEK, u.date_of_hire) AS hire_week
                FROM [user].[users] u
                INNER JOIN [job_position].[positions] jp ON u.fk_job_position_id = jp.pk_job_position_id
                INNER JOIN [job_position].[office] jpo ON jp.fk_job_position_office_id = jpo.pk_job_position_office_id
                INNER JOIN [user].[files] uf ON u.pk_user_id = uf.fk_user_id AND uf.type_file = %s
                WHERE DATEPART(YEAR, u.date_of_hire) <> DATEPART(YEAR, GETDATE()) 
                AND DATEPART(ISO_WEEK, u.date_of_hire) <= DATEPART(ISO_WEEK, GETDATE())
                ORDER BY DATEPART(ISO_WEEK, u.date_of_hire) DESC, years_worked DESC;
            ", UserFiles::TYPE_PROFILE_PICTURE);
            $resultWorkAnniversaries = $this->dbConnection->query($sqlWorkAnniversaries)->fetchAll(PDO::FETCH_ASSOC);
            if (count($resultWorkAnniversaries) > 0) {
                foreach ($resultWorkAnniversaries as $workAnniversaries) {
                    $data['news'][] = $workAnniversaries;
                }
            }

            // Cumpleaños.
            $sqlBirthdays = sprintf("
                SELECT
                    cb.pk_birthday_id,
                    FORMAT(CAST(cb.birthday_date AS DATETIME), 'dd ''de'' MMMM', 'es-ES') AS birthday_date,
                    CONCAT('Cumpleaños ', CONCAT(u.first_name, ' ' , u.last_name_1, ' ', u.last_name_2)) AS title,
                    CONCAT(u.first_name, ' ' , u.last_name_1, ' ', u.last_name_2) AS user_full_name,
                    uf.[file]
                FROM [communication].[birthdays] cb
                INNER JOIN [user].[users] u ON cb.fk_user_id = u.pk_user_id
                INNER JOIN [user].[files] uf ON u.pk_user_id = uf.fk_user_id AND uf.type_file = %s
                WHERE DATEPART(MONTH, cb.birthday_date) = DATEPART(MONTH, GETDATE())
                AND DATEPART(DAY, cb.birthday_date) <= DATEPART(DAY, GETDATE())
                ORDER BY DATEPART(DAY, cb.birthday_date) DESC;
            ", UserFiles::TYPE_PROFILE_PICTURE);
            $resultBirthdays = $this->dbConnection->query($sqlBirthdays)->fetchAll(PDO::FETCH_ASSOC);
            if (count($resultBirthdays) > 0) {
                foreach ($resultBirthdays as $birthday) {
                    $birthday['count_reactions'] = $this->getReactionsByBirthday($birthday['pk_birthday_id']);
                    $birthday['comments'] = $this->getCommentsByBirthday($birthday['pk_birthday_id']);
                    $data['birthdays'][] = $birthday;
                }
            }

            sendJsonResponse(200, ['ok' => true, 'data' => $data]);
        }
        catch(Exception $error) {
            handleExceptionError($error);
        }

        exit();
    }

    public function getAllPosts() {
        try {
            $sql = "SELECT p.*, pt.post_type, CONCAT(u.first_name, ' ', u.last_name_1, ' ', u.last_name_2) AS created_by_full_name
                    FROM [communication].[posts] p
                    INNER JOIN [communication].[post_types] pt ON p.fk_post_type_id = pt.pk_post_type_id
                    LEFT JOIN [user].[users] u ON p.created_by = u.pk_user_id
                    ORDER BY p.created_at DESC";
            $stmt = $this->dbConnection->query($sql);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sendJsonResponse(200, ['ok' => true, 'data' => $result]);
        }
        catch(Exception $error) {
            handleExceptionError($error);
        }

        exit();
    }

    public function getPostById($pk_post_id) {
        try {
            $sql = 'SELECT TOP 1 * FROM [communication].[posts] WHERE pk_post_id = :pk_post_id';
            $stmt = $this->dbConnection->prepare($sql);
            $stmt->bindParam(':pk_post_id', $pk_post_id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            sendJsonResponse(200, ['ok' => true, 'data' => $result]);
        }
        catch(Exception $error) {
            handleExceptionError($error);
        }

        exit();
    }

    public function getAllPostTypes() {
        try {
            $sql = 'SELECT * FROM [communication].[post_types]';
            $stmt = $this->dbConnection->query($sql);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sendJsonResponse(200, ['ok' => true, 'data' => $result]);
        }
        catch(Exception $error) {
            handleExceptionError($error);
        }

        exit();
    }

    public function savePost($data) {
        try {
            $this->dbConnection->beginTransaction();
            $fields = '[' . implode('],[', array_keys($data)) . ']';
            $params = ':' . implode(',:', array_keys($data));
            $sql = sprintf('INSERT INTO [communication].[posts] (%s, [created_by]) VALUES (%s, :created_by)', $fields, $params);
            $stmt = $this->dbConnection->prepare($sql);
            $pk_user_id = isset($_SESSION['pk_user_id']) ? $_SESSION['pk_user_id'] : 0;
            $stmt->bindParam(':fk_post_type_id', $data['fk_post_type_id'], PDO::PARAM_INT);
            $stmt->bindParam(':publish_date', $data['publish_date'], PDO::PARAM_STR);
            $stmt->bindParam(':title', $data['title'], PDO::PARAM_STR);
            $stmt->bindParam(':content', $data['content'], PDO::PARAM_STR);
            $stmt->bindParam(':created_by', $pk_user_id, PDO::PARAM_INT);
            if (!$stmt->execute() || $stmt->rowCount() === 0) {
                throw new Exception('Error: No se pudo crear la publicación.');
            }
            
            $lastInsertId = $this->dbConnection->lastInsertId();
            $this->dbConnection->commit();
            sendJsonResponse(200, ['ok' => true, 'last_insert_id' => $lastInsertId, 'message' => 'Publicación creada exitosamente.']);
        }
        catch(Exception $error) {
            if ($this->dbConnection->inTransaction()) {
                $this->dbConnection->rollBack();
            }
            handleExceptionError($error);
        }

        exit();
    }

    public function updatePost($pk_post_id, $data) {
        try {
            $this->dbConnection->beginTransaction();
            $sql = 'UPDATE [communication].[posts] SET [fk_post_type_id] = :fk_post_type_id, [publish_date] = :publish_date, [title] = :title, [content] = :content, [updated_by] = :updated_by
                    WHERE pk_post_id = :pk_post_id';
            $stmt = $this->dbConnection->prepare($sql);
            $pk_user_id = isset($_SESSION['pk_user_id']) ? $_SESSION['pk_user_id'] : 0;
            $stmt->bindParam(':fk_post_type_id', $data['fk_post_type_id'], PDO::PARAM_INT);
            $stmt->bindParam(':publish_date', $data['publish_date'], PDO::PARAM_STR);
            $stmt->bindParam(':title', $data['title'], PDO::PARAM_STR);
            $stmt->bindParam(':content', $data['content'], PDO::PARAM_STR);
            $stmt->bindParam(':updated_by', $pk_user_id, PDO::PARAM_INT);
            $stmt->bindParam(':pk_post_id', $pk_post_id, PDO::PARAM_INT);
            if (!$stmt->execute() || $stmt->rowCount() === 0) {
                throw new Exception('Error: No se pudo actualizar la publicación.');
            }
            
            $this->dbConnection->commit();
            sendJsonResponse(200, ['ok' => true, 'message' => 'Publicación actualizada exitosamente.']);
        }
        catch(Exception $error) {
            if ($this->dbConnection->inTransaction()) {
                $this->dbConnection->rollBack();
            }
            handleExceptionError($error);
        }

        exit();
    }

    public function updatePostStatus($data) {
        try {
            $this->dbConnection->beginTransaction();
            $sql = 'UPDATE [communication].[posts] SET [status] = :status WHERE [pk_post_id] = :pk_post_id;';
            $stmt = $this->dbConnection->prepare($sql);
            $stmt->bindParam(':status', $data['status'], PDO::PARAM_INT);
            $stmt->bindParam(':pk_post_id', $data['pk_post_id'], PDO::PARAM_INT);
            if (!$stmt->execute() || $stmt->rowCount() === 0) {
                throw new Exception('Error: No se realizaron cambios en el estado de la publicación.');
            }
            
            $this->dbConnection->commit();
            sendJsonResponse(200, ['ok' => true, 'message' => 'El estado de la publicación fue actualizado exitosamente.']);
        }
        catch (Exception $error) {
            if ($this->dbConnection->inTransaction()) {
                $this->dbConnection->rollBack();
            }
            handleExceptionError($error);
        }
    
        exit();
    }

    public function uploadPostFile($data) {
        try {
            if (!isset($data['pk_post_id']) || $_FILES['post_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Error: No se pudo cargar la imagen de la publicación.');
            }
        
            $fileName = $_FILES['post_file']['name'];
            $fileExt = pathinfo($fileName, PATHINFO_EXTENSION);
            $fileSize = $_FILES['post_file']['size'];

            // 5MB
            if ($fileSize > 5000000) {
                throw new Exception('Error: El archivo cargado es demasiado grande');
            }

            $imageType = mime_content_type($_FILES['post_file']['tmp_name']);
            if (!in_array($imageType, ['image/jpeg', 'image/png', 'image/gif'])) {
                throw new Exception('Error: El archivo cargado no es una imagen válida.');
            }
        
            $file = $_FILES['post_file']['tmp_name'];
            $content = file_get_contents($file);
            $file = base64_encode($content);

            $this->dbConnection->beginTransaction();
            $sql = 'UPDATE [communication].[posts] SET [file] = :file, file_name = :file_name, file_extension = :file_extension, file_size = :file_size
                    WHERE pk_post_id = :pk_post_id';
            $stmt = $this->dbConnection->prepare($sql);
            $stmt->bindParam(':file', $file, PDO::PARAM_STR);
            $stmt->bindParam(':file_name', $fileName, PDO::PARAM_STR);
            $stmt->bindParam(':file_extension', $fileExt, PDO::PARAM_STR);
            $stmt->bindParam(':file_size', $fileSize, PDO::PARAM_INT);
            $stmt->bindParam(':pk_post_id', $data['pk_post_id'], PDO::PARAM_INT);
            if (!$stmt->execute()) {
                throw new Exception('Error: No se pudo agregar la imagen a la publicación.');
            }
            
            $this->dbConnection->commit();
            sendJsonResponse(200, ['ok' => true, 'message' => 'La imagen de la publicación fue agregada exitosamente']);
        }
        catch (Exception $error) {
            if ($this->dbConnection->inTransaction()) {
                $this->dbConnection->rollBack();
            }
            handleExceptionError($error);
        }

        exit();
    }

    private function getReactionsByBirthday($birthday_id) {
        try {
            $sql = 'SELECT COUNT(*) AS count FROM [communication].[birthday_reactions] WHERE fk_birthday_id = :fk_birthday_id';
            $stmt = $this->dbConnection->prepare($sql);
            $stmt->bindParam(':fk_birthday_id', $birthday_id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (isset($result) && isset($result['count'])) ? $result['count'] : 0;
        }
        catch(Exception $error) {
            return ['error' => true, 'message' => 'Error al obtener las reacciones del cumpleaños.'];
        }
    }

    public function addBirthdayReaction($data) {
        try {
            $this->dbConnection->beginTransaction();
            $sql1 = 'DELETE FROM [communication].[birthday_reactions] WHERE fk_birthday_id = :fk_birthday_id AND fk_user_id = :fk_user_id';
            $stmt1 = $this->dbConnection->prepare($sql1);
            $stmt1->bindParam(':fk_birthday_id', $data['fk_birthday_id'], PDO::PARAM_INT);
            $stmt1->bindParam(':fk_user_id', $data['fk_user_id'], PDO::PARAM_INT);
            if (!$stmt1->execute()) {
                throw new Exception('Error: No se pudo ejecutar correctamente la limpieza de reacción del cumpleaños.');
            }

            $sql2 = 'INSERT INTO [communication].[birthday_reactions](fk_birthday_id, fk_user_id, reaction_type) VALUES(:fk_birthday_id, :fk_user_id, :reaction_type)';
            $stmt2 = $this->dbConnection->prepare($sql2);
            $stmt2->bindParam(':fk_birthday_id', $data['fk_birthday_id'], PDO::PARAM_INT);
            $stmt2->bindParam(':fk_user_id', $data['fk_user_id'], PDO::PARAM_INT);
            $stmt2->bindParam(':reaction_type', $data['reaction_type'], PDO::PARAM_STR);
            if (!$stmt2->execute() || $stmt2->rowCount() === 0) {
                throw new Exception('Error: No se pudo registrar la reacción del cumpleaños.');
            }

            $this->dbConnection->commit();
            $totalReactions = $this->getReactionsByBirthday($data['fk_birthday_id']);
            sendJsonResponse(200, ['ok' => true, 'total_reactions' => $totalReactions, 'message' => 'La reacción del cumpleaños fue registrada exitosamente.']);
        }
        catch(Exception $error) {
            if ($this->dbConnection->inTransaction()) {
                $this->dbConnection->rollBack();
            }
            handleExceptionError($error);
        }

        exit();
    }

    public function removeBirthdayReaction($data) {
        try {
            $this->dbConnection->beginTransaction();
            $sql = 'DELETE FROM [communication].[birthday_reactions] WHERE fk_birthday_id = :fk_birthday_id AND fk_user_id = :fk_user_id';
            $stmt = $this->dbConnection->prepare($sql);
            $stmt->bindParam(':fk_birthday_id', $data['fk_birthday_id'], PDO::PARAM_INT);
            $stmt->bindParam(':fk_user_id', $data['fk_user_id'], PDO::PARAM_INT);
            if (!$stmt->execute()) {
                throw new Exception('Error: No se pudo remover la reacción del cumpleaños.');
            }

            $this->dbConnection->commit();
            $totalReactions = $this->getReactionsByBirthday($data['fk_birthday_id']);
            sendJsonResponse(200, ['ok' => true, 'total_reactions' => $totalReactions, 'message' => 'La reacción del cumpleaños fue removida exitosamente.']);
        }
        catch(Exception $error) {
            if ($this->dbConnection->inTransaction()) {
                $this->dbConnection->rollBack();
            }
            handleExceptionError($error);
        }

        exit();
    }

    public function addBirthdayComment($data) {
        try {
            $this->dbConnection->beginTransaction();
            $sql = 'INSERT INTO [communication].[birthday_comments](fk_birthday_id, fk_user_id, comment) VALUES(:fk_birthday_id, :fk_user_id, :comment)';
            $stmt = $this->dbConnection->prepare($sql);
            $stmt->bindParam(':fk_birthday_id', $data['fk_birthday_id'], PDO::PARAM_INT);
            $stmt->bindParam(':fk_user_id', $data['fk_user_id'], PDO::PARAM_INT);
            $stmt->bindParam(':comment', $data['comment'], PDO::PARAM_STR);
            if (!$stmt->execute() || $stmt->rowCount() === 0) {
                throw new Exception('Error: No se pudo registrar el comentario del cumpleaños.');
            }

            $this->dbConnection->commit();
            sendJsonResponse(200, ['ok' => true, 'message' => 'El comentario del cumpleaños fue registrado exitosamente.']);
        }
        catch(Exception $error) {
            if ($this->dbConnection->inTransaction()) {
                $this->dbConnection->rollBack();
            }
            handleExceptionError($error);
        }

        exit();
    }

    private function getCommentsByBirthday($birthday_id) {
        try {
            $sql = "SELECT CONCAT(u.first_name, ' ', u.last_name_1, ' ', u.last_name_2) AS user_full_name, bc.comment
                    FROM [communication].[birthday_comments] bc
                    INNER JOIN [user].[users] u ON bc.fk_user_id = u.pk_user_id
                    WHERE bc.fk_birthday_id = :fk_birthday_id";
            $stmt = $this->dbConnection->prepare($sql);
            $stmt->bindParam(':fk_birthday_id', $birthday_id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $result;
        }
        catch(Exception $error) {
            return ['error' => true, 'message' => 'Error al obtener los comentarios del cumpleaños.'];
        }
    }
}
?>