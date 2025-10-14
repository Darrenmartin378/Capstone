<?php
require_once 'Includes/student_init.php';
require_once 'includes/NewResponseHandler.php';

// Handle AJAX requests for real-time score updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'get_practice_test_score') {
        $testId = (int)($_POST['test_id'] ?? 0);
        
        if ($testId > 0) {
            try {
                // Get the latest score for this practice test
                $stmt = $conn->prepare("
                    SELECT 
                        pt.id,
                        pt.title,
                        pt.timer_minutes,
                        COALESCE(SUM(ptq.points), 0) as max_points,
                        COALESCE(ps.score, 0) as student_score,
                        ps.submitted_at
                    FROM practice_tests pt
                    LEFT JOIN practice_test_questions ptq ON pt.id = ptq.practice_test_id
                    LEFT JOIN practice_test_submissions ps ON pt.id = ps.practice_test_id AND ps.student_id = ?
                    WHERE pt.id = ?
                    GROUP BY pt.id
                ");
                $stmt->bind_param('ii', $_SESSION['student_id'], $testId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($row = $result->fetch_assoc()) {
                    echo json_encode([
                        'success' => true,
                        'score' => (float)$row['student_score'],
                        'max_points' => (float)$row['max_points'],
                        'submitted' => !empty($row['submitted_at'])
                    ]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Practice test not found']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid test ID']);
        }
        exit;
    }
}

$responseHandler = new NewResponseHandler($conn);
$studentId = (int)($_SESSION['student_id'] ?? 0);
$sectionId = (int)($_SESSION['section_id'] ?? 0);

// Fetch warm-up sets and practice tests for the student's section
$sets = [];
$practiceTests = [];

if ($sectionId > 0) {
    // Fetch warm-up question sets
    $stmt = $conn->prepare("SELECT id, set_title, created_at FROM question_sets WHERE section_id = ? AND (set_title LIKE 'Warm-Up:%' OR set_title LIKE 'Warm Up:%') ORDER BY created_at DESC");
    $stmt->bind_param('i', $sectionId);
    $stmt->execute();
    $sets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    // Hide archived sets that may still have legacy "[ARCHIVED] " title prefixes
    if (!empty($sets)) {
        $sets = array_values(array_filter($sets, function($row){
            $title = (string)($row['set_title'] ?? '');
            return strpos($title, '[ARCHIVED] ') !== 0; // keep only non-prefixed
        }));
    }
    
    // Fetch practice tests with scores
    $stmt = $conn->prepare("
        SELECT 
            pt.id, 
            pt.title, 
            pt.difficulty, 
            pt.timer_minutes, 
            pt.created_at,
            COALESCE(SUM(ptq.points), 0) as max_points,
            COALESCE(ps.score, 0) as student_score,
            ps.submitted_at,
            CASE WHEN ps.id IS NOT NULL THEN 1 ELSE 0 END as is_submitted
        FROM practice_tests pt
        LEFT JOIN practice_test_questions ptq ON pt.id = ptq.practice_test_id
        LEFT JOIN practice_test_submissions ps ON pt.id = ps.practice_test_id AND ps.student_id = ?
        WHERE pt.section_id = ?
        GROUP BY pt.id
        HAVING pt.title NOT LIKE '[ARCHIVED] %'
        ORDER BY pt.created_at DESC
    ");
    $stmt->bind_param('ii', $studentId, $sectionId);
    $stmt->execute();
    $practiceTests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Debug: Log the practice tests data
    error_log("Practice Tests Data: " . print_r($practiceTests, true));
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
    <h1><i class="fas fa-fire"></i> Practice Tests & Warm-Up Sets</h1>
    <p>Practice tests and warm-up sets you can take anytime</p>
    </div>

  <?php if (empty($sets) && empty($practiceTests)): ?>
    <div class="empty">No practice tests or warm-up sets available.</div>
  <?php else: ?>
    <div class="grid">
      <?php foreach($sets as $s): ?>
        <div class="card">
          <div class="title"><i class="fas fa-fire"></i> <?php echo htmlspecialchars($s['set_title']); ?></div>
          <div class="meta">Uploaded: <?php echo date('M j, Y g:ia', strtotime($s['created_at'])); ?></div>
          <button class="btn" onclick="location.href='clean_question_viewer.php?practice_set_id=<?php echo (int)$s['id']; ?>'">Start Practice</button>
            </div>
      <?php endforeach; ?>
      
      <?php foreach($practiceTests as $test): ?>
        <div class="card" data-test-id="<?php echo (int)$test['id']; ?>">
          <div class="title"><i class="fas fa-clipboard-list"></i> <?php echo htmlspecialchars($test['title']); ?></div>
          <div class="meta">
            Difficulty: <?php echo ucfirst($test['difficulty']); ?> | 
            Timer: <?php echo $test['timer_minutes']; ?> min | 
            Created: <?php echo date('M j, Y g:ia', strtotime($test['created_at'])); ?>
          </div>
          <!-- Debug info -->
          <div style="font-size: 10px; color: #666; margin: 4px 0;">
            DEBUG: is_submitted=<?php echo $test['is_submitted']; ?>, 
            score=<?php echo $test['student_score']; ?>, 
            max=<?php echo $test['max_points']; ?>
          </div>
          <?php if ($test['is_submitted']): ?>
            <div class="score-display" style="color: #16a34a; font-weight: 600; margin: 8px 0;">
              Your Score: <?php echo (float)$test['student_score']; ?> / <?php echo (float)$test['max_points']; ?>
            </div>
            <button class="btn" style="background: linear-gradient(135deg, #6c757d, #545b62);" disabled>Completed</button>
          <?php else: ?>
            <button class="btn" onclick="location.href='student_practice_test.php?test_id=<?php echo (int)$test['id']; ?>'">Start Test</button>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<script>
// Simple page refresh mechanism for real-time updates
document.addEventListener('DOMContentLoaded', function() {
    console.log('Practice tests page loaded');
    
    // Check if we just returned from a practice test
    const urlParams = new URLSearchParams(window.location.search);
    const justCompleted = urlParams.get('completed');
    
    if (justCompleted) {
        console.log('Just completed a test, refreshing page to show updated scores');
        // Remove the parameter and refresh
        const newUrl = window.location.pathname;
        window.history.replaceState({}, document.title, newUrl);
        
        // Force a page refresh to show updated scores
        setTimeout(() => {
            window.location.reload();
        }, 100);
    }
});
</script>

<?php
$content = ob_get_clean();
require_once 'Includes/student_layout.php';
?>

