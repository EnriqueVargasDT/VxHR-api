<?php
require_once '../config/config.php';

class Catalog {
    private $dbConnection;

    public function __construct() {
        $this->dbConnection = dbConnection();
    }

    private function getMetaDataByName($schema, $catalog) {
        $catalogsMetaData = $this->getAllMetaData();
        if (isset($catalogsMetaData[$schema])) {
            if (isset($catalogsMetaData[$schema][$catalog])) {
                return $catalogsMetaData[$schema][$catalog];
            }
        }
        
        return array();
    }

    public function getBasicFields() {
        $fields = array();
        $catalogsMetaData = $this->getAllMetaData();
        foreach ($catalogsMetaData as $schema => $catalog) {
            foreach ($catalog as $catalogName => $data) {
                $fields[$schema][$catalogName] = array(
                    'id' => $data['primary_key'],
                    'description' => $data['description'],
                );
            }
        }
        
        sendJsonResponse(200, array('ok' => true, 'data' => $fields));
        exit();
    }

    public function getAll($schema, $catalog, $available) {
        try {
            $catalogMetaData = $this->getMetaDataByName($schema, $catalog);
            if ($schema === 'default') {
                $columns = $catalogMetaData['columns'];
                $where = $available ? 'WHERE [status] = 1' : '';
                $sql = sprintf('SELECT %s FROM [%s].[%s] %s ORDER BY [%s] DESC;', $columns, 'dbo', $catalog, $where, 'created_at');
                $stmt = $this->dbConnection->query($sql);
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                sendJsonResponse(200, array('ok' => true, 'data' => $result));
            }
            else {
                $columns = $catalogMetaData['join_columns'];
                $alias = $catalogMetaData['alias'] ?? '';
                $primaryKey = $catalogMetaData['primary_key'] ?? '';
                $join = $catalogMetaData['join'] ?? '';
                $where = $available ? "WHERE $alias.[status] = 1" : '';
                $sql = sprintf('SELECT %s FROM [%s].[%s] %s %s ORDER BY %s DESC;', $columns, $schema, $catalog, $alias, $join, "$alias.$primaryKey");
                $stmt = $this->dbConnection->query($sql);
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                sendJsonResponse(200, array('ok' => true, 'data' => $result));
            }
        }
        catch (Exception $error) {
            handleExceptionError($error);
        }
    
        exit();
    }

    public function getItemById($schema, $catalog, $id) {
        try {
            if (isset($id)) {
                $catalogMetaData = $this->getMetaDataByName($schema, $catalog);
                $primaryKey = $catalogMetaData['primary_key'];
                $columns = $catalogMetaData['columns'];
                $sql = sprintf('SELECT TOP 1 %s FROM [%s].[%s] WHERE %s = %s', $columns, $schema, $catalog, $primaryKey, $id);
                $stmt = $this->dbConnection->query($sql);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                sendJsonResponse(200, array('ok' => true, 'data' => $result));
            }
            else {
                sendJsonResponse(401, array('error' => true, 'message' => 'Error: id de catálogo no válido.'));
            }
        }
        catch (Exception $error) {
            handleExceptionError($error);
        }
    
        exit();
    }
    
    public function saveItem($schema, $catalog, $item) {
        try {
            $catalogMetaData = $this->getMetaDataByName($schema, $catalog);
            $fields = array($catalogMetaData['description'] => ':description');
            $params = array(':description' => $item['description']);
            if ($schema === 'job_position') {
                if ($catalog === 'department') {
                    $fields[$catalogMetaData['foreign_key']] = ':area';
                    $params[':area'] = $item['area'];

                    $fields['job_position_department_short'] = ':shortname';
                    $params[':shortname'] = $item['shortname'];
                }
                elseif ($catalog === 'office') {
                    $fields['job_position_office_short'] = ':shortname';
                    $params[':shortname'] = $item['shortname'];

                    $fields['job_position_office_address'] = ':address';
                    $params[':address'] = $item['address'];
                }
            }
            $fields['created_by'] = ':created_by';
            $params[':created_by'] = isset($_SESSION['pk_user_id']) ? $_SESSION['pk_user_id'] : 0;
    
            $columns = implode(',', array_keys($fields));
            $placeholders = implode(',', array_values($fields));
            $sql = sprintf('INSERT INTO [%s].[%s] (%s) VALUES(%s);', $schema, $catalog, $columns, $placeholders);
            $this->dbConnection->beginTransaction();
            $stmt = $this->dbConnection->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            if (!$stmt->execute() || $stmt->rowCount() === 0) {
                throw new Exception('Error: No se pudo crear el registro.');
            }
            $this->dbConnection->commit();
            sendJsonResponse(200, array('ok' => true, 'message' => 'Registro creado correctamente.'));
        }
        catch (Exception $error) {
            if ($this->dbConnection->inTransaction()) {
                $this->dbConnection->rollBack();
            }
            handleExceptionError($error);
        }
    
        exit();
    }    

    public function updateItem($schema, $catalog, $item) {
        try {
            $catalogMetaData = $this->getMetaDataByName($schema, $catalog);
            $columns = array($catalogMetaData['description'] => ':description');
            $params = array(
                ':id' => $item['id'],
                ':description' => $item['description'],
            );
            if ($schema === 'job_position') {
                if ($catalog === 'department') {
                    $columns[$catalogMetaData['description'] . '_short'] = ':shortname';
                    $params[':shortname'] = $item['shortname'];
                }
                elseif ($catalog === 'office') {
                    $columns[$catalogMetaData['description'] . '_short'] = ':shortname';
                    $params[':shortname'] = $item['shortname'];

                    $columns[$catalogMetaData['description'] . '_address'] = ':address';
                    $params[':address'] = $item['address'];
                }
            }

            $setClause = implode(', ', array_map(fn($field, $placeholder) => "[$field] = $placeholder", array_keys($columns), $columns));
            $sql = sprintf(
                'UPDATE [%s].[%s] SET %s WHERE [%s] = :id;',
                $schema,
                $catalog,
                $setClause,
                $catalogMetaData['primary_key']
            );
            $this->dbConnection->beginTransaction();
            $stmt = $this->dbConnection->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            if (!$stmt->execute()) {
                throw new Exception('Error: Falló la instrucción de actualización del registro.');
            }
            $this->dbConnection->commit();
            sendJsonResponse(200, array('ok' => true, 'message' => 'Registro actualizado correctamente.'));
        }
        catch (Exception $error) {
            if ($this->dbConnection->inTransaction()) {
                $this->dbConnection->rollBack();
            }
            handleExceptionError($error);
        }
    
        exit();
    }

    public function updateItemStatus($schema, $catalog, $item) {
        try {
            $catalogMetaData = $this->getMetaDataByName($schema, $catalog);
            $sql = sprintf(
                'UPDATE [%s].[%s] SET [status] = %s WHERE [%s] = :id;',
                $schema,
                $catalog,
                $item['status'],
                $catalogMetaData['primary_key']
            );
            $this->dbConnection->beginTransaction();
            $stmt = $this->dbConnection->prepare($sql);
            $stmt->bindValue(':id', $item['id'], PDO::PARAM_INT);
            if (!$stmt->execute() || $stmt->rowCount() === 0) {
                throw new Exception('Error: No se realizaron cambios en el registro.');
            }
            $this->dbConnection->commit();
            sendJsonResponse(200, array('ok' => true, 'message' => 'Registro actualizado correctamente.'));
        }
        catch (Exception $error) {
            if ($this->dbConnection->inTransaction()) {
                $this->dbConnection->rollBack();
            }
            handleExceptionError($error);
        }
    
        exit();
    }
    
    private function getAllMetaData() {
        return array(
            'job_position' => array(
                'area' => array(
                    'primary_key' => 'pk_job_position_area_id',
                    'description' => 'job_position_area',
                    'foreign_key' => '',
                    'columns' => '[pk_job_position_area_id], [job_position_area], [status], [created_at], [created_by]',
                    'join_columns' => "jpa.pk_job_position_area_id, jpa.job_position_area, jpa.status, jpa.created_at, jpa.created_by, CONCAT(u.first_name, ' ', u.last_name_1, ' ', u.last_name_2) AS created_by_full_name",
                    'alias' => 'jpa',
                    'join' => 'LEFT JOIN [user].[users] u ON jpa.[created_by] = u.[pk_user_id]',
                ),
                'department' => array(
                    'primary_key' => 'pk_job_position_department_id',
                    'description' => 'job_position_department',
                    'foreign_key' => 'fk_job_position_area_id',
                    'columns' => '[pk_job_position_department_id], [job_position_department], [job_position_department_short], [fk_job_position_area_id], [status], [created_at], [created_by]',
                    'join_columns' => "jpd.pk_job_position_department_id, jpd.job_position_department, jpd.job_position_department_short, jpd.fk_job_position_area_id, jpa.job_position_area, jpd.status, jpd.created_at, jpd.created_by, CONCAT(u.first_name, ' ', u.last_name_1, ' ', u.last_name_2) AS created_by_full_name",
                    'alias' => 'jpd',
                    'join' => 'LEFT JOIN [user].[users] u ON jpd.[created_by] = u.[pk_user_id] LEFT JOIN [job_position].[area] jpa ON jpd.[fk_job_position_area_id] = jpa.[pk_job_position_area_id]',
                ),
                'office' => array(
                    'primary_key' => 'pk_job_position_office_id',
                    'description' => 'job_position_office',
                    'foreign_key' => '',
                    'columns' => '[pk_job_position_office_id], [job_position_office], [job_position_office_short], [job_position_office_address], [status], [created_at], created_by',
                    'join_columns' => "jpo.pk_job_position_office_id, jpo.job_position_office, jpo.job_position_office_short, jpo.job_position_office_address, jpo.status, jpo.created_at, jpo.created_by, CONCAT(u.first_name, ' ', u.last_name_1, ' ', u.last_name_2) AS created_by_full_name",
                    'alias' => 'jpo',
                    'join' => 'LEFT JOIN [user].[users] u ON jpo.[created_by] = u.[pk_user_id]',
                ),
                'type' => array(
                    'primary_key' => 'pk_job_position_type_id',
                    'description' => 'job_position_type',
                    'foreign_key' => '',
                    'columns' => '[pk_job_position_type_id], [job_position_type], [status], [created_at], [created_by]',
                    'join_columns' => "jpt.pk_job_position_type_id, jpt.job_position_type, jpt.status, jpt.created_at, jpt.created_by, CONCAT(u.first_name, ' ', u.last_name_1, ' ', u.last_name_2) AS created_by_full_name",
                    'alias' => 'jpt',
                    'join' => 'LEFT JOIN [user].[users] u ON jpt.[created_by] = u.[pk_user_id]',
                ),
                'status' => array(
                    'primary_key' => 'pk_job_position_status_id',
                    'description' => 'job_position_status',
                    'foreign_key' => '',
                    'columns' => '[pk_job_position_status_id], [job_position_status], [status], [created_at], [created_by]',
                    'join_columns' => "jps.pk_job_position_status_id, jps.job_position_status, jps.status, jps.created_at, jps.created_by, CONCAT(u.first_name, ' ', u.last_name_1, ' ', u.last_name_2) AS created_by_full_name",
                    'alias' => 'jps',
                    'join' => 'LEFT JOIN [user].[users] u ON jps.[created_by] = u.[pk_user_id]',
                ),
                'admin_status' => array(
                    'primary_key' => 'pk_job_position_admin_status_id',
                    'description' => 'job_position_admin_status',
                    'foreign_key' => '',
                    'columns' => '[pk_job_position_admin_status_id], [job_position_admin_status], [status], [created_at], [created_by]',
                    'join_columns' => "jpas.pk_job_position_admin_status_id, jpas.job_position_admin_status, jpas.status, jpas.created_at, jpas.created_by, CONCAT(u.first_name, ' ', u.last_name_1, ' ', u.last_name_2) AS created_by_full_name",
                    'alias' => 'jpas',
                    'join' => 'LEFT JOIN [user].[users] u ON jpas.[created_by] = u.[pk_user_id]',
                ),
            ),
            'user' => array(
                'genders' => array(
                    'primary_key' => 'pk_gender_id',
                    'description' => 'gender',
                    'foreign_key' => '',
                    'columns' => '[pk_gender_id], [gender], [status], [created_at], [created_by]',
                    'join_columns' => "ug.pk_gender_id, ug.gender, ug.status, ug.created_at, ug.created_by, CONCAT(u.first_name, ' ', u.last_name_1, ' ', u.last_name_2) AS created_by_full_name",
                    'alias' => 'ug',
                    'join' => 'LEFT JOIN [user].[users] u ON ug.[created_by] = u.[pk_user_id]',
                ),
                'nationalities' => array(
                    'primary_key' => 'pk_nationality_id',
                    'description' => 'nationality',
                    'foreign_key' => '',
                    'columns' => '[pk_nationality_id], [nationality], [status], [created_at], [created_by]',
                    'join_columns' => "un.pk_nationality_id, un.nationality, un.status, un.created_at, un.created_by, CONCAT(u.first_name, ' ', u.last_name_1, ' ', u.last_name_2) AS created_by_full_name",
                    'alias' => 'un',
                    'join' => 'LEFT JOIN [user].[users] u ON un.[created_by] = u.[pk_user_id]',
                ),
                'marital_status' => array(
                    'primary_key' => 'pk_marital_status_id',
                    'description' => 'marital_status',
                    'foreign_key' => '',
                    'columns' => '[pk_marital_status_id], [marital_status], [status], [created_at], [created_by]',
                    'join_columns' => "ums.pk_marital_status_id, ums.marital_status, ums.status, ums.created_at, ums.created_by, CONCAT(u.first_name, ' ', u.last_name_1, ' ', u.last_name_2) AS created_by_full_name",
                    'alias' => 'ums',
                    'join' => 'LEFT JOIN [user].[users] u ON ums.[created_by] = u.[pk_user_id]',
                ),
                'relationships' => array(
                    'primary_key' => 'pk_relationship_id',
                    'description' => 'relationship',
                    'foreign_key' => '',
                    'columns' => '[pk_relationship_id], [relationship], [status], [created_at], [created_by]',
                    'join_columns' => "urs.pk_relationship_id, urs.relationship, urs.status, urs.created_at, urs.created_by, CONCAT(u.first_name, ' ', u.last_name_1, ' ', u.last_name_2) AS created_by_full_name",
                    'alias' => 'urs',
                    'join' => 'LEFT JOIN [user].[users] u ON urs.[created_by] = u.[pk_user_id]',
                ),
                'roles' => array(
                    'primary_key' => 'pk_role_id',
                    'description' => 'role',
                    'foreign_key' => '',
                    'columns' => '[pk_role_id], [role], [status], [created_at], [created_by]',
                    'join_columns' => "ur.pk_role_id, ur.role, ur.status, ur.created_at, ur.created_by, CONCAT(u.first_name, ' ', u.last_name_1, ' ', u.last_name_2) AS created_by_full_name",
                    'alias' => 'ur',
                    'join' => 'LEFT JOIN [user].[users] u ON ur.[created_by] = u.[pk_user_id]',
                ),
                'status' => array(
                    'primary_key' => 'pk_user_status_id',
                    'description' => 'user_status',
                    'foreign_key' => '',
                    'columns' => '[pk_user_status_id], [user_status], [status], [created_at], [created_by]',
                    'join_columns' => "us.pk_user_status_id, us.user_status, us.status, us.created_at, us.created_by, CONCAT(u.first_name, ' ', u.last_name_1, ' ', u.last_name_2) AS created_by_full_name",
                    'alias' => 'us',
                    'join' => 'LEFT JOIN [user].[users] u ON us.[created_by] = u.[pk_user_id]',
                ),
            ),
            'default' => array(
                'states' => array(
                    'primary_key' => 'pk_state_id',
                    'description' => 'state_name',
                    'foreign_key' => 'fk_country_id',
                    'columns' => '[pk_state_id], [state_name], [state_code], [fk_country_id], [created_at]',
                ),
                'countries' => array(
                    'primary_key' => 'pk_country_id',
                    'description' => 'country_name',
                    'foreign_key' => '',
                    'columns' => '[pk_country_id], [country_name], [country_code], [created_at]',
                ),
            ),
        );
    }
}
?>