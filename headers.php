<?php
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header(sprintf('Access-Control-Allow-Origin: %s', getenv('ACCESS_CONTROL_ALLOW_ORIGIN')));
    header("Access-Control-Allow-Credentials: true");
    header('Access-Control-Allow-Methods: OPTIONS,GET,POST,PUT,DELETE');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Content-Type: application/json; charset=UTF-8');
    header('HTTP/1.1 200 OK');
    exit(0);
}

header(sprintf('Access-Control-Allow-Origin: %s', getenv('ACCESS_CONTROL_ALLOW_ORIGIN')));
header("Access-Control-Allow-Credentials: true");
header('Access-Control-Allow-Methods: OPTIONS,GET,POST,PUT,DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=UTF-8');
header('HTTP/1.1 200 OK');
?>