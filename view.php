<?php
session_start();
require_once 'includes/functions.php';

$dbConnection = checkDatabaseConnection();
$pdo = getDatabaseConnection();


$table = $_GET['table'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$baseUrl = getBaseUrl();


if (!$table) {
    $tables = getTables($pdo);
    if (!empty($tables)) {
        header('Location: ' . $baseUrl . 'view?table=' . urlencode($tables[0]));
        exit();
    }
}


if (!validateTable($pdo, $table)) {
    $_SESSION['error_message'] = 'Table not found: ' . htmlspecialchars($table);
    header('Location: ' . $baseUrl . 'view');
    exit();
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


$tableData = getTableData($pdo, $table, $page);
$columns = getTableColumns($pdo, $table);
?>

<?= generateHead("View Table: {$table}"); ?>

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
        <div class="col-md-2">
            <?= generateSidebar($pdo, $table) ?>
        </div>

        <div class="col-md-9">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="bi bi-table"></i> <?= htmlspecialchars($table) ?>
                        <span class="badge bg-secondary ms-2"><?= number_format($tableData['total']) ?> records</span>
                    </h6>
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
        </div>
    </div>
</div>

<script src="<?= $baseUrl ?>assets/js/view.js"></script>
<script>
// Initialize the view page with data from PHP
initializeViewPage(
    <?= json_encode($columns) ?>,
    "<?= addslashes($table) ?>",
    "<?= $baseUrl ?>"
);
</script>

<?= generateFooter() ?>
