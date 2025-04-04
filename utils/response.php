<?php
function handleExceptionError($error) {
    http_response_code(500);
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
}
?>