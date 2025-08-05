<?php
session_start();
require_once 'includes/functions.php';


header('Content-Type: application/json');


try {
    $dbConnection = checkDatabaseConnection();
    $pdo = getDatabaseConnection();
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

if (isset($_GET['table'])) {
    $table = $_GET['table'];
    
    if (validateTable($pdo, $table)) {
        $columns = getTableColumns($pdo, $table);
        echo json_encode($columns);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Table not found']);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Table parameter required']);
}
