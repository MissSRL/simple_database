<?php
session_start();
require_once 'includes/functions.php';

$dbConnection = checkDatabaseConnection();
$pdo = getDatabaseConnection();

$baseUrl = getBaseUrl();
$tables = getTables($pdo);


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_backup'])) {
    try {
        $selectedTables = $_POST['backup_tables'] ?? [];
        $includeStructure = isset($_POST['include_structure']);
        $includeData = isset($_POST['include_data']);
        
        if (empty($selectedTables)) {
            $_SESSION['error_message'] = 'Please select at least one table to backup.';
        } else {
            $backup = generateBackup($pdo, $selectedTables, $includeStructure, $includeData);
            
            
            $dbName = $dbConnection['database'];
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "backup_{$dbName}_{$timestamp}.sql";
            
            
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($backup));
            echo $backup;
            exit();
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Backup failed: ' . $e->getMessage();
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_backup'])) {
    try {
        if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error_message'] = 'Please select a valid backup file.';
        } else {
            $uploadedFile = $_FILES['backup_file']['tmp_name'];
            $sqlContent = file_get_contents($uploadedFile);
            
            if ($sqlContent === false) {
                $_SESSION['error_message'] = 'Failed to read backup file.';
            } else {
                $result = restoreBackup($pdo, $sqlContent);
                $_SESSION['success_message'] = "Backup restored successfully. {$result['statements']} statements executed.";
            }
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Restore failed: ' . $e->getMessage();
    }
    
    header('Location: ' . $baseUrl . 'backup');
    exit();
}

function generateBackup($pdo, $tables, $includeStructure = true, $includeData = true) {
    $backup = "-- Database Backup\n";
    $backup .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
    $backup .= "-- Database: " . $_SESSION['db_connection']['database'] . "\n\n";
    
    $backup .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
    
    foreach ($tables as $table) {
        $backup .= "-- Table: {$table}\n";
        
        if ($includeStructure) {
            
            $stmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
            $row = $stmt->fetch();
            $backup .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $backup .= $row['Create Table'] . ";\n\n";
        }
        
        if ($includeData) {
            
            $stmt = $pdo->query("SELECT * FROM `{$table}`");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($rows)) {
                $columns = array_keys($rows[0]);
                $columnList = '`' . implode('`, `', $columns) . '`';
                
                $backup .= "INSERT INTO `{$table}` ({$columnList}) VALUES\n";
                
                $values = [];
                foreach ($rows as $row) {
                    $rowValues = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $rowValues[] = 'NULL';
                        } else {
                            $rowValues[] = "'" . addslashes($value) . "'";
                        }
                    }
                    $values[] = '(' . implode(', ', $rowValues) . ')';
                }
                
                $backup .= implode(",\n", $values) . ";\n\n";
            }
        }
    }
    
    $backup .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    
    return $backup;
}

function restoreBackup($pdo, $sqlContent) {
    
    $statements = preg_split('/;\s*\n/', $sqlContent);
    $executedCount = 0;
    
    $pdo->beginTransaction();
    
    try {
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement) && !preg_match('/^--/', $statement)) {
                $pdo->exec($statement);
                $executedCount++;
            }
        }
        
        $pdo->commit();
        return ['statements' => $executedCount];
    } catch (Exception $e) {
        $pdo->rollback();
        throw $e;
    }
}
?>

<?= generateHead("Backup & Restore") ?>

<div class="container-fluid">
    <?= generateNavigation('backup') ?>
    
    <!-- Backup Warning -->
    <div class="alert alert-warning alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle-fill"></i> 
        <strong>Important:</strong> It's highly recommended to create a backup of your database before making any changes. 
        This ensures you can restore your data if something goes wrong.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle"></i> <?= htmlspecialchars($_SESSION['success_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($_SESSION['error_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-2 d-none d-md-block">
            <?= generateSidebar($pdo) ?>
        </div>

        <?= generateMobileSidebar($pdo) ?>

        <div class="col-12 col-md-9">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-cloud-download"></i> Create Backup
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="backupForm">
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="bi bi-table"></i> Select Tables</h6>
                                <div class="border rounded p-3 mb-3" style="max-height: 300px; overflow-y: auto;">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="selectAllTables" onchange="toggleAllTables()">
                                        <label class="form-check-label fw-bold" for="selectAllTables">
                                            Select All Tables
                                        </label>
                                    </div>
                                    <hr>
                                    <?php foreach ($tables as $table): ?>
                                        <div class="form-check">
                                            <input class="form-check-input table-checkbox" type="checkbox" name="backup_tables[]" value="<?= htmlspecialchars($table) ?>" id="table_<?= htmlspecialchars($table) ?>">
                                            <label class="form-check-label" for="table_<?= htmlspecialchars($table) ?>">
                                                <?= htmlspecialchars($table) ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h6><i class="bi bi-gear"></i> Backup Options</h6>
                                <div class="border rounded p-3 mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="include_structure" id="includeStructure" checked>
                                        <label class="form-check-label" for="includeStructure">
                                            <strong>Include Table Structure</strong>
                                            <small class="text-muted d-block">Include CREATE TABLE statements</small>
                                        </label>
                                    </div>
                                    <div class="form-check mt-3">
                                        <input class="form-check-input" type="checkbox" name="include_data" id="includeData" checked>
                                        <label class="form-check-label" for="includeData">
                                            <strong>Include Table Data</strong>
                                            <small class="text-muted d-block">Include INSERT statements with data</small>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i>
                                    <strong>Backup will include:</strong>
                                    <ul class="mb-0 mt-2">
                                        <li>SQL statements to recreate selected tables</li>
                                        <li>All data from selected tables (if enabled)</li>
                                        <li>Proper foreign key handling</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end">
                            <button type="submit" name="create_backup" class="btn btn-primary">
                                <i class="bi bi-download"></i> Download Backup
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-cloud-upload"></i> Restore Backup
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Warning:</strong> Restoring a backup will overwrite existing data. Make sure to create a backup before restoring.
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data" id="restoreForm">
                        <div class="mb-3">
                            <label for="backupFile" class="form-label">
                                <i class="bi bi-file-earmark-arrow-up"></i> Select Backup File
                            </label>
                            <input type="file" class="form-control" id="backupFile" name="backup_file" accept=".sql" required>
                            <div class="form-text">Select a .sql backup file to restore</div>
                        </div>
                        
                        <div class="d-flex justify-content-end">
                            <button type="submit" name="restore_backup" class="btn btn-warning" onclick="return confirmRestore()">
                                <i class="bi bi-upload"></i> Restore Backup
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= getBaseUrl() ?>assets/js/backup.js"></script>
<script>

initializeBackupPage();
</script>

<?= generateFooter() ?>
