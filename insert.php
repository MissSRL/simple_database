<?php
session_start();
require_once 'includes/functions.php';

$dbConnection = checkDatabaseConnection();
$pdo = getDatabaseConnection();


$table = $_GET['table'] ?? '';
$baseUrl = getBaseUrl();


if (!$table || !validateTable($pdo, $table)) {
    $_SESSION['error_message'] = 'Table not found: ' . htmlspecialchars($table);
    header('Location: ' . $baseUrl . 'view');
    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['insert_record'])) {
    try {
        $data = [];
        $columns = getTableColumns($pdo, $table);
        $autoIncrementColumns = [];
        
        
        foreach ($columns as $column) {
            if ($column['Extra'] === 'auto_increment') {
                $autoIncrementColumns[] = $column['Field'];
            }
        }
        
        foreach ($_POST['fields'] as $field => $value) {
            
            if (in_array($field, $autoIncrementColumns) && $value === '') {
                continue;
            }
            
            if ($value !== '') { 
                $data[$field] = $value;
            }
        }
        
        if (!empty($data)) {
            $columns = array_keys($data);
            $placeholders = array_map(function($col) { return ":{$col}"; }, $columns);
            
            $sql = "INSERT INTO `{$table}` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($data);
            
            $_SESSION['success_message'] = "Record inserted successfully! Insert ID: " . $pdo->lastInsertId();
            header('Location: ' . $baseUrl . 'view?table=' . urlencode($table));
            exit();
        } else {
            $error_message = 'Please fill in at least one field.';
        }
    } catch (Exception $e) {
        $error_message = 'Insert failed: ' . $e->getMessage();
    }
}


$columns = getTableColumns($pdo, $table);
?>

<?= generateHead("Insert Record: {$table}") ?>

<div class="container-fluid">
    <?= generateNavigation('insert') ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-2 d-none d-md-block">
            <?= generateSidebar($pdo, $table) ?>
        </div>

        <?= generateMobileSidebar($pdo, $table) ?>

        <div class="col-12 col-md-9">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="bi bi-plus-circle"></i> Insert New Record into <?= htmlspecialchars($table) ?>
                    </h6>
                    <div class="header-actions">
                        <a href="<?= $baseUrl ?>view?table=<?= urlencode($table) ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-left"></i> Back to Table
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <?php foreach ($columns as $index => $column): ?>
                                <div class="col-md-6 mb-3">
                                    <label for="field_<?= $column['Field'] ?>" class="form-label">
                                        <?= htmlspecialchars($column['Field']) ?>
                                        <?php if ($column['Null'] === 'NO'): ?>
                                            <span class="text-danger">*</span>
                                        <?php endif; ?>
                                    </label>
                                    <div class="input-group">
                                        <?php
                                        $inputType = 'text';
                                        $placeholder = 'Enter value';
                                        $step = '';
                                        
                                        
                                        if (strpos($column['Type'], 'int') !== false || strpos($column['Type'], 'decimal') !== false || strpos($column['Type'], 'float') !== false || strpos($column['Type'], 'double') !== false) {
                                            $inputType = 'number';
                                            if (strpos($column['Type'], 'decimal') !== false || strpos($column['Type'], 'float') !== false || strpos($column['Type'], 'double') !== false) {
                                                $step = 'step="0.01"';
                                            }
                                        } elseif (strpos($column['Type'], 'date') !== false) {
                                            $inputType = 'date';
                                        } elseif (strpos($column['Type'], 'time') !== false) {
                                            $inputType = 'time';
                                        } elseif (strpos($column['Type'], 'datetime') !== false || strpos($column['Type'], 'timestamp') !== false) {
                                            $inputType = 'datetime-local';
                                        } elseif (strpos($column['Type'], 'email') !== false) {
                                            $inputType = 'email';
                                        }
                                        
                                        
                                        if (strpos($column['Type'], 'text') !== false || strpos($column['Type'], 'blob') !== false):
                                        ?>
                                            <textarea 
                                                class="form-control" 
                                                id="field_<?= $column['Field'] ?>" 
                                                name="fields[<?= $column['Field'] ?>]"
                                                rows="3"
                                                placeholder="<?= $placeholder ?>"
                                                <?= ($column['Null'] === 'NO' && $column['Extra'] !== 'auto_increment') ? 'required' : '' ?>
                                            ><?= htmlspecialchars($_POST['fields'][$column['Field']] ?? $column['Default'] ?? '') ?></textarea>
                                        <?php else: ?>
                                            <input 
                                                type="<?= $inputType ?>" 
                                                class="form-control" 
                                                id="field_<?= $column['Field'] ?>" 
                                                name="fields[<?= $column['Field'] ?>]"
                                                value="<?= htmlspecialchars($_POST['fields'][$column['Field']] ?? $column['Default'] ?? '') ?>"
                                                placeholder="<?= $placeholder ?>"
                                                <?= $step ?>
                                                <?= ($column['Null'] === 'NO' && $column['Extra'] !== 'auto_increment') ? 'required' : '' ?>
                                            >
                                        <?php endif; ?>
                                        
                                        <span class="input-group-text">
                                            <small class="text-muted"><?= htmlspecialchars($column['Type']) ?></small>
                                        </span>
                                    </div>
                                    
                                    <div class="form-text">
                                        <?php if ($column['Key'] === 'PRI'): ?>
                                            <i class="bi bi-key text-warning"></i> Primary Key
                                        <?php endif; ?>
                                        <?php if ($column['Extra'] === 'auto_increment'): ?>
                                            <i class="bi bi-arrow-up text-info"></i> Auto Increment (leave empty)
                                        <?php endif; ?>
                                        <?php if ($column['Null'] === 'YES'): ?>
                                            <i class="bi bi-question-circle text-muted"></i> Optional
                                        <?php elseif ($column['Extra'] !== 'auto_increment'): ?>
                                            <i class="bi bi-exclamation-circle text-danger"></i> Required
                                        <?php endif; ?>
                                        <?php if ($column['Default'] !== null): ?>
                                            <i class="bi bi-gear text-success"></i> Default: <?= htmlspecialchars($column['Default']) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if (($index + 1) % 2 === 0): ?>
                                    </div><div class="row">
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <a href="<?= $baseUrl ?>view?table=<?= urlencode($table) ?>" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Cancel
                            </a>
                            <div>
                                <button type="reset" class="btn btn-outline-warning me-2">
                                    <i class="bi bi-arrow-clockwise"></i> Reset Form
                                </button>
                                <button type="submit" name="insert_record" class="btn btn-success">
                                    <i class="bi bi-plus-circle"></i> Insert Record
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-info-circle"></i> Column Information</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-dark">
                            <thead>
                                <tr>
                                    <th>Column</th>
                                    <th>Type</th>
                                    <th>Null</th>
                                    <th>Key</th>
                                    <th>Default</th>
                                    <th>Extra</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($columns as $column): ?>
                                    <tr>
                                        <td class="fw-bold"><?= htmlspecialchars($column['Field']) ?></td>
                                        <td><code><?= htmlspecialchars($column['Type']) ?></code></td>
                                        <td>
                                            <?php if ($column['Null'] === 'YES'): ?>
                                                <span class="badge bg-success">YES</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">NO</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($column['Key']): ?>
                                                <span class="badge bg-warning"><?= htmlspecialchars($column['Key']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($column['Default'] ?? 'NULL') ?></td>
                                        <td>
                                            <?php if ($column['Extra']): ?>
                                                <span class="badge bg-info"><?= htmlspecialchars($column['Extra']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?= generateFooter() ?>
