let columnIndex = 1;

function addColumn() {
    const container = document.getElementById('columnsContainer');
    const newRow = document.createElement('div');
    newRow.className = 'row mb-2 column-row';
    newRow.innerHTML = `
        <div class="col-md-3">
            <input type="text" class="form-control" name="columns[${columnIndex}][name]" placeholder="Column name" required>
        </div>
        <div class="col-md-2">
            <select class="form-select" name="columns[${columnIndex}][type]" required>
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
            <input type="text" class="form-control" name="columns[${columnIndex}][length]" placeholder="Length">
        </div>
        <div class="col-md-2">
            <input type="text" class="form-control" name="columns[${columnIndex}][default]" placeholder="Default">
        </div>
        <div class="col-md-2">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="columns[${columnIndex}][null]" id="null_${columnIndex}">
                <label class="form-check-label" for="null_${columnIndex}">NULL</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="columns[${columnIndex}][primary]" id="primary_${columnIndex}">
                <label class="form-check-label" for="primary_${columnIndex}">PK</label>
            </div>
        </div>
        <div class="col-md-1">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="columns[${columnIndex}][auto_increment]" id="ai_${columnIndex}">
                <label class="form-check-label" for="ai_${columnIndex}">AI</label>
            </div>
            <button type="button" class="btn btn-outline-danger btn-sm mt-1" onclick="removeColumn(this)">
                <i class="bi bi-dash"></i>
            </button>
        </div>
    `;
    container.appendChild(newRow);
    columnIndex++;
}

function removeColumn(button) {
    const row = button.closest('.column-row');
    row.remove();
}

function addSampleColumns() {
    
    const container = document.getElementById('columnsContainer');
    const rows = container.querySelectorAll('.column-row');
    for (let i = 1; i < rows.length; i++) {
        rows[i].remove();
    }
    
    
    columnIndex = 1;
    
    
    const firstRow = container.querySelector('.column-row');
    const inputs = firstRow.querySelectorAll('input, select');
    inputs[0].value = 'id'; 
    inputs[1].value = 'INT'; 
    inputs[2].value = ''; 
    inputs[3].value = ''; 
    inputs[4].checked = false; 
    inputs[5].checked = true; 
    inputs[6].checked = true; 
    
    
    const sampleColumns = [
        { name: 'name', type: 'VARCHAR', length: '255', null: false },
        { name: 'email', type: 'VARCHAR', length: '255', null: true },
        { name: 'created_at', type: 'DATETIME', default: 'CURRENT_TIMESTAMP', null: false },
        { name: 'updated_at', type: 'DATETIME', null: true }
    ];
    
    sampleColumns.forEach(col => {
        addColumn();
        const lastRow = container.lastElementChild;
        const inputs = lastRow.querySelectorAll('input, select');
        inputs[0].value = col.name; 
        inputs[1].value = col.type; 
        if (col.length) inputs[2].value = col.length; 
        if (col.default) inputs[3].value = col.default; 
        inputs[4].checked = col.null; 
    });
}


document.addEventListener('change', function(e) {
    if (e.target.name && e.target.name.includes('[type]')) {
        const row = e.target.closest('.column-row');
        const lengthInput = row.querySelector('input[name*="[length]"]');
        
        switch (e.target.value) {
            case 'VARCHAR':
                if (!lengthInput.value) lengthInput.value = '255';
                break;
            case 'CHAR':
                if (!lengthInput.value) lengthInput.value = '50';
                break;
            case 'DECIMAL':
                if (!lengthInput.value) lengthInput.value = '10,2';
                break;
            default:
                if (['INT', 'BIGINT', 'TEXT', 'DATETIME', 'DATE', 'TIME', 'BOOLEAN'].includes(e.target.value)) {
                    lengthInput.value = '';
                }
        }
    }
});


document.addEventListener('change', function(e) {
    if (e.target.name && e.target.name.includes('[primary]') && e.target.checked) {
        const allPrimaryKeys = document.querySelectorAll('input[name*="[primary]"]');
        allPrimaryKeys.forEach(pk => {
            if (pk !== e.target) {
                pk.checked = false;
            }
        });
    }
});
