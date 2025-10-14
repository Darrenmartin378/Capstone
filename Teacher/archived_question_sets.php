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
.archived-shell{max-width:1200px;margin:0 auto;padding:20px}
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
.row-actions{display:flex;gap:8px}
</style>

<div class="archived-shell">
    <div class="archived-header">
        <h1 style="margin:0;">Archived Question Sets</h1>
        <div style="display:flex;gap:8px;align-items:center;">
<<<<<<< HEAD
            <button class="btn btn-back" onclick="window.location.href='question_bank.php'">
=======
            <button class="btn btn-back" onclick="window.location.href='clean_question_creator.php'">
>>>>>>> 2fcad03c27dbe56cf4dba808f3f13a749f478b16
                <i class="fas fa-arrow-left"></i> Back to Question Bank
            </button>
        </div>
    </div>

    <?php if (empty($archivedSets)): ?>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:24px;text-align:center;color:#6b7280;">
            No archived sets.
        </div>
    <?php else: ?>
        <table class="archived-table">
            <thead>
                <tr>
                    <th>Question Set</th>
                    <th>Section</th>
                    <th>Created</th>
                    <th style="width:220px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($archivedSets as $set): ?>
                <tr>
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


