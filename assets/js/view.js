
let tableColumns = [];
let tableName = '';
let baseUrl = '';


function initializeViewPage(columns, table, url) {
    tableColumns = columns;
    tableName = table;
    baseUrl = url;
}

function toggleUpdateRecords() {
    const section = document.getElementById('updateRecordsSection');
    const deleteSection = document.getElementById('deleteRecordsSection');
    
    
    if (deleteSection.classList.contains('show')) {
        deleteSection.classList.remove('show');
    }
    
    
    section.classList.toggle('show');
    
    if (section.classList.contains('show')) {
        
        setTimeout(() => {
            buildUpdateSQL();
            setupLivePreview('update');
        }, 300);
    }
}

function toggleDeleteRecords() {
    const section = document.getElementById('deleteRecordsSection');
    const updateSection = document.getElementById('updateRecordsSection');
    
    
    if (updateSection.classList.contains('show')) {
        updateSection.classList.remove('show');
    }
    
    
    section.classList.toggle('show');
    
    if (section.classList.contains('show')) {
        
        setTimeout(() => {
            buildDeleteSQL();
            setupLivePreview('delete');
        }, 300);
    }
}

function setupLivePreview(type) {
    
    const container = document.getElementById(type === 'update' ? 'updateRecordsSection' : 'deleteRecordsSection');
    
    
    const selects = container.querySelectorAll('select');
    const inputs = container.querySelectorAll('input[type="text"]');
    
    selects.forEach(select => {
        select.addEventListener('change', () => type === 'update' ? buildUpdateSQL() : buildDeleteSQL());
    });
    
    inputs.forEach(input => {
        input.addEventListener('input', () => type === 'update' ? buildUpdateSQL() : buildDeleteSQL());
    });
}

function addSetField() {
    const container = document.getElementById('bulkUpdateSetFields');
    const setRow = document.createElement('div');
    setRow.className = 'row mb-2 set-row';
    
    setRow.innerHTML = `
        <div class="col-md-5">
            <select class="form-select" name="set_column[]">
                <option value="">Select Column</option>
                ${tableColumns.map(col => `<option value="${escapeHtml(col.Field)}">${escapeHtml(col.Field)}</option>`).join('')}
            </select>
        </div>
        <div class="col-md-6">
            <input type="text" class="form-control" name="set_value[]" placeholder="New value">
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-outline-danger" onclick="removeField(this, 'update')">
                <i class="bi bi-dash"></i>
            </button>
        </div>
    `;
    
    container.appendChild(setRow);
    
    
    const newSelect = setRow.querySelector('select');
    const newInput = setRow.querySelector('input');
    
    newSelect.addEventListener('change', buildUpdateSQL);
    newInput.addEventListener('input', buildUpdateSQL);
}

function addWhereField(modalType) {
    const container = document.getElementById(modalType === 'update' ? 'bulkUpdateWhereFields' : 'bulkDeleteWhereFields');
    const whereRow = document.createElement('div');
    whereRow.className = 'row mb-2 where-row';
    
    whereRow.innerHTML = `
        <div class="col-md-4">
            <select class="form-select" name="where_column[]">
                <option value="">Select Column</option>
                ${tableColumns.map(col => `<option value="${escapeHtml(col.Field)}">${escapeHtml(col.Field)}</option>`).join('')}
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
            <button type="button" class="btn btn-outline-danger" onclick="removeField(this, '${modalType}')">
                <i class="bi bi-dash"></i>
            </button>
        </div>
    `;
    
    container.appendChild(whereRow);
    
    
    const selects = whereRow.querySelectorAll('select');
    const input = whereRow.querySelector('input');
    
    selects.forEach(select => {
        select.addEventListener('change', modalType === 'update' ? buildUpdateSQL : buildDeleteSQL);
    });
    
    input.addEventListener('input', modalType === 'update' ? buildUpdateSQL : buildDeleteSQL);
}

function removeField(button, type) {
    const row = button.closest('.row');
    row.remove();
    
    
    if (type === 'update') {
        buildUpdateSQL();
    } else {
        buildDeleteSQL();
    }
}

function buildUpdateSQL() {
    const setColumns = document.querySelectorAll('#bulkUpdateSetFields select[name="set_column[]"]');
    const setValues = document.querySelectorAll('#bulkUpdateSetFields input[name="set_value[]"]');
    const whereColumns = document.querySelectorAll('#bulkUpdateWhereFields select[name="where_column[]"]');
    const whereOperators = document.querySelectorAll('#bulkUpdateWhereFields select[name="where_operator[]"]');
    const whereValues = document.querySelectorAll('#bulkUpdateWhereFields input[name="where_value[]"]');
    
    let setClause = [];
    for (let i = 0; i < setColumns.length; i++) {
        if (setColumns[i].value && setValues[i].value !== undefined) {
            
            setClause.push("`" + setColumns[i].value + "` = '" + escapeSQL(setValues[i].value) + "'");
        }
    }
    
    let whereClause = [];
    for (let i = 0; i < whereColumns.length; i++) {
        if (whereColumns[i].value) {
            const operator = whereOperators[i].value;
            if (operator === 'IS NULL' || operator === 'IS NOT NULL') {
                whereClause.push("`" + whereColumns[i].value + "` " + operator);
            } else if (whereValues[i].value !== undefined) {
                whereClause.push("`" + whereColumns[i].value + "` " + operator + " '" + escapeSQL(whereValues[i].value) + "'");
            }
        }
    }
    
    let sql = "UPDATE `" + tableName + "` SET ";
    sql += setClause.length ? setClause.join(', ') : '...';
    sql += ' WHERE ';
    sql += whereClause.length ? whereClause.join(' AND ') : '...';
    
    document.getElementById('updateSqlPreview').textContent = sql;
    
    
    if (setClause.length > 0 && whereClause.length > 0) {
        fetchAffectedRows('update', sql);
    } else {
        document.getElementById('updatePreviewResult').innerHTML = '<div class="alert alert-info">Complete the form to see affected rows</div>';
    }
    
    return { sql, isValid: setClause.length > 0 && whereClause.length > 0 };
}

function buildDeleteSQL() {
    const whereColumns = document.querySelectorAll('#bulkDeleteWhereFields select[name="where_column[]"]');
    const whereOperators = document.querySelectorAll('#bulkDeleteWhereFields select[name="where_operator[]"]');
    const whereValues = document.querySelectorAll('#bulkDeleteWhereFields input[name="where_value[]"]');
    
    let whereClause = [];
    for (let i = 0; i < whereColumns.length; i++) {
        if (whereColumns[i].value) {
            const operator = whereOperators[i].value;
            if (operator === 'IS NULL' || operator === 'IS NOT NULL') {
                whereClause.push("`" + whereColumns[i].value + "` " + operator);
            } else if (whereValues[i].value !== undefined) {
                whereClause.push("`" + whereColumns[i].value + "` " + operator + " '" + escapeSQL(whereValues[i].value) + "'");
            }
        }
    }
    
    let sql = "DELETE FROM `" + tableName + "` WHERE ";
    sql += whereClause.length ? whereClause.join(' AND ') : '...';
    
    document.getElementById('deleteSqlPreview').textContent = sql;
    
    
    if (whereClause.length > 0) {
        fetchAffectedRows('delete', sql);
    } else {
        document.getElementById('deletePreviewResult').innerHTML = '<div class="alert alert-info">Complete the form to see affected rows</div>';
    }
    
    return { sql, isValid: whereClause.length > 0 };
}

function escapeSQL(value) {
    if (value === null || value === undefined) return '';
    return value.toString().replace(/'/g, "''");
}

function fetchAffectedRows(operation, sql) {
    const previewContainer = document.getElementById(operation === 'update' ? 'updatePreviewResult' : 'deletePreviewResult');
    previewContainer.innerHTML = '<div class="d-flex justify-content-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
    
    
    const formData = new FormData();
    formData.append('preview_query', sql);
    formData.append('operation', operation);
    formData.append('table', tableName);
    
    
    fetch(baseUrl + 'preview_query.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            return response.json();
        }
        throw new Error('Received non-JSON response from server');
    })
    .then(data => {
        if (data.error) {
            previewContainer.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
        } else {
            let html = `<div class="alert alert-info">
                <strong>${data.count}</strong> row(s) will be affected
            </div>`;
            
            if (data.sample && data.sample.length > 0) {
                html += '<div class="table-responsive" style="max-height:200px;"><table class="table table-sm table-dark">';
                html += '<thead><tr>';
                
                
                for (const key in data.sample[0]) {
                    html += `<th>${escapeHtml(key)}</th>`;
                }
                html += '</tr></thead><tbody>';
                
                
                data.sample.forEach(row => {
                    html += '<tr>';
                    for (const key in row) {
                        html += `<td>${escapeHtml(row[key] || '')}</td>`;
                    }
                    html += '</tr>';
                });
                
                html += '</tbody></table></div>';
                
                if (data.count > data.sample.length) {
                    html += `<p class="text-muted small mt-2">Showing ${data.sample.length} of ${data.count} affected rows</p>`;
                }
            }
            
            previewContainer.innerHTML = html;
        }
    })
    .catch(error => {
        console.error('Preview error:', error);
        previewContainer.innerHTML = `<div class="alert alert-danger">
            <p><strong>Error:</strong> ${error.message}</p>
            <p class="small text-muted">Check the browser console for more details.</p>
        </div>`;
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
