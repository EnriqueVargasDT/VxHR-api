<?php
define('DB_SERVER', getenv('DB_SERVER'));
define('DB_USERNAME', getenv('DB_USERNAME'));
define('DB_PASSWORD', getenv('DB_PASSWORD'));
define('DB_DATABASE', 'VICA-DEV');
print_r($_SERVER);

function dbConnection() {
    $connection = null;
    try {
        $connection = new PDO('sqlsrv:server=' . DB_SERVER . ';Database=' . DB_DATABASE, DB_USERNAME, DB_PASSWORD);
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    catch (PDOException $error) {
        http_response_code(500);
        echo json_encode(['error' => true, 'message' => 'Error de conexión: ' . $error->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    
    return $connection;
}
?>