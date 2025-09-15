<?php
require_once __DIR__ . '/includes/teacher_init.php';

// Handle AJAX request to get student responses
if (isset($_GET['action']) && $_GET['action'] === 'get_student_responses') {
    header('Content-Type: application/json');
    
    $studentId = intval($_GET['student_id'] ?? 0);
    $setTitle = trim($_GET['set_title'] ?? '');
    
    if ($studentId > 0 && !empty($setTitle)) {
        try {
            $stmt = $conn->prepare("
                SELECT qr.*, qb.question_text, qb.question_type, qb.answer as correct_answer
                FROM quiz_responses qr
                JOIN question_bank qb ON qr.question_id = qb.id
                WHERE qr.student_id = ? AND qr.set_title = ? AND qr.teacher_id = ?
                ORDER BY qb.id
            ");
            $stmt->bind_param('isi', $studentId, $setTitle, $teacherId);
            $stmt->execute();
            $responses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            echo json_encode(['success' => true, 'responses' => $responses]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    }
    exit;
}

// Handle essay grading form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_essay_grades') {
    $studentId = intval($_POST['student_id'] ?? 0);
    $setTitle = trim($_POST['set_title'] ?? '');
    $essayScores = $_POST['essay_scores'] ?? [];
    
    if ($studentId > 0 && !empty($setTitle) && !empty($essayScores)) {
        try {
            foreach ($essayScores as $questionId => $score) {
                $score = intval($score);
                if ($score >= 0 && $score <= 10) {
                    // Update the essay score (1-10 points)
                    $stmt = $conn->prepare("
                        UPDATE quiz_responses 
                        SET partial_score = ?, submitted_at = NOW()
                        WHERE student_id = ? AND question_id = ? AND set_title = ? AND teacher_id = ?
                    ");
                    $stmt->bind_param('iiisi', $score, $studentId, $questionId, $setTitle, $teacherId);
                    $stmt->execute();
                }
            }
            
            // Update the overall quiz score
            updateOverallQuizScore($studentId, $setTitle, $teacherId);
            
            // Redirect to prevent form resubmission
            header("Location: teacher_grading.php?grade_comprehension_set=" . urlencode($setTitle) . "&graded=success");
            exit;
            
        } catch (Exception $e) {
            error_log("Error grading essays: " . $e->getMessage());
            $gradingError = "Error saving grades. Please try again.";
        }
    }
}

// Function to update overall quiz score
function updateOverallQuizScore($studentId, $setTitle, $teacherId) {
    global $conn;
    
    // Get automated score
    $stmt = $conn->prepare("
        SELECT correct_answers, total_points 
        FROM quiz_scores 
        WHERE student_id = ? AND set_title = ? AND teacher_id = ?
    ");
    $stmt->bind_param('isi', $studentId, $setTitle, $teacherId);
    $stmt->execute();
    $scoreData = $stmt->get_result()->fetch_assoc();
    
    if ($scoreData) {
        $automatedScore = $scoreData['correct_answers'];
        
        // Get essay scores
        $stmt = $conn->prepare("
            SELECT SUM(partial_score) as total_essay_score, COUNT(*) as essay_count
            FROM quiz_responses qr
            JOIN question_bank qb ON qr.question_id = qb.id
            WHERE qr.student_id = ? AND qr.set_title = ? AND qb.question_type = 'essay' AND qr.teacher_id = ?
        ");
        $stmt->bind_param('isi', $studentId, $setTitle, $teacherId);
        $stmt->execute();
        $essayData = $stmt->get_result()->fetch_assoc();
        
        $totalEssayScore = $essayData['total_essay_score'] ?? 0;
        
        // Get question type counts for this quiz
        $stmt = $conn->prepare("
            SELECT 
                SUM(CASE WHEN question_type = 'multiple_choice' THEN 1 ELSE 0 END) as mc_count,
                SUM(CASE WHEN question_type = 'matching' THEN 1 ELSE 0 END) as matching_count,
                SUM(CASE WHEN question_type = 'essay' THEN 1 ELSE 0 END) as essay_count
            FROM question_bank 
            WHERE set_title = ? AND teacher_id = ?
        ");
        $stmt->bind_param('si', $setTitle, $teacherId);
        $stmt->execute();
        $questionTypesResult = $stmt->get_result()->fetch_assoc();
        
        $mcCount = $questionTypesResult['mc_count'] ?? 0;
        $matchingCount = $questionTypesResult['matching_count'] ?? 0;
        $essayCount = $questionTypesResult['essay_count'] ?? 0;
        
        // Get total matching items (each matching question has multiple items)
        $stmt = $conn->prepare("
            SELECT SUM(total_matches) as total_matching_items
            FROM quiz_responses qr
            JOIN question_bank qb ON qr.question_id = qb.id
            WHERE qr.set_title = ? AND qr.teacher_id = ? AND qb.question_type = 'matching'
            LIMIT 1
        ");
        $stmt->bind_param('si', $setTitle, $teacherId);
        $stmt->execute();
        $matchingItemsResult = $stmt->get_result()->fetch_assoc();
        $totalMatchingItems = $matchingItemsResult['total_matching_items'] ?? 0;
        
        // Calculate total possible points
        // MC: 1 point each, Matching: 1 point per item, Essays: 10 points each
        $maxPossibleScore = $mcCount + $totalMatchingItems + ($essayCount * 10);
        
        // Calculate new overall score
        $newOverallScore = $automatedScore + $totalEssayScore;
        $newPercentage = $maxPossibleScore > 0 ? round(($newOverallScore / $maxPossibleScore) * 100, 2) : 0;
        
        // Update quiz_scores table
        $stmt = $conn->prepare("
            UPDATE quiz_scores 
            SET score = ?, correct_answers = ?, total_points = ?
            WHERE student_id = ? AND set_title = ? AND teacher_id = ?
        ");
        $stmt->bind_param('diiisi', $newPercentage, $newOverallScore, $maxPossibleScore, $studentId, $setTitle, $teacherId);
        $stmt->execute();
    }
}
require_once __DIR__ . '/includes/teacher_layout.php';

$assessments = $conn->query("SELECT * FROM assessments WHERE teacher_id = $teacherId ORDER BY created_at DESC");

// Get comprehension question sets for this teacher
$comprehension_sets = $conn->query("
    SELECT DISTINCT set_title, section_id, MIN(created_at) as created_at
    FROM question_bank 
    WHERE teacher_id = $teacherId 
    AND set_title IS NOT NULL 
    AND set_title != ''
    AND question_text NOT IN ('dsad', 'dsadasdasdasd', 'placeholder') 
    AND question_text != '' 
    AND question_text IS NOT NULL
    GROUP BY set_title, section_id
    ORDER BY created_at DESC
");

// Debug: Log comprehension sets found
error_log("Debug: Found " . ($comprehension_sets ? $comprehension_sets->num_rows : 0) . " comprehension sets for teacher $teacherId");

render_teacher_header('grading', $teacherName, 'Grading & Responses');
?>
	<style>
body { background: #f6f8fc; }
.container { max-width: 95%; margin: 32px auto; }
.card { background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); margin-bottom: 32px; }
.card-header { background: #eef2ff; padding: 18px 24px; border-radius: 12px 12px 0 0; font-size: 1.15rem; font-weight: 600; color: #3730a3; border-bottom: 1px solid #e0e7ff; }
.card-body { padding: 24px; }
.btn-primary { background: #6366f1; color: #fff; border: none; border-radius: 6px; padding: 7px 18px; font-size: 1rem; cursor: pointer; transition: background 0.2s; }
.btn-primary:hover { background: #4338ca; }
label { font-weight: 500; color: #3730a3; margin-bottom: 2px; display: block; }
select { border: 1px solid #cbd5e1; border-radius: 6px; padding: 7px 10px; font-size: 1rem; margin-top: 4px; margin-bottom: 12px; width: 100%; box-sizing: border-box; background: #f9fafb; }
.grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; }
table { width: 100%; border-collapse: collapse; margin-top: 18px; background: #fff; border-radius: 8px; overflow: hidden; }
th, td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; text-align: left; }
th { background: #eef2ff; color: #3730a3; font-weight: 600; }
tr:last-child td { border-bottom: none; }
.muted { color: #6b7280; font-size: 1.1rem; margin-bottom: 8px; }
.flash { background: #fde68a; color: #b45309; border-radius: 8px; padding: 12px 18px; margin-bottom: 18px; font-weight: 500; box-shadow: 0 2px 8px rgba(251,191,36,0.08); position: relative; }
.flash-success { background: #d1fae5; color: #065f46; border: 1px solid #10b981; }
.flash-error { background: #fee2e2; color: #991b1b; border: 1px solid #ef4444; }
.flash-close { position: absolute; right: 14px; top: 10px; background: none; border: none; font-size: 1.3rem; color: inherit; cursor: pointer; line-height: 1; opacity: 0.7; }
.flash-close:hover { opacity: 1; }
@media (max-width: 1200px) { .container { max-width: 100%; margin: 16px; } .card-body { padding: 16px; } .grid-3 { grid-template-columns: 1fr; } }
@media (max-width: 768px) { .container { max-width: 100%; margin: 8px; } .card-body { padding: 12px; } th, td { padding: 8px 6px; font-size: 12px; } }
	</style>
	<div class="container">
		<div class="card">
			<div class="card-header"><strong>Auto Grading (Multiple Choice)</strong></div>
			<div class="card-body">
				<form method="GET" class="grid grid-3">
					<div>
						<label>Assessment</label>
						<select name="grade_assessment_id" required>
							<option value="">Select...</option>
							<?php if ($assessments): $assessments->data_seek(0); while ($a = $assessments->fetch_assoc()): ?>
								<option value="<?php echo (int)$a['id']; ?>" <?php echo (isset($_GET['grade_assessment_id']) && (int)$_GET['grade_assessment_id'] === (int)$a['id']) ? 'selected' : ''; ?>><?php echo h($a['title']); ?></option>
							<?php endwhile; endif; ?>
						</select>
					</div>
					<div style="align-self:end;">
						<button class="btn btn-primary" type="submit">Compute</button>
					</div>
				</form>

				<?php if (isset($_GET['grade_assessment_id']) && (int)$_GET['grade_assessment_id'] > 0): ?>
				<?php
				$aid = (int)$_GET['grade_assessment_id'];
				$sql = "SELECT st.name as student_name,
						   SUM(CASE WHEN aq.question_type='multiple_choice' AND ar.answer = aq.answer THEN 1 ELSE 0 END) as correct_mc,
						   SUM(CASE WHEN aq.question_type='multiple_choice' THEN 1 ELSE 0 END) as total_mc
					FROM assessment_responses ar
					JOIN assessment_questions aq ON ar.question_id = aq.id
					JOIN students st ON ar.student_id = st.id
					WHERE ar.assessment_id = $aid
					GROUP BY st.id
					ORDER BY st.name";
				$auto = $conn->query($sql);
				?>
				<table style="margin-top:12px;">
					<thead><tr><th>Student</th><th>Multiple Choice Score</th></tr></thead>
					<tbody>
						<?php if ($auto && $auto->num_rows > 0): while ($r = $auto->fetch_assoc()): ?>
							<tr>
								<td><?php echo h($r['student_name']); ?></td>
								<td><?php echo (int)$r['correct_mc'] . ' / ' . (int)$r['total_mc']; ?></td>
							</tr>
						<?php endwhile; else: ?>
							<tr><td colspan="2">No responses yet.</td></tr>
						<?php endif; ?>
					</tbody>
				</table>
				<?php endif; ?>

				<p class="muted" style="margin-top:10px;">For manual grading and detailed answers, use <a href="teacher_view_responses.php">View Student Responses</a>.</p>
			</div>
		</div>

		<!-- Comprehension Questions Grading Section -->
		<div class="card">
			<div class="card-header"><strong>Comprehension Questions Grading</strong></div>
			<div class="card-body">
				<form method="GET" class="grid grid-3">
					<div>
						<label>Question Set</label>
						<select name="grade_comprehension_set" required>
							<option value="">Select...</option>
							<?php if ($comprehension_sets): $comprehension_sets->data_seek(0); 
								$count = 0;
								while ($cs = $comprehension_sets->fetch_assoc()): 
									$count++;
									// Get section name
									$section_query = $conn->query("SELECT name FROM sections WHERE id = " . (int)$cs['section_id']);
									$section_name = $section_query && $section_query->num_rows > 0 ? $section_query->fetch_assoc()['name'] : 'Unknown Section';
									
									// Debug: Log each option
									error_log("Debug: Adding option $count - Set: " . $cs['set_title'] . ", Section: " . $section_name);
								?>
								<option value="<?php echo htmlspecialchars($cs['set_title']); ?>" 
									<?php echo (isset($_GET['grade_comprehension_set']) && $_GET['grade_comprehension_set'] === $cs['set_title']) ? 'selected' : ''; ?>>
									<?php echo h($cs['set_title']); ?> (<?php echo h($section_name); ?>)
								</option>
							<?php endwhile; 
								error_log("Debug: Total options added: $count");
							endif; ?>
						</select>
					</div>
					<div style="align-self:end;">
						<button class="btn btn-primary" type="submit">View Scores</button>
					</div>
				</form>

				<?php if (isset($_GET['graded']) && $_GET['graded'] === 'success'): ?>
				<div class="flash flash-success" id="success-message">
					✅ Essay grades saved successfully!
					<button type="button" class="flash-close" onclick="closeFlashMessage('success-message')">&times;</button>
				</div>
				<?php endif; ?>
				
				<?php if (isset($gradingError)): ?>
				<div class="flash flash-error" id="error-message">
					❌ <?php echo h($gradingError); ?>
					<button type="button" class="flash-close" onclick="closeFlashMessage('error-message')">&times;</button>
				</div>
				<?php endif; ?>
				
				<?php if (isset($_GET['grade_comprehension_set']) && !empty($_GET['grade_comprehension_set'])): ?>
				<?php
				$setTitle = trim($_GET['grade_comprehension_set']);
				
				// Get quiz scores for this set
				$scores_query = $conn->prepare("
					SELECT qs.*, s.name as student_name, s.section_id, sec.name as section_name
					FROM quiz_scores qs
					JOIN students s ON qs.student_id = s.id
					JOIN sections sec ON qs.section_id = sec.id
					WHERE qs.set_title = ? AND qs.teacher_id = ?
					ORDER BY qs.submitted_at DESC
				");
				$scores_query->bind_param('si', $setTitle, $teacherId);
				$scores_query->execute();
				$scores_result = $scores_query->get_result();
				
				// Get individual responses for essay questions
				$responses_query = $conn->prepare("
					SELECT qr.*, s.name as student_name, qb.question_text, qb.question_type
					FROM quiz_responses qr
					JOIN students s ON qr.student_id = s.id
					JOIN question_bank qb ON qr.question_id = qb.id
					WHERE qr.set_title = ? AND qr.teacher_id = ? AND qb.question_type = 'essay'
					ORDER BY s.name, qr.question_id
				");
				$responses_query->bind_param('si', $setTitle, $teacherId);
				$responses_query->execute();
				$responses_result = $responses_query->get_result();
				?>
				
				<!-- Student List with View Response Button -->
				<h4 style="margin-top: 20px; color: #3730a3;">Students Who Answered This Quiz</h4>
				<div style="overflow-x: auto; margin-top: 12px;">
					<table style="width: 100%; min-width: 1000px; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
						<thead>
							<tr style="background: #f8fafc;">
								<th style="padding: 10px 8px; text-align: left; font-weight: 600; color: #374151; border-bottom: 2px solid #e5e7eb; width: 15%;">Student</th>
								<th style="padding: 10px 8px; text-align: center; font-weight: 600; color: #374151; border-bottom: 2px solid #e5e7eb; width: 10%;">Section</th>
								<th style="padding: 10px 8px; text-align: center; font-weight: 600; color: #374151; border-bottom: 2px solid #e5e7eb; width: 12%;">Auto Score</th>
								<th style="padding: 10px 8px; text-align: center; font-weight: 600; color: #374151; border-bottom: 2px solid #e5e7eb; width: 10%;">Points</th>
								<th style="padding: 10px 8px; text-align: center; font-weight: 600; color: #374151; border-bottom: 2px solid #e5e7eb; width: 10%;">Essay</th>
								<th style="padding: 10px 8px; text-align: center; font-weight: 600; color: #374151; border-bottom: 2px solid #e5e7eb; width: 15%;">Overall</th>
								<th style="padding: 10px 8px; text-align: center; font-weight: 600; color: #374151; border-bottom: 2px solid #e5e7eb; width: 13%;">Submitted</th>
								<th style="padding: 10px 8px; text-align: center; font-weight: 600; color: #374151; border-bottom: 2px solid #e5e7eb; width: 15%;">Action</th>
							</tr>
						</thead>
					<tbody>
						<?php if ($scores_result && $scores_result->num_rows > 0): while ($score = $scores_result->fetch_assoc()): ?>
							<?php
							// Get essay scores for this student
							$essayQuery = $conn->prepare("
								SELECT SUM(partial_score) as total_essay_score, COUNT(*) as essay_count
								FROM quiz_responses qr
								JOIN question_bank qb ON qr.question_id = qb.id
								WHERE qr.student_id = ? AND qr.set_title = ? AND qb.question_type = 'essay'
							");
							$essayQuery->bind_param('is', $score['student_id'], $setTitle);
							$essayQuery->execute();
							$essayResult = $essayQuery->get_result()->fetch_assoc();
							
							$totalEssayScore = $essayResult['total_essay_score'] ?? 0;
							$essayCount = $essayResult['essay_count'] ?? 0;
							$maxEssayScore = $essayCount * 10; // 10 points per essay
							
							// Get question type counts and matching item counts for this quiz
							$questionTypesQuery = $conn->prepare("
								SELECT 
									SUM(CASE WHEN question_type = 'multiple_choice' THEN 1 ELSE 0 END) as mc_count,
									SUM(CASE WHEN question_type = 'matching' THEN 1 ELSE 0 END) as matching_count,
									SUM(CASE WHEN question_type = 'essay' THEN 1 ELSE 0 END) as essay_count
								FROM question_bank 
								WHERE set_title = ? AND teacher_id = ?
							");
							$questionTypesQuery->bind_param('si', $setTitle, $teacherId);
							$questionTypesQuery->execute();
							$questionTypesResult = $questionTypesQuery->get_result()->fetch_assoc();
							
							$mcCount = $questionTypesResult['mc_count'] ?? 0;
							$matchingCount = $questionTypesResult['matching_count'] ?? 0;
							$essayCount = $questionTypesResult['essay_count'] ?? 0;
							
							// Get total matching items (each matching question has multiple items)
							$matchingItemsQuery = $conn->prepare("
								SELECT SUM(total_matches) as total_matching_items
								FROM quiz_responses qr
								JOIN question_bank qb ON qr.question_id = qb.id
								WHERE qr.set_title = ? AND qr.teacher_id = ? AND qb.question_type = 'matching'
								LIMIT 1
							");
							$matchingItemsQuery->bind_param('si', $setTitle, $teacherId);
							$matchingItemsQuery->execute();
							$matchingItemsResult = $matchingItemsQuery->get_result()->fetch_assoc();
							$totalMatchingItems = $matchingItemsResult['total_matching_items'] ?? 0;
							
							// Calculate total possible points
							// MC: 1 point each, Matching: 1 point per item, Essays: 10 points each
							$maxPossibleScore = $mcCount + $totalMatchingItems + ($essayCount * 10);
							
							// Calculate overall score
							$automatedScore = $score['correct_answers'];
							$overallScore = $automatedScore + $totalEssayScore;
							
							// Fix for data inconsistency: if automated score is higher than possible, cap it
							$maxAutomatedPossible = $mcCount + $matchingCount;
							if ($automatedScore > $maxAutomatedPossible && $maxAutomatedPossible > 0) {
								$automatedScore = $maxAutomatedPossible;
								$overallScore = $automatedScore + $totalEssayScore;
							}
							
							$overallPercentage = $maxPossibleScore > 0 ? round(($overallScore / $maxPossibleScore) * 100, 1) : 0;
							?>
							<tr style="border-bottom: 1px solid #e5e7eb; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#f8fafc'" onmouseout="this.style.backgroundColor='white'">
								<td style="padding: 10px 8px; font-weight: 500; color: #111827; font-size: 13px;"><?php echo h($score['student_name']); ?></td>
								<td style="padding: 10px 8px; color: #6b7280; font-size: 13px;"><?php echo h($score['section_name']); ?></td>
								<td style="padding: 10px 8px; text-align: center;">
									<?php 
									// Calculate automated percentage correctly
									$maxAutomatedPoints = $mcCount + $totalMatchingItems;
									$automatedPercentage = $maxAutomatedPoints > 0 ? round(($automatedScore / $maxAutomatedPoints) * 100, 1) : 0;
									// Ensure percentage is not over 100%
									$displayPercentage = min($automatedPercentage, 100);
									?>
									<span style="color: <?php echo $displayPercentage >= 70 ? '#10b981' : ($displayPercentage >= 50 ? '#f59e0b' : '#ef4444'); ?>; font-weight: bold; font-size: 13px;">
										<?php echo number_format($displayPercentage, 1); ?>%
									</span>
								</td>
								<td style="padding: 10px 8px; text-align: center; color: #374151; font-weight: 500; font-size: 13px;"><?php echo $automatedScore; ?> / <?php echo ($mcCount + $totalMatchingItems); ?></td>
								<td style="padding: 10px 8px; text-align: center;">
									<?php if ($essayCount > 0): ?>
										<span style="color: #3b82f6; font-weight: bold; font-size: 13px;">
											<?php echo $totalEssayScore; ?> / <?php echo $maxEssayScore; ?>
										</span>
									<?php else: ?>
										<span style="color: #6b7280; font-style: italic; font-size: 12px;">No essays</span>
									<?php endif; ?>
								</td>
								<td style="padding: 10px 8px; text-align: center;">
									<?php 
									// Ensure overall percentage is not over 100%
									$displayOverallPercentage = min($overallPercentage, 100);
									?>
									<span style="color: <?php echo $displayOverallPercentage >= 70 ? '#10b981' : ($displayOverallPercentage >= 50 ? '#f59e0b' : '#ef4444'); ?>; font-weight: bold; font-size: 13px;">
										<?php echo $overallScore; ?> / <?php echo $maxPossibleScore; ?><br>
										<span style="font-size: 11px;">(<?php echo $displayOverallPercentage; ?>%)</span>
									</span>
								</td>
								<td style="padding: 10px 8px; text-align: center; color: #6b7280; font-size: 12px;"><?php echo date('M j, Y', strtotime($score['submitted_at'])); ?><br><?php echo date('g:i A', strtotime($score['submitted_at'])); ?></td>
								<td style="padding: 10px 8px; text-align: center;">
									<button onclick="viewStudentResponses(<?php echo $score['student_id']; ?>, '<?php echo h($score['student_name']); ?>')"
											style="background: #3b82f6; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 500; transition: background-color 0.2s;"
											onmouseover="this.style.background='#2563eb'" 
											onmouseout="this.style.background='#3b82f6'">
										View
									</button>
								</td>
							</tr>
						<?php endwhile; else: ?>
							<tr>
								<td colspan="8" style="padding: 40px; text-align: center; color: #6b7280; font-style: italic;">
									No submissions yet.
								</td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
				</div>

				
				<?php endif; ?>
			</div>
		</div>
	</div>
<?php render_teacher_footer(); ?>

<script>
function viewStudentResponses(studentId, studentName) {
    // Create modal for viewing student responses
    const modal = document.createElement('div');
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 1000;
    `;
    
    const modalContent = document.createElement('div');
    modalContent.style.cssText = `
        background: white;
        padding: 30px;
        border-radius: 8px;
        max-width: 90%;
        max-height: 90vh;
        overflow-y: auto;
        position: relative;
    `;
    
    modalContent.innerHTML = `
        <button onclick="this.closest('.modal').remove()" 
                style="position: absolute; top: 10px; right: 15px; background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">
            ×
        </button>
        <h3 style="margin-top: 0; color: #3730a3;">Student Responses - ${studentName}</h3>
        <div id="studentResponsesContent">
            <div style="text-align: center; padding: 20px;">
                <div class="spinner" style="border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 40px; height: 40px; animation: spin 2s linear infinite; margin: 0 auto;"></div>
                <p>Loading student responses...</p>
            </div>
        </div>
    `;
    
    modal.className = 'modal';
    modal.appendChild(modalContent);
    document.body.appendChild(modal);
    
    // Load student responses via AJAX
    loadStudentResponses(studentId, studentName);
    
    // Close modal when clicking outside
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.remove();
        }
    });
}

function loadStudentResponses(studentId, studentName) {
    const setTitle = '<?php echo $setTitle; ?>';
    
    fetch('teacher_grading.php?action=get_student_responses&student_id=' + studentId + '&set_title=' + encodeURIComponent(setTitle))
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayStudentResponses(data.responses, studentName);
            } else {
                document.getElementById('studentResponsesContent').innerHTML = 
                    '<div style="color: #ef4444; text-align: center; padding: 20px;">Error loading responses: ' + data.error + '</div>';
            }
        })
        .catch(error => {
            document.getElementById('studentResponsesContent').innerHTML = 
                '<div style="color: #ef4444; text-align: center; padding: 20px;">Error loading responses: ' + error.message + '</div>';
        });
}

function displayStudentResponses(responses, studentName) {
    let html = '<form id="gradingForm" method="POST" action="">';
    html += '<input type="hidden" name="action" value="save_essay_grades">';
    html += '<input type="hidden" name="student_id" value="' + responses[0].student_id + '">';
    html += '<input type="hidden" name="set_title" value="' + responses[0].set_title + '">';
    
    responses.forEach((response, index) => {
        const questionNumber = index + 1;
        const isEssay = response.question_type === 'essay';
        const currentScore = response.partial_score || 0;
        
        html += `
            <div style="border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                <h4 style="margin-top: 0; color: #374151;">
                    Question ${questionNumber} (${response.question_type.toUpperCase()})
                </h4>
                <div style="background: #f9fafb; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                    <strong>Question:</strong><br>
                    ${response.question_text}
                </div>
                <div style="background: #f0f9ff; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                    <strong>Student Answer:</strong><br>
                    <div style="white-space: pre-wrap; margin-top: 8px;">${response.student_answer || 'No answer provided'}</div>
                </div>
        `;
        
        if (isEssay) {
            html += `
                <div style="display: flex; align-items: center; gap: 10px;">
                    <label><strong>Essay Score (1-10):</strong></label>
                    <input type="number" 
                           name="essay_scores[${response.question_id}]" 
                           min="0" 
                           max="10" 
                           value="${currentScore}"
                           style="width: 80px; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;"
                           required>
                    <span style="color: #6b7280; font-size: 14px;">/ 10 points</span>
                </div>
            `;
        } else if (response.question_type === 'matching') {
            const partialScore = response.partial_score || 0;
            const totalMatches = response.total_matches || 0;
            const statusColor = partialScore === totalMatches ? '#10b981' : (partialScore > 0 ? '#f59e0b' : '#ef4444');
            const statusText = partialScore === totalMatches ? 'Perfect' : (partialScore > 0 ? 'Partial' : 'Incorrect');
            
            html += `
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="color: ${statusColor}; font-weight: bold;">${statusText}</span>
                    <span style="color: #6b7280;">(${partialScore}/${totalMatches} points)</span>
                </div>
            `;
        } else {
            const isCorrect = response.is_correct;
            const statusColor = isCorrect ? '#10b981' : '#ef4444';
            const statusText = isCorrect ? 'Correct' : 'Incorrect';
            const points = isCorrect ? '1' : '0';
            
            html += `
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="color: ${statusColor}; font-weight: bold;">${statusText}</span>
                    <span style="color: #6b7280;">(${points}/1 point)</span>
                </div>
            `;
        }
        
        html += '</div>';
    });
    
    html += `
        <div style="text-align: center; margin-top: 30px;">
            <button type="submit" 
                    style="background: #10b981; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 16px;">
                Save Essay Grades
            </button>
        </div>
    </form>
    `;
    
    document.getElementById('studentResponsesContent').innerHTML = html;
}

// Add CSS for spinner animation
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);

// Function to close flash messages
function closeFlashMessage(messageId) {
    const message = document.getElementById(messageId);
    if (message) {
        message.style.opacity = '0';
        message.style.transform = 'translateX(100%)';
        message.style.transition = 'all 0.3s ease';
        setTimeout(() => {
            message.style.display = 'none';
        }, 300);
    }
}

// Auto-dismiss flash messages after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const flashMessages = document.querySelectorAll('.flash');
    flashMessages.forEach(function(message) {
        setTimeout(function() {
            if (message.style.display !== 'none') {
                closeFlashMessage(message.id);
            }
        }, 5000); // Auto-dismiss after 5 seconds
    });
});

// Clear URL parameters to prevent message from showing on refresh
if (window.history.replaceState) {
    const url = new URL(window.location);
    if (url.searchParams.has('graded')) {
        url.searchParams.delete('graded');
        window.history.replaceState({}, document.title, url.pathname + url.search);
    }
}
</script>


