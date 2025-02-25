<?php
require_once '../config/config.php';
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
                ua.last_access_at
                FROM [user].[users] u
                LEFT JOIN [user].[users_auth] ua ON u.pk_user_id = ua.fk_user_id
                LEFT JOIN [user].[marital_status] ums ON u.fk_marital_status_id = ums.pk_marital_status_id
                LEFT JOIN [user].[relationships] urs ON u.fk_emergency_relationship_id = urs.pk_relationship_id
                LEFT JOIN [job_position].[positions] jpp ON u.fk_job_position_id = jpp.pk_job_position_id
                LEFT JOIN [job_position].[area] jpa ON u.fk_job_position_area_id = jpa.pk_job_position_area_id
                LEFT JOIN [job_position].[department] jpd ON u.fk_job_position_department_id = jpd.pk_job_position_department_id
                LEFT JOIN [job_position].[office] jpo ON u.fk_job_position_office_id = jpo.pk_job_position_office_id
            " . PHP_EOL;
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
                LEFT JOIN [job_position].[area] jpa ON u.fk_job_position_area_id = jpa.pk_job_position_area_id
                LEFT JOIN [job_position].[department] jpd ON u.fk_job_position_department_id = jpd.pk_job_position_department_id
                LEFT JOIN [job_position].[office] jpo ON u.fk_job_position_office_id = jpo.pk_job_position_office_id
                WHERE u.pk_user_id = %s
            " . PHP_EOL;
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

    public function save($data) {
        try {
            $columns = $this->getColumns();

            // Excluir columnas de valores por defecto
            unset($columns['pk_user_id']);
            unset($columns['date_of_hire']);
            unset($columns['is_active']);

            $insert = sprintf('INSERT INTO [user].[users](%s)', implode(',', array_keys($columns)));
            $values = array();
            foreach ($columns as $column => $pdoParam) {
                if (array_key_exists($column, $data)) {
                    $values[":$column"] = $pdoParam;
                }
            }

            $sql1 = sprintf("$insert VALUES(%s)", implode(',', array_merge(array_keys($values), array(':created_by',))));
            $stmt1 = $this->dbConnection->prepare($sql1);
            foreach ($values as $placeholder => $pdoParam) {
                $columnName = ltrim($placeholder, ':');
                $columnValue = trim($data[$columnName]);
                $stmt1->bindValue($placeholder, $columnValue, $pdoParam);
            }
            $stmt1->bindValue(':created_by', $_SESSION['pk_user_id'], PDO::PARAM_INT);

            if ($stmt1->execute()) {
                $newUserId = $this->dbConnection->lastInsertId();
                if ($newUserId) {
                    $sql2 = 'INSERT INTO [user].[users_auth] ([username], [password], [fk_user_id], [fk_role_id]) VALUES(:username, :password, :user_id, :role_id);';
                    $stmt2 = $this->dbConnection->prepare($sql2);
                    $password = password_hash($data['password'], PASSWORD_BCRYPT);
                    $stmt2->bindParam(':username', $data['institutional_email'], PDO::PARAM_STR);
                    $stmt2->bindParam(':password', $password, PDO::PARAM_STR);
                    $stmt2->bindParam(':user_id', $newUserId, PDO::PARAM_INT);
                    $stmt2->bindParam(':role_id', $data['role_id'], PDO::PARAM_INT);
                    if ($stmt2->execute()) {
                        $newUserAuthId = $this->dbConnection->lastInsertId();
                        $to = $data['institutional_email'];
                        $subject = '¡Bienvenido a nuestra plataforma digital! VxHR';
                        $template = file_get_contents('../templates/platform_welcome_email.html');
                        $template = str_replace('{{username}}', $data['first_name'].' '.$data['last_name_1'].' '.$data['last_name_2'] , $template);
                        $template = str_replace('{{email}}', $data['institutional_email'], $template);
                        $template = str_replace('{{password}}', $data['password'], $template);
                        $template = str_replace('{{login_link}}', $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'].':3000/login', $template);
                        $message = $template;
                        
                        if ($this->email->send($to, $subject, $message)) {
                            sendJsonResponse(200, array('ok' => true, 'message' => 'Usuario creado correctamente.'));
                        }
                        else {
                            handleError(500, 'No se pudo enviar el correo de bienvenida a plataforma.');
                        }
                    }
                    else {
                        handleError(500, 'No se pudo crear los accesos a plataforma para el usuario.');
                    }
                }
                else {
                    handleError(500, 'No se pudo crear el usuario. Favor de intentar nuevamente.');
                }
            }
            else {
                handleError(500, 'Falló el proceso de creación de usuario.');
            }
        }
        catch(Exception $error) {
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
            
            $sql = sprintf('UPDATE [user].[users] SET %s WHERE [pk_user_id] = :pk_user_id;', implode(',', $SET));
            $stmt = $this->dbConnection->prepare($sql);
            foreach ($columns as $field => $pdoParam) {
                if (isset($data[$field])) {
                    $columnValue = $data[$field];
                    $stmt->bindValue(":$field", $columnValue, $pdoParam);
                }
            }
            $stmt->bindValue(':pk_user_id', $id, PDO::PARAM_INT);
            if ($stmt->execute()) {
                sendJsonResponse(200, array('ok' => true, 'message' => 'Usuario actualizado correctamente.'));
            }
            else {
                handleError(500, 'No se pudo actualizar los datos del usuario.');
            }
        }
        catch(Exception $error) {
            handleExceptionError($error);
        }

        exit();
    }

    public function updateStatus($id, $status) {
        try {
            $sql = sprintf('UPDATE [user].[users] SET [is_active] = :is_active WHERE [pk_user_id] = :id;');
            $stmt = $this->dbConnection->prepare($sql);
            $stmt->bindParam(':is_active', $status, PDO::PARAM_INT);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                sendJsonResponse(200, array('ok' => true, 'message' => 'Registro actualizado correctamente.'));
            }
            else {
                handleError(500, 'No se realizaron cambios en el registro.');
            }
        }
        catch(Exception $error) {
            handleExceptionError($error);
        }

        exit();
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