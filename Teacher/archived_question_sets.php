<?php
require_once __DIR__ . '/includes/teacher_init.php';
require_once __DIR__ . '/includes/teacher_layout.php';

// Fetch archived sets for this teacher
$teacherId = (int)($_SESSION['teacher_id'] ?? 0);
$archivedSets = [];
$hasArchiveCol = false;
try {
    $chk = $conn->query("SHOW COLUMNS FROM question_sets LIKE 'is_archived'");
    $hasArchiveCol = $chk && $chk->num_rows > 0;
} catch (Throwable $e) { /* ignore */ }

$sql = "SELECT qs.*, s.name AS section_name FROM question_sets qs LEFT JOIN sections s ON s.id = qs.section_id WHERE qs.teacher_id = ?";
if ($hasArchiveCol) {
    $sql .= " AND qs.is_archived = 1";
} else {
    // Fallback not used anymore, but keep safe guard to avoid showing prefixed titles to students
    $sql .= " AND 1=0"; // no archived support without column
}
$sql .= " ORDER BY qs.updated_at DESC, qs.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $teacherId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) { $archivedSets[] = $row; }
$stmt->close();

render_teacher_header('archived_question_sets.php', $teacherName, 'Archived Question Sets');
?>

<style>
.archived-shell{max-width:1700px;margin:0 auto;padding:20px}
.archived-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}
.archived-table{width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.1)}
.archived-table thead{background:linear-gradient(135deg,#64748b 0%,#334155 100%);color:#fff}
.archived-table th,.archived-table td{padding:14px 12px}
.btn{display:inline-flex;align-items:center;gap:6px;border:none;border-radius:8px;padding:8px 12px;cursor:pointer}
.btn-back{background:#6b7280;color:#fff}
.btn-back:hover{background:#4b5563}
.btn-unarchive{background:#10b981;color:#fff}
.btn-unarchive:hover{background:#059669}
.btn-delete{background:#ef4444;color:#fff}
.btn-delete:hover{background:#dc2626}
.btn-bulk-delete{background:#dc2626;color:#fff;margin-left:8px}
.btn-bulk-delete:hover{background:#b91c1c}
.btn-bulk-delete:disabled{background:#9ca3af;cursor:not-allowed}
.row-actions{display:flex;gap:8px}
.checkbox-cell{width:50px;text-align:center}
.checkbox{width:18px;height:18px;cursor:pointer}
.bulk-actions{display:none;align-items:center;gap:8px;margin-bottom:16px;padding:12px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px}
.bulk-actions.show{display:flex}
</style>

<div class="archived-shell">
    <div class="archived-header">
        <h1 style="margin:0;">Archived Question Sets</h1>
        <div style="display:flex;gap:8px;align-items:center;">
            <button class="btn btn-back" onclick="window.location.href='question_bank.php'">

                <i class="fas fa-arrow-left"></i> Back to Question Bank
            </button>
        </div>
    </div>

    <?php if (empty($archivedSets)): ?>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:24px;text-align:center;color:#6b7280;">
            No archived sets.
        </div>
    <?php else: ?>
        <!-- Bulk Actions Bar -->
        <div class="bulk-actions" id="bulkActions">
            <span id="selectedCount">0</span> item(s) selected
            <button class="btn btn-bulk-delete" id="bulkDeleteBtn" onclick="bulkDelete()">
                <i class="fas fa-trash"></i> Delete Selected
            </button>
        </div>
        <table class="archived-table">
            <thead>
                <tr>
                    <th class="checkbox-cell">
                        <input type="checkbox" class="checkbox" id="selectAll" onchange="toggleSelectAll()">
                    </th>
                    <th>Question Set</th>
                    <th>Section</th>
                    <th>Created</th>
                    <th style="width:220px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($archivedSets as $set): ?>
                <tr>
                    <td class="checkbox-cell">
                        <input type="checkbox" class="checkbox row-checkbox" value="<?php echo (int)$set['id']; ?>" onchange="updateSelection()">
                    </td>
                    <td><strong><?php echo htmlspecialchars($set['set_title']); ?></strong></td>
                    <td><?php echo htmlspecialchars($set['section_name'] ?? ''); ?></td>
                    <td><?php echo date('M j, Y g:i A', strtotime($set['created_at'])); ?></td>
                    <td>
                        <div class="row-actions">
                            <button class="btn btn-unarchive" onclick="unarchiveSet(<?php echo (int)$set['id']; ?>)"><i class="fas fa-rotate-left"></i> Unarchive</button>
                            <button class="btn btn-delete" onclick="deleteSet(<?php echo (int)$set['id']; ?>)"><i class="fas fa-trash"></i> Delete</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
// Checkbox functionality
function toggleSelectAll() {
    const selectAllCheckbox = document.getElementById('selectAll');
    const rowCheckboxes = document.querySelectorAll('.row-checkbox');
    
    rowCheckboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
    
    updateSelection();
}

function updateSelection() {
    const rowCheckboxes = document.querySelectorAll('.row-checkbox');
    const selectAllCheckbox = document.getElementById('selectAll');
    const bulkActions = document.getElementById('bulkActions');
    const selectedCount = document.getElementById('selectedCount');
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    
    const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
    const totalBoxes = rowCheckboxes.length;
    
    // Update selected count
    selectedCount.textContent = checkedBoxes.length;
    
    // Show/hide bulk actions
    if (checkedBoxes.length > 0) {
        bulkActions.classList.add('show');
    } else {
        bulkActions.classList.remove('show');
    }
    
    // Update select all checkbox state
    if (checkedBoxes.length === 0) {
        selectAllCheckbox.indeterminate = false;
        selectAllCheckbox.checked = false;
    } else if (checkedBoxes.length === totalBoxes) {
        selectAllCheckbox.indeterminate = false;
        selectAllCheckbox.checked = true;
    } else {
        selectAllCheckbox.indeterminate = true;
        selectAllCheckbox.checked = false;
    }
    
    // Enable/disable bulk delete button
    bulkDeleteBtn.disabled = checkedBoxes.length === 0;
}

function bulkDelete() {
    const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
    if (checkedBoxes.length === 0) return;
    
    const count = checkedBoxes.length;
    if (!confirm(`Are you sure you want to permanently delete ${count} question set(s)? This action cannot be undone.`)) {
        return;
    }
    
    const setIds = Array.from(checkedBoxes).map(checkbox => checkbox.value);
    
    // Disable the button during operation
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    bulkDeleteBtn.disabled = true;
    bulkDeleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
    
    // Process deletions sequentially to avoid overwhelming the server
    let completed = 0;
    let errors = [];
    
    function deleteNext() {
        if (completed >= setIds.length) {
            // All deletions completed
            if (errors.length > 0) {
                alert(`Deleted ${completed - errors.length} sets successfully. Errors: ${errors.join(', ')}`);
            } else {
                alert(`Successfully deleted ${completed} question set(s).`);
            }
            location.reload();
            return;
        }
        
        const setId = setIds[completed];
        const p = new URLSearchParams({action:'delete_question_set', set_id:setId});
        
        fetch('clean_question_creator.php', {method:'POST', body:p})
            .then(r => r.json())
            .then(d => {
                if (!d.success) {
                    errors.push(`Set ${setId}: ${d.error || 'Failed'}`);
                }
                completed++;
                deleteNext();
            })
            .catch(e => {
                errors.push(`Set ${setId}: Network error`);
                completed++;
                deleteNext();
            });
    }
    
    deleteNext();
}

// Original functions
function unarchiveSet(setId){
    if(!confirm('Unarchive this set?')) return;
    const p = new URLSearchParams({action:'unarchive_question_set', set_id:setId});
    fetch('clean_question_creator.php', {method:'POST', body:p})
      .then(r=>r.json())
      .then(d=>{ if(d.success){ location.reload(); } else { alert('Error: '+(d.error||'Failed')); } })
      .catch(e=>alert('Network error: '+e.message));
}

function deleteSet(setId){
    if(!confirm('Delete this set permanently?')) return;
    const p = new URLSearchParams({action:'delete_question_set', set_id:setId});
    fetch('clean_question_creator.php', {method:'POST', body:p})
      .then(r=>r.json())
      .then(d=>{ if(d.success){ location.reload(); } else { alert('Error: '+(d.error||'Failed')); } })
      .catch(e=>alert('Network error: '+e.message));
}
</script>

<?php
render_teacher_footer();
?>


