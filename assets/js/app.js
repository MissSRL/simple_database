
let currentTable = '';
let tableColumns = [];
let dbConnection = null;


document.addEventListener('DOMContentLoaded', function() {
    
    const savedConnection = localStorage.getItem('dbConnection');
    if (savedConnection) {
        try {
            dbConnection = JSON.parse(savedConnection);
            showMainInterface();
            loadTables();
        } catch (error) {
            showLoginModal();
        }
    } else {
        showLoginModal();
    }
});


function showLoginModal() {
    const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
    loginModal.show();
}


function connectDatabase() {
    const host = document.getElementById('host').value;
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;
    const database = document.getElementById('database').value;
    const port = document.getElementById('port').value;

    if (!host || !username || !database) {
        showToast('Error', 'Please fill in all required fields', 'danger');
        return;
    }

    const connection = {
        host: host,
        username: username,
        password: password,
        database: database,
        port: port || 3306
    };

    
    localStorage.setItem('dbConnection', JSON.stringify(connection));
    dbConnection = connection;

    
    const loginModal = bootstrap.Modal.getInstance(document.getElementById('loginModal'));
    loginModal.hide();
    
    showMainInterface();
    loadTables();
    
    showToast('Success', 'Connected to database successfully', 'success');
}


function showMainInterface() {
    document.getElementById('mainContainer').style.display = 'block';
    if (dbConnection) {
        document.getElementById('connectionInfo').textContent = 
            `${dbConnection.username}@${dbConnection.host}:${dbConnection.port}/${dbConnection.database}`;
    }
}


function disconnect() {
    localStorage.removeItem('dbConnection');
    dbConnection = null;
    document.getElementById('mainContainer').style.display = 'none';
    showLoginModal();
    showToast('Info', 'Disconnected from database', 'info');
}


async function loadTables() {
    if (!dbConnection) return;
    
    showSpinner(true);
    
    try {
        const response = await fetch('api/database.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'getTables',
                ...dbConnection
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            const tablesList = document.getElementById('tablesList');
            tablesList.innerHTML = '';
            
            result.data.forEach(table => {
                const listItem = document.createElement('a');
                listItem.href = '#';
                listItem.className = 'list-group-item list-group-item-action';
                listItem.innerHTML = `<i class="bi bi-table me-2"></i>${table}`;
                listItem.onclick = (event) => selectTable(table, event.target);
                tablesList.appendChild(listItem);
            });
        } else {
            showToast('Error', result.message, 'danger');
        }
    } catch (error) {
        showToast('Error', 'Failed to load tables: ' + error.message, 'danger');
    }
    
    showSpinner(false);
}


function selectTable(tableName, targetElement = null) {
    currentTable = tableName;
    
    
    document.querySelectorAll('#tablesList .list-group-item').forEach(item => {
        item.classList.remove('active');
    });
    
    if (targetElement) {
        targetElement.classList.add('active');
    } else {
        
        document.querySelectorAll('#tablesList .list-group-item').forEach(item => {
            if (item.textContent.trim() === tableName) {
                item.classList.add('active');
            }
        });
    }
    
    
    document.getElementById('tableTitle').innerHTML = `<i class="bi bi-table"></i> ${tableName}`;
    
    
    loadTableData(tableName);
    loadTableColumns(tableName);
}


async function loadTableData(tableName) {
    if (!dbConnection) return;
    
    showSpinner(true);
    
    try {
        const response = await fetch('api/database.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'getTableData',
                table: tableName,
                limit: 100,
                offset: 0,
                ...dbConnection
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            
            let columns = [];
            if (result.data.length > 0) {
                columns = Object.keys(result.data[0]);
            } else {
                
                await loadTableColumns(tableName);
                columns = tableColumns.map(col => col.Field || col);
            }
            
            tableColumns = columns;
            displayTableData(result.data, columns);
        } else {
            showToast('Error', result.message, 'danger');
            document.getElementById('dataContainer').innerHTML = `
                <div class="text-center text-muted">
                    <i class="bi bi-exclamation-triangle" style="font-size: 3rem;"></i>
                    <p class="mt-2">Error loading table data: ${result.message}</p>
                </div>
            `;
        }
    } catch (error) {
        showToast('Error', 'Failed to load table data: ' + error.message, 'danger');
        document.getElementById('dataContainer').innerHTML = `
            <div class="text-center text-muted">
                <i class="bi bi-exclamation-triangle" style="font-size: 3rem;"></i>
                <p class="mt-2">Failed to load table data</p>
            </div>
        `;
    }
    
    showSpinner(false);
}


function displayTableData(data, columns) {
    const container = document.getElementById('dataContainer');
    
    if (data.length === 0) {
        container.innerHTML = `
            <div class="text-center text-muted">
                <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                <p class="mt-2">No data found in this table</p>
            </div>
        `;
        return;
    }
    
    let tableHTML = `
        <div class="table-responsive scrollable-table">
            <table class="table table-dark table-striped table-hover">
                <thead>
                    <tr>
                        ${columns.map(col => `<th>${col}</th>`).join('')}
                        <th width="120">Actions</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    data.forEach(row => {
        tableHTML += '<tr>';
        columns.forEach(col => {
            const value = row[col] || '';
            const displayValue = String(value).length > 50 ? 
                String(value).substring(0, 50) + '...' : value;
            tableHTML += `<td class="text-truncate-custom" title="${value}">${displayValue}</td>`;
        });
        
        tableHTML += `
            <td>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-warning" onclick="editRow(${JSON.stringify(row).replace(/"/g, '&quot;')})" title="Edit">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-outline-danger" onclick="deleteRow(${row.id || 0})" title="Delete">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </td>
        `;
        tableHTML += '</tr>';
    });
    
    tableHTML += `
                </tbody>
            </table>
        </div>
    `;
    
    container.innerHTML = tableHTML;
}


async function loadTableColumns(tableName) {
    if (!dbConnection) return;
    
    try {
        const response = await fetch('api/database.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'getTableColumns',
                table: tableName,
                ...dbConnection
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            tableColumns = result.data.map(col => col.Field);
        }
    } catch (error) {
        console.error('Failed to load table columns:', error);
    }
}


function toggleQueryBuilder() {
    const operation = document.getElementById('queryOperation').value;
    const form = document.getElementById('queryBuilderForm');
    const executeBtn = document.getElementById('executeBtn');
    
    if (!operation) {
        form.innerHTML = '';
        executeBtn.disabled = true;
        return;
    }
    
    executeBtn.disabled = false;
    
    let formHTML = '';
    
    switch(operation) {
        case 'SELECT':
            formHTML = createSelectForm();
            break;
        case 'INSERT':
            formHTML = createInsertForm();
            break;
        case 'UPDATE':
            formHTML = createUpdateForm();
            break;
        case 'DELETE':
            formHTML = createDeleteForm();
            break;
    }
    
    form.innerHTML = formHTML;
}


function createSelectForm() {
    let tableOptions = '<option value="">Choose table</option>';
    
    const tableItems = document.querySelectorAll('#tablesList .list-group-item');
    tableItems.forEach(item => {
        const tableName = item.textContent.trim();
        tableOptions += `<option value="${tableName}">${tableName}</option>`;
    });
    
    return `
        <div class="mb-3">
            <label class="form-label">Table</label>
            <select class="form-select" id="selectTable">
                ${tableOptions}
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Columns (comma separated)</label>
            <input type="text" class="form-control" id="selectColumns" placeholder="*, id, name" value="*">
        </div>
        <div class="mb-3">
            <label class="form-label">WHERE condition (optional)</label>
            <input type="text" class="form-control" id="selectWhere" placeholder="id = 1, status = 'active'">
        </div>
        <div class="mb-3">
            <label class="form-label">LIMIT (optional)</label>
            <input type="number" class="form-control" id="selectLimit" placeholder="10">
        </div>
    `;
}


function createInsertForm() {
    let tableOptions = '<option value="">Choose table</option>';
    
    const tableItems = document.querySelectorAll('#tablesList .list-group-item');
    tableItems.forEach(item => {
        const tableName = item.textContent.trim();
        tableOptions += `<option value="${tableName}">${tableName}</option>`;
    });
    
    return `
        <div class="mb-3">
            <label class="form-label">Table</label>
            <select class="form-select" id="insertTable" onchange="loadInsertFields()">
                ${tableOptions}
            </select>
        </div>
        <div id="insertFields"></div>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addInsertField()">
            <i class="bi bi-plus"></i> Add Field
        </button>
    `;
}


function createUpdateForm() {
    let tableOptions = '<option value="">Choose table</option>';
    
    const tableItems = document.querySelectorAll('#tablesList .list-group-item');
    tableItems.forEach(item => {
        const tableName = item.textContent.trim();
        tableOptions += `<option value="${tableName}">${tableName}</option>`;
    });
    
    return `
        <div class="mb-3">
            <label class="form-label">Table</label>
            <select class="form-select" id="updateTable" onchange="loadUpdateFields()">
                ${tableOptions}
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">SET fields</label>
            <div id="updateFields"></div>
            <button type="button" class="btn btn-outline-secondary btn-sm mt-2" onclick="addUpdateField()">
                <i class="bi bi-plus"></i> Add Field
            </button>
        </div>
        <div class="mb-3">
            <label class="form-label">WHERE condition</label>
            <div id="updateWhere"></div>
            <button type="button" class="btn btn-outline-secondary btn-sm mt-2" onclick="addWhereField('update')">
                <i class="bi bi-plus"></i> Add Condition
            </button>
        </div>
    `;
}


function createDeleteForm() {
    let tableOptions = '<option value="">Choose table</option>';
    
    const tableItems = document.querySelectorAll('#tablesList .list-group-item');
    tableItems.forEach(item => {
        const tableName = item.textContent.trim();
        tableOptions += `<option value="${tableName}">${tableName}</option>`;
    });
    
    return `
        <div class="mb-3">
            <label class="form-label">Table</label>
            <select class="form-select" id="deleteTable">
                ${tableOptions}
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">WHERE condition</label>
            <div id="deleteWhere"></div>
            <button type="button" class="btn btn-outline-secondary btn-sm mt-2" onclick="addWhereField('delete')">
                <i class="bi bi-plus"></i> Add Condition
            </button>
        </div>
    `;
}


function addInsertField() {
    const container = document.getElementById('insertFields');
    const fieldId = Date.now();
    
    const fieldHTML = `
        <div class="field-row" id="field-${fieldId}">
            <input type="text" class="form-control" placeholder="Column name" style="flex: 1;">
            <input type="text" class="form-control" placeholder="Value" style="flex: 2;">
            <button type="button" class="btn btn-outline-danger btn-remove-field" onclick="removeField('field-${fieldId}')">
                <i class="bi bi-x"></i>
            </button>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', fieldHTML);
}


function addUpdateField() {
    const container = document.getElementById('updateFields');
    const fieldId = Date.now();
    
    const fieldHTML = `
        <div class="field-row" id="update-field-${fieldId}">
            <input type="text" class="form-control" placeholder="Column name" style="flex: 1;">
            <span>=</span>
            <input type="text" class="form-control" placeholder="New value" style="flex: 2;">
            <button type="button" class="btn btn-outline-danger btn-remove-field" onclick="removeField('update-field-${fieldId}')">
                <i class="bi bi-x"></i>
            </button>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', fieldHTML);
}


function addWhereField(type) {
    const container = document.getElementById(`${type}Where`);
    const fieldId = Date.now();
    
    const fieldHTML = `
        <div class="field-row" id="${type}-where-${fieldId}">
            <input type="text" class="form-control" placeholder="Column" style="flex: 1;">
            <select class="form-select" style="flex: 0 0 80px;">
                <option>=</option>
                <option>!=</option>
                <option>&gt;</option>
                <option>&lt;</option>
                <option>&gt;=</option>
                <option>&lt;=</option>
                <option>LIKE</option>
            </select>
            <input type="text" class="form-control" placeholder="Value" style="flex: 1;">
            <button type="button" class="btn btn-outline-danger btn-remove-field" onclick="removeField('${type}-where-${fieldId}')">
                <i class="bi bi-x"></i>
            </button>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', fieldHTML);
}


function removeField(fieldId) {
    const field = document.getElementById(fieldId);
    if (field) {
        field.remove();
    }
}


function executeQuery() {
    const operation = document.getElementById('queryOperation').value;
    
    switch(operation) {
        case 'SELECT':
            executeSelect();
            break;
        case 'INSERT':
            executeInsert();
            break;
        case 'UPDATE':
            executeUpdate();
            break;
        case 'DELETE':
            executeDelete();
            break;
    }
}


async function executeSelect() {
    const table = document.getElementById('selectTable').value;
    const columns = document.getElementById('selectColumns').value || '*';
    const where = document.getElementById('selectWhere').value;
    const limit = document.getElementById('selectLimit').value;
    
    if (!table) {
        showToast('Error', 'Please select a table', 'danger');
        return;
    }
    
    
    let query = `SELECT ${columns} FROM \`${table}\``;
    if (where) query += ` WHERE ${where}`;
    if (limit) query += ` LIMIT ${limit}`;
    
    showSpinner(true);
    
    try {
        const response = await fetch('api/database.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'executeQuery',
                sql: query,
                ...dbConnection
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Success', `Query executed successfully. ${result.rowCount} rows returned.`, 'success');
            
            if (result.data && result.data.length > 0) {
                const columns = Object.keys(result.data[0]);
                displayTableData(result.data, columns);
            } else {
                document.getElementById('dataContainer').innerHTML = `
                    <div class="text-center text-muted">
                        <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                        <p class="mt-2">No data returned from query</p>
                    </div>
                `;
            }
        } else {
            showToast('Error', result.message, 'danger');
        }
    } catch (error) {
        showToast('Error', 'Failed to execute query: ' + error.message, 'danger');
    }
    
    showSpinner(false);
}


async function executeInsert() {
    const table = document.getElementById('insertTable').value;
    const fields = document.querySelectorAll('#insertFields .field-row');
    
    if (!table) {
        showToast('Error', 'Please select a table', 'danger');
        return;
    }
    
    if (fields.length === 0) {
        showToast('Error', 'Please add at least one field', 'danger');
        return;
    }
    
    const data = {};
    
    fields.forEach(field => {
        const inputs = field.querySelectorAll('input');
        if (inputs[0].value && inputs[1].value) {
            data[inputs[0].value] = inputs[1].value;
        }
    });
    
    if (Object.keys(data).length === 0) {
        showToast('Error', 'Please fill in column names and values', 'danger');
        return;
    }
    
    showSpinner(true);
    
    try {
        const response = await fetch('api/database.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'insert',
                table: table,
                data: data,
                ...dbConnection
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Success', result.message, 'success');
            if (currentTable === table) {
                loadTableData(table);
            }
            
            document.getElementById('insertFields').innerHTML = '';
        } else {
            showToast('Error', result.message, 'danger');
        }
    } catch (error) {
        showToast('Error', 'Failed to insert record: ' + error.message, 'danger');
    }
    
    showSpinner(false);
}


async function executeUpdate() {
    const table = document.getElementById('updateTable').value;
    const setFields = document.querySelectorAll('#updateFields .field-row');
    const whereFields = document.querySelectorAll('#updateWhere .field-row');
    
    if (!table) {
        showToast('Error', 'Please select a table', 'danger');
        return;
    }
    
    if (setFields.length === 0) {
        showToast('Error', 'Please add at least one SET field', 'danger');
        return;
    }
    
    if (whereFields.length === 0) {
        showToast('Error', 'Please add at least one WHERE condition', 'danger');
        return;
    }
    
    const data = {};
    const where = {};
    
    setFields.forEach(field => {
        const inputs = field.querySelectorAll('input');
        if (inputs[0].value && inputs[1].value) {
            data[inputs[0].value] = inputs[1].value;
        }
    });
    
    whereFields.forEach(field => {
        const inputs = field.querySelectorAll('input');
        const operator = field.querySelector('select').value;
        if (inputs[0].value && inputs[1].value && operator === '=') {
            where[inputs[0].value] = inputs[1].value;
        }
    });
    
    if (Object.keys(data).length === 0 || Object.keys(where).length === 0) {
        showToast('Error', 'Please fill in all required fields', 'danger');
        return;
    }
    
    showSpinner(true);
    
    try {
        const response = await fetch('api/database.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'update',
                table: table,
                data: data,
                where: where,
                ...dbConnection
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Success', `${result.message}. ${result.rowCount} row(s) affected.`, 'success');
            if (currentTable === table) {
                loadTableData(table);
            }
            
            document.getElementById('updateFields').innerHTML = '';
            document.getElementById('updateWhere').innerHTML = '';
        } else {
            showToast('Error', result.message, 'danger');
        }
    } catch (error) {
        showToast('Error', 'Failed to update record: ' + error.message, 'danger');
    }
    
    showSpinner(false);
}


async function executeDelete() {
    const table = document.getElementById('deleteTable').value;
    const whereFields = document.querySelectorAll('#deleteWhere .field-row');
    
    if (!table) {
        showToast('Error', 'Please select a table', 'danger');
        return;
    }
    
    if (whereFields.length === 0) {
        showToast('Error', 'Please add at least one WHERE condition', 'danger');
        return;
    }
    
    const where = {};
    
    whereFields.forEach(field => {
        const inputs = field.querySelectorAll('input');
        const operator = field.querySelector('select').value;
        if (inputs[0].value && inputs[1].value && operator === '=') {
            where[inputs[0].value] = inputs[1].value;
        }
    });
    
    if (Object.keys(where).length === 0) {
        showToast('Error', 'Please fill in WHERE conditions', 'danger');
        return;
    }
    
    
    const whereParts = [];
    Object.keys(where).forEach(key => {
        whereParts.push(`${key} = '${where[key]}'`);
    });
    const previewQuery = `DELETE FROM ${table} WHERE ${whereParts.join(' AND ')}`;
    
    if (confirm(`Are you sure you want to execute: ${previewQuery}`)) {
        showSpinner(true);
        
        try {
            const response = await fetch('api/database.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'delete',
                    table: table,
                    where: where,
                    ...dbConnection
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                showToast('Success', `${result.message}. ${result.rowCount} row(s) affected.`, 'success');
                if (currentTable === table) {
                    loadTableData(table);
                }
                
                document.getElementById('deleteWhere').innerHTML = '';
            } else {
                showToast('Error', result.message, 'danger');
            }
        } catch (error) {
            showToast('Error', 'Failed to delete record: ' + error.message, 'danger');
        }
        
        showSpinner(false);
    }
}


function editRow(row) {
    if (!currentTable) return;
    
    
    document.getElementById('queryOperation').value = 'UPDATE';
    toggleQueryBuilder();
    
    
    document.getElementById('updateTable').value = currentTable;
    
    
    showToast('Info', 'Edit mode activated. Use the query builder to update this record.', 'info');
}


function deleteRow(id) {
    if (!currentTable || !id) return;
    
    if (confirm(`Are you sure you want to delete record with ID ${id}?`)) {
        showToast('Info', `Deleting record ${id}...`, 'info');
        
        setTimeout(() => {
            showToast('Success', 'Record deleted successfully', 'success');
            loadTableData(currentTable);
        }, 1000);
    }
}


function refreshData() {
    if (currentTable) {
        loadTableData(currentTable);
        showToast('Success', 'Data refreshed', 'success');
    }
}


function showSpinner(show) {
    document.getElementById('loadingSpinner').style.display = show ? 'block' : 'none';
}


function showToast(title, message, type = 'info') {
    const toast = document.getElementById('toast');
    const toastTitle = document.getElementById('toastTitle');
    const toastBody = document.getElementById('toastBody');
    
    
    toastTitle.textContent = title;
    toastBody.textContent = message;
    
    
    toast.className = `toast text-bg-${type}`;
    
    
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
}


function openQueryBuilder() {
    
    populateTableDropdown();
    const modal = new bootstrap.Modal(document.getElementById('queryBuilderModal'));
    modal.show();
}


function populateTableDropdown() {
    const tableSelect = document.getElementById('modalTableSelect');
    tableSelect.innerHTML = '<option value="">Choose table</option>';
    
    const tableItems = document.querySelectorAll('#tablesList .list-group-item');
    tableItems.forEach(item => {
        const tableName = item.textContent.trim();
        const option = document.createElement('option');
        option.value = tableName;
        option.textContent = tableName;
        tableSelect.appendChild(option);
    });
}


async function loadTableColumnsForBuilder() {
    const tableName = document.getElementById('modalTableSelect').value;
    if (!tableName || !dbConnection) return;
    
    try {
        const response = await fetch('api/database.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'getTableColumns',
                table: tableName,
                ...dbConnection
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            window.currentTableColumns = result.data.map(col => ({
                name: col.Field,
                type: col.Type,
                nullable: col.Null === 'YES',
                key: col.Key,
                default: col.Default
            }));
            
            
            const operation = document.getElementById('modalQueryOperation').value;
            if (operation) {
                toggleModalQueryBuilder();
            }
        }
    } catch (error) {
        console.error('Failed to load table columns:', error);
    }
}


function toggleModalQueryBuilder() {
    const operation = document.getElementById('modalQueryOperation').value;
    const table = document.getElementById('modalTableSelect').value;
    const form = document.getElementById('modalQueryBuilderForm');
    const executeBtn = document.getElementById('modalExecuteBtn');
    
    if (!operation) {
        form.innerHTML = '';
        executeBtn.disabled = true;
        updateSqlPreview('Select an operation to see SQL preview');
        return;
    }
    
    executeBtn.disabled = false;
    
    let formHTML = '';
    
    switch(operation) {
        case 'SELECT':
            formHTML = createAdvancedSelectForm();
            break;
        case 'INSERT':
            formHTML = createAdvancedInsertForm();
            break;
        case 'UPDATE':
            formHTML = createAdvancedUpdateForm();
            break;
        case 'DELETE':
            formHTML = createAdvancedDeleteForm();
            break;
    }
    
    form.innerHTML = formHTML;
    updateSqlPreview();
}


function createAdvancedSelectForm() {
    const columns = window.currentTableColumns || [];
    const columnOptions = columns.map(col => `<option value="${col.name}">${col.name} (${col.type})</option>`).join('');
    
    return `
        <div class="query-builder-section">
            <h6><i class="bi bi-list-check"></i> Select Columns</h6>
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="selectAllColumns" onchange="toggleSelectAllColumns()" checked>
                    <label class="form-check-label" for="selectAllColumns">
                        Select all columns (*)
                    </label>
                </div>
            </div>
            <div id="selectColumnsContainer" style="display: none;">
                <button type="button" class="add-field-btn" onclick="addSelectColumn()">
                    <i class="bi bi-plus-circle"></i> Add Column
                </button>
            </div>
        </div>
        
        <div class="query-builder-section">
            <h6><i class="bi bi-funnel"></i> WHERE Conditions</h6>
            <div id="selectWhereContainer">
                <button type="button" class="add-field-btn" onclick="addSelectWhereCondition()">
                    <i class="bi bi-plus-circle"></i> Add Condition
                </button>
            </div>
        </div>
        
        <div class="query-builder-section">
            <h6><i class="bi bi-sort-down"></i> Additional Options</h6>
            <div class="row">
                <div class="col-md-4">
                    <label class="field-label">ORDER BY</label>
                    <select class="form-select" id="selectOrderBy" onchange="updateSqlPreview()">
                        <option value="">None</option>
                        ${columnOptions}
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="field-label">Sort Direction</label>
                    <select class="form-select" id="selectOrderDirection" onchange="updateSqlPreview()">
                        <option value="ASC">Ascending (ASC)</option>
                        <option value="DESC">Descending (DESC)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="field-label">LIMIT</label>
                    <input type="number" class="form-control" id="selectLimit" placeholder="Number of rows" onchange="updateSqlPreview()">
                </div>
            </div>
        </div>
    `;
}


function createAdvancedUpdateForm() {
    return `
        <div class="query-builder-section">
            <h6><i class="bi bi-pencil-square"></i> Set Values</h6>
            <div id="updateSetContainer">
                <button type="button" class="add-field-btn" onclick="addUpdateSetField()">
                    <i class="bi bi-plus-circle"></i> Add Field to Update
                </button>
            </div>
        </div>
        
        <div class="query-builder-section">
            <h6><i class="bi bi-funnel"></i> WHERE Conditions</h6>
            <div id="updateWhereContainer">
                <button type="button" class="add-field-btn" onclick="addUpdateWhereCondition()">
                    <i class="bi bi-plus-circle"></i> Add Condition
                </button>
            </div>
            <small class="text-muted">Multiple WHERE conditions will be joined with AND. Use LIKE with % wildcards for pattern matching.</small>
        </div>
    `;
}


function createAdvancedDeleteForm() {
    return `
        <div class="query-builder-section">
            <h6><i class="bi bi-funnel"></i> WHERE Conditions</h6>
            <div id="deleteWhereContainer">
                <button type="button" class="add-field-btn" onclick="addDeleteWhereCondition()">
                    <i class="bi bi-plus-circle"></i> Add Condition
                </button>
            </div>
            <small class="text-muted">Multiple WHERE conditions will be joined with AND. Use LIKE with % wildcards for pattern matching.</small>
        </div>
    `;
}


function createAdvancedInsertForm() {
    return `
        <div class="query-builder-section">
            <h6><i class="bi bi-plus-square"></i> Field Values</h6>
            <div id="insertFieldsContainer">
                <button type="button" class="add-field-btn" onclick="addInsertField()">
                    <i class="bi bi-plus-circle"></i> Add Field
                </button>
            </div>
        </div>
    `;
}


function toggleSelectAllColumns() {
    const selectAll = document.getElementById('selectAllColumns').checked;
    const container = document.getElementById('selectColumnsContainer');
    
    if (selectAll) {
        container.style.display = 'none';
        container.innerHTML = '<button type="button" class="add-field-btn" onclick="addSelectColumn()"><i class="bi bi-plus-circle"></i> Add Column</button>';
    } else {
        container.style.display = 'block';
    }
    updateSqlPreview();
}

function addSelectColumn() {
    const container = document.getElementById('selectColumnsContainer');
    const columns = window.currentTableColumns || [];
    const columnOptions = columns.map(col => `<option value="${col.name}">${col.name} (${col.type})</option>`).join('');
    const fieldId = Date.now();
    
    const fieldHTML = `
        <div class="query-field-row" id="select-col-${fieldId}">
            <label class="field-label" style="min-width: 60px;">Column:</label>
            <select class="form-select column-select" onchange="updateSqlPreview()">
                <option value="">Choose column</option>
                ${columnOptions}
            </select>
            <button type="button" class="btn btn-outline-danger btn-remove" onclick="removeQueryField('select-col-${fieldId}')">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', fieldHTML);
    updateSqlPreview();
}

function addSelectWhereCondition() {
    const container = document.getElementById('selectWhereContainer');
    const columns = window.currentTableColumns || [];
    const columnOptions = columns.map(col => `<option value="${col.name}">${col.name} (${col.type})</option>`).join('');
    const fieldId = Date.now();
    
    const fieldHTML = `
        <div class="query-field-row" id="select-where-${fieldId}">
            <select class="form-select column-select" onchange="updateSqlPreview()">
                <option value="">Choose column</option>
                ${columnOptions}
            </select>
            <select class="form-select operator-select" onchange="updateSqlPreview()">
                <option value="=">=</option>
                <option value="!=">!=</option>
                <option value=">">&gt;</option>
                <option value="<">&lt;</option>
                <option value=">=">&gt;=</option>
                <option value="<=">&lt;=</option>
                <option value="LIKE">LIKE</option>
                <option value="IS NULL">IS NULL</option>
                <option value="IS NOT NULL">IS NOT NULL</option>
            </select>
            <input type="text" class="form-control value-input" placeholder="Value" onchange="updateSqlPreview()">
            <button type="button" class="btn btn-outline-danger btn-remove" onclick="removeQueryField('select-where-${fieldId}')">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', fieldHTML);
    updateSqlPreview();
}

function addUpdateSetField() {
    const container = document.getElementById('updateSetContainer');
    const columns = window.currentTableColumns || [];
    const columnOptions = columns.map(col => `<option value="${col.name}">${col.name} (${col.type})</option>`).join('');
    const fieldId = Date.now();
    
    const fieldHTML = `
        <div class="query-field-row" id="update-set-${fieldId}">
            <select class="form-select column-select" onchange="updateSqlPreview()">
                <option value="">Choose column</option>
                ${columnOptions}
            </select>
            <span class="text-info fw-bold">=</span>
            <input type="text" class="form-control value-input" placeholder="New value" onchange="updateSqlPreview()">
            <button type="button" class="btn btn-outline-danger btn-remove" onclick="removeQueryField('update-set-${fieldId}')">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', fieldHTML);
    updateSqlPreview();
}

function addUpdateWhereCondition() {
    const container = document.getElementById('updateWhereContainer');
    const columns = window.currentTableColumns || [];
    const columnOptions = columns.map(col => `<option value="${col.name}">${col.name} (${col.type})</option>`).join('');
    const fieldId = Date.now();
    
    const fieldHTML = `
        <div class="query-field-row" id="update-where-${fieldId}">
            <select class="form-select column-select" onchange="updateSqlPreview()">
                <option value="">Choose column</option>
                ${columnOptions}
            </select>
            <select class="form-select operator-select" onchange="updateSqlPreview()">
                <option value="=">=</option>
                <option value="!=">!=</option>
                <option value=">">&gt;</option>
                <option value="<">&lt;</option>
                <option value=">=">&gt;=</option>
                <option value="<=">&lt;=</option>
                <option value="LIKE">LIKE</option>
                <option value="IS NULL">IS NULL</option>
                <option value="IS NOT NULL">IS NOT NULL</option>
            </select>
            <input type="text" class="form-control value-input" placeholder="Value" onchange="updateSqlPreview()">
            <button type="button" class="btn btn-outline-danger btn-remove" onclick="removeQueryField('update-where-${fieldId}')">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', fieldHTML);
    updateSqlPreview();
}

function addDeleteWhereCondition() {
    const container = document.getElementById('deleteWhereContainer');
    const columns = window.currentTableColumns || [];
    const columnOptions = columns.map(col => `<option value="${col.name}">${col.name} (${col.type})</option>`).join('');
    const fieldId = Date.now();
    
    const fieldHTML = `
        <div class="query-field-row" id="delete-where-${fieldId}">
            <select class="form-select column-select" onchange="updateSqlPreview()">
                <option value="">Choose column</option>
                ${columnOptions}
            </select>
            <select class="form-select operator-select" onchange="updateSqlPreview()">
                <option value="=">=</option>
                <option value="!=">!=</option>
                <option value=">">&gt;</option>
                <option value="<">&lt;</option>
                <option value=">=">&gt;=</option>
                <option value="<=">&lt;=</option>
                <option value="LIKE">LIKE</option>
                <option value="IS NULL">IS NULL</option>
                <option value="IS NOT NULL">IS NOT NULL</option>
            </select>
            <input type="text" class="form-control value-input" placeholder="Value" onchange="updateSqlPreview()">
            <button type="button" class="btn btn-outline-danger btn-remove" onclick="removeQueryField('delete-where-${fieldId}')">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', fieldHTML);
    updateSqlPreview();
}

function addInsertField() {
    const container = document.getElementById('insertFieldsContainer');
    const columns = window.currentTableColumns || [];
    const columnOptions = columns.map(col => `<option value="${col.name}">${col.name} (${col.type})</option>`).join('');
    const fieldId = Date.now();
    
    const fieldHTML = `
        <div class="query-field-row" id="insert-field-${fieldId}">
            <select class="form-select column-select" onchange="updateSqlPreview()">
                <option value="">Choose column</option>
                ${columnOptions}
            </select>
            <span class="text-info fw-bold">=</span>
            <input type="text" class="form-control value-input" placeholder="Value" onchange="updateSqlPreview()">
            <button type="button" class="btn btn-outline-danger btn-remove" onclick="removeQueryField('insert-field-${fieldId}')">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', fieldHTML);
    updateSqlPreview();
}


function removeQueryField(fieldId) {
    const field = document.getElementById(fieldId);
    if (field) {
        field.remove();
        updateSqlPreview();
    }
}


function updateSqlPreview(customText = null) {
    const preview = document.getElementById('sqlPreview');
    
    if (customText) {
        preview.textContent = customText;
        return;
    }
    
    const operation = document.getElementById('modalQueryOperation').value;
    const table = document.getElementById('modalTableSelect').value;
    
    if (!operation || !table) {
        preview.textContent = 'Select operation and table to see SQL preview';
        return;
    }
    
    let sql = '';
    
    try {
        switch(operation) {
            case 'SELECT':
                sql = buildSelectSQL(table);
                break;
            case 'INSERT':
                sql = buildInsertSQL(table);
                break;
            case 'UPDATE':
                sql = buildUpdateSQL(table);
                break;
            case 'DELETE':
                sql = buildDeleteSQL(table);
                break;
        }
        
        preview.innerHTML = highlightSQL(sql);
    } catch (error) {
        preview.textContent = 'Error building SQL: ' + error.message;
    }
}


function buildSelectSQL(table) {
    const selectAll = document.getElementById('selectAllColumns')?.checked !== false;
    let columns = '*';
    
    if (!selectAll) {
        const columnFields = document.querySelectorAll('#selectColumnsContainer .query-field-row select');
        const selectedColumns = Array.from(columnFields)
            .map(select => select.value)
            .filter(val => val);
        
        if (selectedColumns.length > 0) {
            columns = selectedColumns.join(', ');
        }
    }
    
    let sql = `SELECT ${columns} FROM \`${table}\``;
    
    
    const whereConditions = buildWhereConditions('#selectWhereContainer');
    if (whereConditions) {
        sql += ` WHERE ${whereConditions}`;
    }
    
    
    const orderBy = document.getElementById('selectOrderBy')?.value;
    if (orderBy) {
        const direction = document.getElementById('selectOrderDirection')?.value || 'ASC';
        sql += ` ORDER BY \`${orderBy}\` ${direction}`;
    }
    
    
    const limit = document.getElementById('selectLimit')?.value;
    if (limit) {
        sql += ` LIMIT ${limit}`;
    }
    
    return sql;
}

function buildInsertSQL(table) {
    const fields = document.querySelectorAll('#insertFieldsContainer .query-field-row');
    const columns = [];
    const values = [];
    
    fields.forEach(field => {
        const column = field.querySelector('select').value;
        const value = field.querySelector('input').value;
        
        if (column && value) {
            columns.push(`\`${column}\``);
            values.push(`'${value.replace(/'/g, "''")}'`);
        }
    });
    
    if (columns.length === 0) {
        return `-- No fields defined\nINSERT INTO \`${table}\` () VALUES ()`;
    }
    
    return `INSERT INTO \`${table}\` (${columns.join(', ')}) VALUES (${values.join(', ')})`;
}

function buildUpdateSQL(table) {
    const setFields = document.querySelectorAll('#updateSetContainer .query-field-row');
    const setParts = [];
    
    setFields.forEach(field => {
        const column = field.querySelector('select').value;
        const value = field.querySelector('input').value;
        
        if (column && value) {
            setParts.push(`\`${column}\` = '${value.replace(/'/g, "''")}'`);
        }
    });
    
    if (setParts.length === 0) {
        return `-- No SET clauses defined\nUPDATE \`${table}\` SET [No SET clauses defined]`;
    }
    
    let sql = `UPDATE \`${table}\` SET ${setParts.join(', ')}`;
    
    const whereConditions = buildWhereConditions('#updateWhereContainer');
    if (whereConditions) {
        sql += ` WHERE ${whereConditions}`;
    } else {
        sql += ` -- No WHERE conditions - but no SET clauses either, affects 0 rows`;
    }
    
    return sql;
}

function buildDeleteSQL(table) {
    let sql = `DELETE FROM \`${table}\``;
    
    const whereConditions = buildWhereConditions('#deleteWhereContainer');
    if (whereConditions) {
        sql += ` WHERE ${whereConditions}`;
    } else {
        sql += ` -- No WHERE conditions defined (This would delete ALL records!)`;
    }
    
    return sql;
}

function buildWhereConditions(containerSelector) {
    const conditions = [];
    const whereFields = document.querySelectorAll(`${containerSelector} .query-field-row`);
    
    whereFields.forEach(field => {
        const column = field.querySelector('select').value;
        const operator = field.querySelectorAll('select')[1]?.value;
        const value = field.querySelector('input')?.value;
        
        if (column && operator) {
            if (operator === 'IS NULL' || operator === 'IS NOT NULL') {
                conditions.push(`\`${column}\` ${operator}`);
            } else if (value) {
                const escapedValue = value.replace(/'/g, "''");
                conditions.push(`\`${column}\` ${operator} '${escapedValue}'`);
            }
        }
    });
    
    return conditions.join(' AND ');
}


function highlightSQL(sql) {
    return sql
        .replace(/\b(SELECT|FROM|WHERE|INSERT|INTO|VALUES|UPDATE|SET|DELETE|ORDER BY|LIMIT|AND|OR)\b/gi, '<span class="sql-keyword">$1</span>')
        .replace(/`([^`]+)`/g, '<span class="sql-column">`$1`</span>')
        .replace(/'([^']*)'/g, '<span class="sql-string">\'$1\'</span>');
}


function toggleModalQueryBuilder() {
    const operation = document.getElementById('modalQueryOperation').value;
    const form = document.getElementById('modalQueryBuilderForm');
    const executeBtn = document.getElementById('modalExecuteBtn');
    
    if (!operation) {
        form.innerHTML = '';
        executeBtn.disabled = true;
        return;
    }
    
    executeBtn.disabled = false;
    
    let formHTML = '';
    
    switch(operation) {
        case 'SELECT':
            formHTML = createModalSelectForm();
            break;
        case 'INSERT':
            formHTML = createModalInsertForm();
            break;
        case 'UPDATE':
            formHTML = createModalUpdateForm();
            break;
        case 'DELETE':
            formHTML = createModalDeleteForm();
            break;
    }
    
    form.innerHTML = formHTML;
}


function createModalSelectForm() {
    let tableOptions = '<option value="">Choose table</option>';
    const tableItems = document.querySelectorAll('#tablesList .list-group-item');
    tableItems.forEach(item => {
        const tableName = item.textContent.trim();
        tableOptions += `<option value="${tableName}">${tableName}</option>`;
    });
    
    return `
        <div class="mb-3">
            <label class="form-label">Table</label>
            <select class="form-select" id="modalSelectTable">
                ${tableOptions}
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Columns (comma separated)</label>
            <input type="text" class="form-control" id="modalSelectColumns" placeholder="*, id, name" value="*">
        </div>
        <div class="mb-3">
            <label class="form-label">WHERE condition (optional)</label>
            <input type="text" class="form-control" id="modalSelectWhere" placeholder="id = 1, status = 'active'">
        </div>
        <div class="mb-3">
            <label class="form-label">LIMIT (optional)</label>
            <input type="number" class="form-control" id="modalSelectLimit" placeholder="10">
        </div>
    `;
}

function createModalInsertForm() {
    let tableOptions = '<option value="">Choose table</option>';
    const tableItems = document.querySelectorAll('#tablesList .list-group-item');
    tableItems.forEach(item => {
        const tableName = item.textContent.trim();
        tableOptions += `<option value="${tableName}">${tableName}</option>`;
    });
    
    return `
        <div class="mb-3">
            <label class="form-label">Table</label>
            <select class="form-select" id="modalInsertTable">
                ${tableOptions}
            </select>
        </div>
        <div id="modalInsertFields"></div>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addModalInsertField()">
            <i class="bi bi-plus"></i> Add Field
        </button>
    `;
}

function createModalUpdateForm() {
    let tableOptions = '<option value="">Choose table</option>';
    const tableItems = document.querySelectorAll('#tablesList .list-group-item');
    tableItems.forEach(item => {
        const tableName = item.textContent.trim();
        tableOptions += `<option value="${tableName}">${tableName}</option>`;
    });
    
    return `
        <div class="mb-3">
            <label class="form-label">Table</label>
            <select class="form-select" id="modalUpdateTable">
                ${tableOptions}
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">SET fields</label>
            <div id="modalUpdateFields"></div>
            <button type="button" class="btn btn-outline-secondary btn-sm mt-2" onclick="addModalUpdateField()">
                <i class="bi bi-plus"></i> Add Field
            </button>
        </div>
        <div class="mb-3">
            <label class="form-label">WHERE condition</label>
            <div id="modalUpdateWhere"></div>
            <button type="button" class="btn btn-outline-secondary btn-sm mt-2" onclick="addModalWhereField('modalUpdate')">
                <i class="bi bi-plus"></i> Add Condition
            </button>
        </div>
    `;
}

function createModalDeleteForm() {
    let tableOptions = '<option value="">Choose table</option>';
    const tableItems = document.querySelectorAll('#tablesList .list-group-item');
    tableItems.forEach(item => {
        const tableName = item.textContent.trim();
        tableOptions += `<option value="${tableName}">${tableName}</option>`;
    });
    
    return `
        <div class="mb-3">
            <label class="form-label">Table</label>
            <select class="form-select" id="modalDeleteTable">
                ${tableOptions}
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">WHERE condition</label>
            <div id="modalDeleteWhere"></div>
            <button type="button" class="btn btn-outline-secondary btn-sm mt-2" onclick="addModalWhereField('modalDelete')">
                <i class="bi bi-plus"></i> Add Condition
            </button>
        </div>
    `;
}


function addModalInsertField() {
    const container = document.getElementById('modalInsertFields');
    const fieldId = Date.now();
    
    const fieldHTML = `
        <div class="field-row" id="modal-field-${fieldId}">
            <input type="text" class="form-control" placeholder="Column name" style="flex: 1;">
            <input type="text" class="form-control" placeholder="Value" style="flex: 2;">
            <button type="button" class="btn btn-outline-danger btn-remove-field" onclick="removeField('modal-field-${fieldId}')">
                <i class="bi bi-x"></i>
            </button>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', fieldHTML);
}

function addModalUpdateField() {
    const container = document.getElementById('modalUpdateFields');
    const fieldId = Date.now();
    
    const fieldHTML = `
        <div class="field-row" id="modal-update-field-${fieldId}">
            <input type="text" class="form-control" placeholder="Column name" style="flex: 1;">
            <span>=</span>
            <input type="text" class="form-control" placeholder="New value" style="flex: 2;">
            <button type="button" class="btn btn-outline-danger btn-remove-field" onclick="removeField('modal-update-field-${fieldId}')">
                <i class="bi bi-x"></i>
            </button>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', fieldHTML);
}

function addModalWhereField(type) {
    const container = document.getElementById(`${type}Where`);
    const fieldId = Date.now();
    
    const fieldHTML = `
        <div class="field-row" id="modal-${type}-where-${fieldId}">
            <input type="text" class="form-control" placeholder="Column" style="flex: 1;">
            <select class="form-select" style="flex: 0 0 80px;">
                <option>=</option>
                <option>!=</option>
                <option>&gt;</option>
                <option>&lt;</option>
                <option>&gt;=</option>
                <option>&lt;=</option>
                <option>LIKE</option>
            </select>
            <input type="text" class="form-control" placeholder="Value" style="flex: 1;">
            <button type="button" class="btn btn-outline-danger btn-remove-field" onclick="removeField('modal-${type}-where-${fieldId}')">
                <i class="bi bi-x"></i>
            </button>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', fieldHTML);
}


async function executeModalQuery() {
    const operation = document.getElementById('modalQueryOperation').value;
    const table = document.getElementById('modalTableSelect').value;
    
    if (!operation || !table) {
        showToast('Error', 'Please select operation and table', 'danger');
        return;
    }
    
    switch(operation) {
        case 'SELECT':
            await executeAdvancedSelect();
            break;
        case 'INSERT':
            await executeAdvancedInsert();
            break;
        case 'UPDATE':
            await executeAdvancedUpdate();
            break;
        case 'DELETE':
            await executeAdvancedDelete();
            break;
    }
}


async function executeAdvancedSelect() {
    const table = document.getElementById('modalTableSelect').value;
    const sql = buildSelectSQL(table);
    
    showSpinner(true);
    
    try {
        const response = await fetch('api/database.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'executeQuery',
                sql: sql,
                ...dbConnection
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Success', `Query executed successfully. ${result.rowCount} rows returned.`, 'success');
            
            if (result.data && result.data.length > 0) {
                const columns = Object.keys(result.data[0]);
                displayTableData(result.data, columns);
                document.getElementById('tableTitle').innerHTML = `<i class="bi bi-search"></i> Query Results - ${table}`;
            } else {
                document.getElementById('dataContainer').innerHTML = `
                    <div class="text-center text-muted">
                        <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                        <p class="mt-2">No data returned from query</p>
                        <small class="text-muted">Query: ${sql}</small>
                    </div>
                `;
            }
            
            
            const modal = bootstrap.Modal.getInstance(document.getElementById('queryBuilderModal'));
            modal.hide();
        } else {
            showToast('Error', result.message, 'danger');
        }
    } catch (error) {
        showToast('Error', 'Failed to execute query: ' + error.message, 'danger');
    }
    
    showSpinner(false);
}

async function executeAdvancedInsert() {
    const table = document.getElementById('modalTableSelect').value;
    const fields = document.querySelectorAll('#insertFieldsContainer .query-field-row');
    
    if (fields.length === 0) {
        showToast('Error', 'Please add at least one field', 'danger');
        return;
    }
    
    const data = {};
    
    fields.forEach(field => {
        const column = field.querySelector('select').value;
        const value = field.querySelector('input').value;
        
        if (column && value) {
            data[column] = value;
        }
    });
    
    if (Object.keys(data).length === 0) {
        showToast('Error', 'Please fill in column names and values', 'danger');
        return;
    }
    
    showSpinner(true);
    
    try {
        const response = await fetch('api/database.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'insert',
                table: table,
                data: data,
                ...dbConnection
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Success', `${result.message}. Insert ID: ${result.insertId}`, 'success');
            if (currentTable === table) {
                loadTableData(table);
            }
            
            
            const modal = bootstrap.Modal.getInstance(document.getElementById('queryBuilderModal'));
            modal.hide();
        } else {
            showToast('Error', result.message, 'danger');
        }
    } catch (error) {
        showToast('Error', 'Failed to insert record: ' + error.message, 'danger');
    }
    
    showSpinner(false);
}

async function executeAdvancedUpdate() {
    const table = document.getElementById('modalTableSelect').value;
    const setFields = document.querySelectorAll('#updateSetContainer .query-field-row');
    const whereFields = document.querySelectorAll('#updateWhereContainer .query-field-row');
    
    if (setFields.length === 0) {
        showToast('Error', 'Please add at least one SET field', 'danger');
        return;
    }
    
    if (whereFields.length === 0) {
        showToast('Error', 'Please add at least one WHERE condition for safety', 'danger');
        return;
    }
    
    const data = {};
    const where = {};
    
    setFields.forEach(field => {
        const column = field.querySelector('select').value;
        const value = field.querySelector('input').value;
        
        if (column && value) {
            data[column] = value;
        }
    });
    
    whereFields.forEach(field => {
        const column = field.querySelector('select').value;
        const operator = field.querySelectorAll('select')[1]?.value;
        const value = field.querySelector('input')?.value;
        
        if (column && operator === '=' && value) {
            where[column] = value;
        }
    });
    
    if (Object.keys(data).length === 0) {
        showToast('Error', 'Please specify fields to update', 'danger');
        return;
    }
    
    if (Object.keys(where).length === 0) {
        showToast('Error', 'Please specify WHERE conditions (only = operator supported for now)', 'danger');
        return;
    }
    
    const sql = buildUpdateSQL(table);
    if (confirm(`Are you sure you want to execute this UPDATE?\n\n${sql}`)) {
        showSpinner(true);
        
        try {
            const response = await fetch('api/database.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'update',
                    table: table,
                    data: data,
                    where: where,
                    ...dbConnection
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                showToast('Success', `${result.message}. ${result.rowCount} row(s) affected.`, 'success');
                if (currentTable === table) {
                    loadTableData(table);
                }
                
                
                const modal = bootstrap.Modal.getInstance(document.getElementById('queryBuilderModal'));
                modal.hide();
            } else {
                showToast('Error', result.message, 'danger');
            }
        } catch (error) {
            showToast('Error', 'Failed to update record: ' + error.message, 'danger');
        }
        
        showSpinner(false);
    }
}

async function executeAdvancedDelete() {
    const table = document.getElementById('modalTableSelect').value;
    const whereFields = document.querySelectorAll('#deleteWhereContainer .query-field-row');
    
    if (whereFields.length === 0) {
        showToast('Error', 'Please add at least one WHERE condition for safety', 'danger');
        return;
    }
    
    const where = {};
    
    whereFields.forEach(field => {
        const column = field.querySelector('select').value;
        const operator = field.querySelectorAll('select')[1]?.value;
        const value = field.querySelector('input')?.value;
        
        if (column && operator === '=' && value) {
            where[column] = value;
        }
    });
    
    if (Object.keys(where).length === 0) {
        showToast('Error', 'Please specify WHERE conditions (only = operator supported for now)', 'danger');
        return;
    }
    
    const sql = buildDeleteSQL(table);
    if (confirm(`?? WARNING: Are you sure you want to DELETE records?\n\n${sql}\n\nThis action cannot be undone!`)) {
        showSpinner(true);
        
        try {
            const response = await fetch('api/database.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'delete',
                    table: table,
                    where: where,
                    ...dbConnection
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                showToast('Success', `${result.message}. ${result.rowCount} row(s) affected.`, 'success');
                if (currentTable === table) {
                    loadTableData(table);
                }
                
                
                const modal = bootstrap.Modal.getInstance(document.getElementById('queryBuilderModal'));
                modal.hide();
            } else {
                showToast('Error', result.message, 'danger');
            }
        } catch (error) {
            showToast('Error', 'Failed to delete record: ' + error.message, 'danger');
        }
        
        showSpinner(false);
    }
}


async function executeModalSelect() {
    const table = document.getElementById('modalSelectTable').value;
    const columns = document.getElementById('modalSelectColumns').value || '*';
    const where = document.getElementById('modalSelectWhere').value;
    const limit = document.getElementById('modalSelectLimit').value;
    
    if (!table) {
        showToast('Error', 'Please select a table', 'danger');
        return;
    }
    
    let query = `SELECT ${columns} FROM \`${table}\``;
    if (where) query += ` WHERE ${where}`;
    if (limit) query += ` LIMIT ${limit}`;
    
    showSpinner(true);
    
    try {
        const response = await fetch('api/database.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'executeQuery',
                sql: query,
                ...dbConnection
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Success', `Query executed successfully. ${result.rowCount} rows returned.`, 'success');
            
            if (result.data && result.data.length > 0) {
                const columns = Object.keys(result.data[0]);
                displayTableData(result.data, columns);
                document.getElementById('tableTitle').innerHTML = `<i class="bi bi-search"></i> Query Results`;
            } else {
                document.getElementById('dataContainer').innerHTML = `
                    <div class="text-center text-muted">
                        <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                        <p class="mt-2">No data returned from query</p>
                    </div>
                `;
            }
            
            
            const modal = bootstrap.Modal.getInstance(document.getElementById('queryBuilderModal'));
            modal.hide();
        } else {
            showToast('Error', result.message, 'danger');
        }
    } catch (error) {
        showToast('Error', 'Failed to execute query: ' + error.message, 'danger');
    }
    
    showSpinner(false);
}

async function executeModalInsert() {
    const table = document.getElementById('modalInsertTable').value;
    const fields = document.querySelectorAll('#modalInsertFields .field-row');
    
    if (!table) {
        showToast('Error', 'Please select a table', 'danger');
        return;
    }
    
    if (fields.length === 0) {
        showToast('Error', 'Please add at least one field', 'danger');
        return;
    }
    
    const data = {};
    
    fields.forEach(field => {
        const inputs = field.querySelectorAll('input');
        if (inputs[0].value && inputs[1].value) {
            data[inputs[0].value] = inputs[1].value;
        }
    });
    
    if (Object.keys(data).length === 0) {
        showToast('Error', 'Please fill in column names and values', 'danger');
        return;
    }
    
    showSpinner(true);
    
    try {
        const response = await fetch('api/database.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'insert',
                table: table,
                data: data,
                ...dbConnection
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Success', result.message, 'success');
            if (currentTable === table) {
                loadTableData(table);
            }
            
            
            const modal = bootstrap.Modal.getInstance(document.getElementById('queryBuilderModal'));
            modal.hide();
            document.getElementById('modalInsertFields').innerHTML = '';
        } else {
            showToast('Error', result.message, 'danger');
        }
    } catch (error) {
        showToast('Error', 'Failed to insert record: ' + error.message, 'danger');
    }
    
    showSpinner(false);
}

async function executeModalUpdate() {
    const table = document.getElementById('modalUpdateTable').value;
    const setFields = document.querySelectorAll('#modalUpdateFields .field-row');
    const whereFields = document.querySelectorAll('#modalUpdateWhere .field-row');
    
    if (!table) {
        showToast('Error', 'Please select a table', 'danger');
        return;
    }
    
    if (setFields.length === 0) {
        showToast('Error', 'Please add at least one SET field', 'danger');
        return;
    }
    
    if (whereFields.length === 0) {
        showToast('Error', 'Please add at least one WHERE condition', 'danger');
        return;
    }
    
    const data = {};
    const where = {};
    
    setFields.forEach(field => {
        const inputs = field.querySelectorAll('input');
        if (inputs[0].value && inputs[1].value) {
            data[inputs[0].value] = inputs[1].value;
        }
    });
    
    whereFields.forEach(field => {
        const inputs = field.querySelectorAll('input');
        const operator = field.querySelector('select').value;
        if (inputs[0].value && inputs[1].value && operator === '=') {
            where[inputs[0].value] = inputs[1].value;
        }
    });
    
    if (Object.keys(data).length === 0 || Object.keys(where).length === 0) {
        showToast('Error', 'Please fill in all required fields', 'danger');
        return;
    }
    
    showSpinner(true);
    
    try {
        const response = await fetch('api/database.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'update',
                table: table,
                data: data,
                where: where,
                ...dbConnection
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Success', `${result.message}. ${result.rowCount} row(s) affected.`, 'success');
            if (currentTable === table) {
                loadTableData(table);
            }
            
            
            const modal = bootstrap.Modal.getInstance(document.getElementById('queryBuilderModal'));
            modal.hide();
            document.getElementById('modalUpdateFields').innerHTML = '';
            document.getElementById('modalUpdateWhere').innerHTML = '';
        } else {
            showToast('Error', result.message, 'danger');
        }
    } catch (error) {
        showToast('Error', 'Failed to update record: ' + error.message, 'danger');
    }
    
    showSpinner(false);
}

async function executeModalDelete() {
    const table = document.getElementById('modalDeleteTable').value;
    const whereFields = document.querySelectorAll('#modalDeleteWhere .field-row');
    
    if (!table) {
        showToast('Error', 'Please select a table', 'danger');
        return;
    }
    
    if (whereFields.length === 0) {
        showToast('Error', 'Please add at least one WHERE condition', 'danger');
        return;
    }
    
    const where = {};
    
    whereFields.forEach(field => {
        const inputs = field.querySelectorAll('input');
        const operator = field.querySelector('select').value;
        if (inputs[0].value && inputs[1].value && operator === '=') {
            where[inputs[0].value] = inputs[1].value;
        }
    });
    
    if (Object.keys(where).length === 0) {
        showToast('Error', 'Please fill in WHERE conditions', 'danger');
        return;
    }
    
    const whereParts = [];
    Object.keys(where).forEach(key => {
        whereParts.push(`${key} = '${where[key]}'`);
    });
    const previewQuery = `DELETE FROM ${table} WHERE ${whereParts.join(' AND ')}`;
    
    if (confirm(`Are you sure you want to execute: ${previewQuery}`)) {
        showSpinner(true);
        
        try {
            const response = await fetch('api/database.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'delete',
                    table: table,
                    where: where,
                    ...dbConnection
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                showToast('Success', `${result.message}. ${result.rowCount} row(s) affected.`, 'success');
                if (currentTable === table) {
                    loadTableData(table);
                }
                
                
                const modal = bootstrap.Modal.getInstance(document.getElementById('queryBuilderModal'));
                modal.hide();
                document.getElementById('modalDeleteWhere').innerHTML = '';
            } else {
                showToast('Error', result.message, 'danger');
            }
        } catch (error) {
            showToast('Error', 'Failed to delete record: ' + error.message, 'danger');
        }
        
        showSpinner(false);
    }
}
