<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

class DatabaseManager {
    private $pdo;
    private $connected = false;
    
    public function __construct() {
        
    }
    
    public function connect($host, $database, $username, $password, $port = 3306) {
        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
            $this->pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
            $this->connected = true;
            return ['success' => true, 'message' => 'Connected successfully'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()];
        }
    }
    
    public function getTables() {
        if (!$this->connected) {
            return ['success' => false, 'message' => 'Not connected to database'];
        }
        
        try {
            $stmt = $this->pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            return ['success' => true, 'data' => $tables];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function getTableData($table, $limit = 100, $offset = 0) {
        if (!$this->connected) {
            return ['success' => false, 'message' => 'Not connected to database'];
        }
        
        try {
            
            if (!$this->tableExists($table)) {
                return ['success' => false, 'message' => 'Table does not exist'];
            }
            
            $sql = "SELECT * FROM `{$table}` LIMIT :limit OFFSET :offset";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $data = $stmt->fetchAll();
            
            
            $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM `{$table}`");
            $countStmt->execute();
            $total = $countStmt->fetchColumn();
            
            return [
                'success' => true, 
                'data' => $data, 
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset
            ];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function getTableColumns($table) {
        if (!$this->connected) {
            return ['success' => false, 'message' => 'Not connected to database'];
        }
        
        try {
            if (!$this->tableExists($table)) {
                return ['success' => false, 'message' => 'Table does not exist'];
            }
            
            $stmt = $this->pdo->prepare("DESCRIBE `{$table}`");
            $stmt->execute();
            $columns = $stmt->fetchAll();
            
            return ['success' => true, 'data' => $columns];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function executeQuery($sql, $params = []) {
        if (!$this->connected) {
            return ['success' => false, 'message' => 'Not connected to database'];
        }
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            
            if (stripos(trim($sql), 'SELECT') === 0) {
                $data = $stmt->fetchAll();
                return [
                    'success' => true, 
                    'data' => $data, 
                    'rowCount' => count($data),
                    'query' => $sql
                ];
            } else {
                $rowCount = $stmt->rowCount();
                return [
                    'success' => true, 
                    'message' => 'Query executed successfully', 
                    'rowCount' => $rowCount,
                    'query' => $sql
                ];
            }
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'query' => $sql];
        }
    }
    
    public function insertRecord($table, $data) {
        if (!$this->connected) {
            return ['success' => false, 'message' => 'Not connected to database'];
        }
        
        try {
            if (!$this->tableExists($table)) {
                return ['success' => false, 'message' => 'Table does not exist'];
            }
            
            $columns = array_keys($data);
            $placeholders = array_map(function($col) { return ":{$col}"; }, $columns);
            
            $sql = "INSERT INTO `{$table}` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $placeholders) . ")";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($data);
            
            return [
                'success' => true, 
                'message' => 'Record inserted successfully',
                'insertId' => $this->pdo->lastInsertId(),
                'query' => $sql
            ];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function updateRecord($table, $data, $where) {
        if (!$this->connected) {
            return ['success' => false, 'message' => 'Not connected to database'];
        }
        
        try {
            if (!$this->tableExists($table)) {
                return ['success' => false, 'message' => 'Table does not exist'];
            }
            
            $setParts = [];
            foreach (array_keys($data) as $column) {
                $setParts[] = "`{$column}` = :{$column}";
            }
            
            $whereParts = [];
            foreach (array_keys($where) as $column) {
                $whereParts[] = "`{$column}` = :where_{$column}";
            }
            
            $sql = "UPDATE `{$table}` SET " . implode(', ', $setParts) . " WHERE " . implode(' AND ', $whereParts);
            
            
            $params = $data;
            foreach ($where as $key => $value) {
                $params["where_{$key}"] = $value;
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return [
                'success' => true, 
                'message' => 'Record updated successfully',
                'rowCount' => $stmt->rowCount(),
                'query' => $sql
            ];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function deleteRecord($table, $where) {
        if (!$this->connected) {
            return ['success' => false, 'message' => 'Not connected to database'];
        }
        
        try {
            if (!$this->tableExists($table)) {
                return ['success' => false, 'message' => 'Table does not exist'];
            }
            
            $whereParts = [];
            foreach (array_keys($where) as $column) {
                $whereParts[] = "`{$column}` = :{$column}";
            }
            
            $sql = "DELETE FROM `{$table}` WHERE " . implode(' AND ', $whereParts);
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($where);
            
            return [
                'success' => true, 
                'message' => 'Record deleted successfully',
                'rowCount' => $stmt->rowCount(),
                'query' => $sql
            ];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    private function tableExists($table) {
        try {
            $stmt = $this->pdo->prepare("SHOW TABLES LIKE :table");
            $stmt->execute([':table' => $table]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }
}


$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

$db = new DatabaseManager();

switch ($method) {
    case 'POST':
        $action = $input['action'] ?? '';
        
        switch ($action) {
            case 'connect':
                $result = $db->connect(
                    $input['host'], 
                    $input['database'], 
                    $input['username'], 
                    $input['password'], 
                    $input['port'] ?? 3306
                );
                break;
                
            case 'getTables':
                $result = $db->connect(
                    $input['host'], 
                    $input['database'], 
                    $input['username'], 
                    $input['password'], 
                    $input['port'] ?? 3306
                );
                if ($result['success']) {
                    $result = $db->getTables();
                }
                break;
                
            case 'getTableData':
                $result = $db->connect(
                    $input['host'], 
                    $input['database'], 
                    $input['username'], 
                    $input['password'], 
                    $input['port'] ?? 3306
                );
                if ($result['success']) {
                    $result = $db->getTableData(
                        $input['table'], 
                        $input['limit'] ?? 100, 
                        $input['offset'] ?? 0
                    );
                }
                break;
                
            case 'getTableColumns':
                $result = $db->connect(
                    $input['host'], 
                    $input['database'], 
                    $input['username'], 
                    $input['password'], 
                    $input['port'] ?? 3306
                );
                if ($result['success']) {
                    $result = $db->getTableColumns($input['table']);
                }
                break;
                
            case 'executeQuery':
                $result = $db->connect(
                    $input['host'], 
                    $input['database'], 
                    $input['username'], 
                    $input['password'], 
                    $input['port'] ?? 3306
                );
                if ($result['success']) {
                    $result = $db->executeQuery($input['sql'], $input['params'] ?? []);
                }
                break;
                
            case 'insert':
                $result = $db->connect(
                    $input['host'], 
                    $input['database'], 
                    $input['username'], 
                    $input['password'], 
                    $input['port'] ?? 3306
                );
                if ($result['success']) {
                    $result = $db->insertRecord($input['table'], $input['data']);
                }
                break;
                
            case 'update':
                $result = $db->connect(
                    $input['host'], 
                    $input['database'], 
                    $input['username'], 
                    $input['password'], 
                    $input['port'] ?? 3306
                );
                if ($result['success']) {
                    $result = $db->updateRecord($input['table'], $input['data'], $input['where']);
                }
                break;
                
            case 'delete':
                $result = $db->connect(
                    $input['host'], 
                    $input['database'], 
                    $input['username'], 
                    $input['password'], 
                    $input['port'] ?? 3306
                );
                if ($result['success']) {
                    $result = $db->deleteRecord($input['table'], $input['where']);
                }
                break;
                
            default:
                $result = ['success' => false, 'message' => 'Invalid action'];
        }
        break;
        
    default:
        $result = ['success' => false, 'message' => 'Method not allowed'];
}

echo json_encode($result);
?>
