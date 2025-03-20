<?php

define('DB_SERVER', 'aorokag8pb.database.windows.net,1433');
define('DB_USERNAME', 'traacedb');
define('DB_PASSWORD', 'Traace2014');
define('DB_DATABASE', 'dev-VXHR');
/*
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'sa');
define('DB_PASSWORD', 'Kingdiamond2025*');
define('DB_DATABASE', 'dev-VXHR');
*/
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