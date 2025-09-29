<?php
require_once __DIR__ . '/includes/student_init.php';
// We'll pass $content to the shared layout at the bottom

$sectionId = (int)($_SESSION['section_id'] ?? ($_SESSION['student_section_id'] ?? 0));

// Detect schema
$cols = [];
try { $rc = $conn->query("SHOW COLUMNS FROM announcements"); if ($rc) { while($r=$rc->fetch_assoc()){ $cols[strtolower($r['Field'])]=true; } } } catch (Throwable $e) {}
$bodyExpr = (isset($cols['message']) && isset($cols['content'])) ? 'COALESCE(message,content)' : (isset($cols['message']) ? 'message' : (isset($cols['content']) ? 'content' : "''"));
$hasSection = isset($cols['section_id']);

// Fetch announcements for student's section or all
$items = [];
try {
    if ($hasSection && $sectionId > 0) {
        $sql = "SELECT id, title, $bodyExpr AS message, created_at FROM announcements WHERE (section_id IS NULL OR section_id = ?) ORDER BY created_at DESC";
        $st = $conn->prepare($sql);
        if ($st) { $st->bind_param('i', $sectionId); $st->execute(); $items = $st->get_result()->fetch_all(MYSQLI_ASSOC); }
    } else {
        $sql = "SELECT id, title, $bodyExpr AS message, created_at FROM announcements ORDER BY created_at DESC";
        $rs = $conn->query($sql); if ($rs) { $items = $rs->fetch_all(MYSQLI_ASSOC); }
    }
} catch (Throwable $e) { $items = []; }

$pageTitle = 'Announcements';
ob_start();
?>
<style>
.ann-card{border:1px solid #e5e7eb;border-radius:12px;margin:12px 0;overflow:hidden;background:#fff}
.ann-head{padding:12px 16px;background:#f8fafc;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center}
.ann-body{padding:14px;white-space:pre-line;color:#374151}
.muted{color:#6b7280;font-size:12px}
</style>

<div class="card">
  <div class="card-header"><strong>Announcements</strong></div>
  <div class="card-body">
    <?php if (empty($items)): ?>
      <div class="muted">No announcements yet.</div>
    <?php else: foreach ($items as $a): ?>
      <div class="ann-card">
        <div class="ann-head"><div><strong><?php echo h($a['title']); ?></strong></div><div class="muted"><?php echo h(date('M j, Y g:ia', strtotime($a['created_at']))); ?></div></div>
        <div class="ann-body"><?php echo nl2br(h($a['message'])); ?></div>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/Includes/student_layout.php';
?>

