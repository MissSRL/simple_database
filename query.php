<?php
session_start();
require_once 'includes/functions.php';

$dbConnection = checkDatabaseConnection();
$pdo = getDatabaseConnection();


$results = null;
$error_message = null;
$success_message = null;
$execution_time = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['execute_query'])) {
    $query = trim($_POST['query']);
    
    if (!empty($query)) {
        try {
            $start_time = microtime(true);
            $stmt = $pdo->prepare($query);
            $stmt->execute();
            $execution_time = round((microtime(true) - $start_time) * 1000, 2);
            
            
            if (stripos($query, 'SELECT') === 0) {
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $success_message = "Query executed successfully. " . count($results) . " rows returned in {$execution_time}ms.";
            } else {
                $affected_rows = $stmt->rowCount();
                $success_message = "Query executed successfully. {$affected_rows} rows affected in {$execution_time}ms.";
            }
        } catch (Exception $e) {
            $error_message = 'Query failed: ' . $e->getMessage();
        }
    } else {
        $error_message = 'Please enter a query to execute.';
    }
}


$tables = getTables($pdo);
?>

<?= generateHead("Query Builder") ?>

<div class="container-fluid">
    <?= generateNavigation('query') ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-2 d-none d-md-block">
            <?= generateSidebar($pdo) ?>
        </div>

        <?= generateMobileSidebar($pdo) ?>

        <div class="col-12 col-md-9">
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-code-square"></i> SQL Query Builder</h6>
                </div>
                <div class="card-body">
                    <form method="POST" id="queryForm" onsubmit="return validateDangerousQuery()">
                        <div class="mb-3">
                            <label for="query" class="form-label">SQL Query</label>
                            <textarea 
                                class="form-control" 
                                id="query" 
                                name="query" 
                                rows="8" 
                                placeholder="Enter your SQL query here..."
                                required
                                oninput="checkForDangerousQuery()"
                            ><?= htmlspecialchars($_POST['query'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="alert alert-danger d-none" id="dangerWarning">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <strong>DANGEROUS OPERATION DETECTED!</strong>
                            <p class="mb-2 mt-2">This query contains potentially destructive operations that can:</p>
                            <ul class="mb-2">
                                <li><strong>DROP:</strong> Permanently delete entire tables or databases</li>
                                <li><strong>DELETE:</strong> Remove data from tables</li>
                                <li><strong>TRUNCATE:</strong> Remove all data from tables</li>
                            </ul>
                            <p class="mb-0"><strong>These operations cannot be undone! Make sure you have a backup before proceeding.</strong></p>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <button type="button" class="btn btn-outline-info me-2" onclick="formatQuery()">
                                    <i class="bi bi-code"></i> Format
                                </button>
                                <button type="button" class="btn btn-outline-warning me-2" onclick="clearQuery()">
                                    <i class="bi bi-trash"></i> Clear
                                </button>
                            </div>
                            <button type="submit" name="execute_query" class="btn btn-primary" id="executeButton">
                                <i class="bi bi-play-fill"></i> Execute Query
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-diagram-3"></i> Select Query Builder</h6>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleQueryBuilder()">
                        <i class="bi bi-eye" id="toggleIcon"></i> <span id="toggleText">Show Builder</span>
                    </button>
                </div>
                <div class="card-body" id="visualQueryBuilder" style="display: none;">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="query-builder-section">
                                <h6><i class="bi bi-table"></i> Tables</h6>
                                <select class="form-select mb-3" id="tableSelect">
                                    <option value="">Select a table...</option>
                                    <?php foreach ($tables as $table): ?>
                                        <option value="<?= htmlspecialchars($table) ?>"><?= htmlspecialchars($table) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="query-builder-section">
                                <h6><i class="bi bi-list-ul"></i> Columns</h6>
                                <select class="form-select mb-3" id="columnSelect" multiple disabled>
                                    <option value="*">* (All columns)</option>
                                </select>
                                <small class="text-muted">Hold Ctrl/Cmd to select multiple columns</small>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="query-builder-section">
                                <h6><i class="bi bi-funnel"></i> Conditions</h6>
                                <div id="conditionsContainer">
                                    <div class="condition-row mb-2">
                                        <div class="row">
                                            <div class="col-4">
                                                <select class="form-select condition-column" disabled>
                                                    <option value="">Column...</option>
                                                </select>
                                            </div>
                                            <div class="col-3">
                                                <select class="form-select condition-operator">
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
                                            <div class="col-4">
                                                <input type="text" class="form-control condition-value" placeholder="Value...">
                                            </div>
                                            <div class="col-1">
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeCondition(this)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-success" onclick="addCondition()">
                                    <i class="bi bi-plus"></i> Add Condition
                                </button>
                            </div>
                            
                            <div class="query-builder-section mt-3">
                                <h6><i class="bi bi-sort-down"></i> Order By</h6>
                                <div class="row">
                                    <div class="col-8">
                                        <select class="form-select" id="orderByColumn" disabled>
                                            <option value="">Order by column...</option>
                                        </select>
                                    </div>
                                    <div class="col-4">
                                        <select class="form-select" id="orderByDirection">
                                            <option value="ASC">ASC</option>
                                            <option value="DESC">DESC</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="query-builder-section mt-3">
                                <h6><i class="bi bi-hash"></i> Limit</h6>
                                <input type="number" class="form-control" id="limitValue" placeholder="Number of rows..." min="1" max="10000">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <button type="button" class="btn btn-success" onclick="generateQuery()">
                            <i class="bi bi-gear"></i> Generate Query
                        </button>
                        <button type="button" class="btn btn-outline-secondary ms-2" onclick="resetBuilder()">
                            <i class="bi bi-arrow-clockwise"></i> Reset Builder
                        </button>
                    </div>
                </div>
            </div>

            <?php if ($results !== null): ?>
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="bi bi-table"></i> Query Results 
                            <span class="badge bg-primary"><?= count($results) ?> rows</span>
                        </h6>
                        <?php if (!empty($results)): ?>
                            <button type="button" class="btn btn-sm btn-outline-success" onclick="exportResults()">
                                <i class="bi bi-download"></i> Export CSV
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($results)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-inbox display-4"></i>
                                <p class="mt-2">No results found</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-dark table-striped table-hover" id="resultsTable">
                                    <thead>
                                        <tr>
                                            <?php foreach (array_keys($results[0]) as $column): ?>
                                                <th><?= htmlspecialchars($column) ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($results as $row): ?>
                                            <tr>
                                                <?php foreach ($row as $value): ?>
                                                    <td><?= htmlspecialchars($value ?? 'NULL') ?></td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="<?= getBaseUrl() ?>assets/js/query.js"></script>
<script>

initializeQueryPage("<?= getBaseUrl() ?>");
</script>

<?= generateFooter() ?>
