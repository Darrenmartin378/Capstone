<?php
require_once __DIR__ . '/includes/student_init.php';

$studentId = (int)($_SESSION['student_id'] ?? 0);
$sectionId = (int)($_SESSION['section_id'] ?? ($_SESSION['student_section_id'] ?? 0));

// Fetch announcements (supports legacy column `content` and optional section targeting)
$ann = [];
try {
    $cols = [];
    if ($rc = $conn->query("SHOW COLUMNS FROM announcements")) {
        while ($r = $rc->fetch_assoc()) { $cols[strtolower($r['Field'])] = true; }
    }
    $hasSection = isset($cols['section_id']);
    $hasMessage = isset($cols['message']);
    $hasContent = isset($cols['content']);
    $bodyExpr = $hasMessage && $hasContent ? 'COALESCE(message, content)' : ($hasMessage ? 'message' : ($hasContent ? 'content' : "''"));
    if ($hasSection && $sectionId > 0) {
        $sql = "SELECT id, title, $bodyExpr AS message, created_at FROM announcements WHERE (section_id IS NULL OR section_id = ?) ORDER BY created_at DESC LIMIT 20";
        $st = $conn->prepare($sql);
        if ($st) { $st->bind_param('i', $sectionId); $st->execute(); $ann = $st->get_result()->fetch_all(MYSQLI_ASSOC); }
    } else {
        $sql = "SELECT id, title, $bodyExpr AS message, created_at FROM announcements ORDER BY created_at DESC LIMIT 20";
        $ra = $conn->query($sql);
        if ($ra) { $ann = $ra->fetch_all(MYSQLI_ASSOC); }
    }
} catch (Throwable $e) {}

// Fallback: use reading_materials joined with teacher_sections (teacher -> section mapping)
try {
    if (empty($materials)) {
        // Confirm table exists
        $rc3 = $conn->query("SHOW TABLES LIKE 'reading_materials'");
        if ($rc3 && $rc3->num_rows > 0 && $sectionId > 0) {
            // Detect columns
            $rmcols = [];
            if ($rc4 = $conn->query("SHOW COLUMNS FROM reading_materials")) {
                while ($r = $rc4->fetch_assoc()) { $rmcols[strtolower($r['Field'])] = true; }
            }
            $titleCol = isset($rmcols['title']) ? 'title' : (isset($rmcols['name']) ? 'name' : 'title');
            $bodyCol = isset($rmcols['content']) ? 'content' : (isset($rmcols['description']) ? 'description' : 'content');
            $dateCol = isset($rmcols['created_at']) ? 'created_at' : (isset($rmcols['created']) ? 'created' : 'created_at');

            // teacher_sections fallback detection
            $tsTableExists = false;
            if ($tst = $conn->query("SHOW TABLES LIKE 'teacher_sections'")) { $tsTableExists = $tst->num_rows > 0; }
            if ($tsTableExists) {
                $sql = "SELECT rm.id, rm.`$titleCol` AS title, rm.`$bodyCol` AS body, rm.`$dateCol` AS created_at
                        FROM reading_materials rm
                        JOIN teacher_sections ts ON ts.teacher_id = rm.teacher_id
                        WHERE ts.section_id = ?
                        ORDER BY rm.`$dateCol` DESC
                        LIMIT 20";
                if ($stm2 = $conn->prepare($sql)) {
                    $stm2->bind_param('i', $sectionId);
                    $stm2->execute();
                    $materials = $stm2->get_result()->fetch_all(MYSQLI_ASSOC);
                }
            }
        }
    }
} catch (Throwable $e) { /* ignore */ }

// Fetch question sets for this section
$sets = [];
try {
    if ($sectionId > 0) {
        $st = $conn->prepare("SELECT id, set_title, created_at FROM question_sets WHERE section_id = ? ORDER BY created_at DESC LIMIT 20");
        if ($st) { $st->bind_param('i', $sectionId); $st->execute(); $sets = $st->get_result()->fetch_all(MYSQLI_ASSOC); }
    }
} catch (Throwable $e) {}

// Fetch posted content materials for the student's section
$materials = [];
try {
    // Detect schema dynamically
    $mcols = [];
    if ($rc2 = $conn->query("SHOW COLUMNS FROM materials")) {
        while ($r2 = $rc2->fetch_assoc()) { $mcols[strtolower($r2['Field'])] = true; }
    }
    if (!empty($mcols)) {
        // Title
        $titleCandidates = ['title','name','material_title'];
        $titleCol = 'title';
        foreach ($titleCandidates as $c) { if (isset($mcols[$c])) { $titleCol = $c; break; } }
        // Body/description
        $bodyCandidates = ['description','content','body','details','text'];
        $bodyCol = "''"; foreach ($bodyCandidates as $c) { if (isset($mcols[$c])) { $bodyCol = $c; break; } }
        // Created timestamp
        $dateCandidates = ['created_at','created','posted_at','date_posted','uploaded_at','date_created'];
        $dateCol = 'created_at'; foreach ($dateCandidates as $c) { if (isset($mcols[$c])) { $dateCol = $c; break; } }
        // Section
        $secCandidates = ['section_id','section','class_id'];
        $secCol = null; foreach ($secCandidates as $c) { if (isset($mcols[$c])) { $secCol = $c; break; } }

        if ($secCol !== null && $sectionId > 0) {
            $sqlm = "SELECT id, `$titleCol` AS title, `$bodyCol` AS body, `$dateCol` AS created_at FROM materials WHERE (`$secCol` IS NULL OR `$secCol` = ?) ORDER BY `$dateCol` DESC LIMIT 20";
            $stm = $conn->prepare($sqlm);
            if ($stm) { $stm->bind_param('i', $sectionId); $stm->execute(); $materials = $stm->get_result()->fetch_all(MYSQLI_ASSOC); }
        } else {
            $sqlm = "SELECT id, `$titleCol` AS title, `$bodyCol` AS body, `$dateCol` AS created_at FROM materials ORDER BY `$dateCol` DESC LIMIT 20";
            if ($rm = $conn->query($sqlm)) { $materials = $rm->fetch_all(MYSQLI_ASSOC); }
        }
    }
} catch (Throwable $e) {}

?>
<style>
.nf-item{border:1px solid #e5e7eb;border-radius:10px;margin:10px 0;overflow:hidden}
.nf-head{padding:10px 12px;background:#f8fafc;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center}
.nf-body{padding:12px;color:#374151;white-space:pre-line}
.muted{color:#6b7280;font-size:12px}
.pill{display:inline-block;padding:2px 8px;border-radius:9999px;background:#eef2ff;color:#3730a3;font-size:12px;font-weight:700}
</style>

<?php if (empty($ann) && empty($sets)): ?>
    <div class="muted">No notifications yet.</div>
<?php endif; ?>

<?php foreach ($ann as $a): ?>
    <a class="nf-item" href="student_announcements.php" style="text-decoration:none;color:inherit;display:block">
        <div class="nf-head">
            <div><span class="pill">📣 Announcement</span> <strong><?php echo h($a['title']); ?></strong></div>
            <div class="muted"><?php echo h(date('M j, Y g:ia', strtotime($a['created_at']))); ?></div>
        </div>
        <div class="nf-body"><?php echo nl2br(h($a['message'])); ?></div>
    </a>
<?php endforeach; ?>

<?php foreach ($sets as $s): ?>
    <a class="nf-item" href="clean_question_viewer.php" style="text-decoration:none;color:inherit;display:block">
        <div class="nf-head">
            <div><span class="pill">❓ Question Set</span> <strong><?php echo h($s['set_title']); ?></strong></div>
            <div class="muted"><?php echo h(date('M j, Y g:ia', strtotime($s['created_at']))); ?></div>
        </div>
        <div class="nf-body">A new question set was posted for your section.</div>
    </a>
<?php endforeach; ?>

<?php foreach ($materials as $m): ?>
    <a class="nf-item" href="student_materials.php" style="text-decoration:none;color:inherit;display:block">
        <div class="nf-head">
            <div><span class="pill">📚 Material</span> <strong><?php echo h($m['title']); ?></strong></div>
            <div class="muted"><?php echo h(date('M j, Y g:ia', strtotime($m['created_at']))); ?></div>
        </div>
        <div class="nf-body"><?php echo nl2br(h(mb_strimwidth((string)($m['body'] ?? ''),0,180,'…'))); ?></div>
    </a>
<?php endforeach; ?>

