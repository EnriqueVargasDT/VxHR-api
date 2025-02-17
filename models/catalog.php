<?php
require_once '../config/config.php';

class Catalog {
    private $dbConnection;

    public function __construct() {
        $this->dbConnection = dbConnection();
    }

    public function getDataByName($schema, $catalog) {
        try {
            $catalogMetaData = $this->getMetaDataByName($schema, $catalog);
            $fields = $catalogMetaData['join_fields'];
            $alias = $catalogMetaData['alias'] ?? '';
            $join = $catalogMetaData['join'] ?? '';
            $sql = sprintf('SELECT %s FROM [%s].[%s] %s %s;', $fields, $schema, $catalog, $alias, $join);
            $stmt = $this->dbConnection->query($sql);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(array('ok' => true, 'data' => $result), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        catch (Exception $error) {
            http_response_code(500);
            echo json_encode(array('error' => true, 'message' => $error->getMessage()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    
        exit();
    }

    public function getItemDataById($schema, $catalog, $id) {
        try {
            if (isset($id)) {
                $catalogMetaData = $this->getMetaDataByName($schema, $catalog);
                $primaryKey = $catalogMetaData['primary_key'];
                $fields = $catalogMetaData['fields'];
                $sql = sprintf('SELECT TOP 1 %s FROM [%s].[%s] WHERE %s = %s', $fields, $schema, $catalog, $primaryKey, $id);
                $stmt = $this->dbConnection->query($sql);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                echo json_encode(array('ok' => true, 'data' => $result), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            else {
                http_response_code(500);
                echo json_encode(array('error' => true, 'message' => 'Error al intentar obtener el registro: id no válida.'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
        catch (Exception $error) {
            http_response_code(500);
            echo json_encode(array('error' => true, 'message' => $error->getMessage()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    
        exit();
    }
    
    public function saveNewItem($schema, $catalog, $item) {
        try {
            $catalogMetaData = $this->getMetaDataByName($schema, $catalog);
            $fields = array($catalogMetaData['description'] => ':description');
            $params = array(':description' => $item['description']);
            if ($schema === 'job_position') {
                if ($catalog === 'department') {
                    $fields[$catalogMetaData['foreign_key']] = ':area';
                    $params[':area'] = $item['area'];
                }
                elseif ($catalog === 'office') {
                    $fields[$catalogMetaData['address']] = ':address';
                    $params[':address'] = $item['address'];
                }
            }
    
            $columns = implode(',', array_keys($fields));
            $placeholders = implode(',', array_values($fields));
            $sql = sprintf('INSERT INTO [%s].[%s] (%s) VALUES(%s);', $schema, $catalog, $columns, $placeholders);
            $stmt = $this->dbConnection->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
    
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                echo json_encode(array('ok' => true, 'message' => 'Registro agregado correctamente.'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
        catch (Exception $error) {
            http_response_code(500);
            echo json_encode(array('error' => true, 'message' => $error->getMessage()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    
        exit();
    }    

    public function updateItem($schema, $catalog, $item) {
        try {
            $catalogMetaData = $this->getMetaDataByName($schema, $catalog);
            $fields = array($catalogMetaData['description'] => ':description');
            $params = array(
                ':description' => $item['description'],
                ':id' => $item['id']
            );
            if ($schema === 'job_position' && $catalog === 'office') {
                $fields[$catalogMetaData['description'] . '_address'] = ':address';
                $params[':address'] = $item['address'];
            }

            $setClause = implode(', ', array_map(fn($field, $placeholder) => "[$field] = $placeholder", array_keys($fields), $fields));
            $sql = sprintf(
                'UPDATE [%s].[%s] SET %s WHERE [%s] = :id;',
                $schema,
                $catalog,
                $setClause,
                $catalogMetaData['primary_key']
            );
            $stmt = $this->dbConnection->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
    
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                echo json_encode(array('ok' => true, 'message' => 'Registro actualizado correctamente.'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
        catch (Exception $error) {
            http_response_code(500);
            echo json_encode(array('error' => true, 'message' => $error->getMessage()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    
        exit();
    }    

    private function getMetaDataByName($schema, $catalog) {
        $catalogsMetaData = $this->getAllMetaData();
        if ($catalogsMetaData[$schema]) {
            if ($catalogsMetaData[$schema][$catalog]) {
                return $catalogsMetaData[$schema][$catalog];
            }
        }
        
        return array();
    }
    
    private function getAllMetaData() {
        return array(
            'job_position' => array(
                'area' => array(
                    'primary_key' => 'pk_job_position_area_id',
                    'description' => 'job_position_area',
                    'foreign_key' => '',
                    'fields' => 'pk_job_position_area_id AS id, job_position_area AS description, created_at, created_by',
                    'join_fields' => "jpa.pk_job_position_area_id AS id, jpa.job_position_area AS description, jpa.created_at, CONCAT(u.first_name, ' ', u.last_name_1, ' ', u.last_name_2) AS created_by",
                    'alias' => 'jpa',
                    'join' => 'LEFT JOIN [user].[users] u ON jpa.[created_by] = u.[pk_user_id]',
                ),
                'department' => array(
                    'primary_key' => 'pk_job_position_department_id',
                    'description' => 'job_position_department',
                    'foreign_key' => 'fk_job_position_area_id',
                    'fields' => 'pk_job_position_department_id AS id, job_position_department AS description, fk_job_position_area_id, created_at, created_by',
                    'join_fields' => "jpd.pk_job_position_department_id AS id, jpd.job_position_department AS description, jpa.job_position_area AS area, jpd.created_at, CONCAT(u.first_name, ' ', u.last_name_1, ' ', u.last_name_2) AS created_by",
                    'alias' => 'jpd',
                    'join' => 'LEFT JOIN [user].[users] u ON jpd.[created_by] = u.[pk_user_id] LEFT JOIN [job_position].[area] jpa ON jpd.[fk_job_position_area_id] = jpa.[pk_job_position_area_id]',
                ),
                'office' => array(
                    'primary_key' => 'pk_job_position_office_id',
                    'description' => 'job_position_office',
                    'foreign_key' => '',
                    'fields' => 'pk_job_position_office_id AS id, job_position_office AS description, job_position_office_address AS address, created_at, created_by',
                    'join_fields' => "jpo.pk_job_position_office_id AS id, jpo.job_position_office AS description, jpo.job_position_office_address AS address, jpo.created_at, CONCAT(u.first_name, ' ', u.last_name_1, ' ', u.last_name_2) AS created_by",
                    'alias' => 'jpo',
                    'join' => 'LEFT JOIN [user].[users] u ON jpo.[created_by] = u.[pk_user_id]',
                ),
                'type' => array(
                    'primary_key' => 'pk_job_position_type_id',
                    'description' => 'job_position_type',
                    'foreign_key' => '',
                    'fields' => 'pk_job_position_type_id AS id, job_position_type AS description, created_at, created_by',
                    'join_fields' => "jpt.pk_job_position_type_id AS id, jpt.job_position_type AS description, jpt.created_at, CONCAT(u.first_name, ' ', u.last_name_1, ' ', u.last_name_2) AS created_by",
                    'alias' => 'jpt',
                    'join' => 'LEFT JOIN [user].[users] u ON jpt.[created_by] = u.[pk_user_id]',
                ),
                'status' => array(
                    'primary_key' => 'pk_job_position_status_id',
                    'description' => 'job_position_status',
                    'foreign_key' => '',
                    'fields' => 'pk_job_position_status_id AS id, job_position_status AS description, created_at, created_by',
                    'join_fields' => "jps.pk_job_position_status_id AS id, jps.job_position_status AS description, jps.created_at, CONCAT(u.first_name, ' ', u.last_name_1, ' ', u.last_name_2) AS created_by",
                    'alias' => 'jps',
                    'join' => 'LEFT JOIN [user].[users] u ON jps.[created_by] = u.[pk_user_id]',
                ),
            ),
            'user' => array(
                'genders' => array(
                    'primary_key' => 'pk_gender_id',
                    'description' => 'gender',
                    'foreign_key' => '',
                    'fields' => 'pk_gender_id AS id, gender AS description, created_at, created_by',
                    'join_fields' => "ug.pk_gender_id AS id, ug.gender AS description, ug.created_at, CONCAT(u.first_name, ' ', u.last_name_1, ' ', u.last_name_2) AS created_by",
                    'alias' => 'ug',
                    'join' => 'LEFT JOIN [user].[users] u ON ug.[created_by] = u.[pk_user_id]',
                ),
                'nationalities' => array(
                    'primary_key' => 'pk_nationality_id',
                    'description' => 'nationality',
                    'foreign_key' => '',
                    'fields' => 'pk_nationality_id AS id, nationality AS description, created_at, created_by',
                    'join_fields' => "un.pk_nationality_id AS id, un.nationality AS description, un.created_at, CONCAT(u.first_name, ' ', u.last_name_1, ' ', u.last_name_2) AS created_by",
                    'alias' => 'un',
                    'join' => 'LEFT JOIN [user].[users] u ON un.[created_by] = u.[pk_user_id]',
                ),
                'marital_status' => array(
                    'primary_key' => 'pk_marital_status_id',
                    'description' => 'marital_status',
                    'foreign_key' => '',
                    'fields' => 'pk_marital_status_id AS id, marital_status AS description, created_at, created_by',
                    'join_fields' => "ums.pk_marital_status_id AS id, ums.marital_status AS description, ums.created_at, CONCAT(u.first_name, ' ', u.last_name_1, ' ', u.last_name_2) AS created_by",
                    'alias' => 'ums',
                    'join' => 'LEFT JOIN [user].[users] u ON ums.[created_by] = u.[pk_user_id]',
                ),
                'relationships' => array(
                    'primary_key' => 'pk_relationship_id',
                    'description' => 'relationship',
                    'foreign_key' => '',
                    'fields' => 'pk_relationship_id AS id, relationship AS description, created_at, created_by',
                    'join_fields' => "urs.pk_relationship_id AS id, urs.relationship AS description, urs.created_at, CONCAT(u.first_name, ' ', u.last_name_1, ' ', u.last_name_2) AS created_by",
                    'alias' => 'urs',
                    'join' => 'LEFT JOIN [user].[users] u ON urs.[created_by] = u.[pk_user_id]',
                ),
                'roles' => array(
                    'primary_key' => 'pk_role_id',
                    'description' => 'role',
                    'foreign_key' => '',
                    'fields' => 'pk_role_id AS id, role AS description, created_at, created_by',
                    'join_fields' => "ur.pk_role_id AS id, ur.role AS description, ur.created_at, CONCAT(u.first_name, ' ', u.last_name_1, ' ', u.last_name_2) AS created_by",
                    'alias' => 'ur',
                    'join' => 'LEFT JOIN [user].[users] u ON ur.[created_by] = u.[pk_user_id]',
                ),
                'status' => array(
                    'primary_key' => 'pk_user_status_id',
                    'description' => 'user_status',
                    'foreign_key' => '',
                    'fields' => 'pk_user_status_id AS id, user_status AS description, created_at, created_by',
                    'join_fields' => "us.pk_user_status_id AS id, us.user_status AS description, us.created_at, CONCAT(u.first_name, ' ', u.last_name_1, ' ', u.last_name_2) AS created_by",
                    'alias' => 'us',
                    'join' => 'LEFT JOIN [user].[users] u ON us.[created_by] = u.[pk_user_id]',
                ),
            ),
        );
    }
}
?>