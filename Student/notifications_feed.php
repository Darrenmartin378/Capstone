<?php
require_once __DIR__ . '/includes/student_init.php';

$studentId = (int)($_SESSION['student_id'] ?? 0);
$sectionId = (int)($_SESSION['section_id'] ?? ($_SESSION['student_section_id'] ?? 0));

// Check if viewed_notifications table exists, create if not
try {
    $tableCheck = $conn->query("SHOW TABLES LIKE 'viewed_notifications'");
    if ($tableCheck->num_rows == 0) {
        $createTable = "
            CREATE TABLE `viewed_notifications` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `student_id` int(11) NOT NULL,
              `notification_type` enum('announcement','question_set','material') NOT NULL,
              `notification_id` int(11) NOT NULL,
              `viewed_at` timestamp NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`id`),
              UNIQUE KEY `unique_view` (`student_id`, `notification_type`, `notification_id`),
              KEY `student_id` (`student_id`),
              KEY `notification_type` (`notification_type`),
              KEY `notification_id` (`notification_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ";
        $conn->query($createTable);
    }
} catch (Throwable $e) {}

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
    
    // Exclude viewed announcements
    if ($hasSection && $sectionId > 0) {
        $sql = "SELECT a.id, a.title, $bodyExpr AS message, a.created_at 
                FROM announcements a 
                LEFT JOIN viewed_notifications vn ON vn.student_id = ? AND vn.notification_type = 'announcement' AND vn.notification_id = a.id
                WHERE (a.section_id IS NULL OR a.section_id = ?) AND vn.id IS NULL
                ORDER BY a.created_at DESC LIMIT 20";
        $st = $conn->prepare($sql);
        if ($st) { $st->bind_param('ii', $studentId, $sectionId); $st->execute(); $ann = $st->get_result()->fetch_all(MYSQLI_ASSOC); }
    } else {
        $sql = "SELECT a.id, a.title, $bodyExpr AS message, a.created_at 
                FROM announcements a 
                LEFT JOIN viewed_notifications vn ON vn.student_id = ? AND vn.notification_type = 'announcement' AND vn.notification_id = a.id
                WHERE vn.id IS NULL
                ORDER BY a.created_at DESC LIMIT 20";
        $st = $conn->prepare($sql);
        if ($st) { $st->bind_param('i', $studentId); $st->execute(); $ann = $st->get_result()->fetch_all(MYSQLI_ASSOC); }
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

// Fetch question sets for this section (exclude viewed ones)
$sets = [];
try {
    if ($sectionId > 0) {
        $st = $conn->prepare("
            SELECT qs.id, qs.set_title, qs.created_at 
            FROM question_sets qs 
            LEFT JOIN viewed_notifications vn ON vn.student_id = ? AND vn.notification_type = 'question_set' AND vn.notification_id = qs.id
            WHERE qs.section_id = ? AND vn.id IS NULL
            ORDER BY qs.created_at DESC LIMIT 20
        ");
        if ($st) { $st->bind_param('ii', $studentId, $sectionId); $st->execute(); $sets = $st->get_result()->fetch_all(MYSQLI_ASSOC); }
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
            $sqlm = "SELECT m.id, m.`$titleCol` AS title, m.`$bodyCol` AS body, m.`$dateCol` AS created_at 
                     FROM materials m 
                     LEFT JOIN viewed_notifications vn ON vn.student_id = ? AND vn.notification_type = 'material' AND vn.notification_id = m.id
                     WHERE (m.`$secCol` IS NULL OR m.`$secCol` = ?) AND vn.id IS NULL
                     ORDER BY m.`$dateCol` DESC LIMIT 20";
            $stm = $conn->prepare($sqlm);
            if ($stm) { $stm->bind_param('ii', $studentId, $sectionId); $stm->execute(); $materials = $stm->get_result()->fetch_all(MYSQLI_ASSOC); }
        } else {
            $sqlm = "SELECT m.id, m.`$titleCol` AS title, m.`$bodyCol` AS body, m.`$dateCol` AS created_at 
                     FROM materials m 
                     LEFT JOIN viewed_notifications vn ON vn.student_id = ? AND vn.notification_type = 'material' AND vn.notification_id = m.id
                     WHERE vn.id IS NULL
                     ORDER BY m.`$dateCol` DESC LIMIT 20";
            $stm = $conn->prepare($sqlm);
            if ($stm) { $stm->bind_param('i', $studentId); $stm->execute(); $materials = $stm->get_result()->fetch_all(MYSQLI_ASSOC); }
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
    <a class="nf-item" href="student_announcements.php" style="text-decoration:none;color:inherit;display:block" 
       onclick="markNotificationViewedAndRedirect('announcement', <?php echo $a['id']; ?>, 'student_announcements.php'); return false;">
        <div class="nf-head">
            <div><span class="pill">📣 Announcement</span> <strong><?php echo h($a['title']); ?></strong></div>
            <div class="muted"><?php echo h(date('M j, Y g:ia', strtotime($a['created_at']))); ?></div>
        </div>
        <div class="nf-body"><?php echo nl2br(h($a['message'])); ?></div>
    </a>
<?php endforeach; ?>

<?php foreach ($sets as $s): ?>
    <a class="nf-item" href="clean_question_viewer.php" style="text-decoration:none;color:inherit;display:block"
       onclick="markNotificationViewedAndRedirect('question_set', <?php echo $s['id']; ?>, 'test_redirect.php'); return false;">
        <div class="nf-head">
            <div><span class="pill">❓ Question Set</span> <strong><?php echo h($s['set_title']); ?></strong></div>
            <div class="muted"><?php echo h(date('M j, Y g:ia', strtotime($s['created_at']))); ?></div>
        </div>
        <div class="nf-body">A new question set was posted for your section.</div>
    </a>
<?php endforeach; ?>

<?php foreach ($materials as $m): ?>
    <a class="nf-item" href="student_materials.php" style="text-decoration:none;color:inherit;display:block"
       onclick="markNotificationViewedAndRedirect('material', <?php echo $m['id']; ?>, 'student_materials.php'); return false;">
        <div class="nf-head">
            <div><span class="pill">📚 Material</span> <strong><?php echo h($m['title']); ?></strong></div>
            <div class="muted"><?php echo h(date('M j, Y g:ia', strtotime($m['created_at']))); ?></div>
        </div>
        <div class="nf-body"><?php echo nl2br(h(mb_strimwidth((string)($m['body'] ?? ''),0,180,'…'))); ?></div>
    </a>
<?php endforeach; ?>

<script>
function markNotificationViewed(type, id) {
    // Send AJAX request to mark notification as viewed
    fetch('mark_notification_viewed.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'type=' + encodeURIComponent(type) + '&id=' + encodeURIComponent(id)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove the notification from the UI
            const notificationElement = event.target.closest('.nf-item');
            if (notificationElement) {
                notificationElement.style.opacity = '0.5';
                notificationElement.style.pointerEvents = 'none';
                // Optionally remove the element after a delay
                setTimeout(() => {
                    notificationElement.remove();
                    // Check if there are any notifications left
                    const remainingNotifications = document.querySelectorAll('.nf-item');
                    if (remainingNotifications.length === 0) {
                        const container = document.getElementById('notificationsBody');
                        if (container) {
                            container.innerHTML = '<div style="color:#64748b;">No new notifications.</div>';
                        }
                    }
                    // Update notification badge count
                    updateNotificationBadge();
                }, 500);
            }
        }
    })
    .catch(error => {
        console.error('Error marking notification as viewed:', error);
    });
}

function updateNotificationBadge() {
    // Update the notification badge count by refreshing the page or making an AJAX call
    // For simplicity, we'll just reload the notifications
    const modal = document.getElementById('notificationsModal');
    if (modal && modal.style.display === 'flex') {
        openNotifications();
    }
}

function markNotificationViewedAndRedirect(type, id, redirectUrl) {
    // Prevent default link behavior
    event.preventDefault();
    
    console.log('Marking notification as viewed:', type, id, redirectUrl);
    
    // Send AJAX request to mark notification as viewed
    fetch('mark_notification_viewed.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'type=' + encodeURIComponent(type) + '&id=' + encodeURIComponent(id)
    })
    .then(response => {
        console.log('Response status:', response.status);
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.text(); // Use text() first to see raw response
    })
    .then(text => {
        console.log('Raw response:', text);
        try {
            const data = JSON.parse(text);
            console.log('Parsed response data:', data);
            if (data.success) {
                // Close the notification modal
                if (typeof closeNotifications === 'function') {
                    closeNotifications();
                } else {
                    document.getElementById('notificationsModal').style.display = 'none';
                }
                // Show success message
                alert('Notification marked as viewed! Redirecting in 3 seconds...');
                // Redirect to the target page after a delay
                setTimeout(() => {
                    window.location.href = redirectUrl;
                }, 3000);
            } else {
                console.error('Failed to mark notification as viewed:', data.message);
                alert('Failed to mark notification as viewed: ' + data.message + '. Redirecting in 3 seconds...');
                // Still redirect even if marking fails
                setTimeout(() => {
                    window.location.href = redirectUrl;
                }, 3000);
            }
        } catch (e) {
            console.error('Error parsing JSON response:', e);
            console.log('Raw response was:', text);
            alert('Error parsing response. Still redirecting in 3 seconds...');
            // Close modal and redirect anyway
            if (typeof closeNotifications === 'function') {
                closeNotifications();
            } else {
                document.getElementById('notificationsModal').style.display = 'none';
            }
            setTimeout(() => {
                window.location.href = redirectUrl;
            }, 3000);
        }
    })
    .catch(error => {
        console.error('Error marking notification as viewed:', error);
        alert('Error: ' + error.message + '. Still redirecting in 3 seconds...');
        // Close modal and redirect anyway
        if (typeof closeNotifications === 'function') {
            closeNotifications();
        } else {
            document.getElementById('notificationsModal').style.display = 'none';
        }
        setTimeout(() => {
            window.location.href = redirectUrl;
        }, 3000);
    });
}
</script>

