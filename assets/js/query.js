

let tableColumns = {};
let baseUrl = '';

function initializeQueryPage(baseUrlParam) {
    baseUrl = baseUrlParam;
    
    
    document.addEventListener('DOMContentLoaded', function() {
        checkForDangerousQuery();
        
        
        const tableSelect = document.getElementById('tableSelect');
        if (tableSelect) {
            tableSelect.addEventListener('change', handleTableChange);
        }
    });
}

function toggleQueryBuilder() {
    const builder = document.getElementById('visualQueryBuilder');
    const icon = document.getElementById('toggleIcon');
    const text = document.getElementById('toggleText');
    
    if (builder.style.display === 'none') {
        builder.style.display = 'block';
        icon.className = 'bi bi-eye-slash';
        text.textContent = 'Hide Builder';
    } else {
        builder.style.display = 'none';
        icon.className = 'bi bi-eye';
        text.textContent = 'Show Builder';
    }
}

function handleTableChange() {
    const table = this.value;
    const columnSelect = document.getElementById('columnSelect');
    const conditionColumns = document.querySelectorAll('.condition-column');
    const orderByColumn = document.getElementById('orderByColumn');
    
    if (table) {
        
        console.log('Fetching columns for table:', table);
        console.log('Request URL:', `${baseUrl}get_columns.php?table=${encodeURIComponent(table)}`);
        
        fetch(`${baseUrl}get_columns.php?table=${encodeURIComponent(table)}`)
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(columns => {
                console.log('Received columns:', columns);
                tableColumns[table] = columns;
                
                
                columnSelect.innerHTML = '<option value="*">* (All columns)</option>';
                columns.forEach(column => {
                    columnSelect.innerHTML += `<option value="${column.Field}">${column.Field}</option>`;
                });
                columnSelect.disabled = false;
                
                
                conditionColumns.forEach(select => {
                    select.innerHTML = '<option value="">Column...</option>';
                    columns.forEach(column => {
                        select.innerHTML += `<option value="${column.Field}">${column.Field}</option>`;
                    });
                    select.disabled = false;
                });
                
                
                orderByColumn.innerHTML = '<option value="">Order by column...</option>';
                columns.forEach(column => {
                    orderByColumn.innerHTML += `<option value="${column.Field}">${column.Field}</option>`;
                });
                orderByColumn.disabled = false;
            })
            .catch(error => {
                console.error('Error fetching columns:', error);
                alert('Failed to load columns for the selected table. Please check the console for details.');
            });
    } else {
        
        columnSelect.innerHTML = '<option value="*">* (All columns)</option>';
        columnSelect.disabled = true;
        
        conditionColumns.forEach(select => {
            select.innerHTML = '<option value="">Column...</option>';
            select.disabled = true;
        });
        
        orderByColumn.innerHTML = '<option value="">Order by column...</option>';
        orderByColumn.disabled = true;
    }
}

function addCondition() {
    const container = document.getElementById('conditionsContainer');
    const conditionRow = document.createElement('div');
    conditionRow.className = 'condition-row mb-2';
    
    const table = document.getElementById('tableSelect').value;
    let columnOptions = '<option value="">Column...</option>';
    
    if (table && tableColumns[table]) {
        tableColumns[table].forEach(column => {
            columnOptions += `<option value="${column.Field}">${column.Field}</option>`;
        });
    }
    
    conditionRow.innerHTML = `
        <div class="row">
            <div class="col-4">
                <select class="form-select condition-column" ${!table ? 'disabled' : ''}>
                    ${columnOptions}
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
    `;
    
    container.appendChild(conditionRow);
}

function removeCondition(button) {
    const conditionRow = button.closest('.condition-row');
    conditionRow.remove();
}

function generateQuery() {
    const table = document.getElementById('tableSelect').value;
    if (!table) {
        alert('Please select a table first.');
        return;
    }
    
    let query = 'SELECT ';
    
    
    const selectedColumns = Array.from(document.getElementById('columnSelect').selectedOptions).map(option => option.value);
    if (selectedColumns.length === 0 || selectedColumns.includes('*')) {
        query += '*';
    } else {
        query += selectedColumns.map(col => `\`${col}\``).join(', ');
    }
    
    query += ` FROM \`${table}\``;
    
    
    const conditions = [];
    document.querySelectorAll('.condition-row').forEach(row => {
        const column = row.querySelector('.condition-column').value;
        const operator = row.querySelector('.condition-operator').value;
        const value = row.querySelector('.condition-value').value;
        
        if (column && operator) {
            if (operator === 'IS NULL' || operator === 'IS NOT NULL') {
                conditions.push(`\`${column}\` ${operator}`);
            } else if (value) {
                if (operator === 'IN' || operator === 'NOT IN') {
                    conditions.push(`\`${column}\` ${operator} (${value})`);
                } else {
                    const quotedValue = isNaN(value) ? `'${value.replace(/'/g, "''")}'` : value;
                    conditions.push(`\`${column}\` ${operator} ${quotedValue}`);
                }
            }
        }
    });
    
    if (conditions.length > 0) {
        query += ' WHERE ' + conditions.join(' AND ');
    }
    
    
    const orderByColumn = document.getElementById('orderByColumn').value;
    const orderByDirection = document.getElementById('orderByDirection').value;
    if (orderByColumn) {
        query += ` ORDER BY \`${orderByColumn}\` ${orderByDirection}`;
    }
    
    
    const limit = document.getElementById('limitValue').value;
    if (limit) {
        query += ` LIMIT ${limit}`;
    }
    
    document.getElementById('query').value = query;
    
    
    scrollToElementOnMobile('query');
}

function resetBuilder() {
    document.getElementById('tableSelect').value = '';
    document.getElementById('columnSelect').innerHTML = '<option value="*">* (All columns)</option>';
    document.getElementById('columnSelect').disabled = true;
    
    const container = document.getElementById('conditionsContainer');
    container.innerHTML = `
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
    `;
    
    document.getElementById('orderByColumn').innerHTML = '<option value="">Order by column...</option>';
    document.getElementById('orderByColumn').disabled = true;
    document.getElementById('orderByDirection').value = 'ASC';
    document.getElementById('limitValue').value = '';
}

function formatQuery() {
    const query = document.getElementById('query').value;
    if (!query) return;
    
    const formatted = query
        
        .replace(/\bSELECT\b/gi, '\nSELECT')
        .replace(/\bINSERT\s+INTO\b/gi, '\nINSERT INTO')
        .replace(/\bUPDATE\b/gi, '\nUPDATE')
        .replace(/\bDELETE\s+FROM\b/gi, '\nDELETE FROM')
        .replace(/\bDROP\s+(TABLE|DATABASE|INDEX|VIEW)\b/gi, '\nDROP $1')
        .replace(/\bCREATE\s+(TABLE|DATABASE|INDEX|VIEW)\b/gi, '\nCREATE $1')
        .replace(/\bALTER\s+TABLE\b/gi, '\nALTER TABLE')
        .replace(/\bTRUNCATE\s+TABLE\b/gi, '\nTRUNCATE TABLE')
        
        
        .replace(/\bFROM\b/gi, '\nFROM')
        .replace(/\bSET\b/gi, '\nSET')
        .replace(/\bVALUES\b/gi, '\nVALUES')
        .replace(/\bWHERE\b/gi, '\nWHERE')
        .replace(/\bORDER BY\b/gi, '\nORDER BY')
        .replace(/\bGROUP BY\b/gi, '\nGROUP BY')
        .replace(/\bHAVING\b/gi, '\nHAVING')
        .replace(/\bLIMIT\b/gi, '\nLIMIT')
        .replace(/\bOFFSET\b/gi, '\nOFFSET')
        
        
        .replace(/\b(INNER\s+JOIN|LEFT\s+JOIN|RIGHT\s+JOIN|FULL\s+OUTER\s+JOIN|JOIN)\b/gi, '\n$1')
        .replace(/\bON\b/gi, '\n  ON')
        
        
        .replace(/\bAND\b/gi, '\n  AND')
        .replace(/\bOR\b/gi, '\n  OR')
        
        
        .replace(/\n\s*\n/g, '\n')
        .trim();
    
    document.getElementById('query').value = formatted;
    
    
    checkForDangerousQuery();
    
    
    scrollToElementOnMobile('query');
}

function clearQuery() {
    if (confirm('Are you sure you want to clear the query?')) {
        document.getElementById('query').value = '';
        checkForDangerousQuery(); 
    }
}

function checkForDangerousQuery() {
    const query = document.getElementById('query').value.toUpperCase();
    const dangerWarning = document.getElementById('dangerWarning');
    const executeButton = document.getElementById('executeButton');
    
    
    const dangerousPatterns = [
        /\bDROP\s+(TABLE|DATABASE|INDEX|VIEW)\b/i,
        /\bDELETE\s+FROM\b/i,
        /\bTRUNCATE\s+TABLE\b/i
    ];
    
    
    const isDangerous = dangerousPatterns.some(pattern => pattern.test(query));
    
    if (isDangerous && query.trim() !== '') {
        dangerWarning.classList.remove('d-none');
        executeButton.classList.remove('btn-primary');
        executeButton.classList.add('btn-danger');
        executeButton.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i> Execute Dangerous Query';
    } else {
        dangerWarning.classList.add('d-none');
        executeButton.classList.remove('btn-danger');
        executeButton.classList.add('btn-primary');
        executeButton.innerHTML = '<i class="bi bi-play-fill"></i> Execute Query';
    }
}

function validateDangerousQuery() {
    const query = document.getElementById('query').value.toUpperCase();
    
    
    const dangerousPatterns = [
        { pattern: /\bDROP\s+(TABLE|DATABASE|INDEX|VIEW)\b/i, action: 'DROP' },
        { pattern: /\bDELETE\s+FROM\b/i, action: 'DELETE' },
        { pattern: /\bTRUNCATE\s+TABLE\b/i, action: 'TRUNCATE' }
    ];
    
    
    for (const dangerous of dangerousPatterns) {
        if (dangerous.pattern.test(query)) {
            const confirmMessage = `?? DANGER: You are about to execute a ${dangerous.action} operation!

This operation is IRREVERSIBLE and will permanently:
${dangerous.action === 'DROP' ? '• Delete entire tables/databases and all their data' : ''}
${dangerous.action === 'DELETE' ? '• Remove data from tables' : ''}
${dangerous.action === 'TRUNCATE' ? '• Remove ALL data from tables' : ''}

?? Make sure you have a backup before proceeding!

Type "I UNDERSTAND THE RISK" to confirm:`;
            
            const userConfirmation = prompt(confirmMessage);
            
            if (userConfirmation !== "I UNDERSTAND THE RISK") {
                alert('Operation cancelled for safety. Query not executed.');
                return false;
            }
            break;
        }
    }
    
    return true;
}

function exportResults() {
    const table = document.getElementById('resultsTable');
    if (!table) return;
    
    let csv = '';
    
    
    const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.textContent);
    csv += headers.join(',') + '\n';
    
    
    table.querySelectorAll('tbody tr').forEach(row => {
        const cells = Array.from(row.querySelectorAll('td')).map(td => {
            let value = td.textContent;
            if (value.includes(',') || value.includes('"') || value.includes('\n')) {
                value = '"' + value.replace(/"/g, '""') + '"';
            }
            return value;
        });
        csv += cells.join(',') + '\n';
    });
    
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `query_results_${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}
