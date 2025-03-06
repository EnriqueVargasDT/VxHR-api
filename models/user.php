<?php
require_once '../config/config.php';
require_once 'jobPosition.php';
require_once 'email.php';

class User {
    private $dbConnection;
    private $email;

    public function __construct() {
        $this->dbConnection = dbConnection();
        $this->email = new Email();
    }

    public function getAll() {
        try {
            $sql = "
                SELECT u.*,
                CONCAT(u.first_name, ' ' , u.last_name_1, ' ', u.last_name_2) AS full_name,
                ums.marital_status,
                urs.relationship AS emergency_relationship,
                jpp.job_position,
                jpa.job_position_area,
                jpd.job_position_department,
                jpo.job_position_office,
                ua.username,
                CONCAT('data:image/', uf.file_extension, ';base64,', uf.[file]) AS profile_picture,
                ua.last_access_at
                FROM [user].[users] u
                LEFT JOIN [user].[users_auth] ua ON u.pk_user_id = ua.fk_user_id
                LEFT JOIN [user].[marital_status] ums ON u.fk_marital_status_id = ums.pk_marital_status_id
                LEFT JOIN [user].[relationships] urs ON u.fk_emergency_relationship_id = urs.pk_relationship_id
                LEFT JOIN [user].[user_files] uf ON u.pk_user_id = uf.fk_user_id AND uf.is_profile_picture = 1
                LEFT JOIN [job_position].[positions] jpp ON u.fk_job_position_id = jpp.pk_job_position_id
                LEFT JOIN [job_position].[area] jpa ON jpp.fk_job_position_area_id = jpa.pk_job_position_area_id
                LEFT JOIN [job_position].[department] jpd ON jpp.fk_job_position_department_id = jpd.pk_job_position_department_id
                LEFT JOIN [job_position].[office] jpo ON jpp.fk_job_position_office_id = jpo.pk_job_position_office_id
            ";
            $stmt = $this->dbConnection->query($sql);
            $users = array();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $users[] = $row;
            }

            sendJsonResponse(200, array('ok' => true, 'users' => $users));
        }
        catch(Exception $error) {
            handleExceptionError($error);
        }

        exit();
    }

    public function getById($pk_user_id) {
        try {
            $sql = "
                SELECT u.*,
                CONCAT(u.first_name, ' ' , u.last_name_1, ' ', u.last_name_2) AS full_name,
                ums.marital_status,
                urs.relationship AS emergency_relationship,
                jpp.job_position,
                jpa.job_position_area,
                jpd.job_position_department,
                jpo.job_position_office,
                ua.fk_role_id AS role_id
                FROM [user].[users] u
                LEFT JOIN [user].[users_auth] ua ON u.pk_user_id = ua.fk_user_id
                LEFT JOIN [user].[marital_status] ums ON u.fk_marital_status_id = ums.pk_marital_status_id
                LEFT JOIN [user].[relationships] urs ON u.fk_emergency_relationship_id = urs.pk_relationship_id
                LEFT JOIN [job_position].[positions] jpp ON u.fk_job_position_id = jpp.pk_job_position_id
                LEFT JOIN [job_position].[area] jpa ON jpp.fk_job_position_area_id = jpa.pk_job_position_area_id
                LEFT JOIN [job_position].[department] jpd ON jpp.fk_job_position_department_id = jpd.pk_job_position_department_id
                LEFT JOIN [job_position].[office] jpo ON jpp.fk_job_position_office_id = jpo.pk_job_position_office_id
                WHERE u.pk_user_id = %s
            ";
            $sql = sprintf($sql, $pk_user_id);
            $stmt = $this->dbConnection->query($sql);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                sendJsonResponse(200, array('ok' => true, 'user' => $user));
            }
            else {
                handleError(500, 'No se encontró al usuario en la plataforma');
            }
        }
        catch(Exception $error) {
            handleExceptionError($error);
        }

        exit();
    }

    private function validateExistence($field, $value) {
        $sql = "SELECT COUNT(*) FROM [user].[users] WHERE $field = :value";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':value', $value, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    }

    public function save($data) {
        try {
            $fieldsToValidate = [
                'curp' => 'CURP',
                'rfc' => 'RFC',
                'imss' => 'NSS',
                'institutional_email' => 'Correo Institucional'
            ];
            if (isset($data['infonavit'])) {
                $fieldsToValidate['infonavit'] = 'Número de Crédito Infonavit';
            }
    
            foreach ($fieldsToValidate as $field => $message) {
                if ($this->validateExistence($field, $data[$field])) {
                    handleError(500, ['type' => $field, 'message' => "Error: El $message ya existe en la base de datos."]);
                    return;
                }
            }

            $columns = $this->getColumns();

            // Excluir columnas de valores por defecto
            unset($columns['pk_user_id'], $columns['date_of_hire'], $columns['is_active']);

            $insert = sprintf('INSERT INTO [user].[users](%s)', implode(',', array_keys($columns)));
            $values = array();
            foreach ($columns as $column => $pdoParam) {
                if (array_key_exists($column, $data)) {
                    $values[":$column"] = $pdoParam;
                }
            }

            $sql1 = sprintf("$insert VALUES(%s)", implode(',', array_merge(array_keys($values), array(':created_by',))));
            $this->dbConnection->beginTransaction();
            $stmt1 = $this->dbConnection->prepare($sql1);
            foreach ($values as $placeholder => $pdoParam) {
                $columnName = ltrim($placeholder, ':');
                $columnValue = trim($data[$columnName]);
                $stmt1->bindValue($placeholder, $columnValue, $pdoParam);
            }
            $stmt1->bindValue(':created_by', $_SESSION['pk_user_id'], PDO::PARAM_INT);
            if (!$stmt1->execute() || $stmt1->rowCount() === 0) {
                throw new Exception('Error: No se pudo crear el usuario.');
            }
            $this->dbConnection->commit();
            
            $newUserId = $this->dbConnection->lastInsertId();

            if (isset($data['fk_job_position_id'])) {
                // Antes de todo, validar si la nueva vacante ya esta ocupada.
                $sql2 = "SELECT COUNT(*) FROM [user].[users] WHERE [fk_job_position_id] = :fk_job_position_id";
                $stmt2 = $this->dbConnection->prepare($sql2);
                $stmt2->bindParam(':fk_job_position_id', $data['fk_job_position_id'], PDO::PARAM_INT);
                $stmt2->execute();
                $exists = $stmt2->fetchColumn();
                if ($exists > 0) {
                    throw new Exception('Error: La vacante ya está ocupada por otro usuario.');
                } 

                $sql3 = 'UPDATE [job_position].[positions] SET [fk_job_position_status_id] = :job_position_status_id, [fk_job_position_admin_status_id] = :job_position_admin_status_id WHERE pk_job_position_id = :pk_job_position_id';
                $this->dbConnection->beginTransaction();
                $stmt3 = $this->dbConnection->prepare($sql3);
                $fkJobPositionId = $data['fk_job_position_id'];
                $JOB_POSITION_STATUS_BUSY = JobPosition::STATUS_BUSY;
                $JOB_POSITION_ADMIN_STATUS_BUSY = JobPosition::ADMIN_STATUS_BUSY;
                $stmt3->bindParam(':pk_job_position_id', $fkJobPositionId, PDO::PARAM_INT);
                $stmt3->bindParam(':job_position_status_id', $JOB_POSITION_STATUS_BUSY, PDO::PARAM_INT);
                $stmt3->bindParam(':job_position_admin_status_id', $JOB_POSITION_ADMIN_STATUS_BUSY, PDO::PARAM_INT);
                if (!$stmt3->execute() || $stmt3->rowCount() === 0) {
                    throw new Exception('Error: No se realizaron cambios en la vacante asignada.');
                }
                $this->dbConnection->commit();
            }

            $sql4 = 'INSERT INTO [user].[users_auth] ([username], [password], [fk_user_id], [fk_role_id]) VALUES(:username, :password, :user_id, :role_id);';
            $this->dbConnection->beginTransaction();
            $stmt4 = $this->dbConnection->prepare($sql4);
            $password = password_hash($data['password'], PASSWORD_BCRYPT);
            $stmt4->bindParam(':username', $data['institutional_email'], PDO::PARAM_STR);
            $stmt4->bindParam(':password', $password, PDO::PARAM_STR);
            $stmt4->bindParam(':user_id', $newUserId, PDO::PARAM_INT);
            $stmt4->bindParam(':role_id', $data['role_id'], PDO::PARAM_INT);
            if (!$stmt4->execute() || $stmt4->rowCount() === 0) {
                throw new Exception('Error: No se pudo crear la cuenta de acceso a plataforma.');
            }
            $this->dbConnection->commit();
            
            $send = $this->sendWelcomeEmail($data);
            if ($send) {
                sendJsonResponse(200, array('ok' => true, 'new_user_id' => $newUserId, 'message' => 'Usuario creado correctamente.'));
            }
            else {
                handleError(500, 'No se pudo enviar el correo de bienvenida a plataforma.');
            }
        }
        catch(Exception $error) {
            if ($this->dbConnection->inTransaction()) {
                $this->dbConnection->rollBack();
            }
            handleExceptionError($error);
        }

        exit();
    }

    public function update($id, $data) {
        try {
            $SET = array();
            $columns = $this->getColumns();
            foreach ($columns as $field => $pdoParam) {
                if (isset($data[$field])) {
                    $SET[] = "[$field] = :$field";
                }
            }
            
            // Validar si sigue teniendo el mismo puesto, o se ha cambiado.
            if (isset($data['fk_job_position_id'])) {
                $sql1 = "SELECT [fk_job_position_id] FROM [user].[users] WHERE [pk_user_id] = :pk_user_id";
                $stmt1 = $this->dbConnection->prepare($sql1);
                $stmt1->bindParam(':pk_user_id', $id, PDO::PARAM_INT);
                $stmt1->execute();
                $result = $stmt1->fetch(PDO::FETCH_ASSOC);
                if ($result['fk_job_position_id'] !== $data['fk_job_position_id']) {
                    // Antes de todo, validar si la nueva vacante ya esta ocupada.
                    $sql2 = "SELECT COUNT(*) FROM [user].[users] WHERE [fk_job_position_id] = :fk_job_position_id";
                    $stmt2 = $this->dbConnection->prepare($sql2);
                    $stmt2->bindParam(':fk_job_position_id', $data['fk_job_position_id'], PDO::PARAM_INT);
                    $stmt2->execute();
                    $exists = $stmt2->fetchColumn();
                    if ($exists > 0) {
                        throw new Exception('Error: La vacante ya está ocupada por otro usuario.');
                    } 

                    // Liberar la vacante anterior
                    $sql3 = 'UPDATE [job_position].[positions] SET [fk_job_position_status_id] = :job_position_status_id, [fk_job_position_admin_status_id] = :job_position_admin_status_id WHERE pk_job_position_id = :pk_job_position_id';
                    $this->dbConnection->beginTransaction();
                    $stmt3 = $this->dbConnection->prepare($sql3);
                    $fkJobPositionIdOld = $result['fk_job_position_id'];
                    $JOB_POSITION_STATUS_AVAILABLE = JobPosition::STATUS_AVAILABLE;
                    $JOB_POSITION_ADMIN_STATUS_CREATED = JobPosition::ADMIN_STATUS_CREATED;
                    $stmt3->bindParam(':pk_job_position_id', $fkJobPositionIdOld, PDO::PARAM_INT);
                    $stmt3->bindParam(':job_position_status_id', $JOB_POSITION_STATUS_AVAILABLE, PDO::PARAM_INT);
                    $stmt3->bindParam(':job_position_admin_status_id', $JOB_POSITION_ADMIN_STATUS_CREATED, PDO::PARAM_INT);
                    if (!$stmt3->execute()) {
                        throw new Exception('Error: No se pudo actualizar el estatus de la vacante actual del usuario.');
                    }
                    $this->dbConnection->commit();

                    // Ocupar la nueva vacante.
                    $sql4 = 'UPDATE [job_position].[positions] SET [fk_job_position_status_id] = :job_position_status_id, [fk_job_position_admin_status_id] = :job_position_admin_status_id WHERE pk_job_position_id = :pk_job_position_id';
                    $this->dbConnection->beginTransaction();
                    $stmt4 = $this->dbConnection->prepare($sql4);
                    $fkJobPositionIdNew = $data['fk_job_position_id'];
                    $JOB_POSITION_STATUS_BUSY = JobPosition::STATUS_BUSY;
                    $JOB_POSITION_ADMIN_STATUS_BUSY = JobPosition::ADMIN_STATUS_BUSY;
                    $stmt4->bindParam(':pk_job_position_id', $fkJobPositionIdNew, PDO::PARAM_INT);
                    $stmt4->bindParam(':job_position_status_id', $JOB_POSITION_STATUS_BUSY, PDO::PARAM_INT);
                    $stmt4->bindParam(':job_position_admin_status_id', $JOB_POSITION_ADMIN_STATUS_BUSY, PDO::PARAM_INT);
                    if (!$stmt4->execute()) {
                        throw new Exception('Error: No se pudo actualizar el estatus de la nueva vacante para el usuario.');
                    }
                    $this->dbConnection->commit();
                }
            }

            // Actualizar el usuario
            $sql5 = sprintf('UPDATE [user].[users] SET %s WHERE [pk_user_id] = :pk_user_id;', implode(',', $SET));
            $this->dbConnection->beginTransaction();
            $stmt5 = $this->dbConnection->prepare($sql5);
            foreach ($columns as $field => $pdoParam) {
                if (isset($data[$field])) {
                    $columnValue = $data[$field];
                    $stmt5->bindValue(":$field", $columnValue, $pdoParam);
                }
            }
            $stmt5->bindValue(':pk_user_id', $id, PDO::PARAM_INT);
            if (!$stmt5->execute()) {
                throw new Exception('Error: No se realizaron cambios en el usuario.');
            }
            $this->dbConnection->commit();

            // Actualizar los datos de la cuenta.
            $sql6 = 'UPDATE [user].[users_auth] SET [username] = :username, [fk_role_id] = :role_id WHERE fk_user_id = :fk_user_id;';
            $this->dbConnection->beginTransaction();
            $stmt6 = $this->dbConnection->prepare($sql6);
            $stmt6->bindParam(':username', $data['institutional_email'], PDO::PARAM_STR);
            $stmt6->bindParam(':role_id', $data['role_id'], PDO::PARAM_INT);
            $stmt6->bindParam(':fk_user_id', $id, PDO::PARAM_INT);
            if (!$stmt6->execute()) {
                throw new Exception('Error: No se realizaron cambios en el cuenta del usuario.');
            }
            $this->dbConnection->commit();
            
            sendJsonResponse(200, array('ok' => true, 'message' => 'Registro actualizado correctamente.'));
        }
        catch(Exception $error) {
            if ($this->dbConnection->inTransaction()) {
                $this->dbConnection->rollBack();
            }
            handleExceptionError($error);
        }

        exit();
    }

    public function updateStatus($id, $status) {
        try {
            $sql = sprintf('UPDATE [user].[users] SET [is_active] = :is_active WHERE [pk_user_id] = :id;');
            $this->dbConnection->beginTransaction();
            $stmt = $this->dbConnection->prepare($sql);
            $stmt->bindParam(':is_active', $status, PDO::PARAM_INT);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            if (!$stmt->execute() || $stmt->rowCount() === 0) {
                throw new Exception('Error: No se realizaron cambios en el estatus del usuario.');
            }
            $this->dbConnection->commit();
            sendJsonResponse(200, array('ok' => true, 'message' => 'Registro actualizado correctamente.'));
        }
        catch(Exception $error) {
            if ($this->dbConnection->inTransaction()) {
                $this->dbConnection->rollBack();
            }
            handleExceptionError($error);
        }

        exit();
    }

    public function saveProfileImage($userId) {
        try {
            if ($_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Error: No se pudo cargar la imagen.');
            }
    
            $fileName = $_FILES['profile_picture']['name'];
            $fileExt = pathinfo($fileName, PATHINFO_EXTENSION);
            $fileSize = $_FILES['profile_picture']['size'];
    
            if ($fileSize > 5000000) { // 5MB como ejemplo
                throw new Exception('Error: El archivo es demasiado grande.');
            }
    
            $imageType = mime_content_type($_FILES['profile_picture']['tmp_name']);
            if (!in_array($imageType, ['image/jpeg', 'image/png', 'image/gif'])) {
                throw new Exception('Error: El archivo no es una imagen válida.');
            }
    
            $file = $_FILES['profile_picture']['tmp_name'];
            $content = file_get_contents($file);
            $fileBase64 = base64_encode($content);
    
            $sql1 = "SELECT pk_file_id FROM [user].[user_files] WHERE fk_user_id = :fk_user_id AND is_profile_picture = 1";
            $stmt1 = $this->dbConnection->prepare($sql1);
            $stmt1->bindParam(':fk_user_id', $userId, PDO::PARAM_INT);
            $stmt1->execute();
            $exists = $stmt1->fetch(PDO::FETCH_ASSOC);
    
            $this->dbConnection->beginTransaction();
    
            if ($exists) {
                $sql2 = "
                    UPDATE [user].[user_files] SET [file] = :file, file_name = :file_name, file_extension = :file_extension, file_size = :file_size
                    WHERE pk_file_id = :pk_file_id
                ";
                $stmt2 = $this->dbConnection->prepare($sql2);
                $stmt2->bindParam(':file', $imageBase64, PDO::PARAM_STR);
                $stmt2->bindParam(':file_name', $fileName, PDO::PARAM_STR);
                $stmt2->bindParam(':file_extension', $fileExt, PDO::PARAM_STR);
                $stmt2->bindParam(':file_size', $fileSize, PDO::PARAM_INT);
                $stmt2->bindParam(':pk_file_id', $exists['pk_file_id'], PDO::PARAM_INT);
                if (!$stmt2->execute()) {
                    throw new Exception('Error: No se pudo actualizar la fotografía de perfil.');
                }
    
                sendJsonResponse(200, array('ok' => true, 'message' => 'Fotografía de perfil actualizada correctamente.'));
            }
            else {
                $sql3 = "
                        INSERT INTO [user].[user_files] (fk_user_id, [file], file_name, file_extension, file_size, is_profile_picture) 
                        VALUES (:fk_user_id, :file, :file_name, :file_extension, :file_size, :is_profile_picture)
                ";
                $stmt3 = $this->dbConnection->prepare($sql3);
                $is_profile_picture = 1;
                $stmt3->bindParam(':fk_user_id', $userId, PDO::PARAM_INT);
                $stmt3->bindParam(':file', $fileBase64, PDO::PARAM_STR);
                $stmt3->bindParam(':file_name', $fileName, PDO::PARAM_STR);
                $stmt3->bindParam(':file_extension', $fileExt, PDO::PARAM_STR);
                $stmt3->bindParam(':file_size', $fileSize, PDO::PARAM_INT);
                $stmt3->bindParam(':is_profile_picture', $is_profile_picture, PDO::PARAM_INT);
                if (!$stmt3->execute()) {
                    throw new Exception('Error: No se pudo cargar la fotografía de perfil.');
                }

                sendJsonResponse(200, array('ok' => true, 'message' => 'Fotografía cargada correctamente.'));
            }

            $this->dbConnection->commit();
        }
        catch (Exception $error) {
            if ($this->dbConnection->inTransaction()) {
                $this->dbConnection->rollBack();
            }
    
            handleExceptionError($error);
        }
    
        exit();
    }

    private function sendWelcomeEmail($data) {
        $to = $data['institutional_email'];
        $subject = '¡Bienvenido a nuestra plataforma digital! VxHR';
        $template = file_get_contents('../templates/platform_welcome_email.html');
        $template = str_replace('{{username}}', $data['first_name'].' '.$data['last_name_1'].' '.$data['last_name_2'] , $template);
        $template = str_replace('{{email}}', $data['institutional_email'], $template);
        $template = str_replace('{{password}}', $data['password'], $template);
        $template = str_replace('{{login_link}}', $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'].':3000/login', $template);
        $message = $template;
        $send = $this->email->send($to, $subject, $message);
        return $send;
    }

    private function getColumns() {
        $sql = "SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'users' AND TABLE_SCHEMA = 'user' AND COLUMN_NAME NOT IN('created_at', 'updated_at');";
        $stmt = $this->dbConnection->query($sql);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $columns = array();
        foreach ($result as $index => $column) {
            switch ($column['DATA_TYPE']) {
                case 'varchar':
                case 'nvarchar':
                case 'datetime':
                case 'date':
                    $columns[$column['COLUMN_NAME']] = PDO::PARAM_STR;
                    break;
                case 'int':
                case 'tinyint':
                    $columns[$column['COLUMN_NAME']] = PDO::PARAM_INT;
                    break;
                default:
                    $columns[$column['COLUMN_NAME']] = PDO::PARAM_STR;
                    break;
            }
        }

        return $columns;
    }
}
?>