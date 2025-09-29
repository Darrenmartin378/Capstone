<?php
require_once 'Includes/student_init.php';
require_once 'includes/NewResponseHandler.php';

$responseHandler = new NewResponseHandler($conn);
$studentId = (int)($_SESSION['student_id'] ?? 0);
$sectionId = (int)($_SESSION['section_id'] ?? 0);

// Fetch warm-up sets for the student's section: title starts with Warm-Up:
$sets = [];
if ($sectionId > 0) {
    $stmt = $conn->prepare("SELECT id, set_title, created_at FROM question_sets WHERE section_id = ? AND (set_title LIKE 'Warm-Up:%' OR set_title LIKE 'Warm Up:%') ORDER BY created_at DESC");
    $stmt->bind_param('i', $sectionId);
    $stmt->execute();
    $sets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

ob_start();
?>
<style>
.shell{max-width:1000px;margin:0 auto;padding:20px}
.header{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px 18px;box-shadow:0 6px 16px rgba(0,0,0,.06);margin-bottom:16px}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px}
.card{background:#fff;border:1px solid #eef2f7;border-radius:16px;box-shadow:0 8px 18px rgba(0,0,0,.06);padding:16px}
.title{font-weight:800;color:#0f172a;margin-bottom:8px}
.meta{color:#6b7280;font-size:12px;margin-bottom:12px}
.btn{background:linear-gradient(90deg,#6366f1,#22c55e);color:#fff;border:none;border-radius:9999px;padding:10px 16px;font-weight:800;cursor:pointer}
.empty{padding:40px;text-align:center;border-radius:12px;border:1px dashed #e5e7eb;background:#fafafa}
</style>
<div class="shell">
  <div class="header">
    <h1 style="margin:0"><i class="fas fa-bolt"></i> Warm-Up Practice</h1>
    <p style="margin:4px 0 0;color:#6b7280">Optional practice sets posted by your teacher. No schedule, start anytime.</p>
  </div>
  <?php if (empty($sets)): ?>
    <div class="empty">No practice sets available.</div>
  <?php else: ?>
    <div class="grid">
      <?php foreach($sets as $s): ?>
        <div class="card">
          <div class="title"><?php echo htmlspecialchars($s['set_title']); ?></div>
          <div class="meta">Uploaded: <?php echo date('M j, Y g:ia', strtotime($s['created_at'])); ?></div>
          <button class="btn" onclick="location.href='clean_question_viewer.php?practice_set_id=<?php echo (int)$s['id']; ?>'">Start Practice</button>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require_once 'Includes/student_layout.php';
?>

