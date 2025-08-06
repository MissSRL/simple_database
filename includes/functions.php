<?php
session_start();

if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 300) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

if (!headers_sent()) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.use_strict_mode', 1);
}

function checkDatabaseConnection() {
    if (!isset($_SESSION['db_connection'])) {
        $baseUrl = getBaseUrl();
        header('Location: ' . $baseUrl);
        exit();
    }
    return $_SESSION['db_connection'];
}


function getDatabaseConnection() {
    $conn = $_SESSION['db_connection'];
    try {
        $dsn = "mysql:host={$conn['host']};port={$conn['port']};dbname={$conn['database']};charset=utf8mb4";
        $pdo = new PDO($dsn, $conn['username'], $conn['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception('Database connection failed: ' . $e->getMessage());
    }
}


function getTables($pdo) {
    $stmt = $pdo->query("SHOW TABLES");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}


function getTableColumns($pdo, $table) {
    $stmt = $pdo->prepare("DESCRIBE `{$table}`");
    $stmt->execute();
    return $stmt->fetchAll();
}


function getTableData($pdo, $table, $page = 1, $limit = 50) {
    $offset = ($page - 1) * $limit;
    
    
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM `{$table}`");
    $countStmt->execute();
    $total = $countStmt->fetchColumn();
    
    
    $stmt = $pdo->prepare("SELECT * FROM `{$table}` LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $data = $stmt->fetchAll();
    
    return [
        'data' => $data,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'totalPages' => ceil($total / $limit)
    ];
}


function validateTable($pdo, $table) {
    $tables = getTables($pdo);
    return in_array($table, $tables);
}

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function checkRateLimit() {
    $key = 'connection_attempts_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'last_attempt' => time()];
    }
    
    $attempts = &$_SESSION[$key];
    $now = time();
    
    if ($now - $attempts['last_attempt'] > 900) {
        $attempts['count'] = 0;
    }
    
    $attempts['last_attempt'] = $now;
    $attempts['count']++;
    
    return $attempts['count'] <= 10;
}


function generateNavigation($currentPage = '') {
    $conn = $_SESSION['db_connection'] ?? null;
    $connectionInfo = $conn ? "{$conn['username']}@{$conn['host']}:{$conn['port']}/{$conn['database']}" : '';
    
    $tablesActive = in_array($currentPage, ['view', 'insert']) ? 'active' : '';
    $queryActive = ($currentPage === 'query') ? 'active' : '';
    $backupActive = ($currentPage === 'backup') ? 'active' : '';
    
    
    $navbarClass = 'navbar-dark bg-dark';
    
    
    $baseUrl = getBaseUrl();
    
    return '
    <nav class="navbar navbar-expand-lg ' . $navbarClass . ' mb-4">
        <div class="container-fluid">
            <!-- Mobile sidebar toggle button -->
            <button class="mobile-toggle-btn d-lg-none" onclick="toggleMobileSidebar()" aria-label="Toggle sidebar">
                <i class="bi bi-list"></i>
            </button>
            
            <a class="navbar-brand" href="' . $baseUrl . '">
                <i class="bi bi-database-gear"></i> 
                <span class="d-none d-sm-inline">Simple Database</span>
                <span class="d-sm-none">DB</span>
            </a>
            
            <!-- Mobile menu toggle -->
            <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <i class="bi bi-three-dots-vertical"></i>
            </button>
            
            <!-- Collapsible menu -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <!-- Main Menu -->
                <div class="navbar-nav me-auto">
                    <a class="nav-link ' . $tablesActive . '" href="' . $baseUrl . 'view">
                        <i class="bi bi-table"></i> <span class="nav-text">Tables</span>
                    </a>
                    <a class="nav-link ' . $queryActive . '" href="' . $baseUrl . 'query">
                        <i class="bi bi-search"></i> <span class="nav-text">Query Builder</span>
                    </a>
                    <a class="nav-link ' . $backupActive . '" href="' . $baseUrl . 'backup">
                        <i class="bi bi-cloud-download"></i> <span class="nav-text">Backup & Restore</span>
                    </a>
                </div>
                
                <!-- Right side - connection info and disconnect -->
                <div class="navbar-nav ms-auto">
                    <span class="navbar-text me-3 d-none d-lg-block">' . htmlspecialchars($connectionInfo) . '</span>
                    <a class="nav-link d-lg-none" href="' . $baseUrl . '?disconnect=1">
                        <i class="bi bi-box-arrow-right"></i> <span class="nav-text">Disconnect</span>
                    </a>
                    <a class="nav-button btn btn-outline-light btn-sm d-none d-lg-inline-flex" href="' . $baseUrl . '?disconnect=1">
                        <i class="bi bi-box-arrow-right"></i> Disconnect
                    </a>
                </div>
            </div>
        </div>
    </nav>';
}


function generateSidebar($pdo, $currentTable = '') {
    $tables = getTables($pdo);
    $baseUrl = getBaseUrl();
    
    $sidebar = '
    <div class="card">
        <div class="card-header">
            <h6 class="mb-0"><i class="bi bi-list"></i> Tables <span class="badge bg-secondary ms-2">' . count($tables) . '</span></h6>
        </div>
        <div class="card-body p-2">';
    
    if (empty($tables)) {
        $sidebar .= '
            <div class="text-center text-muted p-3">
                <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                <p class="mt-2 mb-0">No tables found</p>
                <small>Create your first table to get started</small>
            </div>';
    } else {
        $sidebar .= '
            <div class="list-group list-group-flush">';
        
        foreach ($tables as $table) {
            $active = ($table === $currentTable) ? 'active' : '';
            $sidebar .= '
                <a href="' . $baseUrl . 'view?table=' . urlencode($table) . '" class="list-group-item list-group-item-action ' . $active . '">
                    <i class="bi bi-table me-2"></i>' . htmlspecialchars($table) . '
                </a>';
        }
        
        $sidebar .= '
            </div>';
    }
    
    $sidebar .= '
        </div>
    </div>';
    
    return $sidebar;
}

function generateMobileSidebar($pdo, $currentTable = '') {
    $tables = getTables($pdo);
    $baseUrl = getBaseUrl();
    
    $mobileSidebar = '
        <!-- Mobile sidebar overlay -->
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeMobileSidebar()"></div>
        
        <!-- Mobile sidebar -->
        <div class="sidebar-mobile" id="mobileSidebar">
            <div class="sidebar-header">
                <h6 class="mb-0">
                    <i class="bi bi-list"></i> Tables 
                    <span class="badge bg-secondary ms-2">' . count($tables) . '</span>
                </h6>
                <button class="sidebar-close" onclick="closeMobileSidebar()">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div class="p-2">';
    
    if (empty($tables)) {
        $mobileSidebar .= '
                <div class="text-center text-muted p-3">
                    <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                    <p class="mt-2 mb-0">No tables found</p>
                    <small>Create your first table to get started</small>
                </div>';
    } else {
        $mobileSidebar .= '
                <div class="list-group list-group-flush">';
        
        foreach ($tables as $table) {
            $active = ($table === $currentTable) ? 'active' : '';
            $mobileSidebar .= '
                    <a href="' . $baseUrl . 'view?table=' . urlencode($table) . '" 
                       class="list-group-item list-group-item-action ' . $active . '"
                       onclick="closeMobileSidebar()">
                        <i class="bi bi-table me-2"></i>' . htmlspecialchars($table) . '
                    </a>';
        }
        
        $mobileSidebar .= '
                </div>';
    }
    
    $mobileSidebar .= '
            </div>
        </div>';
    
    return $mobileSidebar;
}


function generateHead($title = 'Simple Database') {
    $baseUrl = getBaseUrl();
    return '
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title) . '</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="' . $baseUrl . 'assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-dark">';
}

function getBaseUrl() {
    
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    
    
    if (substr($scriptDir, -1) !== '/') {
        $scriptDir .= '/';
    }
    
    
    if ($scriptDir === '//') {
        $scriptDir = '/';
    }
    
    return $scriptDir;
}

function downloadBackup($backupContent, $databaseName) {
    $filename = "backup_{$databaseName}_" . date('Y-m-d_H-i-s') . ".sql";
    
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($backupContent));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    echo $backupContent;
    exit();
}

function generateFooter() {
    $baseUrl = getBaseUrl();
    $currentYear = date('Y');
    return '
    <!-- Site Footer - Fixed -->
    <footer class="site-footer fixed-bottom">
        <div class="container-fluid text-center">
            <small>
                &copy; ' . $currentYear . ' 5earle.com - Simple Database | 
                <a href="https://github.com/MissSRL/simple_database" target="_blank" rel="noopener noreferrer">
                    <i class="bi bi-github"></i> Host Your Own
                </a>
            </small>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="' . $baseUrl . 'assets/js/mobile.js"></script>
</body>
</html>';
}
?>
