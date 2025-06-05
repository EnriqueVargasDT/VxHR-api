<?php
// TODO: Filter organization data to get only the active positions.

require_once '../config/config.php';
require_once '../models/userFiles.php';

class Organization {
    private $dbConnection;

    public function __construct() {
        $this->dbConnection = dbConnection();
    }

    public function getData() {
        try {
            $sql = sprintf("
                SELECT
                    jp.*,
                    CONCAT(u.first_name, ' ', u.last_name_1, ' ', u.last_name_2) AS full_name,
                    jpd.job_position_department,
                    jpd.job_position_department_short,
                    jpo.job_position_office,
                    jpo.job_position_office_short,
                    jpa.job_position_area AS area,
                    CONCAT(CASE WHEN CHARINDEX(' ', first_name) > 0 THEN LEFT(first_name, CHARINDEX(' ', first_name) - 1) ELSE first_name END, ' ', u.last_name_1) AS name,
                    u.updated_at as user_updated_at,
                    uf.[file] AS profile_picture,
                    CASE
                        WHEN u.institutional_email IS NOT NULL AND u.institutional_email NOT IN ('', '-') THEN u.institutional_email
                        WHEN u.personal_email IS NOT NULL AND u.personal_email NOT IN ('', '-') THEN u.personal_email
                        ELSE NULL
                    END AS email,
                    CASE
                        WHEN u.work_phone IS NOT NULL AND u.work_phone NOT IN ('', '-') THEN u.work_phone
                        WHEN u.cel_phone IS NOT NULL AND u.cel_phone NOT IN ('', '-') THEN u.cel_phone
                        ELSE NULL
                    END AS phone
                FROM [job_position].[positions] jp
                LEFT JOIN [job_position].[office] jpo ON jp.fk_job_position_office_id = jpo.pk_job_position_office_id
                LEFT JOIN [job_position].[department] jpd ON jp.fk_job_position_department_id = jpd.pk_job_position_department_id
                LEFT JOIN [user].[users] u ON jp.pk_job_position_id = u.fk_job_position_id
                LEFT JOIN [user].[files] uf ON u.pk_user_id = uf.fk_user_id AND uf.type_file = 1
                LEFT JOIN [job_position].[area] jpa ON jp.fk_job_position_area_id = jpa.pk_job_position_area_id
                WHERE fk_job_position_admin_status_id != 5
                ORDER BY jp.job_position_parent_id ASC
            ", UserFiles::TYPE_PROFILE_PICTURE);
            $result = $this->dbConnection->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            $positions = [];
            $dates = [];
            foreach ($result as $key => $value) {
                $dates[] = $value['user_updated_at'];
                $positions[$value['pk_job_position_id']] = array (
                    'id' => $value['pk_job_position_id'],
                    'name' => $value['name'],
                    'profile_picture' => $value['profile_picture'],
                    'position' => $value['job_position'],
                    'full_department' => $value['job_position_department'],
                    'department' => $value['job_position_department_short'],
                    'full_location' => $value['job_position_office'],
                    'location' => $value['job_position_office_short'],
                    'parent_id' => $value['job_position_parent_id'],
                    'area' => $value['area'],
                    'full_name' => $value['full_name'],
                    'email' => $value['email'],
                    'phone' => $_SESSION["user"]["job_position_type"] === "Administrativo" ? $value['phone'] : null,
                    'children' => []
                );
            }
            
            $data = null;
            foreach ($positions as &$node) {
                if (isset($node['parent_id'])) {
                    if ($node['parent_id'] == 0) {
                        $data = &$node;
                    }
                    else {
                        $positions[$node['parent_id']]['children'][] = &$node;;
                    }
                }
            }

            // Cuenta todos los nodos descendientes para acomularlos en cada nodo padre incluyendo nietos, bisnietos, etc de manera recursiva.
            $countDescendants = function(&$node) use (&$countDescendants) {
                $count = count($node['children']);
                foreach ($node['children'] as &$child) {
                    $count += $countDescendants($child);
                }
                $node['descendants_count'] = $count;
                return $count;
            };

            foreach ($positions as &$node) {
                $countDescendants($node);
            }

            sendJsonResponse(200, ['ok' => true, 'data' => $data, 'last_update' => max($dates)]);
        }
        catch (Exception $error) {
            handleExceptionError($error);
        }

        exit();
    }
}
?>