<?php
define('DB_SERVER', getenv('DB_SERVER'));
define('DB_USERNAME', getenv('DB_USERNAME'));
define('DB_PASSWORD', getenv('DB_PASSWORD'));

$DB_DATABASE = 'VICA-PROD';
$HTTP_ORIGIN = $_SERVER['HTTP_ORIGIN'];
if (preg_match('/dev/', $HTTP_ORIGIN) || preg_match('/sandbox/', $HTTP_ORIGIN) || preg_match('/localhost/', $HTTP_ORIGIN)) {
    $DB_DATABASE = 'VICA-DEV';
}
define('DB_DATABASE', $DB_DATABASE);

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