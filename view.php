<?php
session_start();
require_once 'includes/functions.php';

$dbConnection = checkDatabaseConnection();
$pdo = getDatabaseConnection();


$table = $_GET['table'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$baseUrl = getBaseUrl();


$tables = getTables($pdo);

if (!$table) {
    if (!empty($tables)) {
        header('Location: ' . $baseUrl . 'view?table=' . urlencode($tables[0]));
        exit();
    }
    
}


if ($table && !validateTable($pdo, $table)) {
    $_SESSION['error_message'] = 'Table not found: ' . htmlspecialchars($table);
    header('Location: ' . $baseUrl . 'view');
    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_table'])) {
    try {
        $tableName = trim($_POST['table_name']);
        $columns = $_POST['columns'] ?? [];
        
        if (empty($tableName)) {
            throw new Exception('Table name is required');
        }
        
        
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $tableName)) {
            throw new Exception('Table name must start with a letter and contain only letters, numbers, and underscores');
        }
        
        $sql = "CREATE TABLE `{$tableName}` (";
        $columnDefinitions = [];
        
        foreach ($columns as $index => $column) {
            $colName = trim($column['name']);
            $colType = trim($column['type']);
            $colLength = trim($column['length'] ?? '');
            $colNull = isset($column['null']) ? 'NULL' : 'NOT NULL';
            $colDefault = trim($column['default'] ?? '');
            $colPrimary = isset($column['primary']);
            $colAutoIncrement = isset($column['auto_increment']);
            
            if (empty($colName) || empty($colType)) {
                continue;
            }
            
            
            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $colName)) {
                throw new Exception("Column name '{$colName}' is invalid");
            }
            
            $definition = "`{$colName}` {$colType}";
            
            
            if ($colLength && in_array(strtoupper($colType), ['VARCHAR', 'CHAR', 'DECIMAL', 'NUMERIC'])) {
                $definition .= "({$colLength})";
            }
            
            $definition .= " {$colNull}";
            
            if ($colAutoIncrement) {
                $definition .= " AUTO_INCREMENT";
            }
            
            if ($colDefault) {
                if (strtoupper($colDefault) === 'CURRENT_TIMESTAMP') {
                    $definition .= " DEFAULT CURRENT_TIMESTAMP";
                } else {
                    $definition .= " DEFAULT '{$colDefault}'";
                }
            }
            
            if ($colPrimary) {
                $definition .= " PRIMARY KEY";
            }
            
            $columnDefinitions[] = $definition;
        }
        
        if (empty($columnDefinitions)) {
            throw new Exception('At least one column is required');
        }
        
        $sql .= implode(', ', $columnDefinitions) . ")";
        
        $pdo->exec($sql);
        
        $_SESSION['success_message'] = "Table '{$tableName}' created successfully.";
        header('Location: ' . $baseUrl . 'view?table=' . urlencode($tableName));
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Table creation failed: ' . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_record'])) {
    try {
        $whereConditions = [];
        $params = [];
        
        
        if (isset($_POST['where_column']) && is_array($_POST['where_column'])) {
            for ($i = 0; $i < count($_POST['where_column']); $i++) {
                $column = $_POST['where_column'][$i];
                $operator = $_POST['where_operator'][$i] ?? '=';
                $value = $_POST['where_value'][$i] ?? '';
                
                if (!empty($column)) {
                    if ($operator === 'IS NULL' || $operator === 'IS NOT NULL') {
                        $whereConditions[] = "`{$column}` {$operator}";
                    } elseif (!empty($value)) {
                        $paramKey = "where_{$i}";
                        $whereConditions[] = "`{$column}` {$operator} :{$paramKey}";
                        $params[$paramKey] = $value;
                    }
                }
            }
        } 
        
        else if (isset($_POST['where']) && is_array($_POST['where'])) {
            foreach ($_POST['where'] as $key => $value) {
                if (!empty($value)) {
                    $whereConditions[] = "`{$key}` = :where_{$key}";
                    $params["where_{$key}"] = $value;
                }
            }
        }
        
        if (!empty($whereConditions)) {
            $sql = "DELETE FROM `{$table}` WHERE " . implode(' AND ', $whereConditions);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $_SESSION['success_message'] = "Deleted {$stmt->rowCount()} record(s) successfully.";
        } else {
            $_SESSION['error_message'] = 'No conditions specified. For safety, DELETE operations require at least one WHERE condition.';
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Delete failed: ' . $e->getMessage();
    }
    
    header('Location: ' . $baseUrl . 'view?table=' . urlencode($table) . '&page=' . $page);
    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_record'])) {
    try {
        $setData = [];
        $whereConditions = [];
        $params = [];
        
        
        if (isset($_POST['set_column']) && is_array($_POST['set_column'])) {
            for ($i = 0; $i < count($_POST['set_column']); $i++) {
                $column = $_POST['set_column'][$i];
                $value = $_POST['set_value'][$i] ?? '';
                
                if (!empty($column) && $value !== '') {
                    $paramKey = "set_{$i}";
                    $setData[] = "`{$column}` = :{$paramKey}";
                    $params[$paramKey] = $value;
                }
            }
        }
        
        else if (isset($_POST['set']) && is_array($_POST['set'])) {
            foreach ($_POST['set'] as $key => $value) {
                if (!empty($value)) {
                    $setData[] = "`{$key}` = :set_{$key}";
                    $params["set_{$key}"] = $value;
                }
            }
        }
        
        
        if (isset($_POST['where_column']) && is_array($_POST['where_column'])) {
            for ($i = 0; $i < count($_POST['where_column']); $i++) {
                $column = $_POST['where_column'][$i];
                $operator = $_POST['where_operator'][$i] ?? '=';
                $value = $_POST['where_value'][$i] ?? '';
                
                if (!empty($column)) {
                    if ($operator === 'IS NULL' || $operator === 'IS NOT NULL') {
                        $whereConditions[] = "`{$column}` {$operator}";
                    } elseif (!empty($value)) {
                        $paramKey = "where_{$i}";
                        $whereConditions[] = "`{$column}` {$operator} :{$paramKey}";
                        $params[$paramKey] = $value;
                    }
                }
            }
        }
        
        else if (isset($_POST['where']) && is_array($_POST['where'])) {
            foreach ($_POST['where'] as $key => $value) {
                if (!empty($value)) {
                    $whereConditions[] = "`{$key}` = :where_{$key}";
                    $params["where_{$key}"] = $value;
                }
            }
        }
        
        if (!empty($setData) && !empty($whereConditions)) {
            $sql = "UPDATE `{$table}` SET " . implode(', ', $setData) . " WHERE " . implode(' AND ', $whereConditions);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $_SESSION['success_message'] = "Updated {$stmt->rowCount()} record(s) successfully.";
        } else {
            if (empty($setData)) {
                $_SESSION['error_message'] = 'No update values specified.';
            } elseif (empty($whereConditions)) {
                $_SESSION['error_message'] = 'No conditions specified. For safety, UPDATE operations require at least one WHERE condition.';
            }
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Update failed: ' . $e->getMessage();
    }
    
    header('Location: ' . $baseUrl . 'view?table=' . urlencode($table) . '&page=' . $page);
    exit();
}


$tableData = null;
$columns = [];

if (!empty($tables) && $table) {
    $tableData = getTableData($pdo, $table, $page);
    $columns = getTableColumns($pdo, $table);
}


$pageTitle = empty($tables) ? "Create Table" : ($table ? "View Table: {$table}" : "Database Tables");
?>

<?= generateHead($pageTitle); ?>

<div class="container-fluid">
    <?= generateNavigation('view') ?>
    
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
        <!-- Desktop sidebar -->
        <div class="col-md-2 d-none d-md-block">
            <?= generateSidebar($pdo, $table) ?>
        </div>

        <?= generateMobileSidebar($pdo, $table) ?>

        <div class="col-12 col-md-9">
            <?php if (empty($tables)): ?>
                <!-- No tables exist - show create table interface -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="bi bi-plus-circle"></i> Create Your First Table
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            <strong>Welcome!</strong> No tables found in this database. Create your first table to get started.
                        </div>
                        
                        <form method="POST">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="table_name" class="form-label">Table Name</label>
                                    <input type="text" class="form-control" id="table_name" name="table_name" required 
                                           placeholder="e.g., users, products, orders">
                                    <div class="form-text">Must start with a letter, contain only letters, numbers, and underscores</div>
                                </div>
                            </div>
                            
                            <h6 class="text-primary mb-3">Table Columns</h6>
                            <div id="columnsContainer">
                                <div class="row mb-2 column-row">
                                    <div class="col-md-3">
                                        <input type="text" class="form-control" name="columns[0][name]" placeholder="Column name" required>
                                    </div>
                                    <div class="col-md-2">
                                        <select class="form-select" name="columns[0][type]" required>
                                            <option value="">Type</option>
                                            <option value="INT">INT</option>
                                            <option value="BIGINT">BIGINT</option>
                                            <option value="VARCHAR">VARCHAR</option>
                                            <option value="TEXT">TEXT</option>
                                            <option value="DECIMAL">DECIMAL</option>
                                            <option value="DATETIME">DATETIME</option>
                                            <option value="DATE">DATE</option>
                                            <option value="TIME">TIME</option>
                                            <option value="BOOLEAN">BOOLEAN</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <input type="text" class="form-control" name="columns[0][length]" placeholder="Length">
                                    </div>
                                    <div class="col-md-2">
                                        <input type="text" class="form-control" name="columns[0][default]" placeholder="Default">
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="columns[0][null]" id="null_0">
                                            <label class="form-check-label" for="null_0">NULL</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="columns[0][primary]" id="primary_0">
                                            <label class="form-check-label" for="primary_0">PK</label>
                                        </div>
                                    </div>
                                    <div class="col-md-1">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="columns[0][auto_increment]" id="ai_0">
                                            <label class="form-check-label" for="ai_0">AI</label>
                                        </div>
                                        <button type="button" class="btn btn-outline-success btn-sm mt-1" onclick="addColumn()">
                                            <i class="bi bi-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="alert alert-warning">
                                <i class="bi bi-lightbulb"></i>
                                <strong>Tip:</strong> PK = Primary Key, AI = Auto Increment. Most tables should have an auto-incrementing primary key.
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" name="create_table" class="btn btn-primary">
                                    <i class="bi bi-plus-circle"></i> Create Table
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="addSampleColumns()">
                                    <i class="bi bi-magic"></i> Add Sample Columns
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <!-- Tables exist - show normal table view -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="bi bi-table"></i> <?= htmlspecialchars($table) ?>
                            <?php if ($tableData): ?>
                                <span class="badge bg-secondary ms-2"><?= number_format($tableData['total']) ?> records</span>
                            <?php endif; ?>
                        </h6>
                        <?php if ($table): ?>
                            <div class="header-actions">
                                <a href="<?= $baseUrl ?>insert?table=<?= urlencode($table) ?>" class="btn btn-success btn-sm">
                                    <i class="bi bi-plus-circle"></i> Add Record
                                </a>
                                <button class="btn btn-warning btn-sm" onclick="toggleUpdateRecords()">
                                    <i class="bi bi-pencil"></i> Update Records
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="toggleDeleteRecords()">
                                    <i class="bi bi-trash"></i> Delete Records
                                </button>
                                <button class="btn btn-outline-primary btn-sm" onclick="location.reload()">
                                    <i class="bi bi-arrow-clockwise"></i> Refresh
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="collapse mt-3" id="updateRecordsSection">
                    <div class="card border-warning">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="mb-0">
                                <i class="bi bi-pencil-square"></i> Update Records
                            </h6>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="row mb-4">
                                    <div class="col-md-12">
                                        <h6 class="text-warning">Set Values:</h6>
                                        <div id="bulkUpdateSetFields">
                                            <div class="row mb-2 set-row">
                                                <div class="col-md-5">
                                                    <select class="form-select" name="set_column[]">
                                                        <option value="">Select Column</option>
                                                        <?php foreach ($columns as $column): ?>
                                                            <option value="<?= htmlspecialchars($column['Field']) ?>"><?= htmlspecialchars($column['Field']) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <input type="text" class="form-control" name="set_value[]" placeholder="New value">
                                                </div>
                                                <div class="col-md-1">
                                                    <button type="button" class="btn btn-outline-success" onclick="addSetField()">
                                                        <i class="bi bi-plus"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <div class="row">
                                    <div class="col-md-12">
                                        <h6 class="text-info">WHERE Conditions:</h6>
                                        <p class="text-muted small">Specify conditions to identify which records to update. Multiple conditions are joined with AND.</p>
                                        
                                        <div id="bulkUpdateWhereFields">
                                            <div class="row mb-2 where-row">
                                                <div class="col-md-4">
                                                    <select class="form-select" name="where_column[]">
                                                        <option value="">Select Column</option>
                                                        <?php foreach ($columns as $column): ?>
                                                            <option value="<?= htmlspecialchars($column['Field']) ?>"><?= htmlspecialchars($column['Field']) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-3">
                                                    <select class="form-select" name="where_operator[]">
                                                        <option value="=">=</option>
                                                        <option value="!=">!=</option>
                                                        <option value="<"><</option>
                                                        <option value=">">></option>
                                                        <option value="<="><=</option>
                                                        <option value=">=">>=</option>
                                                        <option value="LIKE">LIKE</option>
                                                        <option value="NOT LIKE">NOT LIKE</option>
                                                        <option value="IN">IN</option>
                                                        <option value="NOT IN">NOT IN</option>
                                                        <option value="IS NULL">IS NULL</option>
                                                        <option value="IS NOT NULL">IS NOT NULL</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <input type="text" class="form-control" name="where_value[]" placeholder="Value">
                                                </div>
                                                <div class="col-md-1">
                                                    <button type="button" class="btn btn-outline-success" onclick="addWhereField('update')">
                                                        <i class="bi bi-plus"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- SQL Preview -->
                                <div class="mt-4">
                                    <label class="form-label fw-bold">SQL Preview:</label>
                                    <div class="bg-dark p-3 rounded border">
                                        <pre id="updateSqlPreview" class="text-info mb-0" style="font-family: 'Courier New', monospace; font-size: 0.9rem; white-space: pre-wrap;">UPDATE `<?= $table ?>` SET ... WHERE ...</pre>
                                    </div>
                                </div>
                                
                                <!-- Preview Results -->
                                <div class="mt-3" id="updatePreviewResult">
                                    <div class="alert alert-info">
                                        Complete the form to see affected rows
                                    </div>
                                </div>
                                
                                <div class="d-flex gap-2 mt-3">
                                    <button type="button" class="btn btn-secondary" onclick="toggleUpdateRecords()">Cancel</button>
                                    <button type="submit" name="update_record" class="btn btn-warning">
                                        <i class="bi bi-pencil"></i> Update Records
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="collapse mt-3" id="deleteRecordsSection">
                    <div class="card border-danger">
                        <div class="card-header bg-danger text-white">
                            <h6 class="mb-0">
                                <i class="bi bi-trash"></i> Delete Records
                            </h6>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="alert alert-danger">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    <strong>Warning:</strong> This action cannot be undone! You are about to delete data from the database.
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-12">
                                        <h6 class="text-danger">WHERE Conditions:</h6>
                                        <p class="text-muted small">Specify conditions to identify which records to delete. Multiple conditions are joined with AND.</p>
                                        
                                        <div id="bulkDeleteWhereFields">
                                            <div class="row mb-2 where-row">
                                                <div class="col-md-4">
                                                    <select class="form-select" name="where_column[]">
                                                        <option value="">Select Column</option>
                                                        <?php foreach ($columns as $column): ?>
                                                            <option value="<?= htmlspecialchars($column['Field']) ?>"><?= htmlspecialchars($column['Field']) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-3">
                                                    <select class="form-select" name="where_operator[]">
                                                        <option value="=">=</option>
                                                        <option value="!=">!=</option>
                                                        <option value="<"><</option>
                                                        <option value=">">></option>
                                                        <option value="<="><=</option>
                                                        <option value=">=">>=</option>
                                                        <option value="LIKE">LIKE</option>
                                                        <option value="NOT LIKE">NOT LIKE</option>
                                                        <option value="IN">IN</option>
                                                        <option value="NOT IN">NOT IN</option>
                                                        <option value="IS NULL">IS NULL</option>
                                                        <option value="IS NOT NULL">IS NOT NULL</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <input type="text" class="form-control" name="where_value[]" placeholder="Value">
                                                </div>
                                                <div class="col-md-1">
                                                    <button type="button" class="btn btn-outline-success" onclick="addWhereField('delete')">
                                                        <i class="bi bi-plus"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-4">
                                    <label class="form-label fw-bold">SQL Preview:</label>
                                    <div class="bg-dark p-3 rounded border">
                                        <pre id="deleteSqlPreview" class="text-danger mb-0" style="font-family: 'Courier New', monospace; font-size: 0.9rem; white-space: pre-wrap;">DELETE FROM `<?= $table ?>` WHERE ...</pre>
                                    </div>
                                </div>
                                
                                <div class="mt-3" id="deletePreviewResult">
                                    <div class="alert alert-info">
                                        Complete the form to see affected rows
                                    </div>
                                </div>
                                
                                <div class="d-flex gap-2 mt-3">
                                    <button type="button" class="btn btn-secondary" onclick="toggleDeleteRecords()">Cancel</button>
                                    <button type="submit" name="delete_record" class="btn btn-danger">
                                        <i class="bi bi-trash"></i> Delete Records
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="card-body p-0">
                    <?php if (!empty($tableData['data'])): ?>
                        <div class="table-responsive" style="max-height: 600px;">
                            <table class="table table-dark table-striped table-hover mb-0">
                                <thead class="sticky-top">
                                    <tr>
                                        <?php foreach ($columns as $column): ?>
                                            <th style="min-width: 120px;">
                                                <?= htmlspecialchars($column['Field']) ?>
                                                <small class="text-muted d-block"><?= htmlspecialchars($column['Type']) ?></small>
                                            </th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tableData['data'] as $row): ?>
                                        <tr>
                                            <?php foreach ($columns as $column): ?>
                                                <td class="text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($row[$column['Field']] ?? '') ?>">
                                                    <?php
                                                    $value = $row[$column['Field']] ?? '';
                                                    if (strlen($value) > 50) {
                                                        echo htmlspecialchars(substr($value, 0, 50)) . '...';
                                                    } else {
                                                        echo htmlspecialchars($value);
                                                    }
                                                    ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if ($tableData['totalPages'] > 1): ?>
                            <div class="card-footer">
                                <nav aria-label="Table pagination">
                                    <ul class="pagination pagination-sm mb-0 justify-content-center">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?table=<?= urlencode($table) ?>&page=<?= $page - 1 ?>">
                                                    <i class="bi bi-chevron-left"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $page - 2); $i <= min($tableData['totalPages'], $page + 2); $i++): ?>
                                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                                <a class="page-link" href="?table=<?= urlencode($table) ?>&page=<?= $i ?>"><?= $i ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $tableData['totalPages']): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?table=<?= urlencode($table) ?>&page=<?= $page + 1 ?>">
                                                    <i class="bi bi-chevron-right"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                                <div class="text-center mt-2">
                                    <small class="text-muted">
                                        Showing page <?= $page ?> of <?= $tableData['totalPages'] ?> 
                                        (<?= number_format($tableData['total']) ?> total records)
                                    </small>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center p-5 text-muted">
                            <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                            <h5 class="mt-3">No records found</h5>
                            <p>This table is empty.</p>
                            <a href="<?= $baseUrl ?>insert?table=<?= urlencode($table) ?>" class="btn btn-success">
                                <i class="bi bi-plus-circle"></i> Add First Record
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="<?= $baseUrl ?>assets/js/view.js"></script>
<script src="<?= $baseUrl ?>assets/js/app.js"></script>
<?php if (empty($tables)): ?>
<script src="<?= $baseUrl ?>assets/js/create_table.js"></script>
<?php else: ?>
<script>

initializeViewPage(
    <?= json_encode($columns) ?>,
    "<?= addslashes($table) ?>",
    "<?= $baseUrl ?>"
);
</script>
<?php endif; ?>

<?= generateFooter() ?>
