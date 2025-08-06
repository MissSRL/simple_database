<?php
session_start();
require_once 'includes/functions.php';


if (isset($_GET['disconnect'])) {
    session_destroy();
    $baseUrl = getBaseUrl();
    header('Location: ' . $baseUrl);
    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['connect'])) {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error_message = 'Security error: Invalid request token. Please try again.';
    }
    
    elseif (!checkRateLimit()) {
        $error_message = 'Too many connection attempts. Please wait 15 minutes before trying again.';
    }
    else {
        $connection = [
            'host' => sanitizeInput($_POST['host'] ?? 'localhost'),
            'username' => sanitizeInput($_POST['username'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'database' => sanitizeInput($_POST['database'] ?? ''),
            'port' => filter_var($_POST['port'] ?? 3306, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1, 'max_range' => 65535, 'default' => 3306]
            ])
        ];
        
        if (empty($connection['username']) || empty($connection['database'])) {
            $error_message = 'Username and database name are required.';
        }
        else {
            try {
                $dsn = "mysql:host={$connection['host']};port={$connection['port']};dbname={$connection['database']};charset=utf8mb4";
                $pdo = new PDO($dsn, $connection['username'], $connection['password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 5,
                    PDO::ATTR_EMULATE_PREPARES => false 
                ]);
                
                $_SESSION['db_connection'] = $connection;
                $_SESSION['success_message'] = 'Connected to database successfully! Please create a backup to secure your data.';
                
                $baseUrl = getBaseUrl();
                header('Location: ' . $baseUrl . 'backup');
                exit();
            } catch (PDOException $e) {
                error_log("Database connection failed: " . $e->getMessage());
                $error_message = 'Connection failed. Please check your credentials and try again.';
            }
        }
    }
}

if (isset($_SESSION['db_connection'])) {
    $baseUrl = getBaseUrl();
    header('Location: ' . $baseUrl . 'view');
    exit();
}

$baseUrl = getBaseUrl();
?>

<?= generateHead('Simple Database - Connect') ?>

<div class="container-fluid" style="min-height: calc(100vh - 150px); padding-top: 2rem;">
    <div class="d-flex justify-content-center">
        <div class="w-100" style="max-width: 900px;">
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card h-100" style="border-top-right-radius: 0; border-bottom-right-radius: 0; border-right: none;">
                        <div class="card-body d-flex flex-column justify-content-center">
                            <div class="text-center mb-3">
                                <i class="bi bi-shield-check" style="font-size: 2.5rem; color: var(--bs-primary);"></i>
                                <h6 class="mb-3 mt-2" style="color: var(--bs-primary);">Secure by Design</h6>
                            </div>
                            <ul class="list-unstyled small">
                                <li class="mb-2">
                                    <i class="bi bi-check-circle me-2" style="color: var(--bs-success);"></i>
                                    Credentials in secure PHP sessions
                                </li>
                                <li class="mb-2">
                                    <i class="bi bi-check-circle me-2" style="color: var(--bs-success);"></i>
                                    SQL injection & XSS protection
                                </li>
                                <li class="mb-2">
                                    <i class="bi bi-check-circle me-2" style="color: var(--bs-success);"></i>
                                    Session-based authentication
                                </li>
                            </ul>
                            <div class="alert py-2 mt-3" style="font-size: 0.8rem; background-color: rgba(28, 77, 43, 0.2); border: 1px solid var(--bs-success); color: #c3e6cb;">
                                <i class="bi bi-info-circle me-1"></i>
                                Credentials are temporary and automatically cleared on disconnect.
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-8">
            <div class="card h-100" style="border-top-left-radius: 0; border-bottom-left-radius: 0;">
                <div class="card-header text-center">
                    <h4 class="mb-0">
                        <i class="bi bi-database"></i> Simple Database
                    </h4>
                    <small class="text-muted">Connect to your database</small>
                </div>
                <div class="card-body">
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="host" class="form-label">Host</label>
                                <input type="text" class="form-control" id="host" name="host" value="localhost" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="port" class="form-label">Port</label>
                                <input type="number" class="form-control" id="port" name="port" value="3306" min="1" max="65535">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password">
                        </div>
                        <div class="mb-3">
                            <label for="database" class="form-label">Database Name</label>
                            <input type="text" class="form-control" id="database" name="database" required>
                        </div>
                        
                        <button type="submit" name="connect" class="btn w-100" style="background-color: var(--bs-primary); border-color: var(--bs-primary); color: white;">
                            <i class="bi bi-plug"></i> Connect to Database
                        </button>
                    </form>
                </div>
                <div class="card-footer text-center text-muted">
                    <small>Enter your database credentials to get started</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Screenshots Gallery Section -->
    <div class="row w-100 mt-4" style="max-width: 900px;">
        <div class="col-12">
            <div class="text-center mb-4">
                <h6 class="text-muted mb-2">
                    <i class="bi bi-images"></i> Preview Simple Database Features
                </h6>
            </div>
            
            <div class="screenshot-gallery mx-auto">
                <div class="row g-3">
                    <div class="col-6 col-md-4 col-lg-2">
                        <img src="<?= $baseUrl ?>images/5earleDatabase6.png" class="screenshot-thumb" alt="Table Management" data-bs-toggle="modal" data-bs-target="#screenshotModal" data-screenshot="1" title="Query Builder - Build SQL queries visually">
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <img src="<?= $baseUrl ?>images/5earleDatabase2.png" class="screenshot-thumb" alt="Query Builder" data-bs-toggle="modal" data-bs-target="#screenshotModal" data-screenshot="2" title="Backup & Restore - Secure your data">
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <img src="<?= $baseUrl ?>images/5earleDatabase3.png" class="screenshot-thumb" alt="Data Management" data-bs-toggle="modal" data-bs-target="#screenshotModal" data-screenshot="3" title="Data Management - Insert, update, delete records">
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <img src="<?= $baseUrl ?>images/5earleDatabase4.png" class="screenshot-thumb" alt="Advanced Operations" data-bs-toggle="modal" data-bs-target="#screenshotModal" data-screenshot="4" title="Advanced Operations - Update records with easy query building">
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <img src="<?= $baseUrl ?>images/5earleDatabase5.png" class="screenshot-thumb" alt="Backup & Restore" data-bs-toggle="modal" data-bs-target="#screenshotModal" data-screenshot="5" title="Advanced Operations - Update records with easy query building">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Screenshot Lightbox Modal -->
<div class="modal fade" id="screenshotModal" tabindex="-1" aria-labelledby="screenshotModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content bg-dark">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="screenshotModalLabel">Screenshot Preview</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-0">
                <img id="modalScreenshot" src="" class="img-fluid" alt="Screenshot">
            </div>
            <div class="modal-footer border-secondary justify-content-between">
                <button type="button" class="btn btn-outline-light" id="prevModalBtn">
                    <i class="bi bi-chevron-left"></i> Previous
                </button>
                <small class="text-muted" id="screenshotCounter">1 of 6</small>
                <button type="button" class="btn btn-outline-light" id="nextModalBtn">
                    Next <i class="bi bi-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<script src="<?= $baseUrl ?>assets/js/homepage.js"></script>

<?= generateFooter() ?>
