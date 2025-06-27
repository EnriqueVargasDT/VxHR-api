<?php
require_once '../config/config.php';
function handleExceptionError($error) {
    http_response_code(400);
    echo json_encode(['error' => true, 'message' => $error->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function handleError($statusCode, $error) {
    http_response_code($statusCode);
    if (is_array($error)) {
        echo json_encode($error, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    else {
        echo json_encode(['error' => true, 'message' => 'Error: ' . $error], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

function sendJsonResponse($statusCode, $response) {
    http_response_code($statusCode);
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;}

function jsonResponse($data = null, $message = null, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode([
        'ok' => true,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}