// backup.js - Backup and restore functionality

function initializeBackupPage() {
    document.addEventListener('DOMContentLoaded', function() {
        const tableCheckboxes = document.querySelectorAll('.table-checkbox');
        const selectAll = document.getElementById('selectAllTables');
        
        if (selectAll && tableCheckboxes.length > 0) {
            tableCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const allChecked = Array.from(tableCheckboxes).every(cb => cb.checked);
                    const noneChecked = Array.from(tableCheckboxes).every(cb => !cb.checked);
                    
                    if (allChecked) {
                        selectAll.checked = true;
                        selectAll.indeterminate = false;
                    } else if (noneChecked) {
                        selectAll.checked = false;
                        selectAll.indeterminate = false;
                    } else {
                        selectAll.checked = false;
                        selectAll.indeterminate = true;
                    }
                });
            });
        }
    });
}

function toggleAllTables() {
    const selectAll = document.getElementById('selectAllTables');
    const tableCheckboxes = document.querySelectorAll('.table-checkbox');
    
    tableCheckboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
}

function confirmRestore() {
    return confirm('Are you sure you want to restore this backup? This will overwrite existing data and cannot be undone.');
}
