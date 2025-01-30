<?php
require_once '../config/config.php';

class Login {
    private $dbConnection;

    public function __construct() {
        $this->dbConnection = dbConnection();
    }

    public function validate($username, $password, $iv) {
        try {
            // Insert $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $decryptedPassword = openssl_decrypt($password, 'AES-256-CBC', '4tdySvjGanV7ya7mkUqTzp4XBBS3gCf3', 0, hex2bin($iv));
            $sql = "SELECT * FROM dbo.employees_auth WHERE username = '$username'";
            $stmt = $this->dbConnection->query($sql);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $valid = array();
            if (password_verify($decryptedPassword, $result['password'])) {
                $valid['valid'] = true;
                $valid['pk_employee_id'] = $result['pk_employee_id'];
            }
            else {
                $valid['valid'] = false;
            }
            echo json_encode($valid);
        }
        catch(error) {
            echo json_encode(array('error' => true, 'message' => $error), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        exit();
    }
}
?>