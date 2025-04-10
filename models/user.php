<?php
require_once '../config/config.php';
require_once 'userFiles.php';
require_once 'jobPosition.php';

class User {
    private $dbConnection;
    private $userFiles;

    public function __construct() {
        $this->dbConnection = dbConnection();
        $this->userFiles = new UserFiles($this->dbConnection);
    }

    private function validateExistence($field, $value) {
        $sql = "SELECT COUNT(*) FROM [user].[users] WHERE $field = :value";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':value', $value, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    }

    public function getById($pk_user_id) {
        try {
            $sql = "
                SELECT
                    u.*,
                    CONCAT(u.first_name, ' ' , u.last_name_1, ' ', u.last_name_2) AS full_name,
                    ums.marital_status,
                    urs.relationship AS emergency_relationship,
                    jpp.job_position,
                    jpa.job_position_area,
                    jpd.job_position_department,
                    jpo.job_position_office,
                    ua.username,
                    ua.fk_role_id AS role_id,
                    uf.[file] AS profile_picture
                FROM [user].[users] u
                LEFT JOIN [user].[users_auth] ua ON u.pk_user_id = ua.fk_user_id
                LEFT JOIN [user].[marital_status] ums ON u.fk_marital_status_id = ums.pk_marital_status_id
                LEFT JOIN [user].[relationships] urs ON u.fk_emergency_relationship_id = urs.pk_relationship_id
                LEFT JOIN [user].[files] uf ON u.pk_user_id = uf.fk_user_id AND uf.type_file = %s
                LEFT JOIN [job_position].[positions] jpp ON u.fk_job_position_id = jpp.pk_job_position_id
                LEFT JOIN [job_position].[area] jpa ON jpp.fk_job_position_area_id = jpa.pk_job_position_area_id
                LEFT JOIN [job_position].[department] jpd ON jpp.fk_job_position_department_id = jpd.pk_job_position_department_id
                LEFT JOIN [job_position].[office] jpo ON jpp.fk_job_position_office_id = jpo.pk_job_position_office_id
                WHERE u.pk_user_id = %s
            ";
            $sql = sprintf($sql, UserFiles::TYPE_PROFILE_PICTURE, $pk_user_id);
            $user = $this->dbConnection->query($sql)->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                sendJsonResponse(200, ['ok' => true, 'user' => $user]);
            }
            else {
                handleError(500, 'No se encontró al usuario en la base de datos.');
            }
        }
        catch(Exception $error) {
            handleExceptionError($error);
        }

        exit();
    }

    private function getColumns() {
        $sql = "
            SELECT
                COLUMN_NAME,
                DATA_TYPE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_NAME = 'users'
            AND TABLE_SCHEMA = 'user'
            AND COLUMN_NAME NOT IN('created_at', 'updated_at');
        ";
        $stmt = $this->dbConnection->query($sql);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $columns = [];
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

    public function hasSignedPolicies() {
        try {
            $sql = sprintf('SELECT has_signed_policies FROM [user].[users] WHERE pk_user_id = %s;', $_SESSION['pk_user_id']);
            $result = $this->dbConnection->query($sql)->fetch(PDO::FETCH_ASSOC);
            sendJsonResponse(200, ['ok' => true, 'has_signed_policies' => $result['has_signed_policies'], ]);
        }
        catch (Exception $error) {
            handleExceptionError($error);
        }

        exit();
    }

    public function save($data) {
        try {
            $fieldsToValidate = [
                'curp' => 'CURP',
                'rfc' => 'RFC',
                'imss' => 'NSS',
                'institutional_email' => 'Correo Institucional'
            ];
            
            if (isset($data['infonavit']) && $data['infonavit'] != '') {
                $fieldsToValidate['infonavit'] = 'Número de Crédito Infonavit';
            }

            foreach ($fieldsToValidate as $field => $message) {
                if ($this->validateExistence($field, $data[$field])) {
                    handleError(500, ['type' => $field, 'message' => "Error: El $message ya existe en la base de datos."]);
                    return;
                }
            }

            $this->dbConnection->beginTransaction();

            $columns = $this->getColumns();

            // Excluir columnas de valores por defecto
            unset($columns['pk_user_id'], $columns['is_active'], $columns['has_signed_policies']);

            // Antes de todo, si se asigno una vacante al usuario, validar si ya esta ocupada.
            if (isset($data['fk_job_position_id'])) {
                $sql2 = "SELECT COUNT(*) FROM [user].[users] WHERE [fk_job_position_id] = :fk_job_position_id";
                $stmt2 = $this->dbConnection->prepare($sql2);
                $stmt2->bindParam(':fk_job_position_id', $data['fk_job_position_id'], PDO::PARAM_INT);
                $stmt2->execute();
                $exists = $stmt2->fetchColumn();
                if ($exists > 0) {
                    throw new Exception('Error: El puesto asignado al nuevo usuario ya está ocupado.');
                } 

                $sql3 = 'UPDATE [job_position].[positions] SET [fk_job_position_status_id] = :job_position_status_id, [fk_job_position_admin_status_id] = :job_position_admin_status_id WHERE pk_job_position_id = :pk_job_position_id';
                $stmt3 = $this->dbConnection->prepare($sql3);
                $fkJobPositionId = $data['fk_job_position_id'];
                $JOB_POSITION_STATUS_BUSY = JobPosition::STATUS_BUSY;
                $JOB_POSITION_ADMIN_STATUS_BUSY = JobPosition::ADMIN_STATUS_BUSY;
                $stmt3->bindParam(':pk_job_position_id', $fkJobPositionId, PDO::PARAM_INT);
                $stmt3->bindParam(':job_position_status_id', $JOB_POSITION_STATUS_BUSY, PDO::PARAM_INT);
                $stmt3->bindParam(':job_position_admin_status_id', $JOB_POSITION_ADMIN_STATUS_BUSY, PDO::PARAM_INT);
                if (!$stmt3->execute() || $stmt3->rowCount() === 0) {
                    throw new Exception('Error: No se pudo actualizar el estado del puesto asignado al nuevo usuario.');
                }
            }

            $insert = sprintf('INSERT INTO [user].[users](%s)', implode(',', array_keys($columns)));
            $values = [];
            foreach ($columns as $column => $pdoParam) {
                if (array_key_exists($column, $data)) {
                    $values[":$column"] = $pdoParam;
                }
            }

            $sql1 = sprintf("$insert VALUES(%s)", implode(',', array_merge(array_keys($values), [':created_by',])));
            $stmt1 = $this->dbConnection->prepare($sql1);
            foreach ($values as $placeholder => $pdoParam) {
                $columnName = ltrim($placeholder, ':');
                $columnValue = trim($data[$columnName]);
                $stmt1->bindValue($placeholder, $columnValue, $pdoParam);
            }
            $stmt1->bindValue(':created_by', $_SESSION['pk_user_id'], PDO::PARAM_INT);
            $stmt1->bindValue(':created_by', $_SESSION['pk_user_id'], PDO::PARAM_INT);
            if (!$stmt1->execute() || $stmt1->rowCount() === 0) {
                throw new Exception('Error: No fue posible crear el usuario.');
            }
            
            $newUserId = $this->dbConnection->lastInsertId();
            
            $sql4 = 'INSERT INTO [user].[users_auth] ([username], [password], [fk_user_id], [fk_role_id]) VALUES(:username, :password, :user_id, :role_id);';
            $stmt4 = $this->dbConnection->prepare($sql4);
            $password = password_hash($data['password'], PASSWORD_BCRYPT);
            $stmt4->bindParam(':username', $data['username'], PDO::PARAM_STR);
            $stmt4->bindParam(':password', $password, PDO::PARAM_STR);
            $stmt4->bindParam(':user_id', $newUserId, PDO::PARAM_INT);
            $stmt4->bindParam(':role_id', $data['role_id'], PDO::PARAM_INT);
            if (!$stmt4->execute() || $stmt4->rowCount() === 0) {
                throw new Exception('Error: No se pudo crear la cuenta de acceso a plataforma para el nuevo usuario.');
            }
            
            $this->dbConnection->commit();
            $send = $this->sendWelcomeEmail($data);
            if ($send) {
                sendJsonResponse(200, ['ok' => true, 'new_user_id' => $newUserId, 'message' => 'Usuario creado exitosamente.']);
            }
            else {
                handleError(500, 'Usuario creado exitosamente, pero no se pudo enviar el correo de bienvenida.');
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
            $SET = [];
            $columns = $this->getColumns();
            foreach ($columns as $field => $pdoParam) {
                if (isset($data[$field])) {
                    $SET[] = "[$field] = :$field";
                }
            }
            
            $this->dbConnection->beginTransaction();

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
                        throw new Exception('Error: El puesto asignado al usuario ya está ocupado.');
                    } 

                    // Liberar la vacante anterior
                    $sql3 = 'UPDATE [job_position].[positions] SET [fk_job_position_status_id] = :job_position_status_id, [fk_job_position_admin_status_id] = :job_position_admin_status_id WHERE pk_job_position_id = :pk_job_position_id';
                    $stmt3 = $this->dbConnection->prepare($sql3);
                    $fkJobPositionIdOld = $result['fk_job_position_id'];
                    $JOB_POSITION_STATUS_AVAILABLE = JobPosition::STATUS_AVAILABLE;
                    $JOB_POSITION_ADMIN_STATUS_CREATED = JobPosition::ADMIN_STATUS_CREATED;
                    $stmt3->bindParam(':pk_job_position_id', $fkJobPositionIdOld, PDO::PARAM_INT);
                    $stmt3->bindParam(':job_position_status_id', $JOB_POSITION_STATUS_AVAILABLE, PDO::PARAM_INT);
                    $stmt3->bindParam(':job_position_admin_status_id', $JOB_POSITION_ADMIN_STATUS_CREATED, PDO::PARAM_INT);
                    if (!$stmt3->execute()) {
                        throw new Exception('Error: No se pudo actualizar el estado del puesto actual del usuario.');
                    }

                    // Ocupar la nueva vacante.
                    $sql4 = 'UPDATE [job_position].[positions] SET [fk_job_position_status_id] = :job_position_status_id, [fk_job_position_admin_status_id] = :job_position_admin_status_id WHERE pk_job_position_id = :pk_job_position_id';
                    $stmt4 = $this->dbConnection->prepare($sql4);
                    $fkJobPositionIdNew = $data['fk_job_position_id'];
                    $JOB_POSITION_STATUS_BUSY = JobPosition::STATUS_BUSY;
                    $JOB_POSITION_ADMIN_STATUS_BUSY = JobPosition::ADMIN_STATUS_BUSY;
                    $stmt4->bindParam(':pk_job_position_id', $fkJobPositionIdNew, PDO::PARAM_INT);
                    $stmt4->bindParam(':job_position_status_id', $JOB_POSITION_STATUS_BUSY, PDO::PARAM_INT);
                    $stmt4->bindParam(':job_position_admin_status_id', $JOB_POSITION_ADMIN_STATUS_BUSY, PDO::PARAM_INT);
                    if (!$stmt4->execute()) {
                        throw new Exception('Error: No se pudo actualizar el estado del nuevo puesto asignado al usuario.');
                    }
                }
            }

            // Actualizar el usuario
            $sql5 = sprintf('UPDATE [user].[users] SET %s WHERE [pk_user_id] = :pk_user_id;', implode(',', $SET));
            $stmt5 = $this->dbConnection->prepare($sql5);
            foreach ($columns as $field => $pdoParam) {
                if (isset($data[$field])) {
                    $columnValue = $data[$field];
                    $stmt5->bindValue(":$field", $columnValue, $pdoParam);
                }
            }
            $stmt5->bindValue(':pk_user_id', $id, PDO::PARAM_INT);
            if (!$stmt5->execute()) {
                throw new Exception('Error: No se realizaron cambios en los datos del usuario.');
            }

            if (isset($data['username']) && isset($data['role_id'])) {
                // Actualizar los datos de la cuenta.
                $sql6 = 'UPDATE [user].[users_auth] SET [username] = :username, [fk_role_id] = :role_id WHERE fk_user_id = :fk_user_id;';
                $stmt6 = $this->dbConnection->prepare($sql6);
                $stmt6->bindParam(':username', $data['username'], PDO::PARAM_STR);
                $stmt6->bindParam(':role_id', $data['role_id'], PDO::PARAM_INT);
                $stmt6->bindParam(':fk_user_id', $id, PDO::PARAM_INT);
                if (!$stmt6->execute()) {
                    throw new Exception('Error: No se realizaron cambios en la cuenta del usuario.');
                }
            }
            
            $this->dbConnection->commit();
            sendJsonResponse(200, ['ok' => true, 'message' => 'Los datos del usuario fueron actualizados exitosamente.']);
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
            $this->dbConnection->beginTransaction();
            
            // Obtener la vacante asociada al usuario.
            $sql1 = 'SELECT [fk_job_position_id] FROM [user].[users] WHERE pk_user_id = :pk_user_id';
            $stmt1 = $this->dbConnection->prepare($sql1);
            $stmt1->bindParam(':pk_user_id', $id, PDO::PARAM_INT);
            $stmt1->execute();
            $userData = $stmt1->fetch(PDO::FETCH_ASSOC);

            // Afectar el usuario
            $fields = '[is_active] = :is_active';
            if ($status === 0) {
                $fields .= ', [fk_job_position_id] = NULL';
            }
            $sql2 = "UPDATE [user].[users] SET $fields WHERE [pk_user_id] = :pk_user_id;";
            $stmt2 = $this->dbConnection->prepare($sql2);
            $stmt2->bindParam(':is_active', $status, PDO::PARAM_INT);
            $stmt2->bindParam(':pk_user_id', $id, PDO::PARAM_INT);
            if (!$stmt2->execute()) {
                throw new Exception('Error: No se realizaron cambios en el estado del usuario.');
            }

            if ($status === 0 && isset($userData['fk_job_position_id'])) {
                $sql3 = 'UPDATE [job_position].[positions] SET fk_job_position_status_id = 1, fk_job_position_admin_status_id = 1 WHERE [pk_job_position_id] = :pk_job_position_id;';
                $stmt3 = $this->dbConnection->prepare($sql3);
                $stmt3->bindParam(':pk_job_position_id', $userData['fk_job_position_id'], PDO::PARAM_INT);
                if (!$stmt3->execute()) {
                    throw new Exception('Error: No se realizaron cambios en el estado del usuario.');
                }
            }
            
            $this->dbConnection->commit();
            sendJsonResponse(200, ['ok' => true, 'message' => 'El estado del usuario fue actualizado exitosamente.']);
        }
        catch(Exception $error) {
            if ($this->dbConnection->inTransaction()) {
                $this->dbConnection->rollBack();
            }
            handleExceptionError($error);
        }

        exit();
    }

    private function sendWelcomeEmail($data) {
        require_once 'email.php';
        $email = new Email();
        $to = $data['institutional_email'];
        $HTTP_HOST = null;
        if ($_SERVER['HTTP_HOST'] === 'localhost') {
            $HTTP_HOST = 'http://localhost:3000';
        }
        else {
            $HTTP_HOST = $_SERVER['HTTP_ORIGIN'];
        }
        $subject = '¡Bienvenido a nuestra plataforma digital! VxHR';
        $template = file_get_contents('../templates/platform_welcome_email.html');
        $template = str_replace('{{username}}', $data['first_name'].' '.$data['last_name_1'].' '.$data['last_name_2'] , $template);
        $template = str_replace('{{email}}', $data['institutional_email'], $template);
        $template = str_replace('{{password}}', $data['password'], $template);
        $template = str_replace('{{login_link}}', $HTTP_HOST.'/login', $template);
        $message = $template;
        $send = $email->send($to, $subject, $message);
        return $send;
    }
}
?>