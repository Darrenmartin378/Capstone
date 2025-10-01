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
.shell{max-width:1240px;margin:0 auto;padding:20px}
.practice-header{margin-bottom:12px}
.practice-header h1{color:#f1f5f9;font-weight:900;margin:0 0 6px 0}
.practice-header p{margin:0;color:rgba(241,245,249,.85)}
.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:24px;justify-content:start;justify-items:stretch}
@media (max-width:900px){.grid{grid-template-columns:1fr}}
.card{position:relative;background:rgba(15,23,42,.85);padding:22px;border-radius:16px;box-shadow:0 8px 32px rgba(0,0,0,.4),0 0 0 1px rgba(139,92,246,.2);transition:transform .18s ease, box-shadow .18s ease, border-color .18s ease;border:1px solid rgba(139,92,246,.3);overflow:hidden;backdrop-filter:blur(12px)}
.card:hover{transform:translateY(-6px);box-shadow:0 16px 40px rgba(0,0,0,.6),0 0 0 1px rgba(139,92,246,.4),0 0 20px rgba(139,92,246,.2);border-color:rgba(139,92,246,.5)}
.title{font-weight:800;color:#f1f5f9;margin-bottom:8px;font-size:20px}
.meta{color:#9aa4b2;font-size:12px;margin-bottom:12px}
.btn{background:linear-gradient(135deg, rgba(139, 92, 246, 0.9), rgba(168, 85, 247, 0.8));color:#fff;border:1px solid rgba(139, 92, 246, 0.5);border-radius:9999px;padding:10px 16px;font-weight:800;cursor:pointer;backdrop-filter:blur(10px);box-shadow:0 0 15px rgba(139, 92, 246, 0.3)}
.empty{padding:40px;text-align:center;border-radius:12px;border:1px dashed rgba(139, 92, 246, 0.3);background:rgba(15, 23, 42, 0.6);color:rgba(241, 245, 249, 0.7);backdrop-filter:blur(8px)}
</style>
<div class="shell">
  <div class="practice-header">
    <h1><i class="fas fa-fire"></i> Warm-Up Practice Sets</h1>
    <p>Optional practice sets you can take anytime</p>
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

