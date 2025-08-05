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
            <a class="navbar-brand" href="' . $baseUrl . '">
                <i class="bi bi-database-gear"></i> Simple Database
            </a>
            
            <!-- Main Menu - Horizontal -->
            <div class="navbar-nav me-auto">
                <a class="nav-link ' . $tablesActive . '" href="' . $baseUrl . 'view">
                    <i class="bi bi-table"></i> Tables
                </a>
                <a class="nav-link ' . $queryActive . '" href="' . $baseUrl . 'query">
                    <i class="bi bi-search"></i> Query Builder
                </a>
                <a class="nav-link ' . $backupActive . '" href="' . $baseUrl . 'backup">
                    <i class="bi bi-cloud-download"></i> Backup & Restore
                </a>
            </div>
            
            <!-- Right side - connection info and disconnect -->
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">' . htmlspecialchars($connectionInfo) . '</span>
                <a class="nav-button btn btn-outline-light btn-sm" href="' . $baseUrl . '?disconnect=1">
                    <i class="bi bi-box-arrow-right"></i> Disconnect
                </a>
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
        <div class="card-body p-2">
            <div class="list-group list-group-flush">';
    
    foreach ($tables as $table) {
        $active = ($table === $currentTable) ? 'active' : '';
        $sidebar .= '
                <a href="' . $baseUrl . 'view?table=' . urlencode($table) . '" class="list-group-item list-group-item-action ' . $active . '">
                    <i class="bi bi-table me-2"></i>' . htmlspecialchars($table) . '
                </a>';
    }
    
    $sidebar .= '
            </div>
        </div>
    </div>';
    
    return $sidebar;
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

function generateFooter() {
    $baseUrl = getBaseUrl();
    $currentYear = date('Y');
    return '
    <!-- Site Footer - Fixed -->
    <footer class="site-footer fixed-bottom">
        <div class="footer-bottom">
            <div class="container-fluid">
                <small>&copy; ' . $currentYear . ' 5earle.com - Simple Database</small>
            </div>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>';
}
?>
