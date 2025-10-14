<?php
require_once __DIR__ . '/includes/teacher_init.php';
require_once __DIR__ . '/includes/teacher_layout.php';

// Auto-save functionality for grading
?>

<script src="includes/autosave.js"></script>

<?php
// Get parameters
$setId = (int)($_GET['set_id'] ?? 0);
$setTitle = $_GET['set_title'] ?? '';

if ($setId <= 0) {
    die('Invalid question set ID');
}

// Get question set details
$stmt = $conn->prepare("
    SELECT qs.*, s.name as section_name 
    FROM question_sets qs 
    LEFT JOIN sections s ON qs.section_id = s.id 
    WHERE qs.id = ?
");
$stmt->bind_param('i', $setId);
$stmt->execute();
$questionSet = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$questionSet) {
    die('Question set not found');
}

// Get all questions in this set with their correct answers
// First try the unified questions table
$stmt = $conn->prepare("
    SELECT q.id, q.type, q.question_text, q.points, q.order_index, q.answer_key, q.choices
    FROM questions q 
    WHERE q.set_id = ? 
    ORDER BY q.order_index, q.id
");
$stmt->bind_param('i', $setId);
$stmt->execute();
$questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// If no questions found in unified table, check separate tables
if (empty($questions)) {
    // Get MCQ questions
    $stmt = $conn->prepare("
        SELECT question_id as id, 'mcq' as type, question_text, points, order_index, 
               correct_answer as answer_key,
               JSON_OBJECT('A', choice_a, 'B', choice_b, 'C', choice_c, 'D', choice_d) as choices
        FROM mcq_questions 
        WHERE set_id = ? 
        ORDER BY order_index, question_id
    ");
    $stmt->bind_param('i', $setId);
    $stmt->execute();
    $mcqQuestions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Get Matching questions
    $stmt = $conn->prepare("
        SELECT question_id as id, 'matching' as type, question_text, points, order_index,
               left_items, right_items, correct_pairs,
               NULL as choices
        FROM matching_questions 
        WHERE set_id = ? 
        ORDER BY order_index, question_id
    ");
    $stmt->bind_param('i', $setId);
    $stmt->execute();
    $matchingQuestions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Get Essay questions
    $stmt = $conn->prepare("
        SELECT question_id as id, 'essay' as type, question_text, points, order_index,
               'Manual grading required' as answer_key,
               NULL as choices
        FROM essay_questions 
        WHERE set_id = ? 
        ORDER BY order_index, question_id
    ");
    $stmt->bind_param('i', $setId);
    $stmt->execute();
    $essayQuestions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Combine all questions
    $questions = array_merge($mcqQuestions, $matchingQuestions, $essayQuestions);
    
    // Sort by order_index
    usort($questions, function($a, $b) {
        return $a['order_index'] <=> $b['order_index'];
    });
}

// Get all students in the section
$stmt = $conn->prepare("
    SELECT s.id, s.name, s.student_number, s.email
    FROM students s 
    WHERE s.section_id = ? 
    ORDER BY s.name
");
$stmt->bind_param('i', $questionSet['section_id']);
$stmt->execute();
$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get all student responses for this set
$stmt = $conn->prepare("
    SELECT sr.*, s.name as student_name, s.student_number
    FROM student_responses sr
    LEFT JOIN students s ON sr.student_id = s.id
    WHERE sr.question_set_id = ?
    ORDER BY sr.student_id, sr.question_id
");
$stmt->bind_param('i', $setId);
$stmt->execute();
$responses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Group responses by student
$studentResponses = [];
foreach ($responses as $response) {
    $studentId = $response['student_id'];
    if (!isset($studentResponses[$studentId])) {
        $studentResponses[$studentId] = [
            'student_name' => $response['student_name'],
            'student_number' => $response['student_number'],
            'responses' => []
        ];
    }
    $studentResponses[$studentId]['responses'][$response['question_id']] = $response;
}

// If no responses found, show all students with empty responses
if (empty($studentResponses) && !empty($students)) {
    foreach ($students as $student) {
        $studentResponses[$student['id']] = [
            'student_name' => $student['name'],
            'student_number' => $student['student_number'],
            'responses' => []
        ];
    }
}

// Handle grading submission
if ($_POST['action'] ?? '' === 'grade_essay') {
    $responseId = (int)($_POST['response_id'] ?? 0);
    $score = (float)($_POST['score'] ?? 0);
    $feedback = $_POST['feedback'] ?? '';
    
    $stmt = $conn->prepare("
        UPDATE student_responses 
        SET score = ?, feedback = ?, graded_at = NOW(), graded_by = ?
        WHERE response_id = ?
    ");
    $stmt->bind_param('dsii', $score, $feedback, $teacherId, $responseId);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode(['success' => true]);
    exit;
}

$pageTitle = 'Student Responses - ' . htmlspecialchars($setTitle);

// Start output buffering for content
ob_start();
?>

<style>
.responses-container {
    background: #f8f9fa;
    min-height: 100vh;
    padding: 20px;
}

.back-button {
    background: #6b7280;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    margin-bottom: 20px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: background-color 0.2s;
}

.back-button:hover {
    background: #4b5563;
    color: white;
    text-decoration: none;
}

.header-section {
    background: white;
    padding: 24px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 24px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    text-align: center;
}

.stat-value {
    font-size: 2rem;
    font-weight: bold;
    color: #1f2937;
    margin-bottom: 8px;
}

.stat-label {
    color: #6b7280;
    font-size: 0.875rem;
}

.student-section {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 24px;
    overflow: hidden;
}

.student-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.student-info h3 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
}

.student-meta {
    font-size: 0.875rem;
    opacity: 0.9;
    margin-top: 4px;
}

.student-score {
    text-align: right;
}

.score-display {
    font-size: 1.5rem;
    font-weight: bold;
    margin-bottom: 4px;
}

.score-label {
    font-size: 0.875rem;
    opacity: 0.9;
}

.responses-content {
    padding: 24px;
}

.question-item {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    margin-bottom: 20px;
    overflow: hidden;
}

.question-header {
    background: #f9fafb;
    padding: 16px;
    border-bottom: 1px solid #e5e7eb;
}

.question-text {
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 8px;
}

.question-meta {
    display: flex;
    gap: 16px;
    font-size: 0.875rem;
    color: #6b7280;
}

.response-content {
    padding: 16px;
}

.response-answer {
    background: #f3f4f6;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 12px;
    white-space: pre-wrap;
    font-family: inherit;
}

.grading-section {
    border-top: 1px solid #e5e7eb;
    padding: 16px;
    background: #fafafa;
}

.grade-form {
    display: flex;
    gap: 12px;
    align-items: end;
}

.grade-input {
    flex: 1;
    max-width: 100px;
}

.feedback-input {
    flex: 2;
}

.grade-btn {
    background: #10b981;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
}

.grade-btn:hover {
    background: #059669;
}

.graded {
    background: #d1fae5;
    border-color: #10b981;
}

.graded .response-answer {
    background: #ecfdf5;
}

.no-responses {
    text-align: center;
    padding: 40px;
    color: #6b7280;
}

.filter-section {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 24px;
}

.filter-controls {
    display: flex;
    gap: 16px;
    align-items: center;
    flex-wrap: wrap;
}

.filter-controls select,
.filter-controls input {
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
}

.filter-controls button {
    background: #3b82f6;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
}

.filter-controls button:hover {
    background: #2563eb;
}
</style>

<div class="responses-container">
    <!-- Back Button -->
    <a href="question_bank.php" class="back-button">
        <i class="fas fa-arrow-left"></i>
        Back to Question Bank
    </a>

    <div class="header-section">
        <h1><i class="fas fa-users"></i> Student Responses</h1>
        <p><strong>Question Set:</strong> <?php echo htmlspecialchars($setTitle); ?></p>
        <p><strong>Section:</strong> <?php echo htmlspecialchars($questionSet['section_name']); ?></p>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo count($questions); ?></div>
                <div class="stat-label">Total Questions</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count($students); ?></div>
                <div class="stat-label">Total Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count($studentResponses); ?></div>
                <div class="stat-label">Students Responded</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count(array_filter($responses, function($r) { return $r['question_type'] === 'essay'; })); ?></div>
                <div class="stat-label">Essay Questions</div>
            </div>
        </div>
    </div>

    <div class="filter-section">
        <div class="filter-controls">
            <select id="studentFilter">
                <option value="">All Students</option>
                <?php foreach ($students as $student): ?>
                    <option value="<?php echo $student['id']; ?>"><?php echo htmlspecialchars($student['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <select id="questionTypeFilter">
                <option value="">All Question Types</option>
                <option value="mcq">Multiple Choice</option>
                <option value="matching">Matching</option>
                <option value="essay">Essay</option>
            </select>
            <select id="gradingFilter">
                <option value="">All Responses</option>
                <option value="graded">Graded Only</option>
                <option value="ungraded">Ungraded Only</option>
            </select>
            <button onclick="applyFilters()">Apply Filters</button>
        </div>
    </div>

    <?php if (empty($questions)): ?>
        <div class="no-responses">
            <i class="fas fa-question-circle" style="font-size: 48px; margin-bottom: 16px; color: #d1d5db;"></i>
            <h3>No Questions Found</h3>
            <p>This question set doesn't have any questions yet. Please add questions to this set first.</p>
        </div>
    <?php elseif (empty($studentResponses)): ?>
        <div class="no-responses">
            <i class="fas fa-users" style="font-size: 48px; margin-bottom: 16px; color: #d1d5db;"></i>
            <h3>No Student Responses</h3>
            <p>No students have submitted responses for this question set yet.</p>
        </div>
        
        <!-- Show questions even when no responses -->
        <div class="student-section">
            <div class="student-header">
                <div class="student-info">
                    <h3>Questions in this Set</h3>
                    <div class="student-meta">Preview of all questions</div>
                </div>
            </div>
            
            <div class="responses-content">
                <?php foreach ($questions as $question): ?>
                    <div class="question-item" data-question-type="<?php echo $question['type']; ?>">
                        <div class="question-header">
                            <div class="question-text"><?php echo htmlspecialchars($question['question_text']); ?></div>
                            <div class="question-meta">
                                <span><strong>Type:</strong> <?php echo strtoupper($question['type']); ?></span>
                                <span><strong>Points:</strong> <?php echo $question['points']; ?></span>
                            </div>
                        </div>
                        
                        <div class="response-content">
                            <div style="background: #f0f9ff; padding: 12px; border-radius: 6px; border-left: 4px solid #3b82f6;">
                                <strong style="color: #1e40af;">Correct Answer:</strong>
                                <?php if ($question['type'] === 'mcq'): ?>
                                    <?php 
                                    $choices = json_decode($question['choices'], true);
                                    $correctAnswer = $question['answer_key'];
                                    if ($choices && isset($choices[$correctAnswer])) {
                                        echo htmlspecialchars($choices[$correctAnswer]);
                                    } else {
                                        echo htmlspecialchars($correctAnswer);
                                    }
                                    ?>
                                <?php elseif ($question['type'] === 'matching'): ?>
                                    <?php 
                                    // Parse the JSON data for matching questions
                                    $leftItems = json_decode($question['left_items'] ?? '[]', true);
                                    $rightItems = json_decode($question['right_items'] ?? '[]', true);
                                    $correctPairs = json_decode($question['correct_pairs'] ?? '[]', true);
                                    
                                    if ($leftItems && $rightItems && $correctPairs) {
                                        echo '<div style="margin-top: 8px;">';
                                        for ($i = 0; $i < count($leftItems); $i++) {
                                            $leftItem = $leftItems[$i];
                                            $correctAnswer = isset($correctPairs[$i]) ? $correctPairs[$i] : 'No match';
                                            echo '<div style="margin-bottom: 4px;">';
                                            echo '<strong>' . ($i + 1) . '. ' . htmlspecialchars($leftItem) . '</strong> → ';
                                            echo '<span style="color: #059669;">' . htmlspecialchars($correctAnswer) . '</span>';
                                            echo '</div>';
                                        }
                                        echo '</div>';
                                    } else {
                                        echo htmlspecialchars($question['answer_key'] ?? 'No correct answers available');
                                    }
                                    ?>
                                <?php else: ?>
                                    <em>Manual grading required</em>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($studentResponses as $studentId => $studentData): ?>
            <div class="student-section" data-student-id="<?php echo $studentId; ?>">
                <div class="student-header">
                    <div class="student-info">
                        <h3><?php echo htmlspecialchars($studentData['student_name']); ?></h3>
                        <div class="student-meta">
                            Student #<?php echo htmlspecialchars($studentData['student_number']); ?>
                        </div>
                    </div>
                    <div class="student-score">
                        <?php
                        $totalScore = 0;
                        $maxScore = 0;
                        $gradedCount = 0;
                        foreach ($studentData['responses'] as $response) {
                            $maxScore += $response['score'] ?? 0;
                            if ($response['score'] !== null) {
                                $totalScore += $response['score'];
                                $gradedCount++;
                            }
                        }
                        $percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 1) : 0;
                        ?>
                        <div class="score-display"><?php echo $totalScore; ?>/<?php echo $maxScore; ?></div>
                        <div class="score-label"><?php echo $percentage; ?>%</div>
                    </div>
                </div>
                
                <div class="responses-content">
                    <?php foreach ($questions as $question): ?>
                        <?php
                        $response = $studentData['responses'][$question['id']] ?? null;
                        ?>
                        <div class="question-item" data-question-type="<?php echo $question['type']; ?>">
                            <div class="question-header">
                                <div class="question-text"><?php echo htmlspecialchars($question['question_text']); ?></div>
                                <div class="question-meta">
                                    <span><strong>Type:</strong> <?php echo strtoupper($question['type']); ?></span>
                                    <span><strong>Points:</strong> <?php echo $question['points']; ?></span>
                                    <span><strong>Status:</strong> 
                                        <?php if ($response && $response['score'] !== null): ?>
                                            <span style="color: #10b981;">Graded (<?php echo $response['score']; ?>/<?php echo $question['points']; ?>)</span>
                                        <?php elseif ($response): ?>
                                            <span style="color: #f59e0b;">Submitted</span>
                                        <?php else: ?>
                                            <span style="color: #6b7280;">Not Answered</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="response-content">
                                <!-- Show correct answer for reference -->
                                <div style="background: #f0f9ff; padding: 12px; border-radius: 6px; margin-bottom: 12px; border-left: 4px solid #3b82f6;">
                                    <strong style="color: #1e40af;">Correct Answer:</strong>
                                    <?php if ($question['type'] === 'mcq'): ?>
                                        <?php 
                                        $choices = json_decode($question['choices'], true);
                                        $correctAnswer = $question['answer_key'];
                                        if ($choices && isset($choices[$correctAnswer])) {
                                            echo htmlspecialchars($choices[$correctAnswer]);
                                        } else {
                                            echo htmlspecialchars($correctAnswer);
                                        }
                                        ?>
                                    <?php elseif ($question['type'] === 'matching'): ?>
                                        <?php 
                                        // Parse the JSON data for matching questions
                                        $leftItems = json_decode($question['left_items'] ?? '[]', true);
                                        $rightItems = json_decode($question['right_items'] ?? '[]', true);
                                        $correctPairs = json_decode($question['correct_pairs'] ?? '[]', true);
                                        
                                        if ($leftItems && $rightItems && $correctPairs) {
                                            echo '<div style="margin-top: 8px;">';
                                            for ($i = 0; $i < count($leftItems); $i++) {
                                                $leftItem = $leftItems[$i];
                                                $correctAnswer = isset($correctPairs[$i]) ? $correctPairs[$i] : 'No match';
                                                echo '<div style="margin-bottom: 4px;">';
                                                echo '<strong>' . ($i + 1) . '. ' . htmlspecialchars($leftItem) . '</strong> → ';
                                                echo '<span style="color: #059669;">' . htmlspecialchars($correctAnswer) . '</span>';
                                                echo '</div>';
                                            }
                                            echo '</div>';
                                        } else {
                                            echo htmlspecialchars($question['answer_key'] ?? 'No correct answers available');
                                        }
                                        ?>
                                    <?php else: ?>
                                        <em>Manual grading required</em>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Student's answer -->
                                <div style="margin-bottom: 12px;">
                                    <strong>Student's Answer:</strong>
                                    <div class="response-answer">
                                        <?php if ($response): ?>
                                            <?php 
                                            $studentAnswer = $response['answer'];
                                            if ($question['type'] === 'mcq' && $response['answer']) {
                                                $choices = json_decode($question['choices'], true);
                                                if ($choices && isset($choices[$studentAnswer])) {
                                                    echo htmlspecialchars($choices[$studentAnswer]);
                                                } else {
                                                    echo htmlspecialchars($studentAnswer);
                                                }
                                            } else {
                                                echo htmlspecialchars($studentAnswer);
                                            }
                                            ?>
                                        <?php else: ?>
                                            <em style="color: #6b7280;">No answer provided</em>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Show if correct/incorrect for auto-graded questions -->
                                <?php if ($response && in_array($question['type'], ['mcq', 'matching'])): ?>
                                    <div style="margin-bottom: 12px;">
                                        <strong>Result:</strong>
                                        <?php if ($response['is_correct']): ?>
                                            <span style="color: #10b981; font-weight: bold;">✓ Correct</span>
                                        <?php else: ?>
                                            <span style="color: #ef4444; font-weight: bold;">✗ Incorrect</span>
                                        <?php endif; ?>
                                        <span style="margin-left: 12px;">
                                            Score: <?php echo $response['score'] ?? 0; ?>/<?php echo $question['points']; ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($question['type'] === 'essay' && $response): ?>
                                    <div class="grading-section <?php echo $response['score'] !== null ? 'graded' : ''; ?>">
                                        <form class="grade-form" onsubmit="gradeEssay(event, <?php echo $response['response_id']; ?>)">
                                            <div>
                                                <label>Score:</label>
                                                <input type="number" name="score" class="grade-input" 
                                                       min="0" max="<?php echo $question['points']; ?>" 
                                                       step="0.1" value="<?php echo $response['score'] ?? ''; ?>" required>
                                                <span>/ <?php echo $question['points']; ?></span>
                                            </div>
                                            <div class="feedback-input">
                                                <label>Feedback:</label>
                                                <textarea name="feedback" rows="2" placeholder="Optional feedback for the student..."><?php echo htmlspecialchars($response['feedback'] ?? ''); ?></textarea>
                                            </div>
                                            <button type="submit" class="grade-btn">
                                                <?php echo $response['score'] !== null ? 'Update Grade' : 'Grade Essay'; ?>
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
function gradeEssay(event, responseId) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    formData.append('action', 'grade_essay');
    formData.append('response_id', responseId);
    
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Grading...';
    submitBtn.disabled = true;
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update the UI to show graded status
            const questionItem = form.closest('.question-item');
            questionItem.classList.add('graded');
            
            // Update the status in the question header
            const statusSpan = questionItem.querySelector('.question-meta span:last-child');
            const score = formData.get('score');
            const maxScore = form.querySelector('input[name="score"]').getAttribute('max');
            statusSpan.innerHTML = `<span style="color: #10b981;">Graded (${score}/${maxScore})</span>`;
            
            // Update button text
            submitBtn.textContent = 'Update Grade';
            
            // Show success message
            showNotification('Essay graded successfully!', 'success');
        } else {
            showNotification('Error grading essay: ' + (data.error || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        showNotification('Error: ' + error.message, 'error');
    })
    .finally(() => {
        submitBtn.disabled = false;
    });
}

function applyFilters() {
    const studentFilter = document.getElementById('studentFilter').value;
    const questionTypeFilter = document.getElementById('questionTypeFilter').value;
    const gradingFilter = document.getElementById('gradingFilter').value;
    
    const studentSections = document.querySelectorAll('.student-section');
    
    studentSections.forEach(section => {
        let showSection = true;
        
        // Student filter
        if (studentFilter && section.dataset.studentId !== studentFilter) {
            showSection = false;
        }
        
        // Question type filter
        if (questionTypeFilter) {
            const questionItems = section.querySelectorAll('.question-item');
            let hasMatchingType = false;
            questionItems.forEach(item => {
                if (item.dataset.questionType === questionTypeFilter) {
                    hasMatchingType = true;
                }
            });
            if (!hasMatchingType) {
                showSection = false;
            }
        }
        
        // Grading filter
        if (gradingFilter) {
            const questionItems = section.querySelectorAll('.question-item');
            let hasMatchingGrading = false;
            questionItems.forEach(item => {
                const isGraded = item.classList.contains('graded');
                if ((gradingFilter === 'graded' && isGraded) || (gradingFilter === 'ungraded' && !isGraded)) {
                    hasMatchingGrading = true;
                }
            });
            if (!hasMatchingGrading) {
                showSection = false;
            }
        }
        
        section.style.display = showSection ? 'block' : 'none';
    });
}

function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#10b981' : '#ef4444'};
        color: white;
        padding: 12px 20px;
        border-radius: 6px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        z-index: 1000;
        font-weight: 500;
    `;
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// Apply filters on page load
document.addEventListener('DOMContentLoaded', function() {
    applyFilters();
});
</script>

<?php 
$content = ob_get_clean();

// Render the teacher layout
render_teacher_header('clean_question_creator.php', $teacherName, $pageTitle);
echo $content;
render_teacher_footer();
?>

