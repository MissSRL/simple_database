<?php

error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once __DIR__ . '/includes/functions.php';


header('Content-Type: application/json');


try {
    $dbConnection = checkDatabaseConnection();
} catch (Exception $e) {
    echo json_encode(['error' => 'Not authenticated: ' . $e->getMessage()]);
    exit();
}


if (!isset($_POST['preview_query']) || !isset($_POST['operation']) || !isset($_POST['table'])) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit();
}

try {
    $pdo = getDatabaseConnection();
    $table = $_POST['table'];
    $operation = $_POST['operation'];
    $query = $_POST['preview_query'];
    
    
    if (!validateTable($pdo, $table)) {
        echo json_encode(['error' => 'Invalid table: ' . $table]);
        exit();
    }
    
    
    if ($operation !== 'update' && $operation !== 'delete') {
        echo json_encode(['error' => 'Invalid operation']);
        exit();
    }
    
    $result = [];
    
    if ($operation === 'update') {
        
        if (preg_match('/UPDATE\s+`?([^`\\s]+)`?\s+SET\s+.+\s+WHERE\s+(.+)/i', $query, $matches)) {
            $tableName = $matches[1];
            $whereClause = $matches[2];
            
            
            if (trim($whereClause) === '...') {
                echo json_encode([
                    'count' => 0,
                    'sample' => []
                ]);
                exit();
            }
            
            
            if ($tableName !== $table) {
                echo json_encode(['error' => 'Table mismatch in query']);
                exit();
            }
            
            $selectQuery = "SELECT * FROM `{$table}` WHERE {$whereClause} LIMIT 10";
            
            try {
                $stmt = $pdo->prepare($selectQuery);
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                
                $countQuery = "SELECT COUNT(*) as total FROM `{$table}` WHERE {$whereClause}";
                $countStmt = $pdo->prepare($countQuery);
                $countStmt->execute();
                $countRow = $countStmt->fetch(PDO::FETCH_ASSOC);
                
                $result = [
                    'count' => $countRow['total'],
                    'sample' => $rows
                ];
            } catch (PDOException $e) {
                echo json_encode(['error' => 'SQL error: ' . $e->getMessage()]);
                exit();
            }
        } else {
            echo json_encode(['error' => 'Could not parse UPDATE query: ' . $query]);
            exit();
        }
    } else if ($operation === 'delete') {
        
        if (preg_match('/DELETE\s+FROM\s+`?([^`\\s]+)`?\s+WHERE\s+(.+)/i', $query, $matches)) {
            $tableName = $matches[1];
            $whereClause = $matches[2];
            
            
            if (trim($whereClause) === '...') {
                echo json_encode([
                    'count' => 0,
                    'sample' => []
                ]);
                exit();
            }
            
            
            if ($tableName !== $table) {
                echo json_encode(['error' => 'Table mismatch in query']);
                exit();
            }
            
            $selectQuery = "SELECT * FROM `{$table}` WHERE {$whereClause} LIMIT 10";
            
            try {
                $stmt = $pdo->prepare($selectQuery);
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                
                $countQuery = "SELECT COUNT(*) as total FROM `{$table}` WHERE {$whereClause}";
                $countStmt = $pdo->prepare($countQuery);
                $countStmt->execute();
                $countRow = $countStmt->fetch(PDO::FETCH_ASSOC);
                
                $result = [
                    'count' => $countRow['total'],
                    'sample' => $rows
                ];
            } catch (PDOException $e) {
                echo json_encode(['error' => 'SQL error: ' . $e->getMessage()]);
                exit();
            }
        } else {
            echo json_encode(['error' => 'Could not parse DELETE query: ' . $query]);
            exit();
        }
    }
    
    echo json_encode($result);
    exit();
} catch (PDOException $e) {
    
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit();
} catch (Exception $e) {
    
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
    exit();
}