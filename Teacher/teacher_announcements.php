<?php
require_once __DIR__ . '/includes/teacher_init.php';
require_once __DIR__ . '/includes/teacher_layout.php';

$flash = '';
$tableError = '';
$messageColumn = 'message';
$selectBodyExpr = 'a.message';

// Ensure table exists to avoid white screen if not migrated
try {
    $conn->query("CREATE TABLE IF NOT EXISTS announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT NOT NULL,
        section_id INT NULL,
        title VARCHAR(200) NOT NULL,
        message TEXT NOT NULL,
        created_at DATETIME NOT NULL,
        INDEX(teacher_id), INDEX(section_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Ensure required columns exist on legacy tables
    $cols = [];
    if ($res = $conn->query("SHOW COLUMNS FROM announcements")) {
        while ($r = $res->fetch_assoc()) { $cols[strtolower($r['Field'])] = true; }
    }
    if (!isset($cols['section_id'])) {
        $conn->query("ALTER TABLE announcements ADD COLUMN section_id INT NULL AFTER teacher_id");
    }
    if (!isset($cols['created_at'])) {
        // choose reference column safely
        $ref = isset($cols['message']) ? 'message' : (isset($cols['content']) ? 'content' : 'title');
        $conn->query("ALTER TABLE announcements ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `$ref`");
    }
    // detect message/content column
    if (isset($cols['message'])) { $messageColumn = 'message'; $selectBodyExpr = 'a.message'; }
    elseif (isset($cols['content'])) { $messageColumn = 'content'; $selectBodyExpr = 'a.content'; }
    else { $conn->query("ALTER TABLE announcements ADD COLUMN message TEXT NULL"); $messageColumn = 'message'; $selectBodyExpr = 'a.message'; }
} catch (Throwable $e) {
    $tableError = $e->getMessage();
}

// Create announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $sectionId = ($_POST['section_id'] !== '') ? (int)$_POST['section_id'] : null;
    if ($title !== '' && $message !== '') {
        try {
            $col = $messageColumn;
            $sql = "INSERT INTO announcements (teacher_id, section_id, title, `$col`, created_at) VALUES (?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('iiss', $teacherId, $sectionId, $title, $message);
            $stmt->execute();
            $flash = 'Announcement posted.';
        } catch (Throwable $e) {
            $tableError = 'Insert failed: ' . $e->getMessage();
        }
    } else {
        $flash = 'Please fill in both title and message.';
    }
}

// Simple fetch
$ann = false;
try {
    $sqlSel = "SELECT a.*, $selectBodyExpr AS body_text, s.name AS section_name FROM announcements a LEFT JOIN sections s ON s.id = a.section_id WHERE a.teacher_id = $teacherId ORDER BY a.created_at DESC";
    $ann = $conn->query($sqlSel);
} catch (Throwable $e) {
    $tableError = $e->getMessage();
}

render_teacher_header('teacher_announcements.php', $teacherName, 'Announcements');
?>
<style>
body{ background:#f6f8fc; }
.container{ max-width: 900px; margin: 22px auto; padding: 0 16px; }
.card{ background:#fff; border:1px solid #e5e7eb; border-radius:14px; box-shadow:0 4px 14px rgba(0,0,0,.06); margin-bottom:20px; overflow:hidden; }
.card-header{ background:#eef2ff; padding:16px 20px; font-weight:700; color:#1f2937; }
.card-body{ padding:20px; }
label{ font-weight:600; color:#374151; font-size:14px; display:block; margin-bottom:6px; }
input[type=text], textarea, select{ width:100%; border:1px solid #cbd5e1; border-radius:10px; padding:10px 12px; background:#fff; font-size:14px; }
textarea{ min-height: 120px; resize: vertical; }
.btn{ border:none; border-radius:10px; padding:10px 16px; background:#6366f1; color:#fff; font-weight:700; cursor:pointer; }
.btn:hover{ background:#4f46e5; }
.badge{ display:inline-block; background:#e0f2fe; color:#075985; padding:4px 8px; border-radius:9999px; font-size:12px; font-weight:700; }
.list{ list-style:none; margin:0; padding:0; }
.item{ border-top:1px solid #e5e7eb; padding:16px 20px; }
.item:first-child{ border-top:none; }
.item-title{ font-weight:700; color:#111827; margin:0 0 6px 0; }
.item-meta{ color:#6b7280; font-size:12px; margin-bottom:8px; display:flex; gap:8px; }
.empty{ text-align:center; color:#6b7280; padding:24px; }
</style>
<div class="container">
    <?php if (!empty($flash)): ?>
        <div class="flash" id="flash-message">
            <?php echo h($flash); ?>
            <button type="button" class="flash-close" onclick="document.getElementById('flash-message').style.display='none';">&times;</button>
        </div>
    <?php endif; ?>
    <?php if (!empty($tableError)): ?>
        <div class="flash flash-error" id="table-error">
            <?php echo h('Database error: ' . $tableError); ?>
            <button type="button" class="flash-close" onclick="document.getElementById('table-error').style.display='none';">&times;</button>
        </div>
    <?php endif; ?>
    <div class="card">
        <div class="card-header">Create Announcement</div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div style="display:grid; grid-template-columns:1fr; gap:12px;">
                    <div>
                        <label>Title</label>
                        <input type="text" name="title" placeholder="Short headline..." required>
                    </div>
                    <div>
                        <label>Message</label>
                        <textarea name="message" placeholder="Write your announcement here..." required></textarea>
                    </div>
                    <div>
                        <label>Target Section (optional)</label>
                        <select name="section_id">
                            <option value="">All Sections</option>
                            <?php foreach (($teacherSections ?? []) as $s): ?>
                                <option value="<?php echo (int)$s['id']; ?>"><?php echo h($s['section_name'] ?: $s['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="text-align:right;">
                        <button class="btn" type="submit"><i class="fas fa-bullhorn"></i> Post Announcement</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Recent Announcements</div>
        <div class="card-body">
            <?php if ($ann && $ann->num_rows > 0): ?>
                <ul class="list">
                    <?php while($a = $ann->fetch_assoc()): ?>
                        <li class="item">
                            <div class="item-title"><?php echo h($a['title']); ?></div>
                            <div class="item-meta">
                                <span class="badge">📣 Announcement</span>
                                <span><?php echo date('M j, Y g:ia', strtotime($a['created_at'])); ?></span>
                                <span>•</span>
                                <span><?php echo $a['section_id'] ? ('Section: '.h($a['section_name'])) : 'All Sections'; ?></span>
                            </div>
                            <div style="color:#374151; white-space:pre-line;"><?php echo nl2br(h($a['body_text'])); ?></div>
                        </li>
                    <?php endwhile; ?>
                </ul>
            <?php else: ?>
                <div class="empty">No announcements yet.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
<?php render_teacher_footer(); ?>


